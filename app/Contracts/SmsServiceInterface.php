<?php

namespace App\Contracts;

interface SmsServiceInterface
{
    public function send(string $phone, string $message): bool;
}
