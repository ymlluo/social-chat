<?php


namespace App\Services\Chat\Contracts;


abstract class BaseMessage implements Message
{
    public $data = [];

    public $from;

    public $userId;

    public $customerId;

    public $valid = true;

    /**
     *
     * @return array
     */
    public function instance()
    {
        return $this->data;
    }

    public function convId($convId = 0)
    {
        if ($convId) {
            $this->data['conv_id'] = $convId;
            return $this;
        }
        return data_get($this->data, 'conv_id', 0);
    }

    /**
     * get received message platform
     *
     * @return string |mixed
     */
    public function platform($platform = '')
    {
        if ($platform) {
            $this->data['platform'] = $platform;
            return $this;
        }
        return data_get($this->data, 'platform', '');
    }

    public function source($source = '')
    {
        if ($source) {
            $this->data['from'] = $source;
            return $this;
        }
        return data_get($this->data, 'from', '');
    }

    /**
     * get received message user id
     *
     * @return array|mixed
     */
    public function userId($userId = '')
    {
        if ($userId) {
            $this->data['user_id'] = $userId;
            return $this;
        }
        return data_get($this->data, 'user_id', '');
    }

    /**
     * get received message customer id
     *
     * @return array|mixed
     */
    public function customerId($customerId = '')
    {
        if ($customerId) {
            $this->data['customer_id'] = $customerId;
            return $this;
        }
        return data_get($this->data, 'customer_id', '');
    }

    public function id()
    {
        return intval(data_get($this->data, 'id', 0));
    }


    public function uid($uid = 0)
    {
        if ($uid) {
            $this->data['uid'] = $uid;
            return $this;
        }
        return data_get($this->data, 'uid');
    }


    /**
     * get received message type
     *
     * @return string | $this
     */
    public function type($type = '')
    {
        if ($type) {
            $this->data['type'] = $type;
            return $this;
        }
        return data_get($this->data, 'type', 'event');
    }

    /**
     * get received message content
     *
     * @return string | $this
     */
    public function content($content = '')
    {
        if ($content) {
            $this->data['text'] = $content;
            return $this;
        }
        return data_get($this->data, 'text');
    }


    public function url($url = '')
    {
        if ($url) {
            $this->data['url'] = $url;
            return $this;
        }
        return data_get($this->data, 'url');
    }


    public function valid(): bool
    {
        return $this->valid;
    }

    abstract public function user();

    abstract public function customer();

    abstract public function format($raw): array;

    abstract public function save();

    abstract public function createTime($time = '');
}