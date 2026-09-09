# Hướng Dẫn Phân Tích & Kế Hoạch Làm Bài — Laravel DI

> Tài liệu bổ trợ cho [Bai_Tap_Thuc_Hanh_Laravel_DI.md](Bai_Tap_Thuc_Hanh_Laravel_DI.md).
> Mục đích: bóc tách từng yêu cầu thành việc cụ thể, chỉ rõ file cần tạo, giải thích cơ chế đằng sau, và cách tự kiểm chứng kết quả.

---

## 0. Bối cảnh môi trường (đọc trước khi bắt đầu)

Project này **chạy trong Docker**, không chạy trực tiếp trên máy. Mọi lệnh `artisan` phải thực thi bên trong container:

```bash
docker compose up -d                       # khởi động (lần đầu trong ngày)
docker exec di_demo_app php artisan <lệnh> # chạy artisan
docker compose logs -f app                 # xem log khi lỗi
```

| Thông số | Giá trị |
|---|---|
| URL ứng dụng | http://localhost:8001 |
| Container app | `di_demo_app` |
| Container DB | `di_demo_db` (PostgreSQL 16) |
| DB từ trong container | `db:5432` |
| DB từ máy host (DBeaver) | `localhost:5435` |

### ⚠️ Khác biệt giữa tài liệu và project

Tài liệu gốc được viết cho **Laravel 10.x/11.x**, còn project đang chạy **Laravel 13 / PHP 8.5**. Ba điểm cần lưu ý:

1. **Không có `app/Http/Kernel.php`** — cấu hình middleware nằm trong `bootstrap/app.php`.
2. **Không có `routes/api.php`** — Bài tập 4 gọi tới `/api/send-notification` nên bạn phải tự tạo file này (xem Bài 4).
3. **Service Provider đăng ký ở `bootstrap/providers.php`** — nhưng `AppServiceProvider` đã có sẵn nên bạn không cần đụng tới.

### Checklist khởi động

- [x] `docker compose up -d` và kiểm tra `docker ps` thấy cả 2 container `Up`
- [x] Mở http://localhost:8001 thấy trang chào mừng Laravel (status 200)
- [x] `docker exec di_demo_app php artisan migrate:status` không báo lỗi

---

## 1. Bài tập 1 — Auto-wiring

### Yêu cầu nói gì

Tạo `UserService` (class cụ thể), inject vào `UserController` qua constructor, khai báo route trả JSON. **Không viết một dòng cấu hình nào** trong Service Provider.

### Cơ chế cần hiểu

Khi Laravel cần dựng `UserController`, nó dùng **Reflection API** đọc chữ ký `__construct()`, thấy type-hint là `UserService` — một **concrete class** (class cụ thể, không phải interface, không phải abstract). Container tự `new UserService()` rồi truyền vào. Đây gọi là **auto-wiring** (phân giải tự động).

Điều kiện để auto-wiring hoạt động:
- Type-hint phải là class cụ thể, khởi tạo được.
- Constructor của class đó không có tham số vô danh (kiểu `string $apiKey` mà container không đoán được).

Nếu type-hint là **interface** thì auto-wiring **thất bại** — đó chính là lý do tồn tại của Bài tập 2.

### Việc cần làm

| # | Việc | Lệnh / File |
|---|---|---|
| 1 | Tạo service | `docker exec di_demo_app php artisan make:class Services/UserService` |
| 2 | Viết hàm `getAllUsers(): array` trả mảng user giả lập | `app/Services/UserService.php` |
| 3 | Tạo controller | `docker exec di_demo_app php artisan make:controller UserController` |
| 4 | Điền type-hint vào constructor | `app/Http/Controllers/UserController.php` |
| 5 | Thêm route | `routes/web.php` |

### Chỗ cần điền (`____`)

- **`public function __construct(____ $userService)`** → điền tên class cụ thể. Nhớ `use App\Services\UserService;` ở đầu file.
- **`$users = ____;`** → gọi phương thức đã viết ở bước 2 thông qua thuộc tính `$this->userService`.

> 💡 **Mẹo PHP 8**: thay vì khai báo `protected UserService $userService;` rồi gán trong constructor, bạn có thể dùng **constructor property promotion** cho gọn:
> ```php
> public function __construct(protected UserService $userService) {}
> ```
> Kết quả hoàn toàn tương đương, ít code hơn hẳn.

### Route cần thêm

```php
// routes/web.php
use App\Http\Controllers\UserController;

Route::get('/users', [UserController::class, 'index']);
```

### Cách kiểm chứng

```bash
curl http://localhost:8001/users
```
Kỳ vọng: JSON chứa 2 user. Nếu thấy lỗi `Target class [...] does not exist` → sai namespace hoặc thiếu `use`.

- [x] `/users` trả về JSON đúng 2 phần tử
- [x] `AppServiceProvider::register()` vẫn **rỗng** (đây là điểm mấu chốt của bài này)

---

## 2. Bài tập 2 — Interface Binding

### Yêu cầu nói gì

Tạo `PaymentGatewayInterface`, hai lớp triển khai `StripePaymentGateway` và `PaypalPaymentGateway`, rồi cấu hình để container biết phải cấp phát lớp nào khi gặp interface.

### Cơ chế cần hiểu

Đây là chữ **D** trong SOLID — **Dependency Inversion**: module cấp cao phụ thuộc vào *abstraction*, không phụ thuộc vào *implementation*.

Container **không thể tự đoán** bạn muốn Stripe hay PayPal khi thấy `PaymentGatewayInterface` — interface không khởi tạo được. Bạn phải khai báo tường minh trong `AppServiceProvider::register()`.

Ba phương thức đăng ký cần phân biệt:

| Phương thức | Hành vi | Dùng khi |
|---|---|---|
| `bind()` | Tạo **instance mới** mỗi lần resolve | Mặc định, dùng cho đa số trường hợp |
| `singleton()` | Tạo **một lần duy nhất**, tái sử dụng suốt vòng đời request | Object nặng, giữ state chung (connection, config) |
| `scoped()` | Như singleton nhưng reset mỗi request (quan trọng với Octane/queue worker) | Chạy môi trường long-running |

Với bài này, `bind()` là lựa chọn đúng — cổng thanh toán không giữ state gì cần chia sẻ.

### Việc cần làm

| # | Việc | Lệnh / File |
|---|---|---|
| 1 | Tạo interface | `docker exec di_demo_app php artisan make:interface Contracts/PaymentGatewayInterface` |
| 2 | Khai báo `charge(float $amount): string` | `app/Contracts/PaymentGatewayInterface.php` |
| 3 | Tạo 2 implementation | `make:class Services/StripePaymentGateway` và `Services/PaypalPaymentGateway` |
| 4 | Cả 2 lớp phải `implements PaymentGatewayInterface` | — |
| 5 | Đăng ký binding | `app/Providers/AppServiceProvider.php` → `register()` |
| 6 | Tạo `OrderController` + route `/checkout` | — |

### Chỗ cần điền (`____`)

- **`$this->app->____(PaymentGatewayInterface::class, StripePaymentGateway::class)`** → chọn 1 trong 3 phương thức ở bảng trên. Với bài này dùng `bind`.
- **`public function __construct(____ $paymentGateway)`** → type-hint **interface**, tuyệt đối không phải class cụ thể. Đây là toàn bộ tinh thần của bài tập.

> ⚠️ **Lỗi thường gặp**: đăng ký binding trong `boot()` thay vì `register()`. Quy tắc: `register()` chỉ để *đăng ký* vào container; `boot()` chạy sau khi mọi provider đã register xong, dùng cho việc cần truy cập service khác.

### Trả lời câu hỏi mở rộng

> *"Đổi từ Stripe sang PayPal có cần sửa `OrderController` không? Tại sao?"*

**Không.** Vì `OrderController` chỉ type-hint `PaymentGatewayInterface` — nó không hề biết đến sự tồn tại của `StripePaymentGateway`. Đổi một dòng trong `AppServiceProvider` là toàn bộ ứng dụng chuyển cổng thanh toán.

Hãy tự chứng minh bằng cách đổi binding sang `PaypalPaymentGateway::class`, gọi lại `/checkout` và xem message đổi từ "Stripe" thành "PayPal" mà không đụng vào controller. Đây là bài học quan trọng nhất của cả bộ bài tập — **hãy làm thật, đừng chỉ đọc**.

### Cách kiểm chứng

```bash
curl http://localhost:8001/checkout
```

- [x] Trả về message của Stripe
- [x] Đổi binding sang Paypal → message đổi, **không sửa `OrderController`**
- [x] Thử xóa dòng binding đi → gặp lỗi `Target [PaymentGatewayInterface] is not instantiable` (hiểu vì sao)

---

## 3. Bài tập 3 — Contextual Binding

### Yêu cầu nói gì

Cùng một interface `StorageDriverInterface`, nhưng `AvatarController` phải nhận `LocalStorageDriver`, còn `VideoController` phải nhận `S3StorageDriver`.

### Cơ chế cần hiểu

Binding thường (`bind`) là **toàn cục** — một interface chỉ ánh xạ tới đúng một implementation cho cả ứng dụng. Khi hai nơi cần hai thứ khác nhau, dùng **contextual binding** với cú pháp ba tầng:

```
$this->app->when(<Class nào đang yêu cầu>)
          ->needs(<Interface gì>)
          ->give(<Trả về cái gì>);
```

Đọc thành câu: *"Khi `AvatarController` cần `StorageDriverInterface`, hãy đưa nó `LocalStorageDriver`."*

### Việc cần làm

| # | Việc |
|---|---|
| 1 | Tạo `app/Contracts/StorageDriverInterface.php` với `upload(string $file): string` |
| 2 | Tạo `LocalStorageDriver` và `S3StorageDriver`, cả 2 `implements` interface trên |
| 3 | Tạo `AvatarController` và `VideoController`, cả 2 inject `StorageDriverInterface` |
| 4 | Viết 2 khối `when()->needs()->give()` trong `AppServiceProvider::register()` |
| 5 | Thêm 2 route để gọi thử |

> 📌 Trong tài liệu gốc, đoạn code Bài 3 gộp nhiều `namespace` vào chung một khối cho ngắn gọn. Thực tế **mỗi class phải nằm trong file riêng**, đúng chuẩn PSR-4: một file = một class, tên file trùng tên class.

### Chỗ cần điền (`____`)

Cả hai `->give(____)` đều nhận **tên class** dạng `::class`. Chú ý đọc comment gợi ý ngay bên cạnh trong tài liệu gốc để biết controller nào ăn driver nào.

### Cách kiểm chứng

- [x] Route avatar trả message chứa "cục bộ"
- [x] Route video trả message chứa "AWS S3"
- [x] Cùng một interface nhưng ra 2 kết quả khác nhau — **đây là điểm cần quan sát**

> 💡 **Câu hỏi tự vấn**: nếu vừa có `bind()` toàn cục *vừa* có `when()` cho một controller, cái nào thắng? Hãy thử: thêm `$this->app->bind(StorageDriverInterface::class, LocalStorageDriver::class)` rồi gọi route video. Contextual binding có độ ưu tiên cao hơn.

---

## 4. Bài tập 4 — Mocking & Testing

### Yêu cầu nói gì

Tạo `SmsServiceInterface`, `NotificationController` inject interface đó, rồi viết test kiểm chứng luồng hoạt động **mà không gửi SMS thật**.

### Cơ chế cần hiểu

Đây là lý do quan trọng nhất khiến người ta dùng DI. Vì controller phụ thuộc vào *interface* chứ không tự `new` một class cụ thể, ta có thể **thay thế implementation trong lúc test**.

`$this->mock(SmsServiceInterface::class, ...)` làm hai việc:
1. Tạo một đối tượng giả (mock) từ interface bằng thư viện **Mockery**.
2. **Ghi đè** binding trong Service Container — từ giờ ai xin `SmsServiceInterface` sẽ nhận mock này.

Khi test gọi `postJson()`, Laravel dựng `NotificationController` và tiêm mock vào thay vì class thật. Không có SMS nào được gửi, không tốn tiền, test chạy nhanh.

Các kỳ vọng (expectation) trong đoạn code mẫu:

| Dòng | Ý nghĩa |
|---|---|
| `shouldReceive('send')` | Hàm `send` **phải** được gọi |
| `->once()` | Đúng 1 lần — gọi 0 lần hoặc 2 lần đều fail |
| `->with('0987654321', '...')` | Tham số truyền vào phải khớp chính xác |
| `->andReturn(true)` | Bắt mock trả về `true` |

### 🚨 Việc bắt buộc làm thêm (tài liệu gốc không nói)

Test gọi tới `/api/send-notification`, nhưng **project này chưa có `routes/api.php`** và `bootstrap/app.php` chưa đăng ký route API. Nếu bỏ qua bước này, test sẽ fail với lỗi **404**.

Có 2 cách xử lý:

**Cách A — tự thêm route API (khuyến nghị, không cài thêm gì):**

1. Tạo file `routes/api.php`:
```php
<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::post('/send-notification', [NotificationController::class, 'sendNotification']);
```

2. Khai báo trong `bootstrap/app.php`:
```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',   // <-- thêm dòng này
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

3. Kiểm tra route đã nhận:
```bash
docker exec di_demo_app php artisan route:list --path=api
```

**Cách B — dùng `php artisan install:api`:** lệnh này làm hộ bạn bước 1 và 2, nhưng đồng thời **cài thêm package `laravel/sanctum`** và một migration `personal_access_tokens`. Bài tập không cần xác thực API nên đây là thứ thừa. Chỉ dùng nếu bạn muốn tìm hiểu Sanctum.

### Việc cần làm

| # | Việc | Lệnh / File |
|---|---|---|
| 1 | Tạo interface | `make:interface Contracts/SmsServiceInterface` |
| 2 | Tạo controller | `make:controller NotificationController` |
| 3 | **Thêm route API** (xem mục 🚨 ở trên) | `routes/api.php` + `bootstrap/app.php` |
| 4 | Tạo file test | `docker exec di_demo_app php artisan make:test --phpunit NotificationTest` |
| 5 | Viết test theo mẫu trong tài liệu | `tests/Feature/NotificationTest.php` |

> ⚠️ Bài này **không cần** đăng ký binding thật cho `SmsServiceInterface` trong `AppServiceProvider` thì test vẫn chạy được, vì mock đã ghi đè container. Nhưng nếu bạn muốn gọi API thật bằng `curl` thì phải có binding — hãy tạo thêm một `LogSmsService` chỉ ghi log rồi bind vào.

### Cách kiểm chứng

```bash
docker exec di_demo_app php artisan test --filter=NotificationTest
```

- [ ] Test **PASS**
- [ ] Thử sửa `->once()` thành `->twice()` → test phải **FAIL** (chứng tỏ expectation có tác dụng thật, không phải test giả)
- [ ] Thử sửa số điện thoại trong `->with()` cho lệch → cũng phải **FAIL**

Hai phép thử ngược này quan trọng: một test luôn xanh bất kể code đúng sai là test vô giá trị.

---

## 5. Bảng tra cứu nhanh

| Tình huống | Cách xử lý |
|---|---|
| Phụ thuộc là **class cụ thể** | Không cần cấu hình — auto-wiring lo hết |
| Phụ thuộc là **interface** | `$this->app->bind(Interface::class, Impl::class)` |
| Muốn dùng chung **1 instance** | `singleton()` thay cho `bind()` |
| Mỗi class cần **implementation khác nhau** | `when()->needs()->give()` |
| Cần **thay thế khi test** | `$this->mock(Interface::class, fn ($mock) => ...)` |
| Cần truyền **tham số vô hướng** (string, int) | `when(X::class)->needs('$apiKey')->give(fn () => config('...'))` |

## 6. Lỗi thường gặp

| Thông báo lỗi | Nguyên nhân |
|---|---|
| `Target [XInterface] is not instantiable` | Quên đăng ký binding, hoặc đăng ký nhầm vào `boot()` |
| `Target class [App\Services\X] does not exist` | Sai namespace, sai tên file, hoặc thiếu `use` |
| Test trả **404** | Chưa thêm `routes/api.php` vào `bootstrap/app.php` |
| Sửa code mà không thấy đổi | `docker exec di_demo_app php artisan optimize:clear` |
| `SQLSTATE ... relation does not exist` | Chưa chạy `php artisan migrate` |

## 7. Checklist tổng kết cuối ngày

- [ ] **Bài 1** — `/users` trả JSON, `AppServiceProvider` vẫn rỗng
- [ ] **Bài 2** — đổi Stripe ↔ PayPal chỉ bằng 1 dòng, controller không đổi
- [ ] **Bài 3** — 2 controller, 2 driver khác nhau, cùng 1 interface
- [ ] **Bài 4** — test PASS, và FAIL đúng như mong đợi khi cố tình phá expectation
- [ ] Chạy `docker exec di_demo_app vendor/bin/pint --dirty` để chuẩn hóa code style
- [ ] Chạy `docker exec di_demo_app php artisan test` — toàn bộ suite xanh
