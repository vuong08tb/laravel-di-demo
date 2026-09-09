<?php

namespace App\Http\Controllers;

use App\Contracts\StorageDriverInterface;
use Illuminate\Http\Response;

class VideoController extends Controller
{
    public function __construct(protected StorageDriverInterface $storageDriver) {}

    public function upload(): Response
    {
        $message = $this->storageDriver->upload('nguoi.mp4');

        return response($message);
    }
}
