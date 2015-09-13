<?php
namespace Wandu\Http\Contracts;

use ArrayAccess;

interface SessionInterface extends ArrayAccess
{
    /**
     * @return array
     */
    public function toArray();

    /**
     * @param string $name
     * @return mixed
     */
    public function get($name);

    /**
     * @param string $name
     * @param mixed $value
     * @return self
     */
    public function set($name, $value);

    /**
     * @param string $name
     * @return bool
     */
    public function has($name);

    /**
     * @param string $name
     * @return self
     */
    public function remove($name);
}
