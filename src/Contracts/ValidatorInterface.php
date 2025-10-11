<?php

namespace GustRouter\Contracts;

interface ValidatorInterface
{
    public function required($value): bool;
    public function email($value): bool;
    public function minLength($value, $length): bool;
}