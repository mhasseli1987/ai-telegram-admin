# گزارش Phase 15 — سیستم لایسنس و به‌روزرسانی

**تاریخ:** 2026-10-10 | **Commit:** — | **تست‌ها:** OK (148 tests, 379 assertions) — 20 تست جدید

## محدوده SPEC
SPECIFICATION.md:629 — License Key, Activation, Domain, Update Check, Version Check, Secure Update
**قید سخت:** پیاده‌سازی نباید باعث آسیب به سایت مشتری شود.

## قبلاً موجود (فاز ۱۳) و در این فاز ارتقا یافته
| قابلیت | وضعیت |
|---|---|
| License Key | ✅ `LicenseManager::activate()` — اعتبارسنجی سمت سرور |
| Activation | ✅ REST `/license/activate` و `/license/deactivate` + صفحه ادمین |
| Fail-Open (D-10) | ✅ حفظ شد — نصب بدون لایسنس یا معتبر = همه قابلیت‌ها فعال |

## جدید در فاز ۱۵

### 1. Domain Binding — `LicenseManager`
- `activate()` مقدار `domain` (هاست `home_url()` بدون www) و `version` را به سرور لایسنس می‌فرستد.
- دامنه تأییدشده‌ی سرور ذخیره می‌شود؛ `status()` فیلدهای `domain` و `domain_match` را برمی‌گرداند.
- **نامنطبق بودن دامنه فقط پرچم اطلاعاتی است (در status و صفحه ادمین نمایش داده می‌شود) و هرگز قابلیت‌ها را قطع نمی‌کند** — مطابق قید «عدم آسیب به سایت مشتری». لایسنس بدون دامنه (قدیمی) همیشه match است.

### 2. Update Check + Version Check — کلاس جدید `includes/License/Updater.php`
- `check()`: واکشی آخرین نسخه از `rtl-theme.com/update-check.php` با ارسال license_key + domain + version فعلی؛ **کش ۱۲ ساعته** در ترنزینت (بدون اضافه‌کردن cron — سبک برای میزبان مشتری).
- `fetch()`: در هر خطا/پاسخ بد malformed → `null` (fail-safe).
- هرگز بسته‌ی `http://` (غیر HTTPS) را به وردپرس پیشنهاد نمی‌دهد → `null`.
- `injectUpdate()` روی فیلتر `pre_set_site_transient_update_plugins`: فقط وقتی `version_compare(ATA_VERSION, latest, '<')` باشد، آپدیت به ترنزینت اضافه می‌شود (Version Check).
- `pluginInfo()` روی `plugins_api`: مودال «مشاهده جزئیات» در صفحه افزونه‌ها.

### 3. Secure Update — گیت دانلود
- فیلتر `upgrader_pre_download` → `gateDownload()`:
  - فقط برای بسته‌های از هاست‌های `rtl-theme.com` فعال می‌شود؛ **بسته‌های ثالث (مثل wordpress.org) دست‌نخورده عبور می‌کنند** — حتی اگر لایسنس منقضی باشد.
  - بسته ATA غیر HTTPS → `WP_Error(ata_insecure_package)`.
  - لایسنس فعال‌شده‌ی منقضی → `WP_Error(ata_license_required)`.
- **بدون آپدیت خودکار:** آپدیت فقط در پیشخوان پیشنهاد می‌شود و نصب آن با کلیک مدیر است — هیچ مسیر نصب اجباری وجود ندارد.

### 4. اصلاحات جانبی (باگ‌های واقعی)
- **هک خراب منو:** `adminPage()` مستقیماً به `admin_menu` هوک شده بود و در زمان ثبت منو خروجی HTML چاپ می‌کرد → جایگزینی با `LicenseManager::registerMenu()` (زیرمنوی استاندارد)؛ زیرمنوی License از لیست SPA حذف شد (فرم کلاسیک بدون وابستگی به JS کار می‌کند).
- **CSRF در صفحه لایسنس:** فرم nonce رندر می‌کرد ولی هرگز verify نمی‌شد → `check_admin_referer('ata_license_action')` + `current_user_can('manage_options')` قبل از هر پردازش POST.
- تایپوی پیام «غیرعالی شد» → «غیرفعال شد» (تست هم اصلاح شد).

## تست‌ها — `TestLicenseUpdater.php` (20 تست جدید)
- Domain: ارسال domain/version، ذخیره دامنه سرور، mismatch فقط پرچم (failOpen حفظ)، لایسنس بدون دامنه = match.
- Update Check: پارس پاسخ، fail-safe روی 500 و پاسخ malformed، رد بسته http، ارسال license_key/domain/version.
- Version Check: تزریق برای نسخه جدیدتر، عدم تزریق برای مساوی/قدیمی‌تر، fail-safe روی ترنزینت بد.
- Gate: عبور بسته ثالث حتی با لایسنس منقضی، رد http، رد بدون لایسنس معتبر، عبور با لایسنس معتبر.
- Wiring: ثبت هر ۳ فیلتر، مودال plugin info.

## گزارش‌دهی صادقانه — محدودیت‌ها
1. سرور واقعی `rtl-theme.com/verify-license.php` و `update-check.php` هنوز سمت فروشنده باید ساخته شود؛ قرارداد API در این فاز تعریف شد (درخواست: license_key/product/domain/version ← پاسخ: valid/expires/product/domain و version/url/package/requires/requires_php/changelog).
2. Signature check بسته (wp_package_validator) اضافه نشد — به‌جای آن گیت HTTPS + allowlist + لایسنس پیاده شد؛ قابل افزودن در نسخه بعد.
3. آپدیت Premium در مخزن wordpress.org نیست؛ آپدیت فقط از سرور RTL-Theme.
