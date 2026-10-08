# معماری هوش مصنوعی (AI Architecture) — AI Telegram Admin (ATA)

| مشخصه | مقدار |
| --- | --- |
| فاز | PHASE 6 — AI Architecture |
| صاحب فاز | AI Architecture Engineer |
| وضعیت | نهایی برای Gate فاز ۶ |
| منبع حقیقت | `SPECIFICATION.md:326-352` (الزامات فاز ۶)؛ قوانین سراسری ۹، ۱۳، ۱۴ در `SPECIFICATION.md:33-38` |
| ورودی مصرف‌شده | `docs/ARCHITECTURE.md` (۳.۲ AI Layer؛ D-5، D-8، D-12، D-15، D-16، D-18)؛ `docs/TELEGRAM_ARCHITECTURE.md` (الگوی پورت/آداپتر هماهنگ)؛ `docs/UX_SPEC.md` (صفحات AI Providers و AI Generator) |

---

## ۱. خلاصه

معماری AI کاملاً **Provider-Agnostic** است: هیچ Provider یا Model ای هاردکد نمی‌شود و افزودن Provider جدید فقط «یک Adapter + ثبت در Registry» است، بدون تغییر Core (الزام `SPECIFICATION.md:352`). زنجیره‌ی رسمی فاز ۶ — Provider → Adapter → AI Client → Request → Prompt Builder → Task → Response Parser → Validation → Result — بخش به بخش بسته می‌شود. پشتیبانی پایه، **OpenAI-Compatible API** با هفت پارامتر تنظیم‌پذیر است: Base URL, API Key, Model, Temperature, Max Tokens, Timeout, Headers (`SPECIFICATION.md:330-338`). امنیت کلید API سرور-فقط و مطابق قانون ۱۳ (بخش ۸). **هیچ کد production نوشته نمی‌شود (قانون ۱۹)** — فقط قراردادها و جریان‌ها.

---

## ۲. جایگاه در معماری کل

```
Application Layer (AiTaskService ← تنها مصرف‌کننده‌ی هسته)
        │ فقط Interface + DTO خنثی (AIRequest/AIResult)
        ▼
Ata\Contracts\AI\AIProviderInterface          ◄── پورت
        ▲ implements                              (این سند، بخش ۴)
Ata\AI\Registry\AIProviderRegistry            ◄── ثبت driverها (D-5)
        │ resolve(driver)
Ata\AI\Providers\OpenAICompatibleProvider     ◄── آداپتر عمومی (بخش ۵)
        │
        ├─ PromptBuilder (قرارداد از فاز ۷؛ MVP: پیش‌فرض‌های ساده — D-16)
        ├─ ResponseParser → Validation        (بخش ۶)
        ├─ SecretStore (کلید فقط در مرز HTTP — D-15)
        └─ HttpClientInterface (SSRF-Gate روی base_url کاربر — D-8)
```

جهت وابستگی دقیقاً مثل لایه‌ی تلگرام (`ARCHITECTURE.md:70`): `Ata\AI` نه `Ata\Application` را می‌شناسد نه `Ata\Admin` را؛ ارتباط فقط با DTOهای خنثی. تفاوت کلیدی با تلگرام: **base_url اینجا از کاربر می‌آید (BYOK)**، پس SSRF-Gate حیاتی‌ترین کنترل این لایه است (D-8).

---

## ۳. مفاهیم و نگاشت واژگان فاز ۶

زنجیره‌ی `SPECIFICATION.md:340-350` به اجزای واقعی این معماری:

| واژه‌ی SPEC | جزء در معماری ATA | نقش |
| --- | --- | --- |
| **Provider** | رکورد `ata_providers` (type=ai) + `ProviderConfig` | یک سرویس AI پیکربندی‌شده توسط کاربر (driver, baseUrl, model, …) |
| **Adapter** | `OpenAICompatibleProvider` (و driverهای آینده) | ترجمه‌ی `AIRequest` به پروتکل خاص سرویس و برگرداندن پاسخ خام |
| **AI Client** | `AiClient` (لایه‌ی نازک داخل آداپتر) | ساخت/امضای درخواست HTTP، timeout، retry سطح انتقال، mask کردن secret |
| **Request** | `AIRequest` (DTO) | عملیات + پیام‌ها + config + context — خنثی و بدون اثری از Provider |
| **Prompt Builder** | `PromptBuilderInterface` (فاز ۷) | ساخت system/user prompt از template/متغیر/tone/زبان |
| **Task** | `AiTask` در هسته: یکی از ۶ عملیات MVP | واحد کار: generate/rewrite/summarize/title/caption/translate |
| **Response Parser** | `ResponseParser` داخل آداپتر | JSON پاسخ → `RawAiCompletion` (متن، مصرف توکن، مدلِ پاسخ‌دهنده) |
| **Validation** | `AiResultValidator` | بررسی ساختار/طول/امنیت خروجی قبل از تحویل به هسته (D-18) |
| **Result** | `AIResult` (DTO) | خروجی نهایی خنثی برای `AiTaskService` |

---

## ۴. پورت: `AIProviderInterface`

```
interface AIProviderInterface {
  generate(AIRequest req): AIResult
  listModels(ProviderConfig cfg): Model[]
  testConnection(ProviderConfig cfg): ConnectionTestResult
}
```

| متد | مصرف‌کننده | MVP/V1 |
| --- | --- | --- |
| `generate()` | `AiTaskService` (همه‌ی ۶ عملیات) | MVP |
| `listModels()` | صفحه‌ی AI Providers → انتخاب Model (`SPECIFICATION.md:410`) | MVP — درایور OpenAI-Compatible از `GET {base}/models`؛ در صورت 404/عدم پشتیبانی → `[]` و UI به «ورود دستی نام مدل» fallback می‌کند |
| `testConnection()` | دکمه‌ی تست اتصال | MVP — یک درخواست chat مینیمال (`max_tokens=1`, prompt ثابت کوتاه)؛ **بدون ذخیره‌ی هیچ secret در پاسخ/لاگ** |

### ۴.۱ DTOها

```
ProviderConfig { providerId, driver, name, baseUrl, model, temperature: 0..2,
                 maxTokens: int, timeoutSec: int, headers: {کدهای allow-list},
                 secretRef }            ► apiKey هرگز اینجا نیست؛ فقط ارجاع SecretStore
AIRequest      { operation: generate|rewrite|summarize|title|caption|translate,
                 messages: ChatMessage[],      // ساخته‌شده توسط PromptBuilder
                 config: RequestConfig,        // model/temperature/maxTokens/timeout override
                 context: TaskContext,         // metadata خنثی: زبان، کانال، طول هدف
                 requestId }
AIResult       { ok: bool, text?, errorCode?, humanMessage?, requestId,
                 usage: {promptTokens?, completionTokens?, totalTokens?},
                 model: string,               // مدلی که واقعاً پاسخ داد
                 finishReason?: stop|length|content_filter|error }
Model          { id, displayName?, ownedBy? }
```

- `AIResult.text` فقط «متن تمیز نهایی» است — اعتبارسنجی و sanitize انجام شده (بخش ۶).
- `finishReason=length` یعنی خروجی بریده شده → هسته می‌تواند به کاربر بگوید «متن کامل نیست؛ maxTokens را بالا ببرید» (پیام انسانی، بدون retry کور).

---

## ۵. آداپتر: `OpenAICompatibleProvider`

### ۵.۱ چرا «یک آداپتر عمومی» کافی است (تصمیم A-1)

بیشتر سرویس‌های رایج (OpenAI، Azure OpenAI، DeepSeek، Groq، Together، OpenRouter، و سرویس‌های ایرانیِ سازگار) همان قرارداد `POST {base}/chat/completions` با `Authorization: Bearer` را پیاده کرده‌اند. پس MVP = **یک** آداپتر با ۷ پارامتر configurable (`SPECIFICATION.md:330-338`) + Registry. این دقیقاً معنی «Providerهای آینده بدون تغییر Core» است: سرویس OpenAI-Compatible جدید = **صفر کد**، فقط افزودن Provider در UI؛ سرویس با پروتکل متفاوت = یک کلاس Adapter جدید + `registry.register()` (D-5).

### ۵.۲ ساختار داخلی

```
OpenAICompatibleProvider
  ├─ سازنده: (HttpClientInterface, SecretStore, PromptBuilderInterface,
  │           ResponseParser, AiResultValidator, LoggerInterface)
  ├─ generate(AIRequest):
  │     1. messages از قبل توسط PromptBuilder ساخته شده‌اند (فاز ۷)
  │     2. AiClient.post("{baseUrl}/chat/completions", {
  │            model, messages, temperature, max_tokens,
  │          }, headers: {Authorization: Bearer <از SecretStore — فقط اینجا>},
  │             timeout: cfg.timeoutSec)
  │     3. ResponseParser: choices[0].message.content → RawAiCompletion
  │        usage → {promptTokens, completionTokens, totalTokens}
  │     4. AiResultValidator (بخش ۶) → AIResult
  └─ خطاها: AiException (بخش ۷)
```

### ۵.۳ قرارداد با PromptBuilder (مرز فاز ۶/۷)

آداپتر **هیچ** منطقی برای ساخت prompt ندارد؛ `AIRequest.messages` را همان‌طور می‌فرستد. تفکیک مسئولیت (D-16): فاز ۶ = حمل و پروتکل؛ فاز ۷ = محتوا و template. در MVP که Prompt Engine کامل هنوز نیامده، یک `DefaultPromptBuilder` ساده (همان Interface) پیام‌ها را از operation + context + ورودی کاربر می‌سازد — پس **ارتقای فاز ۷ هیچ تماسی با آداپتر ندارد** (جایگزینی پیاده‌سازی در Composition Root).

### ۵.۴ headers سفارشی و allow-list

کاربر می‌تواند headers اضافی بدهد (مثلاً `X-Groq-*` یا هدر احراز هویت سرویس ایرانی). allow-list: هدرهای با پیشوند تأییدشده + `Content-Type` ثابت. **هدر `Authorization` هرگز با ورودی کاربر override نمی‌شود** (جلوگیری از دو-منبعی شدن secret و نشت). دلیل فنی: کاربر غیرفنی ممکن است ناخواسته کلید را در دو جا بگذارد؛ منبع حقیقت یکی است (SecretStore).

### ۵.۵ تصمیم‌های ثبت‌شده (قانون ۹)

| # | تصمیم | دلیل فنی |
| --- | --- | --- |
| **A-1** | یک آداپتر عمومی OpenAI-Compatible برای MVP؛ Registry برای driverهای آینده | بیشترین پوشش بازار با کمترین کد؛ «بدون تغییر Core» با register شدن حل می‌شود نه با پیش‌بینی همه‌ی APIها |
| **A-2** | `temperature/maxTokens/timeout` هم در ProviderConfig (پیش‌فرض) هم در RequestConfig (override هر عملیات) | عملیات‌ها نیازهای متفاوت دارند: title کوتاه و خلاق (temp بالاتر، max کم)، translate وفادار (temp پایین). بدون override، کاربر مجبور بود چند Provider تکراری بسازد |
| **A-3** | Timeout پیش‌فرض ۶۰s و سقف قابل تنظیم تا ۱۸۰s؛ retry انتقالی فقط برای network/5xx (حداکثر ۲ بار، backoff) | درخواست‌های AI طولانی‌اند ولی UX صفحه منتظر است (D-12 synchronous در MVP)؛ retry روی 4xx بی‌معنی و پرهزینه است |
| **A-4** | Streaming در MVP **نیست** | UI در MVP پرسش/پاسخ با وضعیت است (D-12)؛ streaming نیاز به SSE/heartbeat در wp-admin دارد = پیچیدگی بی‌ارزش برای MVP؛ پورت طوری است که `generateStreaming()` در V1 **افزودنی** است بدون شکستن مصرف‌کننده |
| **A-5** | شمارش مصرف (`usage`) در هر پاسخ به هسته برگردانده و در Dashboard تجمع می‌شود | «AI Usage» در MVP Dashboard الزامی است (`SPECIFICATION.md:443`)؛ بدون usage در DTO، داشبورد داده‌ای ندارد. تجمع: Options عددی + Transient خلاصه (معماری سیستم ۳.۶) — بدون جدول جدید |
| **A-6** | fallback خودکار بین Providerها در MVP **نیست** | Feature با پیچیدگی بالا (ترتیب، سهمیه، هزینه) و ارزش MVP پایین؛ کاربر یک Provider پیش‌فرض دارد و خطا انسانی و قابل اقدام است. V1: `ProviderChain` پشت همان پورت — Core تغییر نمی‌کند. (در محصول خواهر، fallback یک مزیت رقابتی بود؛ اینجا Market Research اولویت را به سادگی اتصال داده — `PRODUCT_STRATEGY.md`؛ در صورت تناقض، تصمیم با اسکوپ MVP قفل است و V1 جایش آماده) |
| **A-7** | خروجی AI = غیرقابل‌اعتماد؛ Validation اجباری قبل از تحویل به هسته | D-18؛ prompt injection و HTML خصمانه باید قبل از Preview/Telegram خنثی شوند |

---

## ۶. Response Parser و Validation

### ۶.۱ ResponseParser
- ورودی: JSON خام پاسخ. مسیر استاندارد: `choices[0].message.content`؛ `usage.{prompt_tokens, completion_tokens, total_tokens}`؛ `model`.
- **تحمل تفاوت‌ها (تصمیم A-8):** برخی سرویس‌های «سازگار» فیلدها را کمی متفاوت برمی‌گردانند (مثلاً `usage` ندارند، یا `content` آرایه‌ی part هاست). Parser با سلسله‌مراتب fallback می‌خواند و هرچه نبود را `null` می‌گذارد — **هرگز exception ساختاری باعث شکست محتوای سالم نمی‌شود**. `content` غیررشته‌ای (آرایه‌ی parts) → الحاق part های متنی. دلیل فنی: BYOK یعنی ما API کاربر را انتخاب نمی‌کنیم؛ پایداری در برابر تفاوت‌ها ارزش محصول است.
- پاسخ `data: ...` (SSE تصادفی از سرویس‌های عجیب) → تشخیص و خطای انسانی «سرویس در حالت streaming پاسخ داد؛ غیرفعال کنید» (چون A-4).

### ۶.۲ AiResultValidator (خط امنیت محتوایی)
ترتیب checks (همه قبل از برگشت `AIResult.ok=true`):
1. **خالی/سفید نبودن** → `ai_empty_response` (retryable یک‌بار با seed متفاوت در هسته، نه آداپتر).
2. **طول:** سقف سخت ۲۰٬۰۰۰ کاراکتر (محافظ حافظه/DB) → برش با علامت `[بریده شد]` + `finishReason=length`.
3. **Skill خطرناک:** هیچ کنترلی برای HTML اینجا لازم نیست به شرط اینکه **مسئولیت escape/sanitize با مرز مصرف باشد** — ولی یک استثنا: اگر context درخواست HTML تلگرام کرده (operationهای محتوایی)، متن از `HtmlSanitizer` مشترک با لایه‌ی تلگرام (allow-list یکسان — `TELEGRAM_ARCHITECTURE.md` ۵.۴) عبور می‌کند تا Preview و انتشار هر دو امن باشند. دلیل فنی: sanitize در مبدأ = یک‌بار هزینه، دو مرز مصرف (Preview UI و Telegram) امن؛ sanitize در هر مصرف‌کننده = ریسک فراموشی.
4. **Prompt-injection pass-through:** الگوهای آشکار دستور-مانند در **خروجی** (مثلاً «ignore previous instructions» در متن تولیدی) حذف نمی‌شوند (متن ممکن است مشروعاً درباره‌ی AI باشد) ولی در `context.developer` با flag ثبت می‌شوند تا در لاگ developer قابل ردیابی باشد. تصمیم A-9: **فیلتر محتوا سانسور نیست، flag+sanitize ساختاری است** — محتوای متنی نهایتاً در Preview توسط انسان تایید می‌شود (فلوی Approve اجباری UX) و این قوی‌ترین سد injection است.
5. **زبان/جهت:** تشخیص ساده‌ی RTL برای نمایش درست در Preview — cosmetic، بدون رد کردن محتوا.

### ۶.۳ نگاشت ۶ عملیات MVP (`SPECIFICATION.md:414-422`) به Task

| Task | ورودی | خروجی | ملاحظات Prompt (فاز ۷) |
| --- | --- | --- | --- |
| `generate` | topic/context (+channel) | متن کامل پست | template اصلی؛ tone/language از context |
| `rewrite` | متن موجود | بازنویسی هم‌معنی | طول هدف ≈ متن اصلی؛ preserve زبان |
| `summarize` | متن بلند | خلاصه با طول هدف | سقف طول در context |
| `title` | متن پست | عنوان کوتاه | maxTokens پایین؛ بدون punctuation زائد |
| `caption` | تصویر(context توصیف کاربر) + متن | caption ≤۱۰۲۴ | محدودیت تلگرام در Validator هم چک می‌شود |
| `translate` | متن + زبان مقصد | ترجمه | temp پایین؛ preserve قالب‌بندی |

همه از **یک** مسیر `generate(AIRequest)` عبور می‌کنند؛ تفاوت فقط در `operation` و پیام‌هایی است که PromptBuilder می‌سازد. دلیل فنی: آداپتر نباید operation بشناسد (جداسازی کامل محتوا از پروتکل).

---

## ۷. Error Handling

```
AiException { code, httpStatus?, isRetryable(), requestId, humanMessage }
```

| code | منشأ | retryable | پیام انسانی (نمونه) |
| --- | --- | --- | --- |
| `ai_invalid_key` | 401/403 | نه | «کلید API نامعتبر است» |
| `ai_invalid_model` | 404/400 «model not found» | نه | «مدل در این سرویس وجود ندارد» |
| `ai_bad_request` | 400 سایر | نه | «درخواست نامعتبر — تنظیمات را بررسی کنید» |
| `ai_rate_limit` | 429 | بله (Retry-After اگر بود) | «سرویس AI موقتاً محدود کرده» |
| `ai_quota_exceeded` | 429/402 با الگوی quota | نه | «سهمیه/اعتبار سرویس تمام شده» |
| `ai_server_error` | 5xx | بله (≤۲ بار، backoff) | «خطای سمت سرویس AI» |
| `ai_network_error` | timeout/DNS/TLS | بله (≤۲ بار) | «اتصال به سرویس AI برقرار نشد» |
| `ai_ssrf_blocked` | SSRF-Gate | نه | «آدرس سرویس معتبر نیست» (بدون جزئیات فنی در UI؛ جزئیات در لاگ developer) |
| `ai_empty_response` | Validator | یک‌بار | «پاسخی تولید نشد؛ دوباره تلاش کنید» |
| `ai_bad_response` | Parser (ساختار غیرقابل استفاده) | نه | «پاسخ سرویس قابل فهم نبود» + Request ID |

- **بدون نشت کلید:** پیام‌ها هرگز Authorization/کلید/base_url کامل را حمل نمی‌کنند؛ body خام پاسخ سرویس فقط در لاگ developer با redaction (همان Logger معماری سیستم ۳.۹ — redaction خودکار الگوهای `key*/token*/secret*/authorization` شامل بدنه‌ی JSON پاسخ هم می‌شود).
- **تمایز 429-quota از 429-rate:** با `error.code/type` سرویس (الگوهای `insufficient_quota`, `rate_limit_exceeded`) — نگاشت در Adapter؛ اگر مبهم بود، پیش‌فرض `ai_rate_limit` (رفتار محافظه‌کارانه: retry محدود بهتر از شکست قطعی اشتباه).

---

## ۸. امنیت کلید API (قانون ۱۳ — صریح)

| # | تضمین | مکانیزم |
| --- | --- | --- |
| 1 | کلید فقط سمت سرور | تمام فراخوانی‌ها در PHP سمت سرور؛ هیچ JS کلاینتی کلید یا proxy-کلید دریافت نمی‌کند؛ REST endpointها فقط `secretRef` و وضعیت connection را برمی‌گردانند |
| 2 | ورودی یک‌طرفه | UI `type=password` + placeholder ماسک؛ پاسخ API هرگز مقدار را echo نمی‌کند (`UX_SPEC.md:507-512`) |
| 3 | رمزنگاری در سکون | SecretStore AES-256-GCM (`ARCHITECTURE.md:281`)؛ `ata_providers` فقط `secret_ref` |
| 4 | عدم نشت در لاگ/خطا | redaction خودکار Logger + AiException بدون secret + mask در HttpClient (مشترک با تلگرام، T-3) |
| 5 | SSRF-Gate روی base_url | scheme https/http، بلاک private/reserved IP، محافظت DNS-rebinding (resolve + compare) — D-8؛ base_url ورودی **کاربر غیرفنی** است، پس validation قبل از هر درخواست |
| 6 | testConnection بدون ذخیره‌ی side-effect | درخواست مینیمال؛ پاسخ فقط ok/errorCode؛ کلید در هیچ فیلد پاسخ نیست |
| 7 | Authorization override ناپذیر از headers کاربر | allow-list هدرها (۵.۴) |

---

## ۹. Registry و افزودن Provider جدید (سناریوی Extensibility)

```
AIProviderRegistry.register("openai-compatible", factory)   ← bootstrapping
// آینده — سرویس با پروتکل متفاوت:
AIProviderRegistry.register("anthropic-style", factory2)    ← فقط یک کلاس جدید + یک خط ثبت
```

- `supports()` در driver: تشخیص اینکه یک `ProviderConfig` با این driver سازگار است (برای خطایابی تنظیمات).
- UI در MVP یک driver دارد («OpenAI-Compatible»)، ولی ساختار داده (`ata_providers.driver`) و Registry از روز اول چنددرایوره‌اند — **بدون هزینه‌ی MVP، با ارزش V1**. دلیل فنی (قانون ۹): مهاجرت بعدی = backfill ستون driver، در برابر طرح تک‌درایوره = بازنویسی ذخیره‌سازی و UI.

---

## ۱۰. تست‌پذیری (پیش‌نیاز فاز ۱۲)

- `FakeAiProvider` برای تست هسته (AiTaskService، Preview، Dashboard usage) بدون شبکه.
- تست آداپتر با `FakeHttpTransport`: هر کد خطای بخش ۷، پاسخ `usage`-نداشته، `content` آرایه‌ای، SSE تصادفی، timeout.
- تست Validator: متن ۲۰K+، HTML خصمانه، خروجی خالی، finishReason=length.
- تست SSRF-Gate: `base_url` های `http://127.0.0.1`, `http://169.254.169.254`, `file://`, IP خصوصی با DNS-rebinding شبیه‌سازی‌شده → همه blocked.
- تست secret: assert اینکه در هیچ log/exception/response کلید ظاهر نمی‌شود (تست dedicated با FakeLogger).

---

## ۱۱. مفروضات و وضعیت داده (قانون ۱۲)

| # | مورد | وضعیت | محل بستن |
| --- | --- | --- | --- |
| AI-1 | سازگاری دقیق سرویس‌های محبوب ایرانی با قرارداد OpenAI | unconfirmed (نیاز به تست با کلید واقعی هر سرویس) | فاز ۱۲ — ماتریس سازگاری |
| AI-2 | رفتار `listModels` در سرویس‌های مختلف (بعضی 404 می‌دهند) | unconfirmed | fallback دستی در UI طراحی شده (بخش ۴)؛ تست در فاز ۱۲ |
| AI-3 | اعداد timeout بهینه برای سرویس‌های کند | unconfirmed — پیش‌فرض ۶۰s مستدل (A-3) | تنظیم در فاز ۱۱ با bench |
| AI-4 | هزینه‌ی هر عملیات (برای نمایش مصرف ریالی) | notCovered — عمداً در MVP نیست (قیمت‌ها متغیر و provider-محورند؛ نمایش اشتباه بدتر از نبود است) | V1 با جدول قیمت user-editable |

---

## ۱۲. معیار عبور از Gate فاز ۶

1. پشتیبانی OpenAI-Compatible با هر ۷ پارامتر: Base URL, API Key, Model, Temperature, Max Tokens, Timeout, Headers (بخش ۴/۵).
2. زنجیره‌ی کامل SPEC نگاشت شده: Provider → Adapter → AI Client → Request → Prompt Builder → Task → Response Parser → Validation → Result (بخش ۳).
3. هیچ Provider/Model ای هاردکد نشده؛ افزودن Provider جدید بدون تغییر Core (بخش ۹، A-1).
4. امنیت API Key سرور-فقط با چک‌لیست ۷-بندی (بخش ۸).
5. هر ۶ عملیات MVP پوشش دارد (بخش ۶.۳).
6. Error Handling با کدهای ماشین‌خوان + پیام انسانی فارسی بدون نشت secret (بخش ۷).
7. تصمیم‌ها با دلیل فنی ثبت شده‌اند (A-1 تا A-9)؛ موارد نامعلوم unconfirmed/notCovered (بخش ۱۱)؛ هیچ کد production نوشته نشده (قانون ۱۹).

---

*پایان سند — مرحله‌ی بعد: بررسی توسط Independent Reviewer، سپس Gate و ورود به PHASE 7 (موتور پرامپت، `SPECIFICATION.md:356-393`).*
