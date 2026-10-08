# معماری تلگرام (Telegram Architecture) — AI Telegram Admin (ATA)

| مشخصه | مقدار |
| --- | --- |
| فاز | PHASE 5 — Telegram Architecture |
| صاحب فاز | Telegram API Specialist |
| وضعیت | نهایی برای Gate فاز ۵ |
| منبع حقیقت | `SPECIFICATION.md:292-322` (الزامات فاز ۵)؛ قوانین سراسری ۹، ۱۳، ۱۴ در `SPECIFICATION.md:33-38` |
| ورودی مصرف‌شده | `docs/ARCHITECTURE.md` (گفتار دوم: جهت وابستگی؛ ۳.۱ Telegram Layer؛ ۳.۱۳ Webhook؛ D-1، D-4، D-8، D-15، D-17، D-18)؛ `docs/UX_SPEC.md` (فلوهای اتصال Telegram و اتصال Channel) |
| مرجع API | Telegram Bot API رسمی (core.telegram.org/bots/api) — نسخه‌ی دقیق API که در فاز ۱۱ اعتبارسنجی می‌شود: **unconfirmed** (در این سند فقط Endpointهای پایدار و مستند استفاده شده‌اند) |

---

## ۱. خلاصه

این سند معماری لایه‌ی تلگرام را به‌صورت کامل مشخص می‌کند: پورت `TelegramProviderInterface`، پیاده‌سازی `BotApiTelegramProvider`، پوشش ۱۳ قابلیت خواسته‌شده در فاز ۵ (Bot Token، Channels، Posts، Text، Images، Media، Captions، Inline Buttons، Scheduling، Publishing، Webhook، Error Handling، Rate Limits)، و تضمین‌های امنیتی توکن (قانون ۱۳). اصل حاکم از معماری سیستم (D-1 و بخش ۳.۱): **لایه‌ی تلگرام هیچ وابستگی به Business Logic ندارد**؛ فقط با DTOهای خنثی و `HttpClientInterface` مشترک حرف می‌زند.

مرز این سند طبق `ARCHITECTURE.md` (AR-6): جزئیات Endpointهای Telegram، الگوریتم کشف کانال، مدیریت Rate Limit، و Webhook/Polling در اینجا بسته می‌شوند. **هیچ کد production نوشته نمی‌شود (قانون ۱۹)** — فقط قراردادها، امضاها، شبه‌کد و جریان‌ها.

---

## ۲. جایگاه در معماری کل

```
Application Layer (PublishService, ChannelService)
        │ فقط Interface + DTO خنثی
        ▼
Ata\Contracts\Telegram\TelegramProviderInterface      ◄── پورت (این سند، بخش ۳)
        ▲ implements
Ata\Telegram\BotApiTelegramProvider                   ◄── آداپتر (این سند، بخش ۴)
        │
        ├─ SecretStore (خواندن Token — هرگز log نمی‌شود)
        ├─ RateLimitGuard (429/retry-after)
        ├─ HtmlSanitizer (parse_mode=HTML امن)
        └─ HttpClientInterface (WpHttpTransport + SSRF-Gate — از معماری سیستم، D-8)
```

قوانین جهت وابستگی (از `ARCHITECTURE.md:70`، تکرار الزامی):
- `Ata\Telegram` **فقط** از `TelegramProviderInterface`، DTOهای سطح خودش و `HttpClientInterface` استفاده می‌کند — نه از `Ata\Application`، نه `Ata\Admin`، نه `Ata\Queue`.
- Business Logic (زمان‌بندی، وضعیت پست، صف) **خارج** از این لایه است؛ Scheduling/Publishing در این سند یعنی «قرارداد لایه‌ی تلگرام با آن‌ها»، نه پیاده‌سازی زمان‌بندی.
- هیچ تایپ تلگرامی (update, message, chat JSON) به هسته نشت نمی‌کند — Mapper آن‌ها را به DTO خنثی تبدیل می‌کند (D-18: ورودی تلگرام هم غیرقابل‌اعتماد است).

---

## ۳. پورت: `TelegramProviderInterface`

### ۳.۱ قرارداد کامل

```
interface TelegramProviderInterface {
  getMe(): BotInfo
  getChat(ChatId chatId): ChatInfo
  sendMessage(SendMessageRequest req): SendMessageResult
  sendPhoto(SendPhotoRequest req): SendMessageResult
  editMessageText(EditMessageRequest req): SendMessageResult      // V1 — اصلاح پست منتشرشده
  testConnection(): ConnectionTestResult
}
```

| متد | مصرف‌کننده | MVP/V1 | توضیح |
| --- | --- | --- | --- |
| `getMe()` | فلوی «اتصال Telegram» در Settings | MVP | اعتبارسنجی Token + دریافت username ربات |
| `getChat()` | فلوی «اتصال Channel» | MVP | کشف/اعتبارسنجی کانال و وضعیت ادمین بودن ربات |
| `sendMessage()` | `PublishService` | MVP | انتشار متن (parse_mode=HTML) |
| `sendPhoto()` | `PublishService` | MVP | انتشار تصویر + caption |
| `editMessageText()` | ویرایش پس از انتشار | **V1** | خارج از اسکوپ MVP؛ فقط در پورت رزرو می‌شود تا افزودنش Core را تغییر ندهد |
| `testConnection()` | دکمه‌ی تست در UI | MVP | `getMe()` + نگاشت خطا به پیام انسانی |

**چرا این سطح از verbs (دلیل فنی، D-4):** محصول چت‌بات نیست (`PRODUCT_SPEC.md`)؛ لایه‌ی تلگرام فقط آنچه «انتشار در کانال» نیاز دارد را دارد. هر verb اضافه = سطح حمله و بار تست بیشتر. افزودن verb جدید = تغییر Interface + آداپتر، بدون تماس با هسته (هسته فقط از طریق DTOها مصرف می‌کند).

### ۳.۲ DTOها (خنثی — بدون هیچ فیلد خام Telegram)

```
BotInfo            { botId: int, username: string, canJoinGroups: bool }
ChatId             = string  // "@username" یا id عددی منفی به‌صورت رشته
ChatInfo           { chatId, title, chatType: channel|supergroup|group|private,
                     botStatus: administrator|member|kicked|left|restricted }
SendMessageRequest { chatId, text, parseMode: html|plain (پیش‌فرض html),
                     disableWebPagePreview: bool (پیش‌فرض false) }
SendPhotoRequest   { chatId, photoRef: MediaRef, caption?, parseMode }
MediaRef           = { kind: local-file|remote-url|telegram-file-id, value: string }
SendMessageResult  { messageId: int, chatId, date: int(unix), link: string }
ConnectionTestResult { ok: bool, botUsername?, errorCode?, humanMessage, requestId }
```

- `link` = `https://t.me/{channel_username}/{message_id}` — ساخته‌شده در Mapper، برای نمایش «مشاهده نتیجه» در UI (فلوی ۱۱ مرحله‌ای `UX_SPEC.md`).
- `MediaRef` سه حالت دارد: فایل محلی آپلودشده در Media Library (MVP)، URL عمومی (V1)، و `telegram-file-id` بازگشتی از Telegram (بهینه‌سازی V1 برای ارسال مجدد بدون آپلود دوباره).
- هیچ DTO حاوی توکن یا فیلد secret نیست — بازگشت `BotInfo` هم فقط داده‌ی عمومی ربات است (`ARCHITECTURE.md:86`).

---

## ۴. آداپتر: `BotApiTelegramProvider`

### ۴.۱ ساختار داخلی

```
BotApiTelegramProvider
  ├─ سازنده: (HttpClientInterface http, SecretStore secrets, LoggerInterface log,
  │           ClockInterface clock, RateLimitGuard guard, HtmlSanitizer sanitizer)
  │     ► Token هرگز به‌عنوان مقدار خام وارد سازنده نمی‌شود؛ فقط SecretReference.
  │       خواندن مقدار فقط در لحظه‌ی ساختِ درخواست، در مرز HTTP (D-15).
  ├─ send*(req):
  │     1. guard.acquire()        → اگر throttle فعال: انتظار/رد با errorCode
  │     2. payload = Mapper.toApi(req) + sanitize(parseMode)
  │     3. resp = http.post("https://api.telegram.org/bot{TOKEN}/{method}", payload,
  │                         timeout, headers)      ► TOKEN فقط در URL سمت سرور؛
  │                         HttpClient لاگ URL را با mask می‌نویسد: bot***:***
  │     4. اگر 429: guard.recordRetryAfter(resp.retry_after) → RetryableTelegramException
  │     5. اگر !resp.ok: → TelegramException(code, retryable, apiCode, requestId)
  │     6. return Mapper.fromApi(resp.result)
  └─ Mapper: JSON تلگرام ⇄ DTO خنثی (فیلدهای ناشناخته دور ریخته می‌شوند، نه ذخیره)
```

### ۴.۲ نگاشت متدها به Telegram Bot API رسمی

| متد پورت | Endpoint رسمی | پارامترهای کلیدی | یادداشت |
| --- | --- | --- | --- |
| `getMe()` | `getMe` | — | بدون پارامتر؛ سبک‌ترین اعتبارسنجی Token |
| `getChat()` | `getChat` | `chat_id` | برای کانال: بررسی `type=channel` و `status` (معمولاً `administrator`) |
| `sendMessage()` | `sendMessage` | `chat_id`, `text`, `parse_mode=HTML`, `disable_web_page_preview` | محدودیت رسمی: متن تا ۴۰۹۶ کاراکتر → بخش ۵.۳ |
| `sendPhoto()` | `sendPhoto` | `chat_id`, `photo` (multipart فایل / URL / file_id), `caption`, `parse_mode=HTML` | caption تا ۱۰۲۴ کاراکتر → بخش ۵.۳ |
| `editMessageText()` | `editMessageText` | `chat_id`, `message_id`, `text`, `parse_mode` | V1 |

**روش ارسال فایل (تصمیم T-1):** در MVP فایل‌های محلی با `multipart/form-data` روی همان `wp_remote_request` ارسال می‌شوند (پشت `HttpClientInterface`). دلیل فنی: (الف) ارسال URL عمومیِ فایلِ کاربر به Telegram یعنی نشت محتوای سایتمان به سرویس خارجی + وابستگی به در دسترس بودن URL؛ (ب) multipart مسیر رسمی API است و نیازی به آپلود intermediate ندارد. حد اندازه: پیش‌فرض حداکثر ۵MB برای آپلود ربات (مستندات رسمی Telegram برای ارسال فایل توسط ربات؛ اعتبار نسخه‌ی دقیق: unconfirmed — در فاز ۱۱ با تست واقعی تأیید و در ماتریس محدودیت‌ها ثبت می‌شود).

### ۴.۳ تصمیم‌های ثبت‌شده (قانون ۹)

| # | تصمیم | دلیل فنی |
| --- | --- | --- |
| **T-1** | ارسال فایل با multipart از سرور، نه URL عمومی | جلوگیری از نشت/وابستگی؛ مسیر رسمی API؛ کنترل MIME/اندازه در سمت خودمان (چک‌لیست امنیت `ARCHITECTURE.md:523`) |
| **T-2** | `parse_mode=HTML` با Sanitizer بومی (نه Markdown) | کنترل دقیق‌تر روی allow-list تگ‌ها؛ Markdown تلگرام با محتوای AI (کاراکترهای خاص) شکننده است؛ XSS در پارس HTML تلگرام با allow-list رسمی بسته می‌شود (D-18) |
| **T-3** | Token فقط از `SecretStore`، فقط در مرز HTTP، با mask در همه‌ی لاگ‌ها | قانون ۱۳ + D-15؛ حتی `HttpClient` هم URL کامل را لاگ نمی‌کند |
| **T-4** | Rate Limit به‌صورت `RateLimitGuard` مستقل با Transient | محدودیت‌های رسمی تلگرام global/per-chat هستند و بین درخواست‌های cron/REST مشترک — پس state باید اشتراکی و ماندگار باشد، نه در حافظه‌ی یک request |
| **T-5** | Webhook در MVP برای «کشف کانال + وضعیت ادمین»؛ Polling به‌عنوان fallback | مطابق `ARCHITECTURE.md:349-358` (D-17)؛ هر دو پشت `InboundUpdateHandler` مشترک |
| **T-6** | Inline Buttons در پورت MVP **نیست**؛ ساختار DTO طوری است که در V1 با افزودن `replyMarkup?` به `SendMessageRequest` گسترش می‌یابد | SPEC می‌گوید «در صورت نیاز»؛ نیاز MVP (انتشار در کانال) دکمه نمی‌خواهد؛ Feature Creep ممنوع (قانون ۷/۸). دلیل تجاری: دکمه‌ها برای محصول چت‌بات/تعاملی ارزش دارند، نه کانال یک‌طرفه |

---

## ۵. پوشش ۱۳ قابلیت خواسته‌شده (`SPECIFICATION.md:296-310`)

### ۵.۱ Bot Token
- ورودی: فیلد `type=password` در صفحه‌ی Settings (فلوی «اتصال Telegram») → REST `POST ata/v1/telegram/connect` (معماری سیستم ۳.۱۲).
- ذخیره: `SecretStore` (AES-256-GCM، `ARCHITECTURE.md:281`)؛ در جدول `ata_providers` فقط `secret_ref` ذخیره می‌شود، نه مقدار.
- اعتبارسنجی: فرمات `{digits}:{alphanum}` (بررسی سبک سمت کلاینت برای خطای سریع + بررسی واقعی با `getMe()` سمت سرور). **هرگز** با Token ذخیره‌شده‌ی قبلی در پاسخ API برنمی‌گردد؛ UI فقط «متصل به @botname» نشان می‌دهد.
- چرخش/جایگزینی: ذخیره‌ی Token جدید = overwrite در SecretStore + `getMe()` مجدد؛ Token قدیمی پاک می‌شود (بدون تاریخچه‌ی secret).

### ۵.۲ Channels و ۵.۳ Posts
- **کشف کانال (الگوریتم بسته‌شده‌ی AR-6):**
  1. کاربر در فلوی «اتصال Channel» یکی از دو مسیر را انتخاب می‌کند: (الف) وارد کردن `@username` یا لینک `t.me/` کانال؛ (ب) «کشف خودکار» — ربات را ادمین کانال کند و منتظر بماند.
  2. مسیر الف: `getChat()` بلافاصله؛ اگر `type=channel` و `botStatus=administrator` → کانال ثبت می‌شود؛ اگر `left/kicked/member` → پیام انسانی دقیق («ربات را با حق Admin: Post Messages ادمین کنید») + لینک راهنما.
  3. مسیر ب: Webhook/Polling یک `my_chat_member` با `new_chat_member.status=administrator` دریافت می‌کند → کانال کشف و به فهرست «کانال‌های یافت‌شده» اضافه می‌شود → کاربر تأیید نهایی را در UI می‌زند (هیچ کانالی بدون تأیید انسانی ثبت فعال نمی‌شود).
  4. هر کانال ثبت‌شده در `ata_channels` (معماری سیستم ۳.۶) با وضعیت `connected/error/disconnected`.
- **Posts:** یک «پست» = رکورد `ata_posts` با وضعیت‌های ماشین حالت (معماری سیستم ۳.۳). لایه‌ی تلگرام در لحظه‌ی `Publishing` فقط یک بار `sendMessage`/`sendPhoto` صدا می‌خورد؛ نتیجه (`messageId`, `link`) روی پست ذخیره می‌شود.
- **محدودیت‌های رسمی و رفتار ما:**
  | محدودیت | مقدار رسمی | رفتار ATA |
  | --- | --- | --- |
  | طول متن پیام | ۴۰۹۶ کاراکتر | پیش‌نویس/تولید AI در هسته محدود و هشدار می‌دهد (قبل از approve)؛ در لحظه‌ی ارسال، متنِ بلندتر **خودکار نمی‌شکند** (یکپارچگی محتوا) → خطای انسانی «متن طولانی‌تر از حد تلگرام» با پیشنهاد ویرایش. شکستن خودکار = V1 (Feature با ارزش تجاری مثبت: رشته‌پست) |
  | طول caption | ۱۰۲۴ کاراکتر | همان رفتار بالا برای caption |
  | اندازه فایل ارسالی ربات | ~۵MB (unconfirmed — فاز ۱۱) | بررسی قبل از آپلود در Media + پیام انسانی |

### ۵.۴ Text / ۵.۵ Images / ۵.۶ Media / ۵.۷ Captions
- **Text:** `sendMessage` با `parse_mode=HTML`. سانیتایزر بومی (`HtmlSanitizer`) فقط allow-list رسمی Telegram در HTML را نگه می‌دارد: `<b> <i> <u> <s> <a href> <code> <pre> <blockquote> <tg-spoiler>` و در صورت نیاز V1 `<tg-emoji>`. هر تگ/attribute دیگر حذف می‌شود (نه reject کل پیام). این سد اصلی D-18 است: خروجی AI غیرقابل‌اعتماد است و ممکن است HTML خصمانه تولید/بازتولید کند.
- **Images:** از Media Library وردپرس (`MediaRef.kind=local-file`)؛ `sendPhoto` با multipart. فرمت‌های مجاز همان چک‌لیست آپلود (jpeg/png/webp/gif — `ARCHITECTURE.md:289`).
- **Media:** MVP = تصویر (حالت غالب کانال‌های هدف). ویدیو/صوت/فایل در پورت با الگوی `MediaRef` قابل گسترش است ولی **در MVP نیست** (Feature Creep؛ اسکوپ `PRODUCT_STRATEGY.md`).
- **Captions:** همراه `sendPhoto`؛ همان sanitize و محدودیت ۱۰۲۴.

### ۵.۸ Inline Buttons (در صورت نیاز)
تصمیم T-6: در MVP پیاده‌سازی نمی‌شود. نیازسنجی: هیچ‌کدام از یوزکیس‌های MVP (تولید، پیش‌نمایش، تایید، زمان‌بندی، انتشار) به دکمه نیاز ندارند — محصول «انتشار در کانال» است نه «تعامل». مسیر گسترش در V1 آماده است (افزودن `replyMarkup?` اختیاری به DTO + نگاشت به `reply_markup` API) بدون تغییر مصرف‌کننده‌های موجود.

### ۵.۹ Scheduling و ۵.۱۰ Publishing (قرارداد لایه، نه پیاده‌سازی)
- پیاده‌سازی زمان‌بندی/صف در معماری سیستم بسته شده (۳.۴/۳.۵، D-6/D-7، جریان B). قرارداد این لایه:
  1. `Ata\Cron\Runner` → `JobQueueInterface.claim()` → `PublishService.publish(postId)`.
  2. `PublishService` از `ChannelRepository` کانال را می‌گیرد و **فقط** `TelegramProviderInterface.sendMessage/sendPhoto` را صدا می‌کند.
  3. نتیجه: success → `messageId/link` روی پست + `complete`؛ خطای retryable → `retry` با backoff (بخش ۷)؛ خطای قطعی → `fail` با کد.
  4. **Idempotency انتشار (تصمیم T-7):** قبل از ارسال، اگر `telegram_message_id` روی پست پر است → ارسال تکراری انجام نمی‌شود (محافظ در برابر double-publish بعد از crash بین «ارسال موفق» و «ثبت نتیجه»). اگر response تلگرام موفق ولی ثبت نتیجه ناموفق بود، اجراگر بعدی با دیدن نبودِ `message_id` ممکن است دوباره بفرستد → برای بستن این پنجره، `ata_queue.payload` شامل `idempotency_key` است و در سناریوی مشکوک، وضعیت پست به «نیازمند بررسی» می‌رود (یک رکورد لاگ + پیام به ادمین) به‌جای ارسال کور. دلیل فنی: هزینه‌ی پست تکراری در کانال عمومی (آبروی مشتری) از هزینه‌ی یک توقف دستی بیشتر است.

### ۵.۱۱ Webhook
از معماری سیستم ۳.۱۳ (D-17) با بسته‌شدن جزئیات:
- **Endpoint:** `wp-json/ata/v1/webhook/telegram` (POST، عمومی، بدون cookie-auth).
- **Secret Token:** هنگام `setWebhook` مقدار `secret_token` تصادفی ۳۲+ کاراکتر تولید و در `SecretStore` ذخیره می‌شود؛ Telegram آن را در header `X-Telegram-Bot-Api-Secret-Token` برمی‌گرداند؛ مقایسه فقط با `hash_equals`. نبود/نادرستی header → ۴۰۳ بدون body اطلاعاتی.
- **allow-list نوع update:** فقط `my_chat_member`، `chat_member`، `channel_post` (کشف کانال در فلوی اتصال). بقیه → ۲۰۰ و ignore (جلوگیری از retry طوفانی تلگرام).
- **Idempotency:** `update_id` در Transient کوتاه‌مدت؛ تکراری → ۲۰۰ بدون پردازش.
- **IP allowlist:** بازه‌های رسمی تلگرام (api.telegram.org) در سطح reverse-proxy/WAF توصیه می‌شود (مستند راهنمای نصب)؛ در افزونه به‌عنوان لایه‌ی اختیاری settings (چون IPهای تلگرام ممکن است تغییر کنند — hard-code خطرناک است؛ فهرست به‌روز در راهنما، وضعیت: unconfirmed و باید در فاز ۱۱ از مستندات رسمی استخراج و ثبت شود).
- **setWebhook کی صدا می‌شود:** در فلوی «اتصال Telegram» بعد از موفقیت `getMe()`؛ و در ابزار «بازسازی اتصال» در Settings. پاسخ `setWebhook` در لاگ با Request ID.
- **Payload امن:** محتوای update (متن کانال دیگران و…) غیرقابل‌اعتماد است (D-18)؛ فقط فیلدهای allow-list شده (`chat.id`, `chat.username`, `chat.title`, `chat.type`, `new_chat_member.status`, `update_id`) خوانده می‌شوند؛ بقیه دور ریخته می‌شود. هیچ HTML/متنی از update در UI بدون escape رندر نمی‌شود.
- **fallback Polling (T-5):** اگر سایت پشت NAT/localhost باشد یا `setWebhook` ناموفق بماند، cron پنج‌دقیقه‌ای `getUpdates(offset)` را صدا می‌زند و همان `InboundUpdateHandler` را تغذیه می‌کند. حالت فعال (webhook|polling|off) در settings است؛ تشخیص خودکار: اگر `getWebhookInfo().url` خالی و polling ممکن بود → پیشنهاد در UI.

### ۵.۱۲ Error Handling
مدل خطای ۴-جزئی معماری سیستم (کد، پیام انسانی، Request ID، context) با کدگذاری اختصاصی تلگرام:

```
TelegramException {
  code: string            // کد ماشین‌خوان ATA (جدول زیر)
  apiCode: int            // error_code تلگرام
  isRetryable(): bool
  retryAfterSeconds(): ?int
  requestId: string
  humanMessage: string    // فارسی، بدون هیچ secret، بدون JSON خام
}
```

| code ATA | apiCode تلگرام | retryable | پیام انسانی (نمونه) |
| --- | --- | --- | --- |
| `telegram_invalid_token` | 401 | نه | «توکن ربات نامعتبر است؛ از BotFather بررسی کنید» |
| `telegram_not_found` | 404 | نه | «کانال/چت پیدا نشد؛ username را بررسی کنید» |
| `telegram_forbidden` | 400 + «bot is not a member»/«chat not found» | نه | «ربات عضو/ادمین کانال نیست؛ راهنمای ادمین‌کردن» |
| `telegram_bad_request` | 400 (سایر) | نه | «درخواست نامعتبر» + جزئیات در لاگ developer |
| `telegram_too_long` | 400 + «message is too long» | نه | «متن از حد تلگرام بلندتر است» |
| `telegram_rate_limit` | 429 | بله (retry_after) | «تلگرام موقتاً محدود کرده؛ تلاش خودکار در N ثانیه» |
| `telegram_server_error` | 5xx | بله (backoff) | «خطای سمت تلگرام؛ تلاش مجدد خودکار» |
| `telegram_network_error` | — (timeout/DNS) | بله (backoff) | «اتصال به تلگرام برقرار نشد» |

- `humanMessage` + `code` + `requestId` به UI می‌رود؛ `apiCode` + بخش redact‌شده‌ی context فقط به لاگ developer (قانون ۱۳ — `response.description` تلگرام ممکن است echo از ورودی باشد و باید redact شود).
- **بدون نشت توکن در هیچ مسیری:** چون توکن فقط داخل URL سمت `HttpClient` است و `HttpClient` خودش mask می‌زند (T-3)، حتی exception های لایه‌ی HTTP هم URL خام را حمل نمی‌کنند.

### ۵.۱۳ Rate Limits
- **محدودیت‌های رسمی (پایدار و مستند؛ اعداد دقیق: unconfirmed تا تست فاز ۱۱):** ~۳۰ پیام/ثانیه global، ~۱ پیام/ثانیه به یک chat، ~۲۰ پیام/دقیقه به یک گروه. کانال‌های ATA معمولاً یکی هستند پس قید اصلی: global و per-chat.
- **`RateLimitGuard` (T-4):**
  - state در Transient: `{global_window: [timestamps], per_chat: {chatId: last_sent_at}, retry_after_until: ts}`.
  - قبل از هر ارسال: اگر `retry_after_until > now` → رد با `telegram_rate_limit` و زمان باقی‌مانده (اجراگر صف آن را backoff می‌کند).
  - per-chat: حداقل فاصله ۱ ثانیه بین دو پیام یک کانال (صف آن‌ها را serialize می‌کند — claim اتمی صف همین را تضمین می‌کند).
  - **۴۲۹ واقعی:** `parameters.retry_after` تلگرام → `retry_after_until = now + retry_after` + استثنا retryable → صف با همان تأخیر retry می‌کند (backoff نمایی در معماری سیستم ۳.۴).
  - **دلیل فنی Transient:** ارسال‌ها از دو منشأ (REST همزمان، cron) می‌آیند و ممکن است در request های متفاوت PHP باشند؛ state باید اشتراکی باشد ولی نیازی به دوام طولانی ندارد — دقیقاً کاربرد Transient (`ARCHITECTURE.md:229`).

---

## ۶. جریان‌های End-to-End

### جریان ۱ — اتصال Telegram (تنظیمات)
```
UI (فلو ۳ UX_SPEC) → POST /telegram/connect {token}
  → SecretStore.put(ref, token)  ► در حافظه، نه لاگ
  → Provider.testConnection() = getMe()
      ├─ ok  → ata_providers: {driver: botapi, secret_ref, status: connected}
      │        → setWebhook(url, secret_token)  ► ذخیره secret در SecretStore
      │        → پاسخ UI: «متصل شد: @botname» + وضعیت webhook
      └─ 401 → SecretStore.put لغو (rollback) → خطای انسانی telegram_invalid_token
```

### جریان ۲ — اتصال کانال
```
UI (فلو ۵) → POST /channels {username | "auto-discover"}
  ├─ دستی: Provider.getChat() → type/status بررسی → ثبت connected یا خطای راهنما
  └─ خودکار: منتظر webhook my_chat_member → کشف → «تأیید می‌کنید؟» → ثبت
```

### جریان ۳ — انتشار (فوری یا زمان‌بندی‌شده)
```
PublishService.publish(postId)
  → idempotency check (T-7)
  → guard.acquire(chatId)
  → text: sendMessage / photo+caption: sendPhoto (multipart از Media)
  → success: {messageId, link} → post.status=published
  → 429/5xx/network: retryable → queue.retry(backoff ≥ retry_after)
  → 401/404/400-قطعی: post.status=failed + پیام انسانی + لاگ
```

---

## ۷. چک‌لیست امنیت توکن (قانون ۱۳ — صریح و حسابرسی‌پذیر)

| # | تضمین | مکانیزم | ارجاع |
| --- | --- | --- | --- |
| 1 | توکن هرگز در Frontend/HTML/JS نیست | فیلد `type=password` یک‌طرفه؛ پاسخ API هرگز مقدار را برنمی‌گرداند | ۵.۱؛ `UX_SPEC.md:507-512` |
| 2 | توکن هرگز در URL قابل‌لاگ (access log/query string) نیست | فقط در pathِ URL خروجی سمت سرور به api.telegram.org (ساختار رسمی `bot{token}/method`)؛ access log سایت ما هیچ‌وقت این URL را نمی‌بیند چون درخواست خروجی است؛ لاگ داخلی با mask `bot***` | T-3 |
| 3 | توکن هرگز در لاگ/خطا نیست | SecretStore + mask در HttpClient + redaction خودکار Logger (دفاع دوم) | `ARCHITECTURE.md:299`؛ ۵.۱۲ |
| 4 | توکن در DB plaintext نیست | AES-256-GCM در SecretStore؛ `ata_providers` فقط `secret_ref` | D-15 |
| 5 | توکن در Exception/stack trace نیست | TelegramException هیچ فیلد request خام حمل نمی‌کند؛ context فقط redact‌شده | ۵.۱۲ |
| 6 | Webhook بدون اعتبارسنجی پذیرفته نمی‌شود | secret_token + hash_equals + allow-list نوع + idempotency | ۵.۱۱، D-17 |
| 7 | ورودی تلگرام غیرقابل‌اعتماد است | فیلد allow-list + escape در UI + دور ریختن بقیه | ۵.۱۱، D-18 |
| 8 | SSRF از مسیر تلگرام ممکن نیست | URL ثابت `api.telegram.org` (بدون base_url کاربر در این لایه)؛ `HttpClient` همچنان SSRF-Gate دارد | D-8 |

---

## ۸. تست‌پذیری (پیش‌نیاز فاز ۱۲)

- `FakeTelegramProvider` (in-memory) برای تست هسته بدون شبکه — به‌لطف پورت.
- `FakeHttpTransport` برای تست خودِ `BotApiTelegramProvider`: سناریوهای ۴۲۹/۴۰۱/۴۰۴/۵xx/timeout، mask بودن URL در لاگ‌ها، و multipart.
- تست Webhook: payload نمونه با/بدون secret token، update تکراری، نوع غیرمجاز.
- تست Sanitizer: HTML خصمانه (script, onerror, لینک javascript:) → خروجی امن.
- تست Idempotency (T-7): double-publish بعد از شبیه‌سازی crash.

---

## ۹. مفروضات و وضعیت داده (قانون ۱۲)

| # | مورد | وضعیت | محل بستن |
| --- | --- | --- | --- |
| TG-1 | اعداد دقیق Rate Limit تلگرام | unconfirmed (مستند رسمی عمومی است ولی نسخه/عدد دقیق تست نشده) | فاز ۱۱/۱۲ با تست واقعی |
| TG-2 | حد ۵MB آپلود ربات | unconfirmed | فاز ۱۱ |
| TG-3 | بازه‌های IP رسمی تلگرام برای allowlist | notCovered (عمداً hard-code نشد؛ باید از مستندات رسمی روز استخراج شود) | راهنمای نصب + فاز ۱۱ |
| TG-4 | نسخه‌ی Telegram Bot API هدف | unconfirmed (فقط Endpointهای پایدار استفاده شد: getMe/getChat/sendMessage/sendPhoto/setWebhook/getUpdates/getWebhookInfo) | فاز ۱۱ |
| TG-5 | رفتار واقعی تلگرام با کانال‌های محدود (broadcast限制) | notCovered — نیاز به اکانت/کانال تست واقعی | فاز ۱۲ |

---

## ۱۰. معیار عبور از Gate فاز ۵

1. هر ۱۳ قابلیت `SPECIFICATION.md:296-310` پوشش صریح دارد (بخش ۵) — Inline Buttons با تصمیم مستند «در صورت نیاز = MVP نه».
2. `TelegramProviderInterface` + Adapter طراحی شده و از Business Logic مستقل است (بخش ۲/۳، سازگار با `ARCHITECTURE.md:70`).
3. امنیت توکن با چک‌لیست ۸-بندی حسابرسی‌پذیر تضمین شده (بخش ۷) — هیچ توکنی در Frontend/URL(قابل‌لاگ)/Log/Error نیست.
4. Webhook + fallback polling + Error Handling + Rate Limits بسته شده‌اند (۵.۱۱/۵.۱۲/۵.۱۳).
5. هر تصمیم مهم (T-1 تا T-7) دلیل فنی دارد (قانون ۹).
6. موارد بدون داده‌ی قطعی با unconfirmed/notCovered ثبت شده‌اند (قانون ۱۰/۱۲، بخش ۹).
7. هیچ کد production نوشته نشده (قانون ۱۹) — فقط قرارداد/شبه‌کد/جریان.

---

*پایان سند — مرحله‌ی بعد: بررسی توسط Independent Reviewer، سپس Gate و ورود به PHASE 6 (معماری هوش مصنوعی، `SPECIFICATION.md:326-352`).*
