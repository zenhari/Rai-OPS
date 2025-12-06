# راهنمای سریع استفاده از Activity Log

## 🚀 نصب سریع (3 مرحله)

### 1️⃣ ایجاد جدول دیتابیس
```
http://your-domain/database/install_activity_logs.php
```

### 2️⃣ اضافه کردن به Role Permission
- به `admin/role_permission.php` بروید
- روی دکمه **"Activity Log"** در Quick Add کلیک کنید

### 3️⃣ شروع استفاده!

---

## 📝 مثال‌های سریع

### ✅ مشاهده صفحه
```php
logActivity('view', __FILE__, [
    'page_name' => 'Edit User',
    'section' => 'User Management'
]);
```

### ✅ ایجاد رکورد جدید
```php
// بعد از INSERT
logActivity('create', __FILE__, [
    'page_name' => 'Add User',
    'record_id' => $newId,
    'record_type' => 'user',
    'new_value' => $userName
]);
```

### ✅ ویرایش (یک فیلد)
```php
logActivity('update', __FILE__, [
    'page_name' => 'Edit User',
    'field_name' => 'email',
    'old_value' => $oldEmail,
    'new_value' => $newEmail,
    'record_id' => $userId,
    'record_type' => 'user'
]);
```

### ✅ ویرایش (چند فیلد)
```php
logActivity('update', __FILE__, [
    'page_name' => 'Edit User',
    'record_id' => $userId,
    'record_type' => 'user',
    'changes' => [
        ['field' => 'email', 'old' => $oldEmail, 'new' => $newEmail],
        ['field' => 'phone', 'old' => $oldPhone, 'new' => $newPhone]
    ]
]);
```

### ✅ حذف
```php
logActivity('delete', __FILE__, [
    'page_name' => 'Delete User',
    'record_id' => $userId,
    'record_type' => 'user',
    'old_value' => $userName
]);
```

---

## 📊 مشاهده لاگ‌ها

1. منوی **"Full Log"** → **"Activity Log"**
2. فیلتر کنید و لاگ‌ها را ببینید

---

## 💡 نکته مهم

**لاگ‌های Login/Logout به صورت خودکار ثبت می‌شوند!** 
نیازی به اضافه کردن دستی ندارید.

---

برای مثال‌های کامل‌تر، فایل `USAGE_GUIDE.md` را ببینید.

