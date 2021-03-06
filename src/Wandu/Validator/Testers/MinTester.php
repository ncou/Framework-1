<?php
namespace Wandu\Validator\Testers;

use Wandu\Validator\Contracts\Tester;

class MinTester implements Tester
{
    /** @var int */
    protected $min;

    /**
     * @param int $min
     */
    public function __construct($min)
    {
        $this->min = $min;
    }

    /**
     * {@inheritdoc}
     */
    public function test($data, $origin = null, array $keys = []): bool
    {
        return $data >= $this->min;
    }
}
