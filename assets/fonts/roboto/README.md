# Roboto Font - Local Installation Guide

## دانلود فونت‌های Roboto

برای استفاده از فونت Roboto به صورت local، باید فایل‌های فونت را دانلود کنید:

### روش 1: دانلود از Google Fonts Helper (پیشنهادی)

1. به [Google Fonts Helper - Roboto](https://gwfh.mranftl.com/fonts/roboto) مراجعه کنید
2. همه وزن‌ها و استایل‌ها را انتخاب کنید:
   - **100 (Thin)** - Normal & Italic
   - **200 (Extra Light)** - Normal & Italic
   - **300 (Light)** - Normal & Italic
   - **400 (Regular)** - Normal & Italic
   - **500 (Medium)** - Normal & Italic
   - **600 (Semi Bold)** - Normal & Italic
   - **700 (Bold)** - Normal & Italic
   - **800 (Extra Bold)** - Normal & Italic
   - **900 (Black)** - Normal & Italic
3. فرمت **Modern Browsers (woff2, woff)** را انتخاب کنید
4. فایل‌های دانلود شده را در این پوشه قرار دهید

### روش 2: دانلود مستقیم از Google Fonts

1. به [Google Fonts - Roboto](https://fonts.google.com/specimen/Roboto) مراجعه کنید
2. روی "Download family" کلیک کنید
3. فایل ZIP را extract کنید
4. فایل‌های زیر را در این پوشه قرار دهید:

**فایل‌های مورد نیاز (18 فایل):**

**Normal:**
- `roboto-v30-latin-100.woff2` و `roboto-v30-latin-100.woff`
- `roboto-v30-latin-200.woff2` و `roboto-v30-latin-200.woff`
- `roboto-v30-latin-300.woff2` و `roboto-v30-latin-300.woff`
- `roboto-v30-latin-regular.woff2` و `roboto-v30-latin-regular.woff`
- `roboto-v30-latin-500.woff2` و `roboto-v30-latin-500.woff`
- `roboto-v30-latin-600.woff2` و `roboto-v30-latin-600.woff`
- `roboto-v30-latin-700.woff2` و `roboto-v30-latin-700.woff`
- `roboto-v30-latin-800.woff2` و `roboto-v30-latin-800.woff`
- `roboto-v30-latin-900.woff2` و `roboto-v30-latin-900.woff`

**Italic:**
- `roboto-v30-latin-100italic.woff2` و `roboto-v30-latin-100italic.woff`
- `roboto-v30-latin-200italic.woff2` و `roboto-v30-latin-200italic.woff`
- `roboto-v30-latin-300italic.woff2` و `roboto-v30-latin-300italic.woff`
- `roboto-v30-latin-italic.woff2` و `roboto-v30-latin-italic.woff`
- `roboto-v30-latin-500italic.woff2` و `roboto-v30-latin-500italic.woff`
- `roboto-v30-latin-600italic.woff2` و `roboto-v30-latin-600italic.woff`
- `roboto-v30-latin-700italic.woff2` و `roboto-v30-latin-700italic.woff`
- `roboto-v30-latin-800italic.woff2` و `roboto-v30-latin-800italic.woff`
- `roboto-v30-latin-900italic.woff2` و `roboto-v30-latin-900italic.woff`

### ساختار پوشه:

```
assets/fonts/roboto/
├── roboto-v30-latin-100.woff2
├── roboto-v30-latin-100.woff
├── roboto-v30-latin-100italic.woff2
├── roboto-v30-latin-100italic.woff
├── roboto-v30-latin-200.woff2
├── roboto-v30-latin-200.woff
├── roboto-v30-latin-200italic.woff2
├── roboto-v30-latin-200italic.woff
├── roboto-v30-latin-300.woff2
├── roboto-v30-latin-300.woff
├── roboto-v30-latin-300italic.woff2
├── roboto-v30-latin-300italic.woff
├── roboto-v30-latin-regular.woff2
├── roboto-v30-latin-regular.woff
├── roboto-v30-latin-italic.woff2
├── roboto-v30-latin-italic.woff
├── roboto-v30-latin-500.woff2
├── roboto-v30-latin-500.woff
├── roboto-v30-latin-500italic.woff2
├── roboto-v30-latin-500italic.woff
├── roboto-v30-latin-600.woff2
├── roboto-v30-latin-600.woff
├── roboto-v30-latin-600italic.woff2
├── roboto-v30-latin-600italic.woff
├── roboto-v30-latin-700.woff2
├── roboto-v30-latin-700.woff
├── roboto-v30-latin-700italic.woff2
├── roboto-v30-latin-700italic.woff
├── roboto-v30-latin-800.woff2
├── roboto-v30-latin-800.woff
├── roboto-v30-latin-800italic.woff2
├── roboto-v30-latin-800italic.woff
├── roboto-v30-latin-900.woff2
├── roboto-v30-latin-900.woff
├── roboto-v30-latin-900italic.woff2
├── roboto-v30-latin-900italic.woff
└── README.md
```

### استفاده:

فایل CSS در `assets/css/roboto.css` تعریف شده است و به صورت خودکار استفاده می‌شود.

**نکته:** اگر فایل‌های فونت را دانلود نکرده‌اید، فونت از fallback (sans-serif) استفاده می‌کند.

