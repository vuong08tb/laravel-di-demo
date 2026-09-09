<?php

namespace App\Http\Controllers;

use App\Services\UserService;

class UserController extends Controller
{
    //
    public function __construct(protected UserService $userService)
    {
        //
    }

    public function index()
    {
        $users = $this->userService->getAllUsers();

        return response()->json($users);
    }
}
