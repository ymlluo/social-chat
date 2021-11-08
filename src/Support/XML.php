<?php

namespace ymlluo\Wechat\Support;

use ymlluo\Wechat\Exceptions\ErrorCode;
use ymlluo\Wechat\Exceptions\WxException;

class XML
{

    /**
     * xml to array
     * @param $xmlStr
     * @return array
     */
    public static function xml2Array($xmlStr)
    {
        $xmlStr = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]+/u', '', $xmlStr);
        $xml_entity_loader = libxml_disable_entity_loader(true);
        $array = (array)simplexml_load_string($xmlStr, 'SimpleXMLElement', LIBXML_COMPACT | LIBXML_NOCDATA | LIBXML_NOBLANKS);
        libxml_disable_entity_loader($xml_entity_loader);
        return $array;
    }

    /**
     * XML编码
     * @param $data
     * @param string $root
     * @param string $item
     * @param string $attributes
     * @param string $id
     * @return string
     */
    public static function generateXml($data, $root = 'xml', $item = 'item', $attributes = '', $id = 'id')
    {
        $attributeString = $attributes;
        if (is_array($attributes)) {
            foreach ($attributes as $k => $v) {
                $attributeString .= " {$k}=\"{$v}\"";
            }
        }
        $attributeString = trim($attributeString);
        $attributeString = $attributeString ? " {$attributeString}" : "";
        $xml = "<{$root}{$attributeString}>";
        $xml .= self::data2Xml($data, $item, $id);
        $xml .= "</{$root}>";
        return $xml;
    }

    private static function data2Xml($data, $item = 'item', $id = 'id')
    {
        $xml = $attr = '';
        foreach ($data as $key => $val) {
            if (is_numeric($key)) {
                $id && $attr = " {$id}=\"{$key}\"";
                $key = $item;
            }
            $xml .= "<{$key}{$attr}>";
            if ((is_array($val) || is_object($val))) {
                $xml .= self::data2Xml((array)$val, $item, $id);
            } else {
                $xml .= is_numeric($val) ? $val : '<![CDATA[' . preg_replace("/[\\x00-\\x08\\x0b-\\x0c\\x0e-\\x1f]/", '', $val) . ']]>';
            }
            $xml .= "</{$key}>";
        }

        return $xml;
    }
}
