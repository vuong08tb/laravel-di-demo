<?php

namespace App\Http\Controllers;

use App\Contracts\SmsServiceInterface;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected SmsServiceInterface $sms;

    public function __construct(SmsServiceInterface $sms)
    {
        $this->sms = $sms;
    }

    public function sendNotification(Request $request)
    {
        $phone = $request->input('phone');
        $success = $this->sms->send($phone, 'Xin chào! Đây là tin nhắn bảo mật OTP.');

        return response()->json(['sent' => $success]);
    }
}
