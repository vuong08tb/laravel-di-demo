<!-- Chuyển đổi từ Bai_Tap_Thuc_Hanh_Laravel_DI.docx -->
<!-- Header tài liệu gốc: Tài Liệu Thực Hành Laravel DI & Service Container -->

# HƯỚNG DẪN BÀI TẬP THỰC HÀNH: LÀM CHỦ DEPENDENCY INJECTION (DI) TRONG LARAVEL

## PHẦN I: GIỚI THIỆU CHUNG

Bộ tài liệu bài tập này được biên soạn nhằm giúp bạn từng bước làm quen, thực hành và nâng cao kỹ năng làm việc với **Dependency Injection (DI)** và **Service Container** trong framework Laravel. Qua các bài tập từ cơ bản đến nâng cao này, bạn sẽ học được cách viết mã nguồn sạch hơn, tách biệt các tầng chức năng (decoupling) và tạo điều kiện cực kỳ thuận lợi cho việc viết Unit Test.

> **📌 Lưu ý trước khi làm bài:**
> - Bạn cần cài đặt sẵn một dự án Laravel (phiên bản 10.x hoặc 11.x trở lên là tốt nhất).
> - Mọi hoạt động cấu hình ràng buộc (binding) sẽ được thực hiện chủ yếu trong app/Providers/AppServiceProvider.php.
> - Đọc kỹ phần lý thuyết bổ trợ trong mỗi bài tập trước khi tiến hành viết code.

## PHẦN II: BÀI TẬP THỰC HÀNH CHI TIẾT

### Bài tập 1: Auto-wiring (Phân giải tự động không cần cấu hình)

**🎯 Mục tiêu:** Hiểu cơ chế tự động phân giải (auto-wiring) của Laravel dựa trên Reflection API. Bạn không cần khai báo bất kỳ dòng cấu hình nào trong Service Provider, Laravel vẫn tự nhận biết và khởi tạo dependency nếu đó là một Concrete Class (lớp cụ thể).

**📝 Yêu cầu:**

- Tạo một service cụ thể **App\Services\UserService** chứa hàm lấy danh sách người dùng giả lập.
- Tạo một controller **App\Http\Controllers\UserController**, inject service này vào hàm khởi tạo (__construct).
- Định nghĩa một route trong routes/web.php để gọi controller hiển thị dữ liệu ra dạng JSON.

**💻 Mã nguồn gợi ý ban đầu:**

```php
// 1. Tạo file app/Services/UserService.php
<?php

namespace App\Services;

class UserService
{
    public function getAllUsers(): array
    {
        return [
            ['id' => 1, 'name' => 'Nguyen Van A', 'email' => 'a@gmail.com'],
            ['id' => 2, 'name' => 'Tran Thi B', 'email' => 'b@gmail.com'],
        ];
    }
}
```

**Nhiệm vụ của bạn:**

Hãy hoàn thiện đoạn mã của **UserController** bên dưới bằng cách sử dụng Type-hint UserService trong constructor và gọi hàm trong controller:

```php
// 2. Hoàn thiện file app/Http/Controllers/UserController.php
<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    protected UserService $userService;

    // TODO: Viết hàm __construct để nhận UserService được inject tự động bởi Laravel
    public function __construct(____ $userService)
    {
        $this->userService = $userService;
    }

    public function index(): JsonResponse
    {
        // TODO: Gọi hàm getAllUsers() từ service và trả về kết quả dạng json
        $users = ____;

        return response()->json($users);
    }
}
```

### Bài tập 2: Interface Binding (Liên kết Interface với Implementation)

**🎯 Mục tiêu:** Áp dụng nguyên lý Dependency Inversion (chữ D trong SOLID) bằng cách phụ thuộc vào một Abstraction (Interface) thay vì Concrete Class cụ thể. Điều này giúp hệ thống cực kỳ linh hoạt, có thể thay đổi cách triển khai (ví dụ chuyển đổi nhà cung cấp cổng thanh toán) chỉ với một dòng code cấu hình.

**📝 Yêu cầu:**

- Định nghĩa một interface **App\Contracts\PaymentGatewayInterface** có hàm charge($amount).
- Tạo hai lớp triển khai: **StripePaymentGateway** và **PaypalPaymentGateway** kế thừa interface trên.
- Khai báo liên kết (binding) trong **AppServiceProvider** để khi ứng dụng yêu cầu interface, Laravel sẽ tự động cung cấp đối tượng Stripe hoặc Paypal.

**💻 Mã nguồn gợi ý ban đầu:**

```php
// 1. Định nghĩa Interface (app/Contracts/PaymentGatewayInterface.php)
<?php

namespace App\Contracts;

interface PaymentGatewayInterface
{
    public function charge(float $amount): string;
}

// 2. Tạo Stripe Implementation (app/Services/StripePaymentGateway.php)
<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;

class StripePaymentGateway implements PaymentGatewayInterface
{
    public function charge(float $amount): string
    {
        return "Đã thanh toán $" . $amount . " qua cổng Stripe thành công!";
    }
}

// 3. Tạo Paypal Implementation (app/Services/PaypalPaymentGateway.php)
<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;

class PaypalPaymentGateway implements PaymentGatewayInterface
{
    public function charge(float $amount): string
    {
        return "Đã thanh toán $" . $amount . " qua cổng PayPal thành công!";
    }
}
```

**Nhiệm vụ của bạn:**

1. Đăng ký liên kết trong **AppServiceProvider::register()** để ánh xạ interface sang lớp cụ thể mà bạn muốn sử dụng (ví dụ Stripe):

```php
// app/Providers/AppServiceProvider.php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\PaymentGatewayInterface;
use App\Services\StripePaymentGateway;
use App\Services\PaypalPaymentGateway;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // TODO: Thực hiện dòng lệnh bind Interface với StripePaymentGateway cụ thể tại đây
        $this->app->____(
            PaymentGatewayInterface::class,
            StripePaymentGateway::class
        );
    }
}
```

2. Viết mã nguồn cho **OrderController** để inject interface và tiến hành xử lý thanh toán:

```php
// app/Http/Controllers/OrderController.php
<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    protected PaymentGatewayInterface $paymentGateway;

    // TODO: Inject interface chứ KHÔNG inject Concrete Class
    public function __construct(____ $paymentGateway)
    {
        $this->paymentGateway = $paymentGateway;
    }

    public function checkout(): Response
    {
        // Thực hiện thanh toán thử $150.00
        $message = $this->paymentGateway->charge(150.00);

        return response($message);
    }
}
```

> **❓ Câu hỏi mở rộng:** Sau khi hoàn thành, hãy thử đổi liên kết trong AppServiceProvider từ Stripe sang PayPal. Bạn có cần thay đổi bất kỳ dòng code nào trong OrderController để chương trình chuyển đổi cổng thanh toán hay không? Tại sao?

### Bài tập 3: Contextual Binding (Liên kết động theo ngữ cảnh lớp yêu cầu)

**🎯 Mục tiêu:** Giải quyết trường hợp đặc biệt: Cùng một Interface nhưng các Controller hay Service khác nhau lại yêu cầu các lớp triển khai khác nhau. Ví dụ: Upload Avatar cá nhân thì lưu trữ ở thư mục cục bộ (Local Storage), còn Upload Video dung lượng lớn thì bắt buộc đẩy thẳng lên dịch vụ đám mây AWS S3.

**📝 Yêu cầu:**

- Tạo interface **App\Contracts\StorageDriverInterface** có hàm upload($file).
- Tạo hai lớp triển khai: **LocalStorageDriver** và **S3StorageDriver**.
- Khai báo ràng buộc ngữ cảnh trong Service Provider để **AvatarController** nhận được LocalStorageDriver, còn **VideoController** nhận được S3StorageDriver.

**💻 Mã nguồn gợi ý ban đầu:**

```php
// 1. Tạo các thành phần cốt lõi
namespace App\Contracts;
interface StorageDriverInterface {
    public function upload(string $file): string;
}

namespace App\Services;
use App\Contracts\StorageDriverInterface;

class LocalStorageDriver implements StorageDriverInterface {
    public function upload(string $file): string { return "Lưu file [$file] cục bộ tại /storage/uploads/"; }
}

class S3StorageDriver implements StorageDriverInterface {
    public function upload(string $file): string { return "Đẩy file [$file] lên AWS S3 bucket thành công!"; }
}
```

**Nhiệm vụ của bạn:**

Viết code cấu hình trong **AppServiceProvider::register()** để phân giải đúng drive lưu trữ theo ngữ cảnh từng controller dưới đây:

```php
// app/Providers/AppServiceProvider.php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\StorageDriverInterface;
use App\Services\LocalStorageDriver;
use App\Services\S3StorageDriver;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\VideoController;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // TODO: Định cấu hình Contextual Binding cho AvatarController
        $this->app->when(AvatarController::class)
            ->needs(StorageDriverInterface::class)
            ->give(____); // Điền class uploader cục bộ vào đây

        // TODO: Định cấu hình Contextual Binding cho VideoController
        $this->app->when(VideoController::class)
            ->needs(StorageDriverInterface::class)
            ->give(____); // Điền class uploader AWS S3 vào đây
    }
}
```

### Bài tập 4: Mocking & Testing

**🎯 Mục tiêu:** Nhận thấy sức mạnh to lớn của DI trong việc viết Unit Test. Bằng cách thiết kế lỏng lẻo, bạn hoàn toàn có thể tạo ra một thực thể giả lập (Mock Object) và nạp thế chỗ vào Service Container trong quá trình chạy test để không tốn tài nguyên và chi phí gửi SMS thật ra ngoài.

**📝 Yêu cầu:**

- Tạo một interface gửi tin nhắn **App\Contracts\SmsServiceInterface** với hàm send($phone, $message).
- Tạo một controller **NotificationController** có inject SmsServiceInterface.
- Viết một test case bằng PHPUnit / Pest để kiểm chứng luồng hoạt động mà không kích hoạt SMS gateway thực tế.

**💻 Mã nguồn gợi ý ban đầu:**

```php
// 1. Interface SmsServiceInterface.php
namespace App\Contracts;
interface SmsServiceInterface {
    public function send(string $phone, string $message): bool;
}

// 2. Controller xử lý gửi thông báo (app/Http/Controllers/NotificationController.php)
namespace App\Http\Controllers;
use App\Contracts\SmsServiceInterface;
use Illuminate\Http\Request;

class NotificationController extends Controller {
    protected SmsServiceInterface $sms;
    
    public function __construct(SmsServiceInterface $sms) {
        $this->sms = $sms;
    }
    
    public function sendNotification(Request $request) {
        $phone = $request->input('phone');
        $success = $this->sms->send($phone, "Xin chào! Đây là tin nhắn bảo mật OTP.");
        
        return response()->json(['sent' => $success]);
    }
}
```

**Nhiệm vụ của bạn:**

Hoàn thiện đoạn code Unit Test bên dưới bằng cách sử dụng tính năng Mocking của Laravel để tạo thực thể giả lập của SmsServiceInterface, lập trình cho Mock trả về true và ghi đè nó vào container:

```php
// 3. File tests/Feature/NotificationTest.php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Contracts\SmsServiceInterface;
use Mockery\MockInterface;

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
            'phone' => '0987654321'
        ]);

        // Kiểm tra xem phản hồi có thành công và đúng định dạng mong đợi không
        $response->assertStatus(200)
                 ->assertJson(['sent' => true]);
    }
}
```

