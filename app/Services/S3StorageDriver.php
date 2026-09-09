<?php

namespace App\Services;

use App\Contracts\StorageDriverInterface;

class S3StorageDriver implements StorageDriverInterface
{
    public function upload(string $file): string
    {
        return "Đẩy file [$file] lên AWS S3 bucket thành công!";
    }
}
