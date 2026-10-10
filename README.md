# ATA: AI Telegram Admin (آتا)

افزونه وردپرس برای **مدیریت هوشمند کانال‌های تلگرام با هوش مصنوعی** — تولید، بازنویسی، ترجمه، خلاصه‌سازی، زمان‌بندی و صف انتشار خودکار، همه از داخل پیشخوان وردپرس.

- **نسخه:** 1.0.0-MVP
- **سازنده:** RTL-Theme
- **نیازمندی‌ها:** WordPress 6.5+، PHP 8.1+ (با اکستنشن `sodium`)، MySQL 5.7+ / MariaDB 10.3+
- **مجوز:** GPL-2.0+

## قابلیت‌ها

- اتصال Bot Token تلگرام با اعتبارسنجی getMe و ذخیره رمزنگاری‌شده
- اتصال به هر سرویس سازگار با OpenAI (OpenAI، OpenRouter، Groq و …) با مدیریت چند Provider
- تولید / بازنویسی / خلاصه / تیتر / کپشن / ترجمه محتوا با ذخیره خودکار پیش‌نویس
- پیش‌نمایش، تأیید، زمان‌بندی و انتشار فوری به کانال
- صف انتشار اتمیک با retry و backoff نمایی (حداکثر ۵ کار در هر دقیقه)
- پشتیبان ارسال عکس + کپشن در یک پیام
- داشبورد، لاگ ساختاریافته با retention و redaction خودکار secretها
- لایسنس fail-open با اتصال دامنه و سیستم به‌روزرسانی امن از سرور RTL-Theme

## نصب سریع

1. پوشه `ata-telegram-ai-admin` را در `wp-content/plugins/` قرار دهید و افزونه را فعال کنید.
2. از منوی «ATA: AI Admin ← تلگرام»، Bot Token را از [@BotFather](https://t.me/BotFather) وارد کنید.
3. از «AI Providerها»، سرویس هوش مصنوعی و کلید API را ثبت کنید.
4.Bot را به‌عنوان ادمین به کانال اضافه کنید و شناسه کانال (منفی، مثل `-1001234567890`) را ثبت کنید.
5. محتوا بسازید، تأیید کنید و زمان‌بندی کنید.

راهنمای کامل: [INSTALLATION.md](INSTALLATION.md) — راهنمای استفاده: [USER-GUIDE.md](USER-GUIDE.md)

## پیشنهاد امنیتی

کلید رمزنگاری اختصاصی در `wp-config.php` تعریف کنید:

```php
define('ATA_ENCRYPTION_KEY', 'رشته-تصادفی-حداقل-۳۲-کاراکتری');
```

جزئیات: [SECURITY.md](SECURITY.md)

## مستندات

| سند | محتوا |
| --- | --- |
| [INSTALLATION.md](INSTALLATION.md) | نصب و راه‌اندازی |
| [USER-GUIDE.md](USER-GUIDE.md) | استفاده روزمره |
| [FAQ.md](FAQ.md) | پرسش‌های متداول |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | عیب‌یابی |
| [CHANGELOG.md](CHANGELOG.md) | تاریخچه نسخه‌ها |
| [SECURITY.md](SECURITY.md) | امنیت و گزارش آسیب‌پذیری |
| [ARCHITECTURE.md](ARCHITECTURE.md) | معماری فنی |

## توسعه‌دهندگان

- تست: `composer install && vendor/bin/phpunit` (نیازمند محیط تست وردپرس — see `includes/Tests/`)
- اسناد طراحی و گزارش فازها در [`docs/`](docs/)
