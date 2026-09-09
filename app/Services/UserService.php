<?php

namespace App\Services;

class UserService
{
    /**
     * Get the user list
     */
    public function getAllUsers(): array
    {
        return [
            ['id' => 1, 'name' => 'Nguyen Van A', 'email' => 'a@gmail.com'],
            ['id' => 2, 'name' => 'Tran Thi B', 'email' => 'b@gmail.com'],
        ];
    }
}
