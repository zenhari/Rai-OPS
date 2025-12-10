# CAO API Integration - Flight Messages

این پکیج شامل 5 اسکریپت PHP برای ارسال پیام‌های پروازی به API CAO است.

## 📁 فایل‌های موجود

1. **send_mvt_dep.php** - ارسال پیام MVT-DEP (Departure)
2. **send_mvt_arr.php** - ارسال پیام MVT-ARR (Arrival)
3. **send_mvt_dly.php** - ارسال پیام MVT-DLY (Delay)
4. **send_ldm.php** - ارسال پیام LDM (Load Distribution)
5. **send_cpm.php** - ارسال پیام CPM (Container/Pallet)

## 🔧 نحوه استفاده

### روش 1: GET Request
```
https://your-domain.com/admin/cao_api/send_mvt_dep.php?flight_id=12345
```

### روش 2: POST Request
```php
$data = ['flight_id' => 12345];
$ch = curl_init('https://your-domain.com/admin/cao_api/send_mvt_dep.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
```

## 📋 پاسخ API

### موفقیت:
```json
{
    "success": true,
    "message": "MVT\nSD200/10DEC...",
    "response": "{\"status\":\"success\"}",
    "http_code": 200
}
```

### خطا:
```json
{
    "error": "Flight not found"
}
```

## 🔐 احراز هویت

تمام اسکریپت‌ها نیاز به لاگین کاربر دارند. اگر کاربر لاگین نباشد، خطای 401 برمی‌گردانند.

## 📊 ساختار پیام‌ها

### MVT-DEP
- Flight Identifier
- AD (Off Block/Airborne) + ETA
- Delay Block (در صورت وجود)
- Passenger Count
- Special Information

### MVT-ARR
- Flight Identifier
- AA (Touchdown/On Block)
- Flight Day
- Special Information

### MVT-DLY
- Flight Identifier
- ED (Estimated Departure)
- DL (Delay Code + Minutes)
- Special Information

### LDM
- Flight Identifier
- Origin + Passenger Breakdown
- Total Weight
- Baggage Information
- Special Information

### CPM
- Flight Identifier
- Compartment Information (11, 12, 13)
- ULD Details یا N/ در صورت نبود

## 🔑 Token API

Token فعلی: `3aea9ada385ce8dca95f125a0fc1c793`

**نکته:** در صورت تغییر Token، باید در تمام فایل‌ها به‌روزرسانی شود.

## 🗄️ فیلدهای دیتابیس مورد استفاده

### جدول: `flights`

#### MVT-DEP:
- `TaskName` / `FlightNo`
- `FltDate`
- `Rego`
- `Route`
- `actual_out_utc`
- `actual_off_utc`
- `air_time_min`
- `total_pax`
- `delay_diversion_codes`
- `minutes_1`
- `remark_1`

#### MVT-ARR:
- `TaskName` / `FlightNo`
- `FltDate`
- `Rego`
- `Route`
- `actual_on_utc`
- `actual_in_utc`
- `remark_1`

#### MVT-DLY:
- `TaskName` / `FlightNo`
- `FltDate`
- `Rego`
- `Route`
- `TaskStart`
- `delay_diversion_codes` (1-5)
- `minutes_1` تا `minutes_5`
- `remark_1`

#### LDM:
- `TaskName` / `FlightNo`
- `Route`
- `adult`
- `child`
- `infant`
- `total_pax`
- `pcs`
- `weight`
- `remark_1`

#### CPM:
- `TaskName` / `FlightNo`
- `FltDate`

## ⚠️ نکات مهم

1. **فرمت تاریخ:** تمام تاریخ‌ها به فرمت `DDMMM` (مثلاً `10DEC`) تبدیل می‌شوند.

2. **فرمت زمان:** تمام زمان‌ها به فرمت `HHMM` (مثلاً `1420`) تبدیل می‌شوند.

3. **Route Parsing:** Route به صورت `ORIGIN-DESTINATION` پارس می‌شود.

4. **Delay Codes:** در MVT-DLY، برای هر delay code یک پیام جداگانه ارسال می‌شود.

5. **CPM ULD:** در حال حاضر، در صورت نبود ULD، پیام با `N/` ارسال می‌شود. در صورت نیاز به ULD، باید فیلد مربوطه به جدول `flights` اضافه شود.

## 🧪 تست

برای تست هر اسکریپت:

```bash
# با curl
curl -X GET "https://your-domain.com/admin/cao_api/send_mvt_dep.php?flight_id=12345" \
  -H "Cookie: PHPSESSID=your_session_id"

# یا با POST
curl -X POST "https://your-domain.com/admin/cao_api/send_mvt_dep.php" \
  -d "flight_id=12345" \
  -H "Cookie: PHPSESSID=your_session_id"
```

## 📝 مثال استفاده در PHP

```php
// ارسال MVT-DEP
$flightId = 12345;
$url = "https://your-domain.com/admin/cao_api/send_mvt_dep.php?flight_id={$flightId}";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if ($result['success']) {
    echo "پیام با موفقیت ارسال شد!";
} else {
    echo "خطا: " . ($result['error'] ?? 'Unknown error');
}
```

## 🔄 به‌روزرسانی‌های آینده

- [ ] اضافه کردن پشتیبانی از ULD در CPM
- [ ] اضافه کردن Logging برای تمام درخواست‌ها
- [ ] اضافه کردن Retry Mechanism در صورت خطا
- [ ] اضافه کردن Queue System برای ارسال دسته‌ای

