<?php
namespace Wandu\Queue\Contracts;

interface SerializerInterface
{
    /**
     * @param mixed $value
     * @return string
     */
    public function serialize($value);

    /**
     * @param string $string
     * @return mixed
     */
    public function unserialize($string);
}
