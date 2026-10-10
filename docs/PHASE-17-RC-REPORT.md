# گزارش Phase 17 — Release Candidate

**تاریخ:** 2026-10-10 | **مجموعه تست:** OK (162 tests, 458 assertions)

## بسته RC
- **فایل:** `dist/ata-telegram-ai-admin-1.0.0-MVP-rc1.zip` (49.3 KB)
- **SHA-256:** `da2fd2b7e41c6530559ca9f052dc3ab0a8fcdceb083ea779d9058ded8b877d11` *(بازسازی‌شده 2026-10-11 از `bc77d57` — نسخهٔ اولیه از staging قدیمی ساخته شده بود و آخرین فیکس‌ها را نداشت؛ هش قبلی: `fc0802…c41`)*
- **ساختار:** `ata-telegram-ai-admin/ata-telegram-ai-admin.php` + `includes/` (30 فایل PHP، lint پاس)
- **حذف‌شده از بسته:** `includes/Tests`, `docs/`, `SESSIONS/`, `composer.json`, `phpunit.xml.dist`, SPECIFICATION (dev-only)

## چک‌لیست ۱۴ موردی SPEC — همگی به‌صورت تست اجرایی (`TestReleaseChecklist.php`)

| # | مورد | نتیجه | پوشش |
|---|---|---|---|
| 1 | Installation | ✅ | ۵ جدول + `ata_db_version` |
| 2 | Activation | ✅ | cron scheduled + migration guard |
| 3 | Setup | ✅ | ذخیره/خواندن تنظیمات از REST + رد کلید ناشناخته |
| 4 | Telegram Connection | ✅ | getMe موک، توکن در SecretStore رمز، هویت ربات ذخیره |
| 5 | AI Connection | ✅ | افزودن Provider + test اتصال؛ کلید API هرگز در پاسخ نیست |
| 6 | Generation | ✅ | `ai/generate` → ذخیره پیش‌نویس با id |
| 7 | Preview | ✅ | عنوان/متن/کانال/طول |
| 8 | Scheduling | ✅ | صف با زمان آینده؛ runner قبل از موعد منتشر نمی‌کند |
| 9 | Publishing | ✅ | claimed → sendMessage → published → job done |
| 10 | Logs | ✅ | ثبت، خواندن، پاک‌سازی |
| 11 | Errors | ✅ | شکست ارسال → attempts + backoff → exhaustion → failed با دلیل |
| 12 | Security | ✅ | SSRF (loopback/private/link-local/file) بلاک؛ REST بدون لاگین 401؛ secret رمز در DB |
| 13 | Performance | ✅ | ۵ job در یک گذر < 5s (اندازه‌گیری واقعی) |
| 14 | Uninstall | ✅ | ۵ جدول حذف؛ همه آپشن‌ها/secrets حذف (cache-aware) |

## باگ‌های واقعی که RC-Testing در همین فاز پیدا و رفع کرد
1. **[CRITICAL-production] الگوی `LIKE 'ata\_%'` در `Installer::uninstall()` بعد از `prepare()` هیچ‌وقت مچ نمی‌شد** → توکن ربات و کلیدهای API بعد از uninstall در DB می‌ماندند (نشت secret). اصلاح با `esc_like()` + حذف cache-aware با `delete_option()`.
2. **[High] `RestApi::schedulePost` شرط `channel_id <= 0`** — سومین نمونه باگ کانال منفی (بعد از Runner و ContentService). → `=== 0`.
3. **[High] `Runner::runPublish` هیچ لاگی ثبت نمی‌کرد** → صفحه Logs برای انتشارهای صفی خالی می‌ماند. → لاگ موفقیت/شکست اضافه شد.
4. **[Medium] وارنینگ `Undefined array key "headers"` در `OpenAICompatibleProvider::buildHeaders`** → `?? []`.
5. تست‌ها: هماهنگی با بازنویسی `CREATE/DROP TABLE → TEMPORARY` آزمون وردپرس (ایزوله‌سازی DDL واقعی فایل چک‌لیست + پاک‌سازی temp twins).

## فرآیند ساخت (تکرارشدنی)
```bash
git archive HEAD | tar -x -C dist/ata-telegram-ai-admin
# حذف dev files؛ سپس:
powershell Compress-Archive -Path 'ata-telegram-ai-admin' -DestinationPath 'ata-telegram-ai-admin-1.0.0-MVP-rc1.zip'
sha256sum ...
```

## محدودیت‌های RC (شفاف)
- اتصال واقعی تلگرام/OpenAI در محیط تست با HTTP موک پوشش داده شد؛ تست دودی با اکانت واقعی پیش از انتشار نهایی توصیه می‌شود (نیازمند توکن واقعی).
- سرور لایسنس/آپدیت سمت RTL-Theme باید endpointهای قراردادشده در PHASE-15-REPORT را پیاده کند.
- Upgrade (به‌روزرسانی از نسخه قبلی) وجود ندارد — این اولین انتشار است؛ مسیر upgrade فقط dbDelta add-only برای آینده.
