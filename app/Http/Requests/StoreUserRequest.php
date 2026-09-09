<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // TODO: name bắt buộc, chuỗi, tối đa 255 ký tự
            'name' => ['required', 'string', 'max:255'],
            // TODO: email bắt buộc, đúng định dạng, KHÔNG được trùng trong bảng users
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // TODO: password bắt buộc, tối thiểu 8 ký tự, phải khớp password_confirmation
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
