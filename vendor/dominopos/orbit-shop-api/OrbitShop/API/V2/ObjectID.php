<?php namespace OrbitShop\API\V2;


use JsonSerializable;
use OrbitShop\API\V2\ObjectID\Generator;
use OrbitShop\API\V2\ObjectID\InvalidException;

class ObjectID implements JsonSerializable {

    const CHARS_D64 = "-0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz|";
    const CHARS_B64 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";

    /**
     * @var string
     */
    private $data;

    public static function make()
    {
        return new static;
    }

    /**
     * @param string $id
     * @throws InvalidException
     */
    public function __construct($id = NULL)
    {
        if ($id && $this->isValid($id))
        {
            $this->data = hex2bin($id);

            return;
        }

        if ($id && $this->isd64($id))
        {
            $this->data = $this->d64_decode_tr($id);

            return;
        }

        if ($id)
        {
            throw new InvalidException;
        }

        $this->data = $this->generate();
    }

    public function generate()
    {
        return Generator::getInstance()->nextId();
    }

    public function hex()
    {
        return bin2hex($this->data);
    }

    public function __toString()
    {
        return $this->d64();
    }

    /**
     * @param $str
     * @return bool
     */
    private function isValid($str)
    {
        return preg_match('/\\A[0-9a-f]{24}\\z/i', $str) ? true : false;
    }

    /**
     * @param $str
     * @return bool
     */
    private function isd64($str)
    {
        return preg_match('!\\A[0-9A-Za-z_\\|-]{16}\\z!i', $str) ? true : false;
    }

    public function d64()
    {
        return $this->d64_encode_tr($this->data);
    }

    private function d64_encode_tr($enc)
    {
        return rtrim(strtr(base64_encode($enc), static::CHARS_B64, static::CHARS_D64), '|');
    }

    private function d64_decode_tr($enc)
    {
        return base64_decode(strtr($enc, static::CHARS_D64, static::CHARS_B64), true);
    }

    /**
     * {@inheritDoc}
     */
    function jsonSerialize()
    {
        return $this->__toString();
    }
}
