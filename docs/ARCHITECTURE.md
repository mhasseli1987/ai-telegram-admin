# معماری سیستم (System Architecture) — AI Telegram Admin (ATA)

| مشخصه | مقدار |
| --- | --- |
| فاز | PHASE 4 — System Architecture |
| صاحب فاز | System Architect |
| وضعیت | پیش‌نویس نهایی برای Gate فاز ۴ |
| منبع حقیقت | `SPECIFICATION.md:260-289` (الزامات فاز ۴)؛ قوانین سراسری ۹، ۱۳، ۱۴ در `SPECIFICATION.md:33-42` |
| ورودی مصرف‌شده | `docs/PRODUCT_SPEC.md` (بخش‌های ۲، ۱۱، ۱۲)، `docs/PRODUCT_STRATEGY.md` (بخش‌های ۲، ۳، ۵)، `docs/UX_SPEC.md` (بخش‌های ۲، ۶، ۷، ۱۲)، `docs/MARKET_RESEARCH.md` (بخش ۵) |
| خروجی‌های وابسته‌ی آینده | `TELEGRAM_ARCHITECTURE.md` (فاز ۵)، `AI_ARCHITECTURE.md` (فاز ۶)، `PROMPT_ENGINE.md` (فاز ۷) |
| نوع خروجی | سند معماری — **هیچ کدی نوشته نشده است** (قانون ۱۹: `SPECIFICATION.md:43`) |

---

## خلاصه

این سند معماری کل سیستم را با الگوی **Hexagonal / Ports & Adapters** تعریف می‌کند: هسته‌ی منطق تجاری (Application Layer) به هیچ سرویس خارجی وابسته نیست و همه‌ی تکیه‌گاه‌های بیرونی — Telegram، AI، صف، حافظه، زمان، HTTP — پشت **Interface** (پورت) قرار دارند. این دقیقاً ترجمه‌ی معماریِ الزام فاز ۴ است:

> «Telegram Provider و AI Provider نباید به Business Logic وابسته باشند؛ معماری باید Modular و Extensible باشد» (`SPECIFICATION.md:280-285`).

سیزده بخش درخواستی فاز ۴ (`SPECIFICATION.md:264-278`) به‌صورت ماژول صحیح المرز تعریف شده‌اند؛ هر تصمیم مهم معماری در بخش ۸ با **دلیل فنی** ثبت شده (قانون ۹: `SPECIFICATION.md:33`) و امنیت از اولین تصمیم (سیاست‌های Secret، SSRF، CSRF، Webhook) لحاظ شده است (قانون ۱۴: `SPECIFICATION.md:38`).

---
## گفتار اول: اصول معماری (Architecture Principles)

همه‌ی تصمیم‌های این سند از این شش اصل استخراج می‌شوند:

1. **تحت سلسله‌مراتب وابستگی سفت‌وسخت به سمت داخل (Dependency Rule):** وابستگی کد فقط از «بیرون» به «داخل» است. لایه‌ی بیرونی (Telegram، AI، Admin UI، REST، Queue) می‌تواند به قراردادهای هسته نگاه کند؛ هسته (Application Logic) هرگز به پیاده‌سازی‌های بیرونی نگاه نمی‌کند. این آزادترین تفسیر عملیِ الزام «Provider مستقل از Business Logic» است.
2. **Provider-Agnostic به‌معنای Registration بدون تغییر Core:** افزودن Provider جدید (روبات تلگرام جدید، سرویس AI جدید) = افزودن یک کلاس Adapter + یک ردیف ثبت در Registry. هیچ بخشی از هسته تغییر نمی‌کند (`SPECIFICATION.md:352`).
3. **امنیت از ابتدا (قانون ۱۴):** Secretها (`SPECIFICATION.md:37-38`) از روز اول در پشت یک `SecretStore` رمزنگاری‌شده قرار می‌گیرند، نه اینکه بعداً «سخت‌سازی» شوند (`PRODUCT_STRATEGY.md:154`).
4. **عدم فراخوانی سرویس بیرونی در مسیر بارگذاری صفحه:** هیچ Telegram/AI API روی page load اجرا نمی‌شود؛ داده‌ی داشبورد از cache/transient می‌آید (`SPECIFICATION.md:522-533`؛ `UX_SPEC.md:25`).
5. **Testability بدون وردپرس:** چون هسته فقط به Interfaceها وابسته است، در خالی (Unit Test) بدون بارگذاری وردپرس قابل‌تست است — پیش‌نیاز فاز ۱۲.
6. **تکلیف خطا ساخت‌یافته:** هر خطا = Error Code + پیام انسانی + Request ID + Retry Strategy (`SPECIFICATION.md:827-839`). در معماری، هر ماژول قرارداد خطای مشخص با این چهار جزء دارد.

---

## گفتار دوم: نمای کلی و جهت وابستگی

```
┌────────────────────────────────────────────────────────────────────────┐
│                          ADMIN UI (wp-admin, RTL)                        │
│  14 صفحه + AJAX/REST Client (فقط از طریق Application Services)         │
└───────────────▲─────────────────────────────────────────────────────────┘
                │ REST API (wp-json/ata/v1)  +  Legacy AJAX (admin-ajax)
                │ Webhook entry (ata/v1/webhook/telegram) ──┐
┌───────────────┴─────────────────────────────────────────────────────────┐
│                       ╔══════════════════════════════╗                   │
│  Contracts(ports)◄─── ─┤  APPLICATION LAYER (هسته)   │                     │
│  Interfaces          │  ContentService/PostService/ │  ▲ فقط با Interface │
│  Domain Entities     │  PublishService/AiTaskService │  │ حرف می‌زند      │
│  Use-Cases           │  ChannelService/DashboardSvc  │  └── + Repositories│
│                       ╚═══════════════▲══════════════╝                     │
│                                       │                                    │
│      ┌─────────────┬────────────┬─────┴───────┬───────────┬────────────┐   │
│      │             │            │             │           │            │   │
│ Telegram Layer   AI Layer     Queue Layer    Scheduler   Settings     License│
│  (Provider)     (Provider)    (Backend)      (When)     (Config)      (Activ.)│
│      │             │            │             │           │            │   │
│      └─────┬───────┘            └──────┬──────┘           │            │   │
│     مشترک: Transport (WordPress HTTP)  │                  │            │    │
│   ==== Infrastructure/Adapters ========│==================│=========== │    │
│   Database {ata_posts, ata_queue, ...} ◄┘ (Repositories)   │            │    │
│   Logging {ata_logs}   Security {SecretStore,Nonce,Cap}    │            │    │
└──────────────────────────────────────────────────────────────────────────────┘
        خارج (Adapters/Infrastructure)  ←→  داخل (Core؛ ساخت WP-ناآگاه)
```

**قانون جهت وابستگی (به‌صورت الزام معماری):**

- `Ata\Telegram` و `Ata\AI` فقط از `Ata\Contracts\Telegram\TelegramProviderInterface` و قراردادهای سطح خود + `Ata\Http\HttpClientInterface` استفاده می‌کنند. **نه از `Ata\Application`، نه از `Ata\Admin`، نه از `Ata\Queue`** — این معنی الزام `SPECIFICATION.md:282-285` است.
- `Ata\Application` فقط از Interfaceها (`*Interface`) استفاده می‌کند. پیاده‌سازی آن‌ها در `Ata\Infrastructure\Wp*` واقع است.
- تنها نقطه‌ای که «چیزی به همه‌ی چیزها» وصل می‌شود، **Composition Root** است (`Ata\Plugin` + `Ata\Bootstrap`)، نه بدنه‌ی کلاس‌ها.

---

## گفتار سوم: لایه‌ها و ماژول‌ها (۱۳ بخش فاز ۴)

### ۳.۱ Telegram Layer

**مسئولیت:** تمام تعاملات خروجی با Telegram Bot API — شناسایی ربات، ارسال پیام، ارسال تصویر، دریافت Chat، تست اتصال. شامل مدیریت Rate Limit و خطاهای Telegram.

**قرارداد اصلی (پورت):** `Ata\Contracts\Telegram\TelegramProviderInterface`

| متد | ورودی | خروجی | یادداشت فنی |
| --- | --- | --- | --- |
| `getMe()` | — | `BotInfo` | بررسی اعتبار Token؛ هیچ Secret سفارشی برنمی‌گرداند |
| `sendMessage()` | `SendMessageRequest` (chat_id, text, parse_mode, disable_web_page_preview, …) | `SendMessageResult` (message_id) | `parse_mode` فقط از گزینه‌های سفید (HTML متعادل) |
| `sendPhoto()` | `SendPhotoRequest` (chat_id, photo, caption) | `SendMessageResult` | مسیر فایل محلی WP به remote_id |
| `getChat()` | `ChatId` | `ChatInfo` | مورد استفاده‌ی «اتصال کانال» (فاز ۵ جزئیات) |
| `testConnection()` | — | `ConnectionTestResult` | برای دکمه‌ی «تست اتصال» در UI |

**معماری داخلی (فاز ۵ جزئیات در `TELEGRAM_ARCHITECTURE.md`):**

```
TelegramProviderInterface
        ▲
        │
BotApiTelegramProvider  ──►  HttpTransport (HttpClientInterface)
   │  ├─  RateLimitGuard (429/retry-after → RetryableTelegramException)
   │  ├─  ParseUtils (HTML sanitize → HTML)
   │  └─  Mapper (Telegram DTO ⇄ Domain DTO)
```

- **انتقال شبکه:** از `Ata\Http\HttpClientInterface` با پیاده‌سازی `WpHttpTransport` (روی `wp_remote_request`) استفاده می‌شود — نه `wp_remote_post` مستقیم در Provider. دلیل: (الف) SSRF-Gate و Timeout و Throttle در یک نقطه متمرکزند، (ب) تست‌پذیری با `FakeHttpTransport`.
- **Token در این لایه:** هرگز داخل پیام خطا، لاگ یا ساختار برگشتی قرار نمی‌گیرد. `BotApiTelegramProvider` در سازنده فقط `SecretStore::get()` را می‌پذیرد و هرگز مقدار را log نمی‌کند.
- **خطاها:** `TelegramException` با `code` (مثلاً `telegram_rate_limit`)، `isRetryable()`، `retryAfterSeconds()`. لایه بالاتر فقط `code` + پیام انسانی + Request ID را به کاربر/لاگ منتقل می‌کند.

**تصمیم کلیدی ثبت‌شده (D-1، D-4):** پورت «ارسال» جدا از «ورودی» (Webhook بخش ۳.۱۳) تعریف می‌شود؛ محصول چت‌بات نیست (`PRODUCT_SPEC.md:47, 256`)، پس لایه‌ی تلگرام فقط verbs لازم برای انتشار را دارد. Webhook فقط برای کشف کانال و تأیید وضعیت ادمین به کار می‌رود.

### ۳.۲ AI Layer

**مسئولیت:** فراخوانی هر سرویس سازگار با OpenAI (و سرویس‌های آینده) به‌صورت کاملاً Provider-Agnostic: Base URL / API Key / Model / Temperature / Max Tokens / Timeout / Headers (`SPECIFICATION.md:326-352`).

**قرارداد اصلی (پورت):** `Ata\Contracts\AI\AIProviderInterface`

| متد | ورودی | خروجی | یادداشت |
| --- | --- | --- | --- |
| `generate()` | `AIRequest` (operations[]، پیام‌ها، config، context) | `AIResult` | عملیات‌های MVP: `generate/rewrite/summarize/title/caption/translate` (`SPECIFICATION.md:414-422`) |
| `listModels()` | `ProviderConfig` | `Model[]` | برای انتخاب Model در UI؛ خالی برگرداندن = fallback به لیست دستی |
| `testConnection()` | `ProviderConfig` | `ConnectionTestResult` | درخواست مینیمال، بدون ثبت Secret |

**معماری داخلی (فاز ۶ جزئیات در `AI_ARCHITECTURE.md`):**

```
AIProviderRegistry (ثبت Providerها؛ افزودن Provider جدید = ثبت بی‌سیم)   (D-5)
      │
      ▼
AIProviderInterface
      ▲
      │
OpenAICompatibleProvider ──► RequestBuilder → HttpTransport (HttpClientInterface)
      │                                                            ▲
      ├── ResponseParser ──────────────► Validation ───────────────┘
      └── (Providerهای آینده: فقط Adapter + ثبت در Registry)

AIRequest  ←  PromptBuilder (فاز ۷ در PROMPT_ENGINE.md؛ در MVP پیش‌فرض‌های ساده)
AIResult   ←  Validation (ساختارِ پاسخ: content، tokensUsed، model، error)
```

- **Configuration Model:** `ProviderConfig` = `baseUrl`, `apiKey`(فقط در SecretStore)، `model`, `temperature`, `maxTokens`, `timeout`, `headers`. Headers با کلیدهای از پیش تعریف‌شده برای جلوگیری از override هدر Authorization.
- **SSRF-Gate (D-8):** هر `base_url` قبل از استفاده در `HttpClientInterface` اعتبارسنجی می‌شود (scheme http/https، بلاک IP خصوصی/رزرو، جلوگیری از DNS-rebinding با resolve و مقایسه). این مهم‌ترین کنترل امنیتیِ BYOK است چون URL توسط کاربر غیرِفنی وارد می‌شود (`UX_SPEC.md:387`).
- **خطاها:** `AIException` با `code`, `isRetryable()`. Timeout به‌صورت configurable و پیش‌فرض معقول.

**تصمیم کلیدی ثبت‌شده (D-4، D-5، D-8):** لایه‌ی AI هیچ وابستگی به `Ata\Application` ندارد؛ از طریق `AIRequest`/`AIResult` (DTOهای خنثی) با هسته ارتباط برقرار می‌کند.

### ۳.۳ Application Layer (هسته — Business Logic)

**مسئولیت:** تمام استفاده‌کیس‌های محصول (`PRODUCT_SPEC.md:122-139`). این لایه **تنها لایه‌ای است که مفاهیم محصول را می‌فهمد:** پست، کانال، وضعیت، زمان‌بندی، تولید، تایید انسانی.

**نهادهای هسته (Domain Entities/Value Objects):**

| نهاد | ویژگی‌های کلیدی | وضعیت‌ها |
| --- | --- | --- |
| `Post` | id، title، body/caption، channel، status، scheduledAt، publishedAt، telegramMessageId، attempts، requestId | `draft → preview → approved → scheduled → publishing → published/failed` + `cancelled`, `retrying` |
| `Channel` | chatId، username، title، chatType، status | `connected`, `error`, `disconnected` |
| `AiProvider` | id، driver، name، isDefault، config(غیرمبتنی بر Secret)، secretReference | `connected`, `error` |
| `Job` | id، type، payload، status، attempts، maxAttempts، nextRunAt، lockToken، requestId | `pending → running → done` / `failed` / `retrying` / `cancelled` |

**ماشین حالت پست (مطابق `UX_SPEC.md:261` و `UX_SPEC.md:420-428`):**

```
Draft ──preview──► Preview ──approve (انسان، غیرقابل دورزدن)──► Approved
  ▲                 │ ▲                                             │
  │   edit ◄────────┘ │ edit                                        │
  └───────────────────┘                       ┌── schedule ──► Scheduled ─(صف)─┐
                                              │                              ▼
                                        Approved ── publishNow ──► Publishing ──► Published
                                                                   │  error      │ retryable
                                                                   ▼             ▼
                                                                 Failed      Retrying ──► Publishing
لغو (Cancel) از: Scheduled / Publishing / Retrying ──► Cancelled
```

- هیچ مسیرِ انتشار بدون گام `approve` وجود ندارد (`PRODUCT_STRATEGY.md:38`؛ `UX_SPEC.md:427`).
- انتقال وضعیت فقط از طریق **متدهای خاصِ لایه‌ی Application** انجام می‌شود؛ UI و REST نمی‌توانند مستقیم وضعیت را بنویسند.

**سرویس‌های هسته (Use-Case Services):**

| سرویس | مسئولیت | وابستگی (فقط Interface) |
| --- | --- | --- |
| `ContentService` | Draft/Preview/Edit/Approve + ماشین حالت | `PostRepositoryInterface`، `ChannelRepositoryInterface` |
| `PublishService` | تبدیل Post تأییدشده به درخواست Telegram و ارسال | `TelegramProviderInterface`، `PostRepositoryInterface`، `LoggerInterface`، `ClockInterface` |
| `AiTaskService` | شش عملیات تولید/بازنویسی/… / پردازش نتیجه | `AIProviderRegistry`، `PromptBuilderInterface`(V1) |
| `ChannelService` | اتصال/لیست/تست/قطع کانال | `TelegramProviderInterface`، `ChannelRepositoryInterface` |
| `QueueService` | enqueue/claim/complete/fail/retry/cancel | `JobQueueInterface`، `PostRepositoryInterface` |
| `DashboardService` | آمارِ داشبورد از cache/transient | `PostRepositoryInterface`، `LogRepositoryInterface` |
| `LogAccessService` | فیلتر/صفحه‌بندی لاگ‌ها (مطابق `UX_SPEC.md:317-320`) | `LogRepositoryInterface` |

**تصمیم کلیدی (D-3):** هسته به‌صورت **DB-agnostic و WP-agnostic** است: هیچ `$wpdb`، `get_option`، `current_user_can` یا `wp_remote_*` در هسته وجود ندارد؛ همه از پورت‌ها.

### ۳.۴ Queue Layer

**مسئولیت:** نگهداری و اجرای امنِ «کارهای» طولانی (انتشار پست زمان‌بندی‌شده) با Retry، Resume، Cancel، Crash Recovery، Rate Limit، Timeout، Progress (الزامات فاز ۴ و تصمیم‌نهایی فاز ۹: `SPECIFICATION.md:449-472`).

**قرارداد اصلی (پورت):** `Ata\Contracts\Queue\JobQueueInterface`

| متد | توضیح | نکته‌ی فنی |
| --- | --- | --- |
| `enqueue(Job)` | افزودن کار | job_type + payload + nextRunAt؛ idempotency-key برای جلوگیری از duplicate |
| `claim(type, now)` | گرفتن یک کارِ آماده‌ی اجرا با قفل اتمی | **SQL اتمی** (UPDATE … WHERE lock_token=0 RETURNING-style) — مبنای Crash Recovery |
| `complete(id)` | پایان موفق | آزادسازی قفل، ثبت نتیجه |
| `fail(id, code, reason)` | خطای قطعی | به‌سمت Failed/نقطه‌ی تصمیم Retry |
| `retry(id, nextRunAt, attempts)` | تلاش مجدد با Backoff | تلاش‌ها با `maxAttempts` |
| `cancel(id)` | لغو توسط کاربر | فقط از حالت‌های قابل‌لغو |

**مدل اجرا (Phase-aware):**
- **MVP (D-6):** وقت‌گذار = WordPress Cron (event یک‌باره در `nextRunAt` برای هر پست زمان‌بندی‌شده). هنگام اجرا، `QueueService.claim()` صف را با قفل اتمی مرور می‌کند و هر کارِ `nextRunAt <= now` را اجرا می‌کند. این ساده، بدون وابستگی خارجی و کافی برای MVP است.
- **V1 (پایداری ۵۰+ پست؛ `SPECIFICATION.md:449-472`):** پشت همان `JobQueueInterface` می‌توان به `Action Scheduler` (راه‌حل بالغِ WooCommerce) یا Custom Queue مهاجرت کرد — **بدون تغییر هسته**. انتخاب نهایی در فاز ۹ با معیار Retry/Resume/Cancel/Crash Recovery/Rate Limit/Timeout/Progress/Scalability ثبت می‌شود.

**Crash Recovery:** قفلِ کار (`locked_at` + `lock_token`) توسط claim اتمیِ DB گرفته می‌شود؛ اجراگرها بعد از سقوط، قفل‌های منقضی‌شده (متعلق به process مرده) را با `TTL` آزاد می‌کنند. کارِ ناتمام → `retrying` با شمارندهٔ تلاش؛ پس از `maxAttempts` → `failed` (با کد خطا برای کاربر).

### ۳.۵ Scheduler

**مسئولیت:** «کی» اجرا شود — تبدیل انتخاب زمان‌بندی کاربر به رویداد صف (یک‌باره در MVP؛ تکرارشونده خارج از اسکوپ `UX_SPEC.md:290`).

- **Timezone:** همه‌ی ذخیره‌سازی به‌صورت **UTC** در DB + ترجمه به timezone وردپرس برای نمایش و ورودی (`UX_SPEC.md:288`). خطای ساعتِ رایج بین timezone سایت و سرور (UTC mismatch) از ریشه حذف می‌شود.
- **جریان:** `SchedulerService.schedulePost(post, localDateTime)` → اعتبارسنجی (آینده باشد، post در حالت `approved`) → ثبت `Job(type=publish_post, payload=post_id, nextRun_at=UTC)` → ثبت رویداد Cron یک‌باره (MVP) → وضعیت پست `scheduled`.
- **جدا از Queue:** Scheduler یعنی «تصمیمِ زمان»، Queue یعنی «اجرای امن با retry». این تفکیک (D-7) اجازه می‌دهد در فاز ۹ پشت‌اند صف عوض شود بدون لمس زمان‌بندی.
- **Cron توکار وردپرس:** رویدادها با `wp_schedule_single_event`؛ اجراگرِ Cron یک‌نقطه‌ای `Ata\Cron\Runner::run()` که `JobQueueInterface.claim()` را صدا می‌زند. هنگام غیرفعال/حذف افزونه، رویدادهای Cron پاک می‌شوند.

### ۳.۶ Database

**استراتژی (D-2):** انطباق با فاز ۱۱ (`SPECIFICATION.md:512-521`) که گزینه‌ها را «Options / Metadata / Transients» ترجیح می‌دهد ولی «Custom Tables» را در «نیاز واقعی» مجاز می‌داند:

| دسته | سازوکار | دلیل فنی |
| --- | --- | --- |
| تنظیمات و Config | WP Options (`ata_*`) | انتخاب طبیعی WP؛ خواندن پردردسر نیست؛ cache داخلی |
| Secretها | `SecretStore` (Options با مقدار رمزنگاری‌شده + کلید جدا) | قانون ۱۳؛ هرگز plaintext در Options |
| داده‌ی کوتاه‌عمر/نمایش | Transients | داشبورد، شمارنده AI Usage (`UX_SPEC.md:248`) |
| مصرف AI | Options عددی + Transient خلاصه‌ی دوره | بدون جدول؛ شمارش درخواست/خطا |
| **ردیف‌های تراکنشی** (پست‌ها، صف، لاگ‌ها، کانال‌ها، Providerها) | **Custom Tables** با `{prefix}ata_*` | دلیل در D-2 ثبت شده |

**دلیل «نیاز واقعی» برای Custom Tables (تصمیم D-2):** (الف) claim اتمی و قفل صف فقط با «UPDATE اتمی روی جدول تکی» ممکن است؛ Options/Custom Post Type چنین تضمین تراکنشی ندارند. (ب) لاگ‌ها append-only با میلیون‌ها ردیف نباید در CPT/meta بروند (دوغ و روغن جدا). (ج) پستِ محصول وضعیت و المؤرخهٔ مخصوص خود را دارد (`status`, `scheduled_at`, `published_at`) که در WP post_status نمی‌گنجد و هرگوی Meta نویزی می‌سازد. این خروج صریح از ترجیح فاز ۱۱ با «نیاز واقعی» مستند می‌شود.

**مخطط جدول‌های پیشنهادی (مبنا؛ ذکر schema نسخه‌دار در فاز توسعه):**

```
{prefix}ata_posts      id PK, title, body LONGTEXT, image_id nullable, channel_id,
                       status, scheduled_at, published_at, attempts, 
                       last_error_code, request_id, created_at, updated_at
                       INDEX(status), INDEX(status, scheduled_at), INDEX(channel_id)

{prefix}ata_channels   id PK, chat_id, username, title, chat_type, status, created_at
                       UNIQUE(chat_id)

{prefix}ata_providers  id PK, type('telegram'|'ai'), driver, name, is_default,
                       config_json(JSON غیرمبتنی بر Secret), secret_ref, created_at
                       INDEX(type)

{prefix}ata_queue      id PK, job_type, payload JSON, status, attempts, max_attempts,
                       next_run_at, locked_at, lock_token, last_error, request_id,
                       created_at, updated_at
                       INDEX(status, next_run_at), INDEX(job_type)

{prefix}ata_logs       id BIGINT AUTO PK, scope, level, message, context LONGTEXT(JSON
                       redact‌شده), request_id, created_at
                       INDEX(scope, created_at), INDEX(level)
```

- **Migrate به‌صورت نسخه‌دار:** `ata_db_version` option + `dbDelta` در نصب/ارتقا؛ جدول‌های V1 با ALTER‌های افزودنی، نه حذف.
- **پاک‌سازی در Uninstall:** همه‌ی جدول‌های `ata_*` + Options + Cron + Transients در uninstall حذف می‌شوند (الزام `SPECIFICATION.md:681-687`).
- **دسترسی به DB:** فقط از طریق `*RepositoryInterface`های `Ata\Infrastructure\WpDb\*` با `$wpdb->prepare` (کنترل SQL Injection؛ `SPECIFICATION.md:480`). هسته‌ی DB عاری از SQL است.

### ۳.۷ Admin UI

**مسئولیت:** ۱۴ بخش `UX_SPEC.md:40-55` درون `/wp-admin`، RTL، ریسپانسیو، موبایل‌فرندلی.

- **ساختار:** `Ata\Admin\MenuProvider` (ثبت منوی والد `ATA` + ۱۴ submenu)، `Ata\Admin\Page\*Controller` برای هر بخش، `Ata\Admin\View\*` قالب‌های PHP (فقط رندر، بدون منطق تجاری)، `Ata\Admin\Assets` (CSS/JS نسخه‌بندی‌شده با `wp_enqueue_*`).
- **قاعده معماری (D-3):** Controller فقط «ترجمه‌ی HTTP به تماس سرویس هسته» است؛ هیچ فراخوانی مستقیم Telegram/AI در Controller یا View انجام نمی‌شود؛ همه از Application Services.
- **UX امنیتی وفادار به قانون ۱۳:** فیلد Secret = `type=password` + ماسک + هرگز بازگشت مقدار (`UX_SPEC.md:143, 507-512`). فرم‌ها POST؛ Nonce در تمام فرم‌ها؛ Capability چک روی هر صفحه.
- **داده‌ی داشبورد** از `DashboardService` (Transient)؛ هیچ API زنده روی load (`UX_SPEC.md:248`).
- **رندر جدول‌ها** با pagination و فیلتر (پست‌ها، لاگ‌ها) — بی‌نیاز از بارگذاری کل داده (`SPECIFICATION.md:530-532`).
- **نگاشت MVP/V1/V2 صفحات** دقیقاً طبق جدول `UX_SPEC.md:60-77`.

### ۳.۸ Security

بخش مستقل ماژولار ولی به‌عنوان **Cross-cutting** در همه‌ی لایه‌ها اعمال می‌شود (فاز ۱۰ چک‌لیست کامل را سخت‌سازی می‌کند؛ خط مبنای زیر از MVP قفل است — `PRODUCT_STRATEGY.md:154`):

| کنترل | محل اعمال | جزئیات |
| --- | --- | --- |
| رمزنگاری Secret | `Ata\Security\SecretStore` | AES-256-GCM؛ کلید از `ATA_ENCRYPTION_KEY` (wp-config) یا fallback مشتق از salt با ثبت هشدار؛ هرگز در log/error/HTML |
| CSRF | AJAX + Forms | Nonce اختصاصی هر اکشن (`wp_create_nonce('ata_...')`) + verification |
| Capability | هر صفحه/ان‌‌دپوینت | MVP: `manage_options`؛ V2: Capabilityهای اختصاصی (`ata_publish`) برای چندکاربری |
| SQL Injection | فقط در Infrastructure | `$wpdb->prepare` الزامی؛ هسته SQL ندارد |
| XSS | Admin UI | خروجی همیشه با `esc_html/esc_attr/esc_url/esc_textarea` یا `wp_kses` با allow-list محدود |
| SSRF | `Ata\Http\HttpClientInterface` | validation base_url (scheme/IP/private-range/DNS-rebind) برای AI و هر خروجیِ با URL کاربر |
| Prompt Injection | AI Layer + `ContentService` | خروجی AI = دادگان غیرقابل‌اعتماد؛ قبل از ارسال به Telegram با HTML-Sanitizer بومی پاک‌سازی و محدودیت طول؛ متادیتای prompt از ورودی کاربرِ شامل دستورهای خصمانه جدا |
| Webhook Security | بخش ۳.۱۳ | Secret Token؛ IP allowlist؛ idempotency |
| File Upload | Media | اجازه‌ی MIME محدود (image/jpeg, png, webp, gif)، بررسی اسم، اندازه؛ نگهداری خارج از webroot یا با `.htaccess` deny، تحویل از طریق WP |
| Rate Limiting | `HttpTransport` + Queue | 429 → Retryable + Retry-After؛ داده‌ی Throttle در Transient |
| خطای بدون نشت | همه‌ی `Exception`ها | پیام انسانی + Request ID فقط به کاربر؛ جزئیات فنی + context به لاگ Developer با redact (`SPECIFICATION.md:827-839`) |

### ۳.۹ Logging

**مسئولیت:** ثبت ساخت‌یافته‌ی رویدادها با چهار جزء قانون `SPECIFICATION.md:827-839`: Error Code، پیام انسانی، Request ID، Retry/Context — و رعایت قانون ۱۳ (Secret هرگز در لاگ).

- **قرارداد:** `Ata\Contracts\Log\LoggerInterface` (سطح‌ها: debug/info/warning/error).
- **دو نمایش، یک ذخیره (D-9):** ردیف `{prefix}ata_logs` با `scope` (telegram/ai/content/queue/system)، `level`، `message` (فارسیِ انسانی)، `context` (JSON سطح Developer، **redact‌شده**)، `request_id`. UI فقط `message` + `request_id` را نشان می‌دهد (`UX_SPEC.md:316-320`) و جزئیات Developer گشودنی است.
- **Redaction همه‌جا:** `Logger` یک context-processor توکار دارد که الگوهای توکن/کلید (`key*`, `token*`, `secret*`, `authorization`) را قبل از نوشتن با `***` جایگزین می‌کند — حتی اگر توسعه‌دهنده فراموش کند (دفاع دوم).
- **Request ID:** تولید با `RequestIdGenerator` در ابتدای هر عملیات (REST/AJAX/Queue/Webhook) و عبور در کل زنجیره برای اتصال لاگ‌های یک درخواست.
- **Retention:** تنظیمِ مدت‌نگهداری (پیش‌فرض X روز) + پاک‌سازی دستی با تأیید.

### ۳.۱۰ License

**مسئولیت:** فعال‌سازی/اعتبار/وضعیت لایسنس (MVP: درج کلید + فعال‌سازی + وضعیت؛ V1: Update Check — فاز ۱۵: `SPECIFICATION.md:629-644`؛ `UX_SPEC.md:327-331`).

- **قرارداد:** `Ata\Contracts\License\LicenseManagerInterface` — `activate(key, siteUrl)`, `deactivate()`, `status()`, `verify()`.
- **جریان فعال‌سازی:** کلید → ارسال به سرویس لایسنس (از طریق `HttpClientInterface` با TLS) → پاسخ امضاشده (HMAC با کلید عمومی/ثابت مادربُرد) → ذخیره‌ی نتیجه در `SecretStore` + Options (site-hash، expiry، status).
- **اصل حیاتی (D-10):** **Fail-Open با Grace Period.** اگر سرور لایسنس در دسترس نباشد (آفلاین/تعطیلی)، محصول با یک «مهلت» (مثلاً N روز) به کار ادامه می‌دهد و فقط هشدار نمایش می‌دهد؛ **هرگز** سایت/عملکرد مشتری را نمی‌شکند — ترجمه‌ی مستقیم `SPECIFICATION.md:642` («پیاده‌سازی نباید باعث آسیب به سایت مشتری شود»).
- **عدم gate شدن هسته‌ی انتشار:** لایسنس روی «ویژگی‌های مدیریتی/آپدیت» اعمال می‌شود، نه روی جدول‌های DB یا کد هسته؛ حذف افزونه = تمیز.

### ۳.۱۱ Settings

**مسئولیت:** گردآوری و اعتبارسنجی تنظیمات همه‌ی ماژول‌ها.

- **الگو (D-11):** `Ata\Settings\SettingsRegistry` — هر ماژول تعریف تنظیمات خود را (کلید، نوع، پیش‌فرض، سناریوهای اعتبارسنجی/سازگاری) ثبت می‌کند؛ `SettingsService` فقط از روی Registry می‌خواند/می‌نویسد. افزودن بخش جدید بدون مرکزی‌بودن مجدد — Extensibility.
- **تفکیک Secret:** تنظیماتِ معمولی (timezone، مدت، تُن پیش‌فرض) در Options؛ کلیدهای API/Token فقط از `SecretStore` — UI هرگز هر دو را مخلوط نمی‌کند (`UX_SPEC.md:322-325`).
- **پیش‌فرض‌های معقول:** طبق اصل `UX_SPEC.md:325` کاربر غیرفنی بدون لمس تنظیمات هم کار می‌کند.
- **Cache-aware:** هر تغییر، transientهای وابسته (مثلاً داشبورد) را invalid می‌کند.

### ۳.۱۲ REST/AJAX

**مسئولیت:** عملیات ناهم‌زمانِ UI (تست اتصال، تولید، پیش‌نمایش، تایید، انتشار، مدیریت صف) با HTTP.

- **REST اصلی:** prefix `ata/v1` (تحت `wp-json`). جدول زیر پیش‌نمای منابع است (محموله‌ها در فاز توسعه بسته می‌شوند):

| متد | Route | کارکرد | Mark در دسترس |
| --- | --- | --- | --- |
| POST | `/telegram/connect` | ذخیره‌ی Token + تست + getMe | MVP |
| POST | `/telegram/disconnect` | قطع اتصال (با nonce) | MVP |
| POST | `/telegram/test` | تست اتصال جاری | MVP |
| GET | `/channels` | فهرست کانال‌ها | MVP |
| POST | `/ai/providers` / `/ai/providers/{id}` | افزودن/به‌روزرسانی Provider (Secret فقط ورودی) | MVP |
| POST | `/ai/providers/{id}/test` | تست | MVP |
| GET | `/ai/providers/{id}/models` | لیست مدل‌ها | MVP |
| POST | `/ai/generate` | اجرای عملیات AI | MVP |
| POST | `/posts` | Draft | MVP |
| POST | `/posts/{id}/preview`، `/approve`، `/schedule`، `/publish`، `/cancel` | چرخه حیات | MVP |
| GET | `/queue`؛ POST `/queue/{id}/run`, `/cancel`, `/retry` | صف | MVP |
| GET | `/logs`؛ DELETE `/logs` | لاگ | MVP |
| GET | `/dashboard` | آمار (Cache) | MVP |
| GET/POST | `/settings` | تنظیمات | MVP |
| POST | `/license/activate`، `/deactivate` | لایسنس | MVP |

- **آمتیاز/امنیت REST:** احراز هویت کوکی وردپرس + Nonce سراسری REST + Capability در هر Endpoint؛ پاسخ‌ها JSON سالم؛ ورودی‌ها با `sanitize_*`/Validatorهای هسته؛ خروجی با `wp_json_encode` و بدون Secret؛ شناسه‌ی پی‌امتیاز مشکلات V2 (`SPECIFICATION.md:454-473`).
- **AJAX کلاسیک (Legacy):** فقط برای سازگاری با اکشن‌های قدیمی، **همین** سرویس‌های هسته را صدا می‌زند (Controller نازک مشترک) + Nonce + Capability. هیچ منطق دوباره‌ی تجاری در AJAX نیست.
- **عملیات بلند (تولید AI):** MVP به‌صورت synchronous با Timeout بالا + پرس پاسخ صفحه‌ی وضعیت؛ برای V1 به پاترن async/REST+Queue ارتقا می‌یابد — بدون تغییر هسته (D-12).

### ۳.۱۳ Webhook

**مسئولیت:** دریافت اعلان‌های Telegram (ورود، خروج از کانال؛ داده‌ی channel_post برای کشف کانال) — **نه چت‌بات**.

- **نقطه‌ی ورود:** REST route `ata/v1/webhook/telegram` (POST) یا `index.php?ata_webhook=1` — عمومی، بدون Auth ادمین، اما **محمافظی‌شده**:
  1. **Secret Token:** فقط X-Telegram-Bot-Api-Secret-Token (تعیین‌شده هنگام setWebhook) پذیرفته می‌شود؛ مقایسه`hash_equals`.
  2. **IP allowlist (اختیاری):** محدودسازی به بازه‌های IP رسمی Telegram (اطلاع‌رسانی در فاز ۵).
  3. **Idempotency:** ذخیره‌ی `update_id` در Transient برای short-time برای جلوگیری از double-process.
  4. **Update-type allowlist:** فقط `my_chat_member`/`chat_member` (تغییر وضعیت ادمین → به‌روزرسانی وضعیت Channel) و `channel_post` (کشف کانال در فلوی اتصال)؛ بقیه 200 و ignore.
- **آزمایش/بازگشت:** اگر سایت عمومی نباشد، fallback به Polling (`getUpdates`) از طریق Cron اجرا می‌شود (تصمیم نهایی در فاز ۵؛ معماری هر دو پشت `InboundUpdateHandler`).
- **لاگ:** payload رسمی بدون Secret به لاگ Developer (redact به‌صورت خودکار)، Request ID جداگانه.

---

## گفتار چهارم: قراردادهای هسته (Ports) — فهرست مرجع

این Interfaceها **مرزهای ماژول** هستند. پیاده‌سازیِ هر کدام در `Ata\Infrastructure\*` واقع است:

```
Ata\Contracts\
  ├─ Telegram\TelegramProviderInterface               (۳.۱)
  ├─ AI\AIProviderInterface                           (۳.۲)
  ├─ Queue\JobQueueInterface                          (۳.۴)
  ├─ Scheduler\SchedulerInterface                     (۳.۵)
  ├─ Repository\
  │   ├─ PostRepositoryInterface
  │   ├─ ChannelRepositoryInterface
  │   ├─ ProviderRepositoryInterface
  │   ├─ JobRepositoryInterface
  │   └─ LogRepositoryInterface
  ├─ Log\LoggerInterface                              (۳.۹)
  ├─ License\LicenseManagerInterface                  (۳.۱۰)
  ├─ Settings\SettingsServiceInterface                (۳.۱۱)
  ├─ Http\HttpClientInterface                        (مشترک؛ SSRF-Gate)
  └─ Common\ { ClockInterface, IdGeneratorInterface, RequestIdGeneratorInterface }
```

**تز وابستگی (D-13):** بدون کتابخانه‌ی خارجی DI در MVP؛ یک **Service Container داخلی حداقلی** (`Ata\Container`) که map Interface→Factory را نگه می‌دارد. یک Composition Root (`Ata\Bootstrap`) همه‌ی پیوندها را هنگام `plugins_loaded` می‌سازد. این قابلیت تست و داشتن Adapterهای جایگزین را بدون سنگینی DI framework فراهم می‌کند.

---

## گفتار پنجم: جریان‌های End-to-End (دو جریان کلیدی)

### جریان A — تولید → تایید → انتشار فوری
```
Admin UI (AI Generator) ──REST POST /ai/generate──►  AiTaskService
      ▲                                                    │
      │ AIResult (خنثی)                                    ▼
      │                                      HashSet {operation, config, context}
      │                                     └──► OpenAICompatibleProvider → HttpTransport
   Preview (شبیه‌ساز تلگرام)  ◄── ContentService ◄── post:store Draft
      │                                                  │
      ├─ approve (انسان) ──► ContentService.approve()
      │                          │ status: approved
      └─ publishNow ──► PublishService ──► TelegramProviderInterface.sendMessage()
                              │                                    │
                          status: published  ◄── result(+telegram_message_id)
```
هیچ تایپی از Telegram/AI به هسته نشت نمی‌کند؛ فقط DTOهای هسته.

### جریان B — زمان‌بندی → اجرای صف
```
Admin (Scheduler) ──► SchedulerService.schedulePost(post, localDateTime)
   │  اعتبارسنجی + ترجمه وقت به UTC
   │  Post.status := scheduled
   ▼
QueueService.enqueue(Job{publish_post, post_id, next_run_at=UTC})
   │  wp_schedule_single_event(next_run_at, 'ata_due_queue')
   ▼
(Cron) Ata_Queue_Runner ──► JobQueueInterface.claim()      ┌── Crash → locked_at expiry
   │   status: running, lock_token                       → آزادسازی و retrying
   ▼
PublishService(شناسه از payload) ──► TelegramProviderInterface.sendMessage/Photo
   ├─ success → JobQueue.complete + Post.status=published (+ t.me link)
   └─ failure → retryable? retry(next_run+backoff) : fail(code, reason)
```

---

## گفتار ششم: ساختار Namespace و پوشه‌ی پیشنهادی

Namespace موقت `ATA` (از `README.md:33`) به‌صورت PSR-4: `ATA\Ata\...`. ساختار فیزیکی (پیش‌نما؛ در فاز توسعه دقیق می‌شود):

```
ata/
  ata.php                     (bootstrap؛ بارگذاری autoloader + activation/deactivation)
  includes/
    Ata/
      Bootstrap.php           (Composition Root)
      Plugin.php              (یکپارچه‌کننده: hooks، assets، routes، cron)
      Container.php
      Contracts/              (تمام Ports — مستقل از WP)
      Application/            (هسته: Domain + Use-Cases; بدون WP)
      Domain/                 (Entities، Value Objects، State Machine)
      Telegram/               (Provider+builder; فقط Contracts + Http)
      AI/                     (Provider+builder/parser; فقط Contracts + Http)
      Queue/                  (داخلی‌سازی‌های پورت صف: WpCronCoworker، (V1)ActionScheduler)
      Scheduler/
      Settings/               (Registry + Service + SecretStore)
      Security/               (SecretStore، NonceValidator، CapabilityPolicy، SsrfGuard)
      Logging/
      License/
      Http/                   (HttpClientInterface + WpHttpTransport + SsrfGuard)
      Admin/                  (Menu، Pages/Controllers، Views، Assets)
      Rest/                   (Controllers REST؛ ترجمه‌ی HTTP→Application)
      Ajax/                   (controlller نازک Legacy)
      Webhook/                (InboundUpdateHandler + Telegram Webhook Controller)
      Infrastructure/
        WpDb/                 (تحقق Repositories با $wpdb)
        Cron/
        Migrate/              (dbDelta نسخه‌دار)
      i18n/                   (ترجمه‌ها؛ qtranslate؟ خیر — WP i18n استاندارد)
  assets/ (css/js/img — RTL)
  languages/
```

---

## گفتار هفتم: نگاشت معماری به MVP/V1/V2

| ماژول | MVP | V1 | V2 |
| --- | --- | --- | --- |
| Telegram Layer | sendMessage/sendPhoto/getMe/testChannel + Webhook کشف کانال | Rate-Limit پیشرفته، Retry-After | — |
| AI Layer | OpenAI-Compatible + شش عملیات، Registry | Providerهای بیشتر (Anthropic/Gemini/محلی) بدون تغییر Core؛ Stream؛ Cost-Cap client-side | — |
| Content/Publish | ماشین حالت کامل با Preview/Approve | Resume/تجدید تلاش خودکار پیشرفته | Multi-user Roles |
| Queue | WP-Cron + custom table با claim اتمی و Retry ساده | Action Scheduler یا Custom Queue با پایداری ۵۰+، Crash Recovery، Progress، Scalability (`SPECIFICATION.md:449-472`) | — |
| Scheduler | یک‌باره | تکرارشونده (درصورت عبور فیلتر ۱۲.۲) | — |
| Database | ۵ جدول ata_* + Options/Transients | ایندکس‌ها و Partition برای لاگ؛ Cache | — |
| Admin UI | ۱۴ بخش طبق `UX_SPEC.md:60-77` | Templates/Prompts کامل؛ تم تیره | گزارش/تحلیل |
| Security | خط مبنای قانون ۱۳+۱۴ (قفل از MVP؛ `PRODUCT_STRATEGY.md:154`) | چک‌لیست کامل فاز ۱۰ | Capability اختصاصی چندکاربری |
| Logging | Logger با Request ID + Redaction | Retention اتوماتیک + Export | — |
| License | Activate/Deactivate/Status | Update Check (فاز ۱۵) | — |
| Settings | Registry + Service + SecretStore | Migrate برای تنظیمات | Multi-site |
| REST/AJAX | طور کامل برای MVP | Async/Webhook رویدادها | Batch API |
| Webhook | Secret token + allowlist + idempotency + کشف کانال | فشرده‌سازی؛ reconnection alert | — |

---

## گفتار هشتم: ثبت تصمیمات معماری (Decision Log) — با دلیل فنی

| # | تصمیم | دلیل فنی (مستند) |
| --- | --- | --- |
| **D-1** | Telegram و AI به‌صورت **Ports & Adapters** با Interface مستقل از هسته | الزام `SPECIFICATION.md:282-285`؛ تضمین تعویض Provider بدون تغییر Core؛ تست با Mock؛ Aerospace برابر قانون ۹ |
| **D-2** | داده‌های تراکنشی (پست/صف/لاگ/کانال/Provider) در **Custom Tables** به‌جای CPT/Meta | claim اتمیِ صف و Crash Recovery فقط با UPDATE اتمی تضمین می‌شود؛ لاگ append-only باید از Meta جدا بماند؛ پست وضعیت/تاریخ مخصوص دارد (`SPECIFICATION.md:512-521` با ثبت «نیاز واقعی») |
| **D-3** | هسته **WP-agnostic** (بدون $wpdb، get_option، current_user_can، wp_remote_*) | تست‌پذیری Unit بدون وردپرس؛ تضمین جهت وابستگی؛ پیش‌نیاز فاز ۱۲ (`SPECIFICATION.md:536-546`) |
| **D-4** | پورت Telegram فقط **verbs انتشار** (ارسال/تصویر/تست) دارد؛ ورودی از Webhook جدا | محصول چت‌بات نیست (`PRODUCT_SPEC.md:47,256`؛ `SPECIFICATION.md:279`)؛ کاهش سطح حمله؛ Alignment با UCهای MVP |
| **D-5** | **AIProviderRegistry** برای ثبت Providerها (با `supports()` برای انتخاب درایور) | الزام «Providerهای آینده بدون تغییر Core» (`SPECIFICATION.md:352`)؛ مبنای `spec` پارامتر Base URL/Key/Model/… (`SPECIFICATION.md:330-340`) |
| **D-6** | MVP از **WordPress Cron** + جدول صف اختصاصی با claim اتمی استفاده کند؛ پشت `JobQueueInterface` | صفر وابستگی خارجی؛ سادگی MVP؛ پورت ثابت می‌ماند تا فاز ۹ Action Scheduler یا Custom Queue را جایگزین کند (`SPECIFICATION.md:449-457`) |
| **D-7** | تفکیک **Scheduler (کی)** از **Queue (چگونه/با چه retry)** | زمان‌بندی و صف دوعمر متفاوت دارند؛ تعویض backend صف بدون تغییر زمان‌بندی |
| **D-8** | **SSRF-Guard متمرکز** در `HttpClientInterface` برای همه‌ی خروجی‌ها (به‌ویژه base_url کاربر) | BYOK یعنی URL از کاربر غیرِفنی (`UX_SPEC.md:387`)؛ بحرانی‌ترین مسیر نشت به شبکه‌ی داخلی؛ مطابق حمله‌ی SSRF در چک‌لیست فاز ۱۰ (`SPECIFICATION.md:484`) |
| **D-9** | لاگ به‌صورت دو سطح: پیام انسانی + context Developer با **redact خودکار** در یک جدول | قانون ۱۳ (`SPECIFICATION.md:37-39`) + مدل خطاى ۴-جزئی (`SPECIFICATION.md:827-839`)؛ دفاع دوم حتی اگر توسعه‌دهنده redact را فراموش کند |
| **D-10** | لایسنس **Fail-Open با Grace Period**، بدون gate روی هسته | الزام `SPECIFICATION.md:642` (آسیب به سایت مشتری ممنوع)؛ عملی بودن در فروش یک‌باره‌خرید RTL-Theme |
| **D-11** | الگوی **SettingsRegistry** — ماژول‌ها تنظیمات خود را ثبت می‌کنند | Extensibility (اصل ۲)؛ بدون داکیومنت مرکزی؛ Validation متمرکز |
| **D-12** | REST همزمان در MVP، مسیر ارتقاء به async/Queue برای V1 بدون تغییر هسته | تست/سادگی MVP؛ عملیات AI طولانی منافات با UX ندارد چون UI پرس·پاسخ status دارد (`UX_SPEC.md:387`) |
| **D-13** | **Service Container داخلی حداقلی** به‌جای کتابخانه DI خارجی | وابستگی به externals حداقل برای محصول Self-Hosted؛ Control کامل Composition Root؛ بدون barrier نصب |
| **D-14** | زمان‌ها در DB به‌صورت **UTC** + ترجمه به timezone سایت فقط در مرز I/O | خطای تایمزون کلاسیک (سرور UTC × سایت +۳:۳۰) در زمان‌بندی — باگ شماره‌ی یکِ ابزارهای زمان‌بندی self-hosted |
| **D-15** | توکن/API Key فقط در `SecretStore` رمزنگاری‌شده، و فقط در مرز Provider خوانده می‌شود | قانون ۱۳ (`SPECIFICATION.md:37-38`)؛ مبدأ نشت را به یک نقطه‌ی قابل‌رفع محدود می‌کند. |
| **D-16** | Prompts/Templates در MVP فقط پیش‌فرض‌های ساده؛ Prompt Engine کامل در V1 | اسکوپ طبق `PRODUCT_STRATEGY.md:147`؛ معماریِ `PromptBuilderInterface` از اکنون پورت را نگه می‌دارد تا فاز ۷ Core را ست کند |
| **D-17** | Webhook فاقد Auth ادمین ولی با Secret-Token + IP-allowlist + Idempotency | ورودی عمومی باید Validated باشد ولی «بدون لاگین» (ربات تلگرام Auth ندارد)؛ Secret-Token فقط در header مقایسه‌ی `hash_equals` |
| **D-18** | خروجی AI = **غیرقابل‌اعتماد** (Sanitize، محدودیت طول، جدا بودن متادیتا از دستور) | Attack prompt injection (`SPECIFICATION.md:495`)؛ جلوگیری از XSS در HTMLِ پارس‌شوندهٔ تلگرام و دستورالعمل‌های مخفیانه در محتوا |

---

## گفتار نهم: امنیت از ابتدا — چک-لیست متمرکز

(برای Reviewer: عبور از خط مبنای `PRODUCT_STRATEGY.md:154` بدون هیچ «to-do» — این لیست الزامِ MVP است نه V1)

1. هیچ Secret در Frontend/URL/HTML/JS/Log/خطا (`SPECIFICATION.md:499-503`)
2. فراخوانی یک‌نقطه‌ای و Validated HTTP (SSRF-Gate) برای همه‌ی خروجی‌ها
3. تمام فرم‌ها/AJAX/REST با Nonce + Capability
4. همه‌ی کوئری‌ها `$wpdb->prepare`
5. خروجی همه‌ی نمایش‌ها Escaped
6. خروجی AI Sanitize + محدودیت طول + جدا بودن از System Prompt
7. Webhook با Secret-Token/allowlist/Idempotency
8. Upload با MIME/اندازه/نام‌بندی امن
9. Rate Limit/Pentagon Retry-After
10. Error ساخت‌یافته با Request ID بدون Secret
11. Capability در MVP = `manage_options`؛ V2 به‌صورت توسعه‌پذیر
12. لاگ با Redaction خودکار

---

## گفتار دهم: مفروضات و وضعیت داده

| # | فرض/تصمیم | وضعیت | مرجع |
| --- | --- | --- | --- |
| AR-1 | محصول افزونه‌ی وردپرس (PHP/WP) است | تصمیم محصول (`README.md:3`؛ `PRODUCT_SPEC.md:29`) | نهایی از فاز ۰ |
| AR-2 | حداقل‌های هدف برای سازگاری: نسخه‌های مشخص PHP/WP در فاز ۱۱/۱۲ اعتبارسنجی‌شده و ماتریس ثبت می‌شود (`SPECIFICATION.md:548-555`) | unconfirmed | فاز ۱۱/۱۲ |
| AR-3 | Webhook فقط نقشی «ورودی اعلان» دارد (کشف کانال/وضعیت ادمین) — نه پذیرش دستور چت | تصمیم اسکوپ (`PRODUCT_SPEC.md:47`) | نهایی |
| AR-4 | زمان‌بندی تکرارشونده در MVP نیست | `UX_SPEC.md:290` | نهایی |
| AR-5 | پشت‌اند صف نهایی در فاز ۹ انتخاب می‌شود؛ این سند فقط پورت را ثابت می‌کند | unconfirmed (تصمیم مؤجل) | فاز ۹ |
| AR-6 | جزئیات Endpointهای Telegram و الگوریتم کشف کانال به فاز ۵ واگذار می‌شود | مؤجل | فاز ۵ |
| AR-7 | جزئیات Prompt Engine/Response Parser به فاز ۶/۷ واگذار می‌شود؛ فقط قراردادها اینجا | مؤجل | فاز ۶/۷ |

---

## گفتار یازدهم: مرزهای این سند (چه چیزهایی عمداً تعیین نمی‌شود)

- دقیق كردن همه‌ی Endpointهای Telegram و الگوریتم کشف کانال → **فاز ۵** (`TELEGRAM_ARCHITECTURE.md`)
- Adapter/Json Schema دقیق AI و Response Parser → **فاز ۶** (`AI_ARCHITECTURE.md`)
- ساختار Prompt/Tone/متغیرها و Template Engine → **فاز ۷** (`PROMPT_ENGINE.md`)
- انتخاب نهایی اجراگر صف بین WP-Cron/Action Scheduler/Custom (با معیارهای `SPECIFICATION.md:452-468`) → **فاز ۹**
- سخت‌سازی چک‌لیست کامل امنیت → **فاز ۱۰**
- Performance/Indexing/Partition نهایی DB → **فاز ۱۱**
- Schema نهایی جدول‌ها در فایل‌های Migrate‌ی واقعی هنگام توسعه‌ی فاز ۸; اسم‌گذاری دقیق ستون‌ها در همان‌جا
- هیچ Production Code در این فاز نوشته نشده (قانون ۱۹: `SPECIFICATION.md:43`)

---

## گفتار دوازدهم: معیار عبور از Gate فاز ۴

این سند آماده‌ی ورود به فاز ۵ است (برای Reviewer مستقل) اگر:

1. هر ۱۳ بخش الزام فاز ۴ (`SPECIFICATION.md:264-278`) با **مرز واضح** و قرارداد مشخص تعریف شده باشد.
2. اصل «Telegram/AI Provider مستقل از Business Logic» به‌صورت **قابل‌بررسی کدی** (Ports + جهت وابستگی) مستند باشد (`SPECIFICATION.md:282-285`).
3. Modularity/Extensibility مکانیزم مشخص داشته باشد (Registry ها، پورت‌ها، SettingsRegistry) — نه ادعا.
4. هر تصمیم معماری مهم با دلیل فنی ثبت شده باشد (گفتار هشتم، قانون ۹).
5. امنیت (قانون ۱۳ و ۱۴) نه به‌عنوان بخش بعد، بلکه به‌عنوان «خط مبنای از MVP» لحاظ شده باشد (`PRODUCT_STRATEGY.md:154`).
6. نگاشت MVP/V1/V2 با `PRODUCT_SPEC.md` بخش ۱۱ و `PRODUCT_STRATEGY.md` بخش ۵ هم‌خوان باشد و هیچ Feature خارج از اسکوپ وارد نشده باشد (قانون ۷ و ۸).
7. هیچ کدی نوشته نشده باشد (قانون ۱۹).

---

*پایان سند — مرحله‌ی بعد: بررسی توسط Independent Reviewer، سپس Gate و ورود به PHASE 5 (معماری تلگرام، `SPECIFICATION.md:292-324`).*