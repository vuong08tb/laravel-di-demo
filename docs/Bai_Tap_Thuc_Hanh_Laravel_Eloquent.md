# HƯỚNG DẪN BÀI TẬP THỰC HÀNH: ELOQUENT, VALIDATION & BẪY N+1

## PHẦN I: GIỚI THIỆU CHUNG

Bộ bài tập này nối tiếp bộ **Dependency Injection**. Nếu bộ DI dạy bạn cách *lắp ráp* các thành phần, thì bộ này dạy cách *làm việc với dữ liệu* — thứ chiếm phần lớn thời gian của một lập trình viên Laravel đi làm thật.

Điểm xuất phát: hiện `UserService::getAllUsers()` đang trả về **mảng cứng**, chưa hề chạm database, trong khi bảng `users` đã tồn tại đầy đủ và `UserFactory` đã có sẵn. Toàn bộ bài tập bên dưới bắt đầu từ việc gỡ mảng cứng đó ra.

> **📌 Lưu ý trước khi làm bài:**
> - Làm tuần tự từ Bài 1. Bài 3 phụ thuộc vào Bài 1.
> - **Bài 3 là bài quan trọng nhất** của cả bộ. Nếu thiếu thời gian, làm Bài 1 rồi nhảy thẳng sang Bài 3.
> - Mỗi bài đều có phần **Cách kiểm chứng**. Đừng bỏ qua — bài này học bằng cách *quan sát số liệu*, không phải đọc.

---

## PHẦN 0: BỐI CẢNH MÔI TRƯỜNG

| Thông số | Giá trị |
|---|---|
| Laravel | 13.30.1 |
| PHP | 8.5 |
| URL ứng dụng | http://localhost:8001 |
| Container app | `di_demo_app` |
| Container DB | `di_demo_db` (PostgreSQL 16) |
| DB từ host (DBeaver) | `localhost:5435` |
| **DB khi chạy test** | **sqlite in-memory** (khai báo trong `phpunit.xml`) |
| Telescope | Đã cài (`laravel/telescope` v5.24) — http://localhost:8001/telescope |

### ⚠️ Ba khác biệt dễ vấp

**1. Test không dùng PostgreSQL.** `phpunit.xml` ép `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`. Nghĩa là chạy test **không hề đụng** vào dữ liệu Postgres của bạn, và mỗi lần chạy database test được dựng lại từ số không. Đây là lý do `RefreshDatabase` an toàn.

**2. Model dùng cú pháp Attribute.** Laravel 13 trong project này khai báo:

```php
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
```

Đa số tutorial trên mạng vẫn viết `protected $fillable = [...]`. **Cả hai đều chạy được**, nhưng khi tạo model mới hãy theo cú pháp attribute cho đồng bộ với `User` đang có.

**3. Route bài DI là `/order`**, không phải `/checkout` như một số tài liệu ghi.

### 🔭 Telescope — công cụ chính của bộ bài này

Project đã cài sẵn **Laravel Telescope**, mở tại **http://localhost:8001/telescope**. Đây là thứ bạn sẽ dùng để *nhìn thấy* điều đang xảy ra bên dưới, thay vì đoán.

| Tab | Dùng để |
|---|---|
| **Requests** | Mỗi request là một dòng. Bấm vào xem **toàn bộ query của riêng request đó** — đây là tab quan trọng nhất cho Bài 3 |
| **Queries** | Mọi câu SQL, kèm thời gian chạy. Query chậm bị tô đỏ |
| **Models** | Đếm số model được tạo/cập nhật — dấu hiệu gián tiếp của N+1 |
| **Exceptions** | Thay cho việc mò `storage/logs/laravel.log` |

**⚠️ Ba điều phải biết trước khi dùng:**

**1. Mỗi request web đã tốn sẵn 2 query.** Project này để `SESSION_DRIVER=database`, nên mỗi request đều có:

```sql
select * from "sessions" where "id" = '...'     -- đọc session
insert into "sessions" (...)                    -- ghi session
```

Khi đếm query cho Bài 3, **đừng tính 2 câu này vào**. Chỉ đếm các câu chạm bảng `users` và `posts`. Telescope hiển thị rõ từng câu nên bạn nhìn là phân biệt được ngay.

**2. Telescope tự ghi vào database.** Bảng `telescope_entries` phình rất nhanh. Dọn định kỳ:

```bash
docker exec di_demo_app php artisan telescope:prune --hours=24
```

Muốn bắt đầu đo lại từ số không:

```bash
docker exec di_demo_app php artisan telescope:clear
```

**3. Telescope là công cụ *chỉ dành cho dev*.** Nó lưu lại toàn bộ nội dung request — bao gồm cả mật khẩu người dùng gõ vào form. Vì vậy nó được cài bằng `--dev`, và `TelescopeServiceProvider` có sẵn hàm `gate()` giới hạn ai được xem khi chạy ngoài môi trường local. **Không bao giờ bật Telescope công khai trên production.**

### Checklist khởi động

- [ ] `docker compose up -d` — cả 2 container `Up`
- [ ] `docker exec di_demo_app php artisan migrate:status` — không lỗi
- [ ] `docker exec di_demo_app php artisan test` — suite hiện tại xanh
- [ ] `curl http://localhost:8001/users` — trả về 2 user **giả lập** (chính là thứ ta sắp thay)
- [ ] Mở http://localhost:8001/telescope — thấy giao diện Telescope, tab **Requests** có dữ liệu

---

## PHẦN II: BÀI TẬP THỰC HÀNH CHI TIẾT

### Bài tập 1: Nối service vào database thật (Eloquent + Factory + Seeder)

**🎯 Mục tiêu:** Thay dữ liệu giả lập bằng truy vấn Eloquent thật. Hiểu bộ ba **Migration → Factory → Seeder**: migration định nghĩa *cấu trúc*, factory định nghĩa *hình dạng một bản ghi mẫu*, seeder quyết định *đổ bao nhiêu vào đâu*.

**📝 Yêu cầu:**

- Sửa `UserService::getAllUsers()` để đọc từ bảng `users` qua model `App\Models\User`.
- Bật dòng seed 10 user trong `DatabaseSeeder`.
- Giữ nguyên `UserController` — **không sửa một dòng nào**. Đây là phần thưởng của DI: đổi ruột service mà nơi gọi không hay biết.

**💻 Mã nguồn gợi ý ban đầu:**

```php
// database/seeders/DatabaseSeeder.php — bỏ comment dòng này
public function run(): void
{
    User::factory(10)->create();

    User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
}
```

**Nhiệm vụ của bạn:**

Hoàn thiện `UserService`. Chú ý kiểu trả về đã đổi từ `array` sang `Collection`:

```php
// app/Services/UserService.php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserService
{
    /**
     * Lấy toàn bộ user, sắp xếp theo tên tăng dần.
     */
    public function getAllUsers(): Collection
    {
        // TODO: dùng model User, sắp xếp theo cột 'name', lấy tất cả
        return ____;
    }
}
```

Chạy migrate + seed:

```bash
docker exec di_demo_app php artisan migrate:fresh --seed
```

### Cách kiểm chứng

- [ ] `curl http://localhost:8001/users` trả về **11 user** (10 factory + 1 Test User), không còn "Nguyen Van A"
- [ ] Số bản ghi khớp: `docker exec di_demo_db psql -U di_demo_user -d di_demo_db -t -c "select count(*) from users;"`
- [ ] `UserController.php` **không bị sửa dòng nào** — kiểm tra bằng `git diff app/Http/Controllers/UserController.php`
- [ ] JSON trả về **không chứa** `password` và `remember_token`

> 💡 **Câu hỏi tự vấn**: vì sao `password` không lộ ra JSON dù bạn không hề lọc nó ở service hay controller? Gợi ý: đọc lại attribute `#[Hidden]` trên model `User`. Nếu bỏ attribute đó đi thì sao?

---

### Bài tập 2: Form Request Validation

**🎯 Mục tiêu:** Tách logic kiểm tra dữ liệu đầu vào ra khỏi controller. Controller chỉ nên điều phối, không nên chứa hàng chục dòng `if` kiểm tra.

**📝 Yêu cầu:**

- Tạo `App\Http\Requests\StoreUserRequest` bằng `artisan make:request`.
- Thêm route `POST /users` gọi `UserController@store`.
- Controller nhận thẳng `StoreUserRequest` thay cho `Request` — container tự động validate **trước khi** thân hàm chạy.

**💻 Mã nguồn gợi ý ban đầu:**

```bash
docker exec di_demo_app php artisan make:request StoreUserRequest
```

**Nhiệm vụ của bạn:**

```php
// app/Http/Requests/StoreUserRequest.php
public function authorize(): bool
{
    return true;   // bài này chưa học phân quyền, cho qua
}

public function rules(): array
{
    return [
        // TODO: name bắt buộc, chuỗi, tối đa 255 ký tự
        'name'     => ____,
        // TODO: email bắt buộc, đúng định dạng, KHÔNG được trùng trong bảng users
        'email'    => ____,
        // TODO: password bắt buộc, tối thiểu 8 ký tự, phải khớp password_confirmation
        'password' => ____,
    ];
}
```

```php
// app/Http/Controllers/UserController.php
public function store(StoreUserRequest $request): JsonResponse
{
    // TODO: lấy dữ liệu ĐÃ được validate (không dùng $request->all())
    $user = User::create(____);

    return response()->json($user, 201);
}
```

### Cách kiểm chứng

- [ ] Gửi request thiếu field → HTTP **422**, body chứa danh sách lỗi theo từng field
- [ ] Gửi email đã tồn tại → 422 với message về `email`
- [ ] Gửi dữ liệu hợp lệ → HTTP **201**, bản ghi mới nằm trong DB
- [ ] Thân hàm `store()` **không có dòng `if` kiểm tra nào**

```bash
curl -s -X POST http://localhost:8001/users \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"name":"","email":"sai-dinh-dang"}' | head -c 300
```

> 💡 **Câu hỏi tự vấn**: vì sao chỉ cần đổi type-hint từ `Request` sang `StoreUserRequest` là validation tự chạy? Gợi ý: đây vẫn là container đang resolve dependency — đúng cơ chế bạn học ở bộ DI.

---

### Bài tập 3: Quan hệ & Bẫy N+1 ⭐

**🎯 Mục tiêu:** Tự tay gây ra lỗi hiệu năng phổ biến nhất của Laravel, **đo được nó bằng số**, rồi sửa. Đây là bài giá trị nhất của cả bộ — N+1 là thứ bạn sẽ gặp ở mọi dự án thật.

**📝 Yêu cầu:**

- Tạo bảng `posts` với khóa ngoại `user_id`.
- Model `Post` với `belongsTo(User::class)`, model `User` thêm `hasMany(Post::class)`.
- Tạo `PostFactory`, seed mỗi user 5 bài viết.
- Thêm route `/users-with-posts` trả về mỗi user kèm danh sách bài viết.
- **Đếm số câu query** trước và sau khi tối ưu.

**💻 Mã nguồn gợi ý ban đầu:**

```bash
docker exec di_demo_app php artisan make:model Post -mf
```

```php
// database/migrations/xxxx_create_posts_table.php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    // TODO: khóa ngoại tới users, xóa user thì xóa luôn post
    ____;
    $table->string('title');
    $table->text('body');
    $table->timestamps();
});
```

```php
// app/Models/Post.php
#[Fillable(['user_id', 'title', 'body'])]
class Post extends Model
{
    use HasFactory;

    // TODO: một Post thuộc về một User
    public function user(): BelongsTo
    {
        return ____;
    }
}
```

```php
// app/Models/User.php — thêm vào
// TODO: một User có nhiều Post
public function posts(): HasMany
{
    return ____;
}
```

**Nhiệm vụ của bạn — phần quan trọng nhất:**

Bước 1, viết bản **cố tình sai** để nhìn thấy vấn đề:

```php
// app/Services/UserService.php
public function getUsersWithPosts(): Collection
{
    return User::all();   // ← chưa nạp quan hệ
}
```

```php
// controller: lặp và chạm vào $user->posts trong vòng lặp
foreach ($users as $user) {
    $data[] = ['name' => $user->name, 'so_bai_viet' => $user->posts->count()];
}
```

Bước 2, **đo số query bằng Telescope**:

```bash
# xoá sạch dữ liệu cũ để đếm cho gọn
docker exec di_demo_app php artisan telescope:clear

curl -s http://localhost:8001/users-with-posts > /dev/null
```

Mở http://localhost:8001/telescope/requests → bấm vào request `/users-with-posts` vừa xuất hiện → kéo xuống mục **Queries**.

Bạn sẽ thấy **cùng một câu SQL lặp lại rất nhiều lần**, chỉ khác giá trị `user_id`:

```sql
select * from "posts" where "posts"."user_id" = 1 and "posts"."user_id" is not null
select * from "posts" where "posts"."user_id" = 2 and "posts"."user_id" is not null
select * from "posts" where "posts"."user_id" = 3 and "posts"."user_id" is not null
...
```

**Chính hình ảnh lặp lại đó là chữ ký của N+1.** Nhớ trừ đi 2 câu `sessions` như đã nói ở Phần 0.

> **Cách khác, không cần Telescope** — nếu bạn muốn hiểu cơ chế bên dưới, thêm tạm vào `AppServiceProvider::boot()`:
> ```php
> DB::listen(fn ($query) => logger()->info('SQL: '.$query->sql));
> ```
> rồi đếm bằng `docker exec di_demo_app grep -c "SQL:" storage/logs/laravel.log`. Telescope thực chất cũng cắm vào đúng sự kiện này, chỉ khác là nó hiển thị đẹp và gom theo từng request.

Bước 3, **sửa** bằng eager loading rồi đo lại:

```php
// TODO: nạp sẵn quan hệ posts ngay trong 1 câu query
return User::____('posts')->get();
```

### Cách kiểm chứng

- [ ] Trước khi sửa: **12 query** chạm bảng dữ liệu (1 câu lấy `users` + 11 câu lấy `posts` của từng user) — đúng công thức **1 + N**. Telescope sẽ hiện tổng ~14, gồm cả 2 câu `sessions`
- [ ] Sau khi sửa: còn **2 query** (`select * from users` + `select * from posts where user_id in (...)`), bất kể có bao nhiêu user
- [ ] Kết quả JSON **giống hệt nhau** ở cả hai phiên bản — N+1 không làm sai kết quả, chỉ làm chậm. Đây chính là lý do nó khó bị phát hiện.
- [ ] Thêm 100 user rồi đo lại: bản sai tăng lên ~102 query, bản đúng vẫn là 2

> 💡 **Mẹo dùng ở dự án thật**: thêm vào `AppServiceProvider::boot()`
> ```php
> Model::preventLazyLoading(! app()->isProduction());
> ```
> Laravel sẽ **ném exception** ngay khi có ai đó lazy load ở môi trường dev. Bạn không bao giờ vô tình đẩy N+1 lên production nữa. Hãy bật nó lên và chạy lại bản sai ở Bước 1 để xem lỗi nổ ra thế nào.

---

### Bài tập 4: API Resource

**🎯 Mục tiêu:** Tách *hình dạng dữ liệu trả ra* khỏi *cấu trúc bảng*. Trả thẳng model ra JSON khiến API vỡ ngay khi bạn đổi tên cột, và dễ vô tình lộ field nhạy cảm.

**📝 Yêu cầu:**

- Tạo `UserResource`, trả về đúng 4 field: `id`, `name`, `email`, `so_bai_viet`.
- Định dạng `created_at` theo `d/m/Y`.

**💻 Mã nguồn gợi ý ban đầu:**

```bash
docker exec di_demo_app php artisan make:resource UserResource
```

**Nhiệm vụ của bạn:**

```php
// app/Http/Resources/UserResource.php
public function toArray(Request $request): array
{
    return [
        'id'          => $this->id,
        'name'        => $this->name,
        'email'       => $this->email,
        // TODO: đếm số post, nhưng CHỈ khi quan hệ đã được nạp sẵn
        //       (tránh tự tay tạo lại N+1 ngay trong resource!)
        'so_bai_viet' => $this->whenLoaded(____),
        'ngay_tao'    => $this->created_at->format(____),
    ];
}
```

```php
// controller
return UserResource::collection($users);
```

### Cách kiểm chứng

- [ ] JSON chỉ có đúng 5 khóa, không thừa `email_verified_at`, `updated_at`
- [ ] `ngay_tao` hiển thị dạng `09/09/2026`
- [ ] Chạy lại phép đếm query ở Bài 3 — **số query không tăng** sau khi thêm Resource

> ⚠️ **Bẫy hay gặp**: viết thẳng `$this->posts->count()` trong Resource sẽ tạo lại N+1 y hệt Bài 3, dù controller đã eager load đúng. Đó là lý do đề bài bắt dùng `whenLoaded()`.

---

### Bài tập 5: Viết test cho tầng dữ liệu

**🎯 Mục tiêu:** Test có chạm database mà vẫn nhanh và sạch. Mỗi test tự dựng dữ liệu của mình, không phụ thuộc thứ tự chạy.

**📝 Yêu cầu:**

- `tests/Feature/UserApiTest.php` với trait `RefreshDatabase`.
- Test cả nhánh **thành công** lẫn nhánh **thất bại**.

**Nhiệm vụ của bạn:**

```php
class UserApiTest extends TestCase
{
    use ____;   // TODO: trait dựng lại DB sạch trước mỗi test

    public function test_it_lists_users_from_database(): void
    {
        // TODO: tạo sẵn 3 user bằng factory
        ____;

        $this->getJson('/users')
            ->assertStatus(200)
            ->assertJsonCount(____, 'data');
    }

    public function test_it_creates_a_user(): void
    {
        $this->postJson('/users', [
            'name' => 'Nguyen Van A',
            'email' => 'a@gmail.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);

        // TODO: khẳng định bản ghi thật sự nằm trong DB
        $this->____('users', ['email' => 'a@gmail.com']);
    }

    public function test_it_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'trung@gmail.com']);

        $this->postJson('/users', [/* ... email trùng ... */])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
```

### Cách kiểm chứng

- [ ] Cả 3 test PASS
- [ ] Chạy `php artisan test` **hai lần liên tiếp** đều xanh — chứng tỏ test không để lại rác
- [ ] Dữ liệu Postgres của bạn **không đổi** sau khi chạy test (test dùng sqlite in-memory)
- [ ] Xoá dòng `use RefreshDatabase` → test phải **FAIL**, hiểu vì sao

---

### Bài tập 6 (mở rộng — làm khi đã vững 5 bài trên): Queue

**🎯 Mục tiêu:** Tác vụ chậm không nên chạy trong vòng đời request. Migration `create_jobs_table` đã có sẵn trong project.

**📝 Yêu cầu:**

- Tạo `SendWelcomeEmail` job, dispatch sau khi tạo user thành công.
- Đổi `QUEUE_CONNECTION=database` trong `.env`, chạy `php artisan queue:work`.
- Trong test, dùng `Queue::fake()` và `assertPushed()` — không chạy job thật.

### Cách kiểm chứng

- [ ] Request `POST /users` trả về ngay, không đợi job chạy xong
- [ ] Bản ghi xuất hiện trong bảng `jobs` khi chưa có worker
- [ ] Test dùng `Queue::fake()` chạy nhanh và không đụng bảng `jobs`

---

## PHẦN III: LỖI THƯỜNG GẶP

| Triệu chứng | Nguyên nhân |
|---|---|
| `SQLSTATE... relation "posts" does not exist` | Chưa chạy `php artisan migrate` |
| `Add [name] to fillable property` | Thiếu attribute `#[Fillable]` trên model mới |
| `Call to a member function count() on null` | Quan hệ chưa được định nghĩa, hoặc gõ sai tên method |
| JSON trả về `{"data": ...}` ngoài dự kiến | Đó là hành vi mặc định của API Resource, không phải lỗi |
| Test PASS lần đầu, FAIL lần hai | Thiếu `RefreshDatabase`, dữ liệu cũ còn sót |
| Số query không giảm sau khi `with()` | Eager load sai tên quan hệ, hoặc Resource đang lazy load lại |
| Sửa code mà không thấy đổi | `docker exec di_demo_app php artisan optimize:clear` |

---

## PHẦN IV: CHECKLIST TỔNG KẾT

- [ ] **Bài 1** — `/users` đọc từ DB thật, `UserController` không sửa dòng nào
- [ ] **Bài 2** — validation nằm trong Form Request, controller sạch `if`
- [ ] **Bài 3** — đo được **12 query → 2 query**, và giải thích được vì sao ⭐
- [ ] **Bài 4** — Resource kiểm soát output, không làm tăng số query
- [ ] **Bài 5** — 3 test xanh, chạy hai lần liên tiếp vẫn xanh
- [ ] Chạy `docker exec di_demo_app vendor/bin/pint --dirty`
- [ ] Chạy `docker exec di_demo_app php artisan test` — toàn bộ suite xanh

> **Tự đánh giá:** bạn nắm được bộ này nếu trả lời trôi chảy: *"N+1 là gì, làm sao phát hiện, và sửa bằng cách nào?"* — đây là câu hỏi phỏng vấn Laravel phổ biến bậc nhất.
