<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserService
{
    /**
     * Get the user list
     */
    public function getAllUsers(): Collection
    {
        // TODO: dùng model User, sắp xếp theo cột 'name', lấy tất cả
        return User::orderBy('name')->get();
    }
}
