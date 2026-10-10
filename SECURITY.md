# Security Policy — ATA: AI Telegram Admin

## مدل امنیتی

- **Secrets at rest:** توکن ربات و کلیدهای API با **XChaCha20-Poly1305-IETF** (libsodium) رمزنگاری و در جدول options با پیشوند `ata_secret_` ذخیره می‌شوند. کلید از `ATA_ENCRYPTION_KEY` (توصیه‌شده) یا مشتق‌شده از salts وردپرس. payload: `base64(ATAS1 | nonce24 | ciphertext+tag)`.
- **هرگز برگردانده نمی‌شوند:** secretها در پاسخ REST، فرم‌های مدیریت یا لاگ‌ها (redaction خودکار دوطبقی) ظاهر نمی‌شوند؛ در UI فقط ماسک (`••••xxxx`).
- **SSRF Gate:** تک‌نقطه‌ی عبور همه درخواست‌های HTTP خروجی (`WpHttpTransport → SsrfGate`): فقط http(s)، بلاک loopback/private/reserved/link-local/NAT64/ULA (v4+v6)، رزولوشن هر دو address family با fail-closed و مقاومت به DNS rebinding.
- **AuthZ:** تمام ۲۴ endpoint REST با `manage_options`؛ همه فرم‌ها nonce + capability check؛ خروجی unauthenticated = 401.
- **صف انتشار:** claim اتمیک (UPDATE شرطی با lock_token، TTL ۵ دقیقه) — بدون انتشار تکراری.
- **به‌روزرسانی امن:** دانلود بسته فقط از allowlist هاست RTL-Theme با HTTPS و لایسنس معتبر؛ بسته‌های ثالث هرگز دست‌کاری نمی‌شوند؛ **آپدیت خودکار وجود ندارد**.
- **حذف کامل:** uninstall همه جدول‌ها، آپشن‌ها، transients و secretها را cache-aware حذف می‌کند.
- **ایزوله‌سازی فرآیند:** هیچ `eval`، `exec`، دانلود/اجرای کد از راه دور وجود ندارد.

## نسخه‌های پشتیبانی‌شده

| نسخه | پشتیبانی امنیتی |
| --- | --- |
| 1.0.x | ✅ |
| < 1.0 | ❌ |

## گزارش یک آسیب‌پذیری

1. ایمیل به **security@rtl-theme.com** با موضوع `ATA Security Report`
2. شامل: توضیح، مراحل بازتولید، نسخه افزونه و وردپرس، اثر احتمالی
3. پاسخ اولیه حداکثر ۷۲ ساعت؛ بروزرسانی هر ۵ روز کاری
4. **افشای مسئولانه:** لطفاً تا انتشار وصله، جزئیات را عمومی نکنید. اعتبار گزارش‌گران در CHANGELOG (با اجازه) ذکر می‌شود.

خارج از محدوده: تست‌های DoS، اسپم خودکار، آسیب‌پذیری‌های نسخه‌های پایان‌پشتیبانی وردپرس/PHP.

## توصیه‌های هاردنینگ برای مدیران

- `ATA_ENCRYPTION_KEY` را در wp-config.php تعریف کنید (۳۲+ کاراکتر تصادفی)
- PHP 8.2+ و وردپرس ۶.۶+ را به‌روز نگه دارید
- REST API عمومی سایت لازم نیست؟ دسترسی `wp-json` را برای بازدیدکنندگان ناشناس محدود کنید (افزونه ATA فقط برای کاربران دارای capability جواب می‌دهد)
- دسترسی Administrator را محدود کنید — همه قابلیت‌های ATA با `manage_options` کنترل می‌شوند
