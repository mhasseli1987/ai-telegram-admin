# گزارش Phase 16 — QA نهایی

**تاریخ:** 2026-10-10 | **تست‌ها:** OK (148 tests, 379 assertions) پس از اصلاحات

## روش
Reviewer مستقل (subagent) در دسترس نبود (خطای provider) → ممیزی مستقیم کد از ۵ منظر SPEC (Customer / Developer / Security Engineer / RTL Buyer / WordPress Developer). فایل‌های خوانده‌شده کامل: RestApi.php، Runner.php، SecretStore.php، SsrfGate.php، WpHttpTransport.php، Installer.php، PostRepository.php، ContentService.php، LicenseManager.php، Updater.php، MenuProvider.php، admin-app.js، Logger.php، SettingsService.php، Plugin.php.

## Critical — پیدا و رفع شد

| # | ایراد | فایل | اصلاح |
|---|---|---|---|
| C1 | **SPA ادمین کاملاً کرش می‌کرد**: `e.createElement(c/CardBody, …)` به‌جای `c.CardBody` → ReferenceError در رندر داشبورد (بخش پیش‌فرض) → همه صفحات خالی | includes/Admin/js/admin-app.js | `c.CardBody` |
| C2 | **همه درخواست‌های REST از SPA با 403 می‌خوردند**: هدر `X-WP-Nonce` ارسال نمی‌شد و nonce اصلاً localize نشده بود → `rest_cookie_invalid_nonce` | admin-app.js + MenuProvider.php | ارسال هدر nonce + `'nonce' => wp_create_nonce('wp_rest')` |
| C3 | **صفحه اصلی منو (toplevel) اسکریپت SPA را لود نمی‌کرد**: شرط `strpos($hook,'ata-') !== 0` با هوک `toplevel_page_…` شکست می‌خورد → صفحه اصلی خالی | MenuProvider.php | شرط جدید پذیرای `toplevel_page_` و `_page_ata-` |
| C4 | **«انتشار فوری» به کانال واقعی تلگرام شکست می‌خورد**: `publishNow` شرط `$channelId <= 0` — کانال‌های تلگرام شناسه منفی دارند | ContentService.php:77 | `=== 0` (هم‌راستا با فیکس Runner در فاز ۱۳) |

## High — پیدا و رفع شد
- **متن خراب فارسی** «پروVIDERها» در منوی SPA → «AI Providerها».
- **داشبورد شکل داده غلط می‌خواند**: SPA آرایه‌ی `channels`/`queue` انتظار داشت ولی REST شیء `counts` برمی‌گرداند → بازنویسی نمایش بر اساس شکل واقعی (کانال‌ها، جمع صف، لاگ‌ها).
- **CSRF صفحه لایسنس** (فاز ۱۵ رفع شد — nonce بدون verify) — در همین چرخه بسته شد.

## تایید شد (بدون ایراد Critical/High)
- **AuthZ:** هر ۲۴ مسیر REST فقط `adminPerm` = `manage_options`؛ پاسخ 403 ساختاریافته.
- **SQL:** تمام `prepare`ها placeholder + آرگومان؛ `payload LIKE` فقط با int cast؛ ورودی کاربر مستقیم در SQL نیست.
- **SSRF:** تک‌نقطه‌ی عبور همه HTTP = `WpHttpTransport` → `SsrfGate::validate` (v4+v6، rebinding، fail-closed)؛ هیچ `curl_/file_get_contents` مستقیم در مسیر کد تولیدی نیست (curl داخل خود گیت فقط برای resolve است).
- **Secrets:** XChaCha20-Poly1305-IETF، nonce تصادفی ۲۴بایتی، magic+tag؛ fallback کلید از AUTH_KEY/NONCE_SALT (محدودیت مستند). حذف کامل گزینه‌ها در uninstall (بدون نشت توکن/API key).
- **Logger:** redaction دوطبقی قبل از persist.
- **Cron/صف:** claim اتمیک با lock_token، TTL و backoff درست، مقایسه‌های UTC سازگار.
- **Uninstall:** حذف ۵ جدول + همه آپشن‌های `ata\_%` + legacy prefix.

## Medium/Low (مستند، مانع انتشار نیست)
- M1: SPA فقط داشبورد و اتصال تلگرام را واقعی پیاده می‌کند؛ بقیه بخش‌ها «در حال ساخت…» — محدوده MVP طبق SPEC (خطر: انتظار خریدار — باید در توضیحات محصول صادقانه ذکر شود؛ در PHASE-14-RTL-PREP.md بخش ۱۹ آمده).
- M2: `wp.element.render` در React 18 وردپرس ۶.۵ deprecated است (کار می‌کند، warning) — مهاجرت به createRoot در نسخه بعد.
- M3: بدون تعریف `ATA_ENCRYPTION_KEY` رمزنگاری به salts وردپرس وابسته است — در راهنمای نصب توصیه شده.
- L1: هشدار timezone دستی: زمان‌بندی `strtotime` با timezone سایت و مقایسه UTC در صف — درست ولی کد مسیر یکسان‌سازی را دوباره در فاز ۱۷ بررسی می‌کند.

## جمع‌بندی
4 Critical + 2 High رفع شد؛ مجموعه تست 148/148 سبز. Gate فاز ۱۶: **PASS**.
