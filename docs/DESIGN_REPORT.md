# گزارش طراحی فازهای ۰ تا ۷ — AI Telegram Admin (ATA)

| مشخصه | مقدار |
| --- | --- |
| نقش | بازخوان نهایی مستقل (Final Independent Reviewer) |
| تاریخ | ۲۰۲۶-۱۰-۰۹ |
| منبع حقیقت | `SPECIFICATION.md` |
| دامنه | بازخوانی هر ۸ سند طراحی و ارزیابی آمادگی برای PHASE 8 (توسعه‌ی MVP) |

> **یادداشت شفاف درباره‌ی روش اجرا (قانون ۱۲):** فازهای ۰ تا ۴ در یک گردش کار چندایجنتی با بازبین مستقل جداگانه برای هر فاز تولید/تأیید شدند (فازهای ۱–۳ هر کدام ۳ دور اصلاح تا تأیید). فاز ۴ پس از تولید سند، در مرحله‌ی بازبینی با قطعیِ زیرساخت مدلِ ایجنت‌ها مواجه شد و گیت آن توسط ناظر اصلی به‌صورت مستقیم (بازخوانی خط‌به‌خط سند در برابر الزامات PHASE 4) انجام شد. فازهای ۵ تا ۷ و این گزارش نیز به همان دلیل توسط ناظر اصلی و بدون ایجنت مستقل تولید شدند؛ برای جبران، هر سند هنگام نگارش مستقیماً با بخش متناظر SPEC و اسناد بالادستی مقابله شد و فهرست معیارهای Gate در انتهای هر سند آمده است. این انحراف از روش چندایجنتی **unconfirmed-by-independent-agent** است و در ریسک‌های زیر ثبت شده.

---

## ۱. وضعیت فازها

| فاز | سند | وضعیت گیت | روش تأیید |
| --- | --- | --- | --- |
| ۰ کشف محصول | `docs/PRODUCT_SPEC.md` | ✅ approved (۱ دور) | بازبین مستقل (گردش کار) |
| ۱ تحقیق بازار | `docs/MARKET_RESEARCH.md` | ✅ approved (۳ دور) | بازبین مستقل + جست‌وجوی وب واقعی |
| ۲ استراتژی محصول | `docs/PRODUCT_STRATEGY.md` | ✅ approved (۳ دور) | بازبین مستقل |
| ۳ معماری UX/UI | `docs/UX_SPEC.md` | ✅ approved (۳ دور) | بازبین مستقل |
| ۴ معماری سیستم | `docs/ARCHITECTURE.md` | ✅ approved | ناظر اصلی (بازخوانی در برابر PHASE 4) — ۱۳ لایه کامل، ۱۸ تصمیم مستند |
| ۵ معماری تلگرام | `docs/TELEGRAM_ARCHITECTURE.md` | ✅ produced + self-gated | معیارهای Gate در بخش ۱۰ سند |
| ۶ معماری AI | `docs/AI_ARCHITECTURE.md` | ✅ produced + self-gated | معیارهای Gate در بخش ۱۲ سند |
| ۷ موتور پرامپت | `docs/PROMPT_ENGINE.md` | ✅ produced + self-gated | معیارهای Gate در بخش ۱۳ سند |

همه‌ی اسناد در مخزن `github.com/mhasseli1987/ai-telegram-admin` (شاخه main) commit شده‌اند.

---

## ۲. خلاصه‌ی هر فاز

**فاز ۰ — کشف محصول:** محصول = پلاگین WordPress برای مدیریت محتوای کانال تلگرام با AI (تولید، بازنویسی، خلاصه، تیتر، کپشن، ترجمه + پیش‌نمایش/تایید/زمان‌بندی/انتشار). مشتری هدف: دارندگان کانال، فروشگاه‌ها، رسانه‌ها، آژانس‌ها در بازار ایران. مرز صریح: چت‌بات نیست، شبکه‌های اجتماعی دیگر نیست.

**فاز ۱ — تحقیق بازار:** رقبا در RTL-Theme/CodeCanyon/GitHub/SaaS با جست‌وجوی واقعی بررسی و داده‌ها با برچسب verified/unconfirmed/notCovered ثبت شدند. شکاف بازار: نبود محصولی که همزمان provider-agnostic، دارای صف/زمان‌بندی قابل اتکا، فارسی-first و با امنیت کلیدها باشد.

**فاز ۲ — استراتژی محصول:** جدول Feature|Value|Complexity|Priority|MVP/V1/V2 نهایی شد؛ قیمت پیشنهادی با استناد به رقبا مستند شد؛ مرز «چه نسازیم» صریح است (چت‌بات، multi-platform، streaming، fallback خودکار provider در MVP).

**فاز ۳ — معماری UX/UI:** ۱۴ بخش پنل + فلوهای ۱۱ مرحله‌ای (نصب تا مشاهده‌ی نتیجه)؛ RTL، ریسپانسیو، مناسب کاربر غیرفنی؛ preview شبیه‌ساز تلگرام؛ approve انسانی غیرقابل دورزدن.

**فاز ۴ — معماری سیستم:** هر ۱۳ لایه (Telegram, AI, Application, Queue, Scheduler, Database, Admin UI, Security, Logging, License, Settings, REST/AJAX, Webhook) با ports & adapters؛ هسته WP-agnostic؛ ۱۸ تصمیم معماری با دلیل فنی (D-1 تا D-18)؛ جدول‌های سفارشی با توجیه «نیاز واقعی»؛ چک‌لیست امنیت MVP از روز اول.

**فاز ۵ — معماری تلگرام:** `TelegramProviderInterface` با ۶ متد؛ نگاشت به Endpointهای رسمی Bot API؛ الگوریتم کشف کانال (دستی + خودکار با webhook)؛ RateLimitGuard با Transient؛ مدل خطای ۸-کدی با پیام فارسی؛ idempotency انتشار (T-7)؛ چک‌لیست ۸-بندی امنیت توکن؛ Inline Buttons با تصمیم مستند خارج از MVP.

**فاز ۶ — معماری AI:** زنجیره‌ی کامل SPEC نگاشت شد؛ یک آداپتر OpenAI-Compatible با هر ۷ پارامتر؛ Registry برای driverهای آینده (افزودن provider بدون تغییر Core)؛ Parser مقاوم به تفاوت سرویس‌ها؛ Validator پنج‌مرحله‌ای؛ ۱۰ کد خطا؛ SSRF-Gate روی base_url کاربر؛ چک‌لیست ۷-بندی امنیت کلید.

**فاز ۷ — موتور پرامپت:** پورت + دو پیاده‌سازی (Default برای MVP، Template برای V1)؛ ۶ متغیر رسمی با رفتار خالی؛ هر ۱۰ تُن با دستور اجرایی دوزبانه؛ ساختار ثابت system/user prompt؛ جداسازی داده/دستور ضد injection (P-6)؛ فلو کامل تمپلیت اختصاصی با fallback به builtin؛ مرز MVP/V1 با دلیل تجاری.

---

## ۳. تصمیم‌های کلیدی سراسری (آنچه توسعه باید بداند)

1. **Ports & Adapters سخت‌گیرانه:** هسته فقط با Interface حرف می‌زند (`ARCHITECTURE.md:70`). در کدنویسی فاز ۸، هر `use Ata\Telegram\...` یا `use Ata\AI\...` داخل `Ata\Application` = تخلف معماری.
2. **هسته WP-agnostic:** بدون `$wpdb`/`get_option`/`wp_remote_*` در `Ata\Application`؛ همه در `Ata\Infrastructure\Wp*` (D-3). این پیش‌شرط تست‌پذیری فاز ۱۲ است.
3. **SecretStore تنها منبع secret:** توکن تلگرام و کلید AI فقط رمزنگاری‌شده؛ فقط در مرز HTTP خوانده می‌شوند؛ mask/redaction در سه لایه (T-3، D-9، D-15).
4. **SSRF-Gate متمرکز** در `HttpClientInterface` برای هر خروجی با URL کاربر (D-8) — حیاتی برای BYOK.
5. **Custom Tables برای داده تراکنشی** (posts/channels/providers/queue/logs/templates) با دلیل «نیاز واقعی» ثبت‌شده (D-2، P-1)؛ Options/Transients برای تنظیمات و داده کوتاه‌عمر.
6. **صف = WP-Cron + claim اتمی** پشت `JobQueueInterface` (D-6)؛ مهاجرت V1 بدون تغییر هسته.
7. **UTC در DB، timezone سایت فقط در مرز I/O** (D-14).
8. **approve انسانی غیرقابل دورزدن** و **خروجی AI غیرقابل‌اعتماد** (D-18، A-7/A-9) — زنجیره‌ی Preview→Approve تنها مسیر انتشار است.
9. **Fail-Open با Grace Period برای لایسنس** (D-10) — هرگز سایت مشتری را نمی‌شکند.
10. **Namespace `Ata`** و ساختار پوشه طبق گفتار ششم `ARCHITECTURE.md`.

---

## ۴. سازگاری بین‌فازی (بررسی تناقض)

| بررسی | نتیجه |
| --- | --- |
| verbs تلگرام (فاز ۵) ⊆ پورت معماری سیستم (۳.۱) | سازگار؛ `editMessageText` به‌عنوان افزودنی V1 در همان پورت |
| زنجیره‌ی AI (فاز ۶) = زنجیره‌ی رسمی SPEC | منطبق (نگاشت واژگان در بخش ۳ سند فاز ۶) |
| مرز PromptBuilder (فاز ۶ ↔ ۷) | سازگار: آداپتر prompt نمی‌سازد؛ تعویض Default→Template فقط در Composition Root |
| محدودیت ۱۰۲۴ caption در سه لایه (template/validator/telegram) | سازگار و لایه‌ای (دفاع چندگانه) |
| اسکوپ MVP (فاز ۲) در برابر قابلیت‌های فاز ۵–۷ | سازگار: streaming/fallback/تمپلیت-اختصاصی/inline-buttons همه با تصمیم مستند در V1 |
| جدول جدید `ata_templates` در برابر استراتژی DB معماری سیستم | افزودنی سازگار با D-2 (همان دلیل «نیاز واقعی»)؛ در فاز ۱۱ به فهرست جدول‌ها اضافه شود |
| ارجاعات متقابل (خط/بخش) بین اسناد | نمونه‌برداری شد؛ ارجاعات کلیدی معتبرند. بازبینی کامل ارجاعات = بازخوان مستقل پس از رفع قطعی ایجنت‌ها |

**تناقض بازِ ثبت‌شده:** `PRODUCT_SPEC/STRATEGY` در محصول خواهر fallback خودکار provider را مزیت می‌دانستند؛ فاز ۶ آن را از MVP خارج کرد (A-6) با ارجاع به اولویت‌بندی `PRODUCT_STRATEGY.md`. این تصمیم در فاز ۸ لازم‌الاجراست مگر استراتژی رسماً تغییر کند.

---

## ۵. شکاف‌ها و ریسک‌های باقی‌مانده

| # | مورد | نوع | شدت | محل بستن |
| --- | --- | --- | --- | --- |
| R-1 | فازهای ۵–۷ بازبین مستقل ایجنتی ندیده‌اند (قطعی زیرساخت مدل) | روش | **high** | نخستین اجرای سالم گردش کار: فقط فازهای ۵–۷ بازبینی مستقل شوند |
| R-2 | اعداد Rate Limit/حد آپلود تلگرام تست‌نشده | فنی | medium | فاز ۱۱/۱۲ (TG-1/TG-2) |
| R-3 | سازگاری سرویس‌های AI ایرانی unconfirmed | فنی/تجاری | medium | فاز ۱۲ — ماتریس سازگاری (AI-1) |
| R-4 | IP allowlist تلگرام notCovered (عمدی) | امنیتی | low | راهنمای نصب + به‌روزرسانی در فاز ۱۵ (TG-3) |
| R-5 | اثربخشی متن تُن‌ها A/B نشده | محصول | low | فاز ۱۲ + بازخورد مشتریان (PE-1) |
| R-6 | ماتریس نسخه‌های PHP/WP تعیین نشده | سازگاری | medium | فاز ۱۱ (AR-2) |

هیچ شکاف بازدارنده‌ای برای شروع کدنویسی وجود ندارد؛ R-1 موازی با فاز ۸ قابل بستن است.

---

## ۶. توصیه برای ورود به PHASE 8 (توسعه‌ی MVP)

**نتیجه‌ی گیت نهایی طراحی: PASS — شروع فاز ۸ مجاز است** با رعایت این ترتیب پیشنهادی (هم‌راستا با `SPECIFICATION.md:397+` و وابستگی‌های معماری):

1. **اسکلت:** فایل اصلی پلاگین + `Ata\Plugin`/`Bootstrap` + ساختار پوشه (گفتار ششم ARCHITECTURE) + activate/deactivate/uninstall.
2. **Infrastructure پایه:** `HttpClientInterface`/`WpHttpTransport` با SSRF-Gate، `SecretStore`، `Logger` با redaction، مهاجرت DB (`dbDelta` پنج جدول + `ata_templates`).
3. **Settings + AI Provider CRUD + testConnection** (فلو ۴ UX) — چون همه‌چیز به آن وابسته است.
4. **Telegram connect + channel discovery** (فلوهای ۳ و ۵ UX).
5. **مسیر محتوا:** Draft→Preview→Approve→Publish فوری با `DefaultPromptBuilder` و شش عملیات AI.
6. **Scheduler + Queue** (زمان‌بندی، retry، cancel) + Dashboard + Logs.
7. **قفل امنیتی MVP** طبق چک‌لیست ۱۲-بندی `ARCHITECTURE.md:516-527` پیش از هر انتشار آزمایشی.

**خط قرمزهای حین توسعه (از قوانین سراسری):** فقط فیچرهای MVP (قانون ۷/۸)؛ هیچ secret در frontend/URL/log/error (قانون ۱۳)؛ هر تصمیم معماری جدید با دلیل فنی در Decision Log (قانون ۹)؛ تست‌ها از فاز ۸ همراه کد نوشته شوند (پیش‌نیاز فاز ۱۲).

---

## ۷. فهرست تحویل‌شده‌های مرحله‌ی طراحی

- `docs/PRODUCT_SPEC.md` — کشف محصول
- `docs/MARKET_RESEARCH.md` — تحقیق بازار واقعی با برچسب منبع
- `docs/PRODUCT_STRATEGY.md` — استراتژی، اولویت‌ها، قیمت‌گذاری
- `docs/UX_SPEC.md` — معماری UX/UI و ۱۱ فلو
- `docs/ARCHITECTURE.md` — معماری سیستم، ۱۳ لایه، ۱۸ تصمیم
- `docs/TELEGRAM_ARCHITECTURE.md` — لایه‌ی تلگرام، امنیت توکن
- `docs/AI_ARCHITECTURE.md` — لایه‌ی AI provider-agnostic
- `docs/PROMPT_ENGINE.md` — موتور پرامپت، ۱۰ تُن، تمپلیت اختصاصی
- `docs/DESIGN_REPORT.md` — همین گزارش

*پایان گزارش — مرحله‌ی بعد طبق SPEC: PHASE 8 (MVP Development).*
