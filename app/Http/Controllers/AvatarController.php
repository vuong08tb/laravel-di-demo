<?php

namespace App\Http\Controllers;

use App\Contracts\StorageDriverInterface;
use Illuminate\Http\Response;

class AvatarController extends Controller
{
    public function __construct(protected StorageDriverInterface $storageDriver) {}

    public function upload(): Response
    {
        $message = $this->storageDriver->upload('avatar.jpg');

        return response($message);
    }
}
