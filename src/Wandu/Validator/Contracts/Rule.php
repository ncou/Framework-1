<?php
namespace Wandu\Validator\Contracts;

interface Rule
{
    /**
     * @return array
     */
    public function definition(): array;
}
