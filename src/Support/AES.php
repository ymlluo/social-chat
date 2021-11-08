<?php


namespace ymlluo\Wechat\Support;


use ymlluo\Wechat\Exceptions\ErrorCode;
use ymlluo\Wechat\Exceptions\WxException;

class AES
{


    public static function encode(string $text,string $key,string $iv){
        if(self::isKey($key) && self::isIv($iv)){
            return openssl_encrypt($text, 'AES-256-CBC', $key, OPENSSL_ZERO_PADDING, $iv);
        }
       return false;
    }

    public static function decode(string $text,string $key,string $iv){
        if(self::isKey($key) && self::isIv($iv)){
            return  $decrypt = openssl_decrypt($text, 'AES-256-CBC', $key, OPENSSL_ZERO_PADDING,$iv);
        }
        return false;
    }

    /**
     *
     */
    public static function isKey(string $key)
    {
        return in_array(strlen($key), [16, 24, 32], true);
    }

    public static function isIv(string $iv)
    {
       return strlen($iv) === 16;
    }


}
