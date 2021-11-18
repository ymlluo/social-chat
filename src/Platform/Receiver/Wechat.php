<?php

namespace Ymlluo\SocialChat\Platform\Receiver;

use App\Services\Chat\Contracts\Message;
use Ymlluo\SocialChat\Support\Encrypt;
use Ymlluo\SocialChat\Support\XML;

class Wechat implements Message
{
    public $configs = [];
    public $encrypt;
    public $postXml = '';
    public $message = [];
    public $reply = [];
    public $ready = false;

    public function __construct($configs)
    {
        $this->configs = $configs;
        $this->valid();
    }

    public function valid()
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
            $echoStr = 'no signature';
            if ($this->checkSignature()) {
                $echoStr = $_GET['echostr'] ?? '';
            }
            echo $echoStr;
            die();
        } else {
            $this->postXml = file_get_contents('php://input');
            $this->encrypt = $_GET['encrypt_type'] ?? '';
            if ($this->encrypt) {
                //aes解密
                $this->postXml = Encrypt::decodeXML($this->postXml, $this->configs['aes_key']);
            }
            $this->message = XML::xml2Array($this->postXml);
            $this->reply['ToUserName'] = $this->fromUserName();
            $this->reply['FromUserName'] = $this->toUserName();
        }
        return $this;
    }

    /**
     * @return bool
     */
    private function checkSignature(): bool
    {
        $signature = $_GET['signature'] ?? '';
        $timestamp = $_GET['timestamp'] ?? '';
        $nonce = $_GET['nonce'] ?? '';

        if (Encrypt::signature($this->configs['token'], $timestamp, $nonce) == $signature) {
            return true;
        } else {
            return false;
        }
    }


    public function text($text)
    {
        $this->reply['MsgType'] = 'text';
        $this->reply['Content'] = $text;
        $this->ready = true;
        return $this;
    }

    public function image($path)
    {
        $this->reply['MsgType'] = 'image';
        $this->reply['Image']['MediaId'] = $this->getMediaId($path);
        $this->ready = true;
        return $this;
    }

    protected function getMediaId($path)
    {
        return '';
    }


    /**
     * @return mixed
     */
    public function toUserName()
    {
        return $this->message['ToUserName'] ?? null;
    }

    /**
     * @return mixed
     */
    public function fromUserName()
    {
        return $this->message['FromUserName'] ?? null;
    }

    /**
     * @return mixed
     */
    public function createTime()
    {
        return $this->message['CreateTime'] ?? null;
    }

    /**
     * @return mixed
     */
    public function type()
    {
        return $this->message['MsgType'] ?? null;
    }

    /**
     * @return mixed
     */
    public function event()
    {
        return $this->message['Event'];
    }


    /**
     * @return mixed
     */
    public function reply()
    {
        if (!$this->ready) {
            throw new \Exception("reply message not ready yet");
        }
        $this->reply['CreateTime'] = time();
        $xml = XML::generateXml($this->reply);
        if ($this->encrypt) {
            try {
                $xml = Encrypt::encodeXML(
                    $this->configs['app_id'],
                    $this->configs['aes_key'],
                    $this->configs['token'],
                    $xml
                );
            } catch (\Exception $e) {

            }
        }
        return response($xml)->send();
    }

}