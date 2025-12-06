# Activity Log System - راهنمای استفاده

## نصب

1. ابتدا جدول را ایجاد کنید:
   - فایل `database/install_activity_logs.php` را اجرا کنید
   - یا دستی SQL را از `database/activity_logs_table.sql` اجرا کنید

2. صفحه Activity Log را به Role Permission اضافه کنید:
   - به `admin/role_permission.php` بروید
   - از Quick Add، "Activity Log" را اضافه کنید

## نحوه استفاده

### 1. ثبت لاگ برای مشاهده صفحه (View)

```php
// در ابتدای هر صفحه
logActivity('view', __FILE__, [
    'page_name' => 'Edit User',
    'section' => 'User Management'
]);
```

### 2. ثبت لاگ برای ایجاد رکورد (Create)

```php
// بعد از INSERT موفق
logActivity('create', __FILE__, [
    'page_name' => 'Add New User',
    'section' => 'User Form',
    'record_id' => $newUserId,
    'record_type' => 'user',
    'new_value' => $username
]);
```

### 3. ثبت لاگ برای ویرایش (Update)

```php
// برای یک فیلد
logActivity('update', __FILE__, [
    'page_name' => 'Edit User',
    'section' => 'User Form',
    'field_name' => 'email',
    'old_value' => $oldEmail,
    'new_value' => $newEmail,
    'record_id' => $userId,
    'record_type' => 'user'
]);

// برای چند فیلد همزمان
logActivity('update', __FILE__, [
    'page_name' => 'Edit Flight',
    'section' => 'Flight Details',
    'record_id' => $flightId,
    'record_type' => 'flight',
    'changes' => [
        ['field' => 'departure_time', 'old' => $oldTime, 'new' => $newTime],
        ['field' => 'arrival_time', 'old' => $oldArrival, 'new' => $newArrival],
        ['field' => 'status', 'old' => $oldStatus, 'new' => $newStatus]
    ]
]);
```

### 4. ثبت لاگ برای حذف (Delete)

```php
// قبل از DELETE
logActivity('delete', __FILE__, [
    'page_name' => 'Delete User',
    'section' => 'User Management',
    'record_id' => $userId,
    'record_type' => 'user',
    'old_value' => $userName
]);
```

### 5. ثبت لاگ برای Login/Logout

```php
// در login.php بعد از موفقیت
logActivity('login', __FILE__, [
    'page_name' => 'User Login',
    'section' => 'Authentication'
]);

// در logout
logActivity('logout', __FILE__, [
    'page_name' => 'User Logout',
    'section' => 'Authentication'
]);
```

### 6. ثبت لاگ برای Export/Print

```php
logActivity('export', __FILE__, [
    'page_name' => 'Export Flights',
    'section' => 'Flight Report',
    'new_value' => 'CSV Export - 150 records'
]);

logActivity('print', __FILE__, [
    'page_name' => 'Print Certificate',
    'section' => 'Certificate',
    'record_id' => $certificateId,
    'record_type' => 'certificate'
]);
```

## مثال کامل در یک صفحه Edit

```php
<?php
require_once '../../config.php';
checkPageAccessWithRedirect('admin/users/edit.php');

// ثبت لاگ برای مشاهده صفحه
logActivity('view', __FILE__, [
    'page_name' => 'Edit User',
    'section' => 'User Management'
]);

$userId = intval($_GET['id'] ?? 0);
$db = getDBConnection();

// دریافت اطلاعات کاربر
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $oldData = $user; // ذخیره داده‌های قدیمی
    
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // به‌روزرسانی
    $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
    $stmt->execute([$firstName, $lastName, $email, $userId]);
    
    // ثبت تغییرات
    $changes = [];
    if ($oldData['first_name'] != $firstName) {
        $changes[] = ['field' => 'first_name', 'old' => $oldData['first_name'], 'new' => $firstName];
    }
    if ($oldData['last_name'] != $lastName) {
        $changes[] = ['field' => 'last_name', 'old' => $oldData['last_name'], 'new' => $lastName];
    }
    if ($oldData['email'] != $email) {
        $changes[] = ['field' => 'email', 'old' => $oldData['email'], 'new' => $email];
    }
    
    if (!empty($changes)) {
        logActivity('update', __FILE__, [
            'page_name' => 'Edit User',
            'section' => 'User Form',
            'record_id' => $userId,
            'record_type' => 'user',
            'changes' => $changes
        ]);
    }
    
    header('Location: edit.php?id=' . $userId . '&success=1');
    exit();
}
?>
```

## نکات مهم

1. **کارایی**: لاگ‌ها به صورت خودکار در دیتابیس ذخیره می‌شوند. برای صفحات پرترافیک، می‌توانید لاگ view را غیرفعال کنید.

2. **امنیت**: مقادیر حساس (مثل پسورد) را در لاگ ذخیره نکنید.

3. **حجم داده**: برای جلوگیری از پر شدن دیتابیس، می‌توانید یک cron job برای پاک کردن لاگ‌های قدیمی (مثلاً بیشتر از 90 روز) تنظیم کنید.

4. **فیلتر کردن**: در صفحه Activity Log می‌توانید بر اساس کاربر، نوع عملیات، صفحه، تاریخ و... فیلتر کنید.

## پاک کردن لاگ‌های قدیمی

```sql
-- حذف لاگ‌های قدیمی‌تر از 90 روز
DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

