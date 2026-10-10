# Architecture — ATA: AI Telegram Admin

معماری اجراشده نسخه 1.0-MVP (سند طراحی کامل: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md))

## نمای کلی — Ports & Adapters (D-1)

```
Admin UI (SPA + کلاسیک)
        │ REST /ata/v1 (24 endpoint, manage_options)
        ▼
┌─────────────────────────── includes/ ───────────────────────────┐
│ Admin/   RestApi, MenuProvider (Entry Points)                    │
│ Services/ContentService (Use Case: تولید/تأیید/انتشار)           │
│ Cron/    Runner (صف اتمیک), SchedulerAdapter                     │
│ License/ LicenseManager (fail-open), Updater (update/version)    │
│ Core/    Plugin (Composition Root), Container (DI), Settings     │
│ ─────────────────── Ports (Contracts/) ────────────────────────── │
│   HttpClientInterface · TelegramProviderInterface ·              │
│   AIProviderInterface · SecretStoreInterface · LoggerInterface   │
│ ─────────────────── Adapters ──────────────────────────────────── │
│ Telegram/BotApiTelegramProvider · AI/OpenAICompatibleProvider ·  │
│ Infrastructure/WpHttpTransport (SSRF Gate) · WpDb/* (5 جدول) ·   │
│ Security/SecretStore (XChaCha20) · Logging/Logger                │
└──────────────────────────────────────────────────────────────────┘
```

## تصمیم‌های کلیدی

| ID | تصمیم |
| --- | --- |
| D-1 | Ports & Adapters — core بدون وابستگی به WP؛ آداپترها لبه‌ها |
| D-2 | دیتای اختصاصی در ۵ جدول own-schema با dbDelta add-only (بدون drop در آپدیت) |
| D-8 | SSRF Gate اجباری روی همه ترافیک خروجی (fail-closed) |
| D-9 | لاگ ساختاریافته با redaction و retention |
| D-10 | لایسنس fail-open: فقط لایسنس فعالِ منقضی محدود می‌کند |
| D-13 | Container = mapping ساده interface→factory؛ Plugin تنها نقطه سیم‌کشی |
| D-14 | صف اتمیک: UPDATE شرطی + lock_token + TTL، بجای SELECT-then-UPDATE |
| — | زمان‌ها UTC در دیتابیس؛ نمایش با timezone وردپرس |

## جریان انتشار (مسیر بحرانی)

```
schedulePost (REST) ──► ata_queue (pending, next_run_at UTC)
                              │
Runner::handle (cron دقیقه‌ای) │ claim اتمیک:
        UPDATE queue SET status='running', lock_token=…
        WHERE status='pending' AND locked_at IS NULL AND next_run_at <= now
        LIMIT 1
                              ▼
        runPublish: sendMessage/sendPhoto (Bot API)
        ├─ موفق: post=published + job=done + log info
        └─ خطا: attempts+1, backoff 2ⁿ×60s (سقف ۱h, حداکثر ۵ تلاش) → failed
```

قفل‌های منقضی در شروع هر گذر (`clearExpiredLocks`) آزاد می‌شوند — crash بعد از claim باعث قفل ابدی نمی‌شود.

## لایه‌بندی امنیت

1. **AuthN/AuthZ:** همه REST با `manage_options`؛ فرم‌ها با nonce
2. **ورودی:** sanitize_text_field/sanitize_key، `prepare()` روی تمام SQL، placeholder `intval`
3. **خروجی:** esc_html/esc_attr در PHP-render؛ REST فقط JSON
4. **خروجی HTTP:** SsrfGate (v4+v6, rebinding) — تنها مسیر `HttpClientInterface`
5. **ذخیره‌سازی:** secretها رمزنگاری AEAD؛ لاگ‌ها redact؛ uninstall کامل

## تست

- مجموعه رسمی وردپرس 6.5 + PHPUnit 9.6 (bootstrap سفارشی با مرتب‌سازی autoloader اول)
- ۱۶۲ تست / ۴۵۸ assertion: یکپارچه (REST، صف، تلگرام/AI با HTTP موک، لایسنس/آپدیتر، چک‌لیست ۱۴ موردی RC) + واحد (Logger، SecretStore، LicenseManager، Runner)
- HTTP موک در سطح `HttpClientInterface` — هیچ درخواست واقعی در تست‌ها

## محدودیت‌های شناخته‌شده MVP

- رابط SPA کامل فقط داشبورد/تلگرام؛ بقیه از REST
- بدون Multisite، آلبوم، ویدیو، چند-ربات
- امضای بسته آپدیت در نقشه راه
