# Skyputer OFP Data Collector

این endpoint برای دریافت داده‌های OFP (Operational Flight Plan) از شرکت‌های خارجی طراحی شده است.

## Endpoint URL

```
POST https://application/skyputer/collector.php
```

## فرمت‌های پشتیبانی شده

این endpoint دو فرمت را پشتیبانی می‌کند:

1. **JSON Format** - داده‌های JSON ساختار یافته
2. **String Format** - داده‌های String با جداکننده‌های خاص

## استفاده

### 1. ارسال JSON Format

```php
<?php
$url = 'https://application/skyputer/collector.php';

// نمونه داده JSON
$data = [
    'binfo' => [
        'OPT' => 'CHABAHAR AIRLINES',
        'UNT' => 'LBS',
        'FPF' => '-> FUEL INCLUDES 10.0 PCT PERF FACTOR',
        'FLN' => 'IRU 7661',
        'DTE' => 'APR 02 2025',
        'ETD' => '10:00',
        'ETA' => '11:08',
        'REG' => 'EPCBI (MD83) - MSN: 53187',
        'RTS' => 'OIMM - OIII',
        // ... سایر فیلدها
    ],
    'futbl' => [
        [
            'Param' => 'TRIP FUEL',
            'Time' => '01:08:00',
            'Value' => 9285
        ],
        // ... سایر رکوردها
    ],
    // ... سایر جداول
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode === 200 && $result['ok']) {
    echo "Success! Request ID: " . $result['request_id'];
    echo "Record ID: " . $result['record_id'];
} else {
    echo "Error: " . $result['error'];
}
?>
```

### 2. ارسال String Format

```php
<?php
$url = 'https://application/skyputer/collector.php';

// نمونه داده String
$data = 'binfo:|OPT=CHABAHAR AIRLINES;UNT=LBS;FPF=-> FUEL INCLUDES 10.0 PCT PERF FACTOR;FLN=IRU 7661;DTE=APR 02 2025;ETD=10:00;ETA=11:08;REG=EPCBI (MD83) - MSN: 53187;RTS=OIMM - OIII||futbl:|PRM=TRIP FUEL;TIM=01:08:00.00000;VAL=9285|PRM=CONT[5%];TIM=00:05:00.00000;VAL=513||';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: text/plain'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode === 200 && $result['ok']) {
    echo "Success! Request ID: " . $result['request_id'];
} else {
    echo "Error: " . $result['error'];
}
?>
```

## Response Format

### Success Response (200 OK)

```json
{
    "ok": true,
    "message": "OFP data received and saved successfully",
    "request_id": "a1b2c3d4e5f6...",
    "record_id": 123,
    "flight_info": {
        "flight_number": "IRU 7661",
        "date": "APR 02 2025",
        "operator": "CHABAHAR AIRLINES",
        "unit": "LBS",
        "eta": "11:08",
        "etd": "10:00",
        "aircraft_reg": "EPCBI (MD83) - MSN: 53187",
        "route": "OIMM - OIII"
    },
    "format": "json"
}
```

### Error Response (400 Bad Request)

```json
{
    "error": "Validation failed",
    "errors": [
        "Missing FLN (Flight Number)",
        "Missing DTE (Date)"
    ],
    "request_id": "a1b2c3d4e5f6..."
}
```

### Error Response (500 Internal Server Error)

```json
{
    "error": "Database error",
    "message": "Failed to save OFP data",
    "request_id": "a1b2c3d4e5f6..."
}
```

## فیلدهای الزامی

برای موفقیت آمیز بودن درخواست، داده‌های زیر باید وجود داشته باشند:

- `binfo.FLN` - شماره پرواز (Flight Number)
- `binfo.DTE` - تاریخ پرواز (Date)

## جداول پشتیبانی شده

این endpoint جداول زیر را پشتیبانی می‌کند:

- `binfo` - اطلاعات پایه پرواز
- `futbl` - جدول سوخت
- `mpln` - مسیر اصلی (Primary Route)
- `apln` - مسیر جایگزین اول (1st Alternate Route)
- `bpln` - مسیر جایگزین دوم (2nd Alternate Route)
- `tpln` - مسیر Take-off Alternate
- `cstbl` - سناریوی سوخت بحرانی
- `aldrf` - Altitude Drift
- `wtdrf` - Weight Drift
- `wdtmp` - Wind & Temperature Aloft
- `wdclb` - Wind Climb
- `wddes` - Wind Descent
- `icatc` - ICAO ATC Format

## محدودیت‌ها

- **حداکثر حجم بدنه**: 20 MB
- **روش‌های مجاز**: POST, OPTIONS
- **Content-Type**: 
  - `application/json` برای JSON format
  - `text/plain` یا هر نوع دیگری برای String format

## لاگ‌ها

تمام درخواست‌ها در مسیر زیر لاگ می‌شوند:

```
skyputer/logs/skyputer_ofp-YYYY-MM-DD.ndjson
```

هر رکورد شامل:
- `timestamp_utc` - زمان دریافت درخواست (UTC)
- `request_id` - شناسه یکتای درخواست
- `data` - اطلاعات درخواست (فرمت، شماره پرواز، تاریخ، و...)

## ذخیره‌سازی در دیتابیس

داده‌های دریافت شده در جدول `skyputer_ofp_data` ذخیره می‌شوند:

```sql
CREATE TABLE skyputer_ofp_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    flight_number VARCHAR(50) NULL,
    ofp_date VARCHAR(50) NULL,
    operator VARCHAR(255) NULL,
    unit VARCHAR(10) NULL,
    eta VARCHAR(20) NULL,
    etd VARCHAR(20) NULL,
    aircraft_reg VARCHAR(100) NULL,
    route VARCHAR(255) NULL,
    data_format ENUM('json', 'string') NOT NULL,
    raw_data LONGTEXT NULL,
    parsed_data JSON NOT NULL,
    client_ip VARCHAR(45) NULL,
    request_id VARCHAR(32) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_flight_number (flight_number),
    INDEX idx_ofp_date (ofp_date),
    INDEX idx_created_at (created_at)
);
```

## نمونه تست

برای تست endpoint می‌توانید از فایل‌های نمونه استفاده کنید:

- `Skyputer_jsonSample.txt` - نمونه JSON
- `Skyputer_stringSample.txt` - نمونه String
- `StringGuide.txt` - راهنمای فرمت String

## نکات مهم

1. **امنیت**: در محیط production بهتر است CORS و IP restrictions اعمال شود
2. **احراز هویت**: می‌توانید API Key یا Token برای احراز هویت اضافه کنید
3. **محدودیت نرخ**: برای جلوگیری از abuse می‌توانید Rate Limiting اضافه کنید

