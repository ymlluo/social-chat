<?php


namespace App\Services\Chat\Contracts;

use App\Events\ChatAssignedEvent;
use App\Events\ChatClosedEvent;
use App\Events\ChatMessageReceivedEvent;
use App\Models\Bytedance\TiktokUser;
use App\Models\Chat\ChatConversation;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatTaskConfig;
use App\Services\CacheService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ChatService
{
    const PLATFORM_DY = 'DouYin';
    const PLATFORM_WB = 'Weibo';

    const SERVICE_ENTER_MODE_IMMEDIATE = 1;
    const SERVICE_ENTER_MODE_EVENT = 2;
    const SERVICE_ENTER_MODE_COUNTER = 3;
    const SERVICE_ENTER_MODE_KEYWORD = 4;

    const HUMAN_SERVICE_STATUS_DEFAULT = 0;//not set
    const HUMAN_SERVICE_STATUS_QUEUE = 2; // queue
    const HUMAN_SERVICE_STATUS_ONGOING = 3; //human service
    const HUMAN_SERVICE_STATUS_ROBOT = 4;//robot

    //1:conversation,2:ticket
    const CONV_TYPE_CONVERSATION = 1;
    const CONV_TYPE_TICKET = 2;

    //1:open,2:close
    const CONV_STATUS_OPEN = 1;
    const CONV_STATUS_CLOSE = 2;

    //1:user,2:customer,3:system
    const CONV_CLOSED_BY_USER = 1;
    const CONV_CLOSED_BY_CUSTOMER = 2;
    const CONV_CLOSED_BY_SYSTEM = 3;

    //1:user,2:customer
    const CONV_CREATED_BY_USER = 1;
    const CONV_CREATED_BY_CUSTOMER = 2;

    const KEY_SERVICE_ENTER_MODE = 'service_enter_mode';
    const KEY_SERVICE_ENTER_TARGET_COUNT = 'service_enter_target_count';
    const KEY_SERVICE_ENTER_TARGET_WORDS = 'service_enter_target_words';
    const KEY_SERVICE_EXIT_TARGET_WORDS = 'service_exit_target_words';
    const KEY_SERVICE_START_AT = 'service_start_at';
    const KEY_SERVICE_END_AT = 'service_end_at';
    const KEY_CUSTOMER_SEND_COUNT = 'customer_send_count';
    const KEY_CUSTOMER_SERVICE_STATUS = 'customer_service_status';
    const KEY_SERVICE_QUEUE_BUSY_COUNT = 'service_queue_busy_count';
    const KEY_SERVICE_QUEUE_BUSY_TIPS = 'service_queue_busy_tips';
    const KEY_SERVICE_QUEUE_ENTER_TIPS = 'service_queue_enter_tips';
    const KEY_SERVICE_QUEUE_EXIT_TIPS = 'service_queue_exit_tips';
    const KEY_ALLOW_SEND_TIPS = 'allow_send_system_tips';
    const KEY_EVENT_EXIT_SERVICE_QUEUE = 'service_queue_exit';


    public $platform;

    public $userId;

    public $customerId;

    public $receiveMessage;

    public $conversation;

    public $user;

    public $customer;

    public $hasMessage = false;

    public function __construct($platform = '', $userId = '', $customerId = '')
    {
        $this->userId = $userId;
        $this->customerId = $customerId;
        $this->platform = $platform;
    }


    public function receiveMessage(Message $message)
    {
        $this->userId = $message->userId();
        $this->customerId = $message->customerId();
        $this->receiveMessage = $message;
        if ($this->hasMessage = $message->valid()) {
            event(new ChatMessageReceivedEvent($this, $message));
        }
        return $this->reply();
    }


    protected function replyMessage($text, $type = 'text')
    {
        if (!self::KEY_ALLOW_SEND_TIPS || empty($text)) {
            return $this->receiveMessage->reply('');
        }
        return $this->receiveMessage->reply($text, $type);
    }

    public function matchReply($text)
    {
        return $this->replyMessage('');
    }

    public function reply()
    {
        if ($this->hasMessage) {
            if (!$this->receiveMessage->type() == 'text') {

                return $this->receiveMessage->reply('');
            }
            //记录 customer 发送消息数
            $this->setCustomerConfig(self::KEY_CUSTOMER_SEND_COUNT, intval($this->getCustomerConfig(self::KEY_CUSTOMER_SEND_COUNT, 0)), 600);
            switch ($this->getCustomerConfig(self::KEY_CUSTOMER_SERVICE_STATUS)) {
                case self::HUMAN_SERVICE_STATUS_DEFAULT:
                default:
                    //默认状态
                    if ($this->needService()) {
                        //需要转人工
                        $this->convCreate();
                        $this->setCustomerConfig(self::KEY_CUSTOMER_SERVICE_STATUS, self::HUMAN_SERVICE_STATUS_QUEUE);
                        if ($this->convQueueCount() >= $this->getUserConfig(self::KEY_SERVICE_QUEUE_BUSY_COUNT)) {
                            return $this->replyMessage($this->getUserConfig(self::KEY_SERVICE_QUEUE_BUSY_TIPS, '目前排队人数较多，请您耐心等待，正在为您转接人工服务,请稍候...'));
                        }
                        //尝试分配任务
                        \Artisan::queue('wb:task:assign:conversation');

                        return $this->replyMessage($this->getUserConfig(self::KEY_SERVICE_QUEUE_ENTER_TIPS, '正在为您转接人工服务,请稍候...'));
                    }
                    break;
                case self::HUMAN_SERVICE_STATUS_QUEUE:
                    if ($this->exitService()) {
                        $this->delCustomerConfig(self::KEY_CUSTOMER_SERVICE_STATUS);
                        return $this->replyMessage($this->getUserConfig(self::KEY_SERVICE_QUEUE_EXIT_TIPS, '已退出人工服务。'));
                    }
                    break;
                case self::HUMAN_SERVICE_STATUS_ONGOING:
                    if ($convCurrent = $this->convCurrent()) {
                        $convCurrent->{'msg_count_by_customer'}++;
                        $convCurrent->save();
                    }
                    $this->receiveMessage->reply('');
                    break;
                case self::HUMAN_SERVICE_STATUS_ROBOT:
                    return $this->matchReply($this->receiveMessage->content());
                    break;
            }
        }
        return $this->receiveMessage->reply('');
    }


    public function convCreate(int $createdBy = self::CONV_CREATED_BY_CUSTOMER)
    {
        $conv = ChatConversation::query()
            ->firstOrCreate([
                'platform' => $this->platform,
                'type' => self::CONV_TYPE_CONVERSATION,
                'status' => self::CONV_STATUS_OPEN,
                'user_id' => $this->userId,
                'customer_id' => $this->customerId,
            ], ['created_by' => $createdBy]);
        return $conv;
    }

    public function convClose(int $closedBy)
    {
        if ($convCur = $this->convCurrent()) {
            $convCur->update([
                'status' => self::CONV_STATUS_CLOSE,
                'end_at' => now(),
                'closed_by' => $closedBy
            ]);
            $this->delCustomerConfig(self::KEY_CUSTOMER_SERVICE_STATUS);
            if ($uid = $convCur->uid) {
                ChatTaskConfig::query()->where('platform', $this->platform)->where('uid', $uid)->decrement('current');
                ChatTaskConfig::query()->where('platform', $this->platform)->where('uid', $uid)->increment('free');
            }
            broadcast(new ChatClosedEvent($convCur));
        }
    }

    //['platform','type','status','user_id','customer_id'
    public function convCurrent()
    {
        return ChatConversation::query()
            ->where('platform', $this->platform)
            ->where('type', self::CONV_TYPE_CONVERSATION)
            ->where('status', self::CONV_STATUS_OPEN)
            ->where('user_id', $this->userId)
            ->where('customer_id', $this->customerId)
            ->first();
    }

    public function convAssign(Conversation $conv, int $uid)
    {
        $conversation = $conv->instance();
        $conversation->{'uid'} = $uid;
        $conversation->{'human_service_at'} = now();
        $conversation->{'msg_count_by_customer'} = intval($this->getCustomerConfig(self::KEY_CUSTOMER_SEND_COUNT));
        $this->delCustomerConfig(self::KEY_CUSTOMER_SEND_COUNT);
        $this->setCustomerConfig(self::KEY_CUSTOMER_SERVICE_STATUS, self::HUMAN_SERVICE_STATUS_ONGOING);
        ChatTaskConfig::query()->where('platform', $this->platform)->where('uid', $uid)->increment('current');
        ChatTaskConfig::query()->where('platform', $this->platform)->where('uid', $uid)->decrement('free');
        ChatMessage::query()->where('platform', $conversation->platform())
            ->where('customer_id', $conversation->customerId())
            ->where('conv_id', 0)
            ->update(['conv_id' => $conversation->id()]);
        $conversation->save();
        broadcast(new ChatAssignedEvent($conv));
    }

    public function convQueueCount()
    {
        return ChatConversation::query()
            ->where('platform', $this->platform)
            ->where('type', self::CONV_TYPE_CONVERSATION)
            ->where('status', self::CONV_STATUS_OPEN)
            ->where('uid', 0)
            ->count();
    }

    public function freeUserCount()
    {
        return (int)ChatTaskConfig::query()->where('platform', $this->platform)->where('status', 1)->sum('free');
    }

    public function convAssignCount()
    {
        \Log::info('convAssignCount', ['conv_count' => $this->convQueueCount(), 'free_user_count' => $this->freeUserCount()]);
        return min([$this->convQueueCount(), $this->freeUserCount()]);
    }

    public function convAssignTask()
    {
        if (CacheService::isLock('CHAT:ASSIGN:TASK:' . $this->platform)) {
            return 0;
        }
        CacheService::lock('CHAT:ASSIGN:TASK:' . $this->platform);
        try {
            if (!$this->isServiceTime()) {
                return 0;
            }
            if ($this->convQueueCount() == 0) {
                return 0;
            }
            if ($this->freeUserCount() == 0) {
                return 0;
            }
            $uid = $this->getAssignUid();
            $conv = $this->convQueue();
            if ($uid && $conv) {
                $this->convAssign($conv, $uid);
            }
            return 0;
        } catch (\Exception $exception) {
            report($exception);
            return 1;
        } finally {
            \Log::alert('unlock assign chat task');
            CacheService::unlock('CHAT:ASSIGN:TASK:' . $this->platform);
        }
    }

    public function getAssignUid()
    {
        $config = ChatTaskConfig::query()->where('platform', $this->platform)->where('status', 1)->where('free', '>', 0)->orderByDesc('free')->orderBy('current')->first();
        return $config->{'uid'};
    }

    public function convQueue()
    {
        return ChatConversation::query()
            ->where('platform', $this->platform)
            ->where('type', self::CONV_TYPE_CONVERSATION)
            ->where('status', self::CONV_STATUS_OPEN)
            ->where('uid', 0)
            ->orderByDesc('msg_count_by_user')
            ->orderBy('id')
            ->first();
    }


    private function userCacheKey($key)
    {
        return $this->platform . ':c:' . $this->userId . ':' . $key . ':';
    }

    private function customerCacheKey($key)
    {
        return $this->platform . ':c:' . $this->userId . ':' . $this->customerId . ':' . $key . ':';
    }

    public function setUserConfig($key, $val, $ttl = 0)
    {
        \Cache::set($this->userCacheKey($key), $val, $ttl);
        return $this;
    }

    public function getUserConfig($key, $default = '')
    {
        return Cache::get($this->userCacheKey($key), $default);
    }

    public function delUserConfig($key)
    {
        return Cache::delete($this->userCacheKey($key));
    }

    public function getCustomerConfig($key, $default = '')
    {
        return Cache::get($this->customerCacheKey($key), $default);
    }


    public function setCustomerConfig($key, $val, $ttl = 0)
    {
        \Cache::set($this->customerCacheKey($key), $val, $ttl);
        return $this;
    }

    public function delCustomerConfig($key)
    {
        \Cache::delete($this->customerCacheKey($key));
        return $this;
    }

    public function needService()
    {
        if (!$this->isServiceTime()) {
            return false;
        }
        if ($this->receiveMessage->source() != 'receive') {
            return false;
        }
        if ($this->receiveMessage->type() != 'text') {
            return false;
        }
        $svMode = $this->getUserConfig(self::KEY_SERVICE_ENTER_MODE);
        switch ($svMode) {
            case self::SERVICE_ENTER_MODE_IMMEDIATE;
            default:
                return true;
            case self::SERVICE_ENTER_MODE_EVENT:
                //todo 暂未支持
                break;
            case self::SERVICE_ENTER_MODE_COUNTER:
                return $this->getCustomerConfig(self::KEY_CUSTOMER_SEND_COUNT) >= $this->getUserConfig(self::KEY_SERVICE_ENTER_TARGET_COUNT, 5);
            case self::SERVICE_ENTER_MODE_KEYWORD:
                if ($this->receiveMessage->getTye() == 'text') {
                    return trim(mb_strtoupper($this->receiveMessage->content())) == trim(mb_strtoupper($this->getUserConfig(self::KEY_SERVICE_ENTER_TARGET_WORDS, '转人工')));
                }

        }
        return false;
    }

    public function exitService()
    {
        switch ($this->receiveMessage->type()) {
            case 'text':
                return trim(mb_strtoupper($this->receiveMessage->content())) == trim(mb_strtoupper(self::getUserConfig(self::KEY_SERVICE_EXIT_TARGET_WORDS, 'Q')));
            case 'event':
                return $this->receiveMessage->content() == self::KEY_EVENT_EXIT_SERVICE_QUEUE;
            default:
                return false;
        }
    }

    public function isServiceTime()
    {
        $startTime = $this->getUserConfig(self::KEY_SERVICE_START_AT, '0:00');
        $endTime = $this->getUserConfig(self::KEY_SERVICE_END_AT, '24:00');
        return (bool)((now() >= Carbon::parse($startTime)) && (Carbon::now() < Carbon::parse($endTime)));
    }

    public function user()
    {
        switch ($this->platform) {
            case 'DouYin':
                return TiktokUser::getUserByOpenid($this->userId);
            default:
                throw new \Exception('not support platform');
        }
    }

    public function customer()
    {
        switch ($this->platform) {
            case 'DouYin':
                return TiktokUser::getUserByOpenid($this->customerId);
            default:
                throw new \Exception('not support platform');
        }
    }
}