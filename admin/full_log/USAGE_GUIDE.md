# راهنمای استفاده از سیستم Activity Log

## مرحله 1: نصب جدول دیتابیس

### روش 1: استفاده از فایل نصب (پیشنهادی)
1. به آدرس زیر بروید:
   ```
   http://your-domain/database/install_activity_logs.php
   ```
2. اگر پیام موفقیت نمایش داده شد، جدول ایجاد شده است.

### روش 2: اجرای دستی SQL
1. فایل `database/activity_logs_table.sql` را باز کنید
2. محتوای آن را در phpMyAdmin یا MySQL اجرا کنید

---

## مرحله 2: اضافه کردن به Role Permission

1. به `admin/role_permission.php` بروید
2. در بخش "Quick Add Pages" روی دکمه **"Activity Log"** کلیک کنید
3. این صفحه فقط برای Admin قابل دسترسی خواهد بود

---

## مرحله 3: استفاده در صفحات

### مثال 1: ثبت لاگ برای مشاهده صفحه

در ابتدای هر صفحه که می‌خواهید لاگ شود:

```php
<?php
require_once '../../config.php';
checkPageAccessWithRedirect('admin/users/edit.php');

// ثبت لاگ برای مشاهده صفحه
logActivity('view', __FILE__, [
    'page_name' => 'Edit User',
    'section' => 'User Management'
]);

// بقیه کد...
?>
```

### مثال 2: ثبت لاگ برای ایجاد رکورد جدید

```php
<?php
// بعد از INSERT موفق
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_user') {
    $db = getDBConnection();
    
    $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email) VALUES (?, ?, ?)");
    $stmt->execute([$firstName, $lastName, $email]);
    
    $newUserId = $db->lastInsertId();
    
    // ثبت لاگ
    logActivity('create', __FILE__, [
        'page_name' => 'Add New User',
        'section' => 'User Form',
        'record_id' => $newUserId,
        'record_type' => 'user',
        'new_value' => $firstName . ' ' . $lastName
    ]);
    
    header('Location: index.php?success=1');
    exit();
}
?>
```

### مثال 3: ثبت لاگ برای ویرایش (یک فیلد)

```php
<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_email') {
    $userId = intval($_POST['user_id']);
    
    // دریافت مقدار قدیمی
    $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $oldEmail = $stmt->fetchColumn();
    
    // به‌روزرسانی
    $newEmail = trim($_POST['email']);
    $stmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
    $stmt->execute([$newEmail, $userId]);
    
    // ثبت لاگ
    logActivity('update', __FILE__, [
        'page_name' => 'Edit User',
        'section' => 'User Form',
        'field_name' => 'email',
        'old_value' => $oldEmail,
        'new_value' => $newEmail,
        'record_id' => $userId,
        'record_type' => 'user'
    ]);
}
?>
```

### مثال 4: ثبت لاگ برای ویرایش (چند فیلد همزمان)

```php
<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_user') {
    $userId = intval($_POST['user_id']);
    
    // دریافت داده‌های قدیمی
    $stmt = $db->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // دریافت داده‌های جدید
    $newData = [
        'first_name' => trim($_POST['first_name']),
        'last_name' => trim($_POST['last_name']),
        'email' => trim($_POST['email']),
        'phone' => trim($_POST['phone'])
    ];
    
    // به‌روزرسانی
    $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
    $stmt->execute([$newData['first_name'], $newData['last_name'], $newData['email'], $newData['phone'], $userId]);
    
    // جمع‌آوری تغییرات
    $changes = [];
    if ($oldData['first_name'] != $newData['first_name']) {
        $changes[] = [
            'field' => 'first_name',
            'old' => $oldData['first_name'],
            'new' => $newData['first_name']
        ];
    }
    if ($oldData['last_name'] != $newData['last_name']) {
        $changes[] = [
            'field' => 'last_name',
            'old' => $oldData['last_name'],
            'new' => $newData['last_name']
        ];
    }
    if ($oldData['email'] != $newData['email']) {
        $changes[] = [
            'field' => 'email',
            'old' => $oldData['email'],
            'new' => $newData['email']
        ];
    }
    if ($oldData['phone'] != $newData['phone']) {
        $changes[] = [
            'field' => 'phone',
            'old' => $oldData['phone'],
            'new' => $newData['phone']
        ];
    }
    
    // ثبت لاگ فقط اگر تغییری وجود داشته باشد
    if (!empty($changes)) {
        logActivity('update', __FILE__, [
            'page_name' => 'Edit User',
            'section' => 'User Form',
            'record_id' => $userId,
            'record_type' => 'user',
            'changes' => $changes
        ]);
    }
}
?>
```

### مثال 5: ثبت لاگ برای حذف

```php
<?php
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $userId = intval($_GET['id']);
    
    // دریافت اطلاعات قبل از حذف
    $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ثبت لاگ قبل از حذف
    logActivity('delete', __FILE__, [
        'page_name' => 'Delete User',
        'section' => 'User Management',
        'record_id' => $userId,
        'record_type' => 'user',
        'old_value' => $user['first_name'] . ' ' . $user['last_name']
    ]);
    
    // حذف
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    
    header('Location: index.php?deleted=1');
    exit();
}
?>
```

### مثال 6: ثبت لاگ برای Login/Logout

در فایل `login.php`:

```php
<?php
// بعد از موفقیت‌آمیز بودن لاگین
if (loginUser($username, $password)) {
    // ثبت لاگ
    logActivity('login', __FILE__, [
        'page_name' => 'User Login',
        'section' => 'Authentication'
    ]);
    
    header('Location: /dashboard/');
    exit();
}
?>
```

در فایل `logout.php` یا تابع logout:

```php
<?php
// قبل از logout
logActivity('logout', __FILE__, [
    'page_name' => 'User Logout',
    'section' => 'Authentication'
]);

logoutUser();
header('Location: /login.php');
exit();
?>
```

### مثال 7: ثبت لاگ برای Export/Print

```php
<?php
// Export
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    // کد export...
    
    logActivity('export', __FILE__, [
        'page_name' => 'Export Flights',
        'section' => 'Flight Report',
        'new_value' => 'CSV Export - ' . count($flights) . ' records'
    ]);
}

// Print
if (isset($_GET['print'])) {
    // کد print...
    
    logActivity('print', __FILE__, [
        'page_name' => 'Print Certificate',
        'section' => 'Certificate',
        'record_id' => $certificateId,
        'record_type' => 'certificate'
    ]);
}
?>
```

---

## مثال کامل: اضافه کردن به یک صفحه Edit

فرض کنید می‌خواهید به صفحه `admin/users/edit.php` لاگ اضافه کنید:

```php
<?php
require_once '../../config.php';
checkPageAccessWithRedirect('admin/users/edit.php');

$user_id = $_GET['id'] ?? '';
$user = getUserById($user_id);

// 1. ثبت لاگ برای مشاهده صفحه
logActivity('view', __FILE__, [
    'page_name' => 'Edit User',
    'section' => 'User Management',
    'record_id' => $user_id,
    'record_type' => 'user'
]);

// 2. ثبت لاگ برای ویرایش
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_user') {
    // ذخیره داده‌های قدیمی
    $oldData = $user;
    
    // به‌روزرسانی داده‌ها
    $newData = [
        'first_name' => trim($_POST['first_name']),
        'last_name' => trim($_POST['last_name']),
        'email' => trim($_POST['email'])
    ];
    
    // جمع‌آوری تغییرات
    $changes = [];
    foreach ($newData as $field => $newValue) {
        if (isset($oldData[$field]) && $oldData[$field] != $newValue) {
            $changes[] = [
                'field' => $field,
                'old' => $oldData[$field],
                'new' => $newValue
            ];
        }
    }
    
    // به‌روزرسانی در دیتابیس
    updateUser($user_id, $newData);
    
    // ثبت لاگ
    if (!empty($changes)) {
        logActivity('update', __FILE__, [
            'page_name' => 'Edit User',
            'section' => 'User Form',
            'record_id' => $user_id,
            'record_type' => 'user',
            'changes' => $changes
        ]);
    }
    
    header('Location: edit.php?id=' . $user_id . '&success=1');
    exit();
}
?>
```

---

## مشاهده لاگ‌ها

1. به منوی **"Full Log"** در sidebar بروید
2. روی **"Activity Log"** کلیک کنید
3. می‌توانید فیلتر کنید بر اساس:
   - کاربر
   - نوع عملیات (view, create, update, delete, ...)
   - صفحه
   - تاریخ
   - نوع رکورد

---

## نکات مهم

1. **کارایی**: برای صفحات پرترافیک، می‌توانید لاگ `view` را غیرفعال کنید
2. **امنیت**: هرگز پسوردها یا اطلاعات حساس را در لاگ ذخیره نکنید
3. **حجم داده**: برای جلوگیری از پر شدن دیتابیس، لاگ‌های قدیمی را پاک کنید:

```sql
-- حذف لاگ‌های قدیمی‌تر از 90 روز
DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

4. **مقدارهای طولانی**: تابع به صورت خودکار مقادیر بیشتر از 1000 کاراکتر را کوتاه می‌کند

---

## تست سیستم

1. یک صفحه را باز کنید (مثلاً Edit User)
2. یک تغییر ایجاد کنید
3. به Activity Log بروید
4. باید لاگ‌های خود را ببینید

---

## سوالات متداول

**سوال**: آیا می‌توانم لاگ view را غیرفعال کنم؟
**جواب**: بله، فقط خط `logActivity('view', ...)` را حذف یا کامنت کنید.

**سوال**: چطور فقط تغییرات را لاگ کنم؟
**جواب**: قبل از UPDATE، داده‌های قدیمی را ذخیره کنید و فقط فیلدهایی که تغییر کرده‌اند را در `changes` قرار دهید.

**سوال**: آیا می‌توانم لاگ‌های خاصی را حذف کنم؟
**جواب**: بله، می‌توانید مستقیماً از دیتابیس حذف کنید یا یک صفحه مدیریت لاگ بسازید.

