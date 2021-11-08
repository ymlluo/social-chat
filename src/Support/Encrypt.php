<?php


namespace ymlluo\Wechat\Support;


use ymlluo\Wechat\Exceptions\ErrorCode;
use ymlluo\Wechat\Exceptions\WxException;

class Encrypt
{

    /**
     *
     * @param $appId
     * @param $aesKey
     * @param $token
     * @param $xmlStr
     * @return string
     * @throws \Exception
     */
    public static function encodeXML($appId, $aesKey, $token, $xmlStr): string
    {
        $xmlStr = openssl_random_pseudo_bytes(16) . pack('N', strlen($xmlStr)) . $xmlStr . $appId;
        $padLen = 32 - strlen($xmlStr) % 32;
        $xmlStr = $xmlStr . str_repeat(chr($padLen), $padLen);
        $key = self::decodeKey($aesKey);
        $cipherText = openssl_encrypt($xmlStr, 'AES-256-CBC', $key, OPENSSL_ZERO_PADDING, substr($key, 0, 16));
        if ($cipherText === false) {
            throw new \Exception('Encrypt AES Error');
        }
        $timestamp = time();
        $nonce = rand(100000000, 999999999);
        $signature = self::signature($token, $timestamp, $nonce, $cipherText);
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n<xml><Encrypt><![CDATA[$cipherText]]></Encrypt><MsgSignature><![CDATA[$signature]]></MsgSignature><TimeStamp>$timestamp</TimeStamp><Nonce><![CDATA[$nonce]]></Nonce></xml>\r\n";
        return $xml;
    }

    public static function decodeXML($xmlStr, $aesKey)
    {
        $arr = XML::xml2Array($xmlStr);
        $cipherText = $arr['Encrypt'];
        $key = self::decodeKey($aesKey);
        $decrypt = openssl_decrypt($cipherText, 'AES-256-CBC', $key, OPENSSL_ZERO_PADDING, substr($key, 0, 16));
        if ($decrypt === false) {
            throw new \Exception('Decrypt AES Error');
        }
        $padLen = ord(substr($decrypt, -1));
        if ($padLen > 0 && $padLen <= 32) {
            $decrypt = substr($decrypt, 0, -$padLen);;
        }
        $content = substr($decrypt, 16);
        $len = unpack("N", substr($content, 0, 4));
        $len = $len[1];
        $xml = substr($content, 4, $len);
        return $xml;
    }


    /**
     *
     * @param $token
     * @param $timestamp
     * @param $nonce
     * @param string $data
     * @return string
     */
    public static function signature($token, $timestamp, $nonce, $data = ''): string
    {
        $array = [$token, $timestamp, $nonce, $data];
        sort($array, SORT_STRING);
        return sha1(implode('', $array));
    }

    /**
     * @param string $aesKey
     * @return false|string
     */
    public static function decodeKey(string $aesKey)
    {
        return base64_decode($aesKey . '=');
    }

}
