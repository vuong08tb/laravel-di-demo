<?php

namespace App\Services;

use App\Contracts\StorageDriverInterface;

class LocalStorageDriver implements StorageDriverInterface
{
    public function upload(string $file): string
    {
        return "Lưu file [$file] cục bộ tại /storage/uploads/";
    }
}
