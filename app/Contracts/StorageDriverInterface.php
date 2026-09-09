<?php

namespace App\Contracts;

interface StorageDriverInterface
{
    public function upload(string $file): string;
}
