<?php
namespace Orbit;


class EncodedUUID {

    const CHARS_D64 = ".0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz-";
    const CHARS_B64 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
    protected static $modelsUsing = [];

    public static function make() {
        $u = uuid_create(UUID_TYPE_TIME);
        $hex = substr($u, 14, 4) . substr($u, 9, 4) . substr($u, 0, 8) . substr($u, 19, 4) . substr($u, 24);
        return rtrim(strtr(base64_encode(hex2bin($hex)), EncodedUUID::CHARS_B64, EncodedUUID::CHARS_D64), '-');
    }

    public static function makeMany($count) {
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = static::make();
        }
        return $result;
    }

    public static function registerUseInModel($class)
    {
        if (!in_array($class, static::$modelsUsing)) {
            static::$modelsUsing[] = $class;
        }
    }

    public static function getModelsUsing()
    {
        return static::$modelsUsing;
    }
}