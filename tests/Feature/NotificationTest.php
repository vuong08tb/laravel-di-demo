<?php

namespace Tests\Feature;

use App\Contracts\SmsServiceInterface;
use Mockery\MockInterface;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    public function test_it_can_send_notification_using_mocked_sms_service()
    {
        // TODO: Tạo thực thể Mock từ SmsServiceInterface sử dụng Mockery
        $mockSms = $this->mock(SmsServiceInterface::class, function (MockInterface $mock) {
            // Thiết lập kỳ vọng hàm 'send' được gọi chính xác 1 lần và trả về giá trị true
            $mock->shouldReceive('send')
                ->once()
                ->with('0987654321', 'Xin chào! Đây là tin nhắn bảo mật OTP.')
                ->andReturn(true);
        });

        // Lúc này khi chúng ta thực hiện request đến API, Laravel Service Container
        // sẽ tự động nạp $mockSms thay vì class thật
        $response = $this->postJson('/api/send-notification', [
            'phone' => '0987654321',
        ]);

        // Kiểm tra xem phản hồi có thành công và đúng định dạng mong đợi không
        $response->assertStatus(200)
            ->assertJson(['sent' => true]);
    }
}