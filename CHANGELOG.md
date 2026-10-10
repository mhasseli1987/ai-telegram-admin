# Changelog — ATA: AI Telegram Admin

تمام تغییرات مهم این پروژه در این فایل ثبت می‌شود.
قالب بر اساس [Keep a Changelog](https://keepachangelog.com/) و نسخه‌بندی [SemVer](https://semver.org/).

## [1.0.0-MVP] — 2026-10-10

### Added
- **هسته پلاگین:** معماری Ports & Adapters با Container سبک DI، autoloader PSR-4، Composition Root
- **تلگرام:** اتصال Bot Token با اعتبارسنجی getMe، ارسال پیام متنی و عکس+کپشن در یک پیام، قطع اتصال، تست اتصال
- **هوش مصنوعی:** Provider سازگار با OpenAI (chat/completions)، عملیات generate/rewrite/summarize/title/caption/translate، تست اتصال، لیست مدل‌ها، مدیریت چند Provider با secret رمزنگاری‌شده
- **محتوا:** تولید با ذخیره خودکار پیش‌نویس، پیش‌نمایش، تأیید، زمان‌بندی، انتشار فوری، لغو
- **صف انتشار:** claim اتمیک با lock_token و TTL، حداکثر ۵ کار در هر گذر cron، retry با backoff نمایی (۲ⁿ×۶۰s، سقف ۱ ساعت، حداکثر ۵ تلاش)، اجرای فوری/لغو/retry دستی
- **زمان‌بندی:** WP-Cron با سرعت دقیقه‌ای (`ata_minute`) + ادپتور Action Scheduler
- **دیتابیس:** ۵ جدول با dbDelta (add-only برای آینده)، migration از مدل CPT قدیمی
- **REST API:** ۲۴ endpoint زیر `ata/v1` با محافظت `manage_options`
- **امنیت:**
  - SecretStore با XChaCha20-Poly1305-IETF (پیشوند `ata_secret_`، magic `ATAS1`)، پشتیبانی `ATA_ENCRYPTION_KEY`
  - SSRF Gate: بلاک IPهای خصوصی/رزرو v4+v6، DNS rebinding، fail-closed — تک‌نقطه‌ی عبور همه ترافیک خروجی
  - Redaction خودکار secretها در لاگ‌ها
  - nonce و capability check در همه فرم‌ها و endpointها
- **لاگ:** ساختاریافته (scope/level/context/request_id) با retention قابل تنظیم
- **لایسنس:** فعال‌سازی با سرور RTL-Theme، fail-open با grace، دامنه‌بایندینگ اطلاعاتی (عدم تطابق فقط هشدار)
- **به‌روزرسانی:** بررسی نسخه با کش ۱۲ ساعته، پیشنهاد آپدیت در صفحه افزونه‌ها، گیت دانلود امن (HTTPS + allowlist + لایسنس معتبر — فقط برای بسته‌های RTL-Theme)، بدون نصب خودکار
- **مدیریت:** منوی ۱۴ بخشی با SPA واکنش‌گرا (داشبورد + اتصال تلگرام تعاملی) و صفحه لایسنس کلاسیک
- **تست:** ۱۶۲ تست یکپارچه/واحد (۴۵۸ assertion) روی مجموعه تست رسمی وردپرس 6.5 — شامل چک‌لیست اجرایی ۱۴ موردی Release

### Fixed (در طول MVP)
- رفع ۴ باگ Critical رابط مدیریت (کرش SPA، نبود nonce REST، صفحه اصلی بدون اسکریپت، رد کانال‌های منفی در سه مسیر)
- رفع نشت secret پس از uninstall (الگوی LIKE خراب توسط escaping)
- رفع URL فرمت تلگرام و عدم ثبت attempts در retry

### Security
- ممیزی امنیتی کامل (Phase 10 + QA نهایی Phase 16): SQL، XSS، CSRF، SSRF، AuthZ، Secret-at-rest — بدون باگ باز Critical/High

## [Unreleased]
- رابط کامل SPA برای همه بخش‌ها (فعلاً داشبورد و تلگرام)
- قالب پرامپت سفارشی
- پشتیبانی Multisite
- آلبوم و ویدیو
- امضای بسته به‌روزرسانی (package signature validation)
