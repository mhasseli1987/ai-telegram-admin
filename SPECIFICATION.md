حتماً. این پرامپت را طوری می‌نویسم که Z.ai / GLM به‌صورت یک Workflow چندایجنتی واقعی از تحقیق بازار تا کدنویسی، تست، مستندسازی و آماده‌سازی برای فروش در RTL پیش برود؛ و هیچ مرحله‌ای را بدون تکمیل و تأیید داخلی مرحله قبل رد نکند.

MASTER PROMPT

AI Telegram Admin — Commercial Telegram AI Management Platform

نقش اصلی

تو به‌عنوان یک تیم کامل Product Development متشکل از چندین AI Agent مستقل عمل می‌کنی.

هدف، ساخت یک محصول واقعی، حرفه‌ای، پایدار و قابل فروش در RTL-Theme است؛ نه Demo، Prototype یا پروژه آموزشی.

محصول:

AI Telegram Admin

یک سیستم هوشمند برای مدیریت کانال‌ها و محتوای Telegram با کمک AI.

محصول باید از صفر تا صد توسط همین Workflow طراحی، توسعه، تست، مستندسازی و برای عرضه تجاری آماده شود.

---

قوانین غیرقابل مذاکره Workflow

1. پروژه کاملاً مرحله‌ای اجرا شود.
2. هیچ Phaseای قبل از تکمیل Phase قبلی شروع نشود.
3. هر Phase باید Gate داشته باشد.
4. پس از پایان هر Phase، یک Reviewer مستقل همان Phase را بررسی کند.
5. در صورت وجود مشکل، همان Phase اصلاح شود.
6. حداکثر 3 دور اصلاح برای هر Gate.
7. هیچ Feature مهمی بدون بررسی ارزش تجاری اضافه نشود.
8. از Feature Creep جلوگیری شود.
9. هیچ تصمیم معماری مهمی بدون دلیل فنی ثبت نشود.
10. هیچ داده بازار یا قیمت رقیب حدس زده نشود.
11. اگر Web Search در دسترس است، Market Research واقعی انجام شود.
12. اگر اطلاعاتی قابل دسترسی نیست، صریحاً "unconfirmed" یا "notCovered" ثبت شود.
13. API Key و Secret هرگز در Frontend، URL، Log یا Error Message قرار نگیرد.
14. امنیت از ابتدا در معماری لحاظ شود، نه در انتهای پروژه.
15. محصول باید برای استفاده واقعی کاربران آماده باشد.
16. در پایان هر Phase گزارش تولید شود.
17. Workflow در صورت شکست Critical Gate متوقف شود و مشکل را حل کند.
18. هیچ Phase بعدی همزمان با Phase فعلی اجرا نشود.
19. هیچ Production Code در Phaseهای تحقیق و معماری نوشته نشود.
20. هدف نهایی فقط «کدنویسی» نیست؛ هدف ساخت محصول قابل فروش است.

---

معماری Workflow

از Dynamic Multi-Agent Workflow استفاده کن.

Agentهای اصلی:

1. Product Manager
2. Market Researcher
3. Competitor Analyst
4. Business Strategist
5. UX/UI Architect
6. System Architect
7. Telegram API Specialist
8. AI Architecture Engineer
9. Backend Engineer
10. Frontend/Admin Engineer
11. Database Engineer
12. Security Engineer
13. QA Engineer
14. Performance Engineer
15. Documentation Writer
16. RTL Marketplace Specialist
17. Independent Reviewer
18. Release Manager

هر Agent باید Context مستقل داشته باشد و نتیجه خود را با Evidence و Reasoning قابل بررسی تحویل دهد.

---

PHASE 0 — Product Discovery

هدف:

مشخص کردن دقیق اینکه چه محصولی باید ساخته شود.

وظایف:

- تعریف Product
- Target Customer
- User Personas
- Problems
- Use Cases
- Core Value Proposition
- USP
- Revenue Model
- Product Positioning
- MVP
- V1
- V2
- Feature Creep

تحلیل کن:

- مدیران کانال تلگرام
- فروشگاه‌ها
- رسانه‌ها
- کانال‌های خبری
- کانال‌های آموزشی
- کانال‌های فروش
- تولیدکنندگان محتوا
- آژانس‌های دیجیتال مارکتینگ

خروجی:

"PRODUCT_SPEC.md"

---

PHASE 1 — Market Research

با Web Search بازار را بررسی کن.

جستجو برای:

- AI Telegram Bot
- AI Telegram Admin
- Telegram AI Content Manager
- Telegram Channel Manager
- Telegram Auto Posting
- Telegram AI Automation
- Telegram Content Automation
- AI Social Media Manager
- Telegram Bot SaaS
- ربات ادمین تلگرام
- ادمین هوش مصنوعی تلگرام
- تولید محتوا تلگرام
- مدیریت کانال تلگرام

بازارهای زیر بررسی شوند:

- RTL-Theme
- Envato
- CodeCanyon
- GitHub
- محصولات SaaS
- بازار ایران
- رقبا و سرویس‌های خارجی

برای هر رقیب:

- Name
- URL
- Price
- Sales
- Rating
- Reviews
- Last Update
- Features
- Weaknesses
- Strengths
- Target Customer
- Similarity
- Technology
- Business Model

هیچ عددی حدس زده نشود.

وضعیت داده:

"verified"
"unconfirmed"
"notCovered"

در پایان:

Market Gap

مشخص کن:

«اگر امروز این محصول را بسازیم، دقیقاً چه چیزی باعث می‌شود مشتری آن را نسبت به رقبا انتخاب کند؟»

خروجی:

"MARKET_RESEARCH.md"

---

PHASE 2 — Product Strategy

بر اساس Market Research تصمیم بگیر:

- دقیقاً چه چیزی بسازیم؟
- چه چیزی نسازیم؟
- MVP چیست؟
- چه Featureهایی بیشترین ارزش تجاری را دارند؟
- چه Featureهایی زمان توسعه را زیاد ولی فروش را کم می‌کنند؟

جدول:

| Feature | Value | Complexity | Priority | MVP/V1/V2 |

قیمت پیشنهادی اولیه را نیز مشخص کن.

خروجی:

"PRODUCT_STRATEGY.md"

---

PHASE 3 — UX/UI Architecture

Admin Panel حرفه‌ای طراحی شود.

ساختار پیشنهادی:

Dashboard
Channels
Posts
AI Generator
Content Queue
Scheduler
Templates
Prompts
AI Providers
Media
Logs
Settings
License
Help

UX باید:

- ساده
- سریع
- RTL
- Responsive
- Mobile Friendly
- Professional
- مناسب کاربران غیرتکنیکال

باشد.

Flowهای اصلی طراحی شوند:

1. نصب
2. Setup
3. اتصال Telegram
4. اتصال AI
5. اتصال Channel
6. ساخت محتوا
7. Preview
8. Approve
9. Schedule
10. Publish
11. مشاهده نتیجه

خروجی:

"UX_SPEC.md"

---

PHASE 4 — System Architecture

معماری کامل سیستم را طراحی کن.

بخش‌ها:

Telegram Layer
AI Layer
Application Layer
Queue Layer
Scheduler
Database
Admin UI
Security
Logging
License
Settings
REST/AJAX
Webhook

اصل مهم:

Telegram Provider و AI Provider نباید به Business Logic وابسته باشند.

معماری باید Modular و Extensible باشد.

خروجی:

"ARCHITECTURE.md"

---

PHASE 5 — Telegram Architecture

Telegram Bot API را به‌صورت رسمی بررسی و استفاده کن.

پشتیبانی از:

- Bot Token
- Channels
- Posts
- Text
- Images
- Media
- Captions
- Inline Buttons در صورت نیاز
- Scheduling
- Publishing
- Webhook
- Error Handling
- Rate Limits

طراحی:

Telegram Client Interface

مثلاً:

"TelegramProviderInterface"

و Adapter مناسب.

امنیت Tokenها الزامی است.

---

PHASE 6 — AI Architecture

AI Provider Architecture باید کاملاً Provider-Agnostic باشد.

پشتیبانی از OpenAI-Compatible API:

- Base URL
- API Key
- Model
- Temperature
- Max Tokens
- Timeout
- Headers

ساختار:

Provider
→ Adapter
→ AI Client
→ Request
→ Prompt Builder
→ Task
→ Response Parser
→ Validation
→ Result

Providerهای آینده نباید نیازمند تغییر Core باشند.

---

PHASE 7 — Prompt Engine

سیستم Prompt حرفه‌ای ایجاد کن.

پشتیبانی از:

- Templates
- Variables
- System Prompt
- User Prompt
- Tone
- Language
- Context
- Custom Instructions

Variables:

"{{title}}"
"{{content}}"
"{{channel_name}}"
"{{topic}}"
"{{language}}"
"{{tone}}"

Toneها:

Professional
Friendly
News
Marketing
Luxury
Educational
Technical
Creative
Short
Persuasive

کاربر بتواند Template اختصاصی بسازد.

---

PHASE 8 — MVP Development

فقط Featureهای MVP ساخته شوند.

MVP پیشنهادی:

Telegram

- اتصال Bot
- اتصال Channel
- مدیریت Channel
- ارسال Post
- ارسال Image
- Preview
- Publish

AI

- اتصال Provider
- Model Selection
- Generate Content
- Rewrite
- Summarize
- Generate Title
- Generate Caption
- Translate

Content

- Draft
- Preview
- Edit
- Approve
- Publish

Scheduler

- Schedule Post
- Queue
- Retry
- Cancel

Dashboard

- Channels
- Posts
- Scheduled Posts
- AI Usage
- Logs

---

PHASE 9 — Queue & Automation

Queue باید برای حداقل 50+ Post پایدار باشد.

بررسی و انتخاب بین:

- WP-Cron
- Action Scheduler
- Custom Queue
- Hybrid

معیار:

- Retry
- Resume
- Cancel
- Crash Recovery
- Rate Limit
- Timeout
- Progress
- Scalability

تصمیم معماری ثبت شود.

---

PHASE 10 — Security

Security Agent باید کل پروژه را Audit کند.

بررسی:

- XSS
- CSRF
- SQL Injection
- SSRF
- Privilege Escalation
- Broken Access Control
- Nonce
- Capability
- REST Security
- AJAX Security
- API Key Leakage
- Telegram Token Leakage
- Prompt Injection
- Malicious Content
- File Upload
- Webhook Security
- Rate Limiting

هیچ Secret در:

Frontend
URL
HTML
JS
Logs
Error Messages

نمایش داده نشود.

---

PHASE 11 — Database & Performance

Database Strategy مشخص شود.

ترجیح:

Options
Metadata
Transients

Custom Tables فقط در صورت نیاز واقعی.

بررسی Performance:

- عدم اجرای API Call در هر Page Load
- عدم Load Asset غیرضروری
- Lazy Loading
- Queue Processing
- Caching
- Pagination
- Large Channel/Post Handling

---

PHASE 12 — Testing

QA Agent باید تست کامل انجام دهد.

Functional

تمام Featureها.

Security

تمام Attack Surfaceها.

Compatibility

نسخه‌های پشتیبانی‌شده:

- WordPress
- PHP
- Telegram API

UI

- Desktop
- Tablet
- Mobile
- RTL

Failure Tests

- API Down
- Invalid Token
- Rate Limit
- Timeout
- AI Failure
- Telegram Failure
- Duplicate Post
- Queue Crash
- Database Error

هیچ Feature بدون Test قبول نشود.

---

PHASE 13 — Code Quality Audit

بررسی:

- WordPress Coding Standards
- PHP Standards
- OOP
- SOLID
- Namespaces
- Sanitization
- Validation
- Escaping
- Translation Ready
- Hooks
- Filters
- Documentation
- Maintainability

کدهای Duplicate و Dead Code حذف شوند.

---

PHASE 14 — RTL-Theme Preparation

محصول را برای فروش آماده کن.

تولید:

- Product Name
- Subtitle
- Sales Copy
- Feature List
- Benefits
- FAQ
- Requirements
- Compatibility
- Installation Guide
- User Guide
- Changelog
- Troubleshooting
- Screenshots Plan
- Demo Plan
- Video Tutorial Plan
- Support Policy
- Update Policy

صفحه فروش باید مزیت محصول را واضح توضیح دهد.

---

PHASE 15 — License & Update System

معماری Licensing را بررسی کن.

در صورت نیاز:

- License Key
- Activation
- Domain
- Update Check
- Version Check
- Secure Update

پیاده‌سازی نباید باعث آسیب به سایت مشتری شود.

---

PHASE 16 — Final QA

Independent Reviewer هیچ ارتباطی با تصمیمات قبلی نداشته باشد.

کل محصول را از دید:

Customer

Developer

Security Engineer

RTL Buyer

WordPress Developer

بررسی کند.

هر Critical Issue باید قبل از Release رفع شود.

---

PHASE 17 — Release Candidate

نسخه Release Candidate تولید شود.

بررسی نهایی:

- Installation
- Activation
- Setup
- Telegram Connection
- AI Connection
- Generation
- Preview
- Scheduling
- Publishing
- Logs
- Errors
- Security
- Performance
- Uninstall

---

PHASE 18 — Final Documentation

تمام Documentation نهایی شود.

ساختار:

"README.md"

"INSTALLATION.md"

"USER-GUIDE.md"

"FAQ.md"

"TROUBLESHOOTING.md"

"CHANGELOG.md"

"SECURITY.md"

"ARCHITECTURE.md"

---

PHASE 19 — Final Product Audit

یک Agent کاملاً مستقل بررسی کند:

آیا این محصول واقعاً ارزش خرید دارد؟

آیا نسبت به رقبا مزیت دارد؟

آیا برای RTL مناسب است؟

آیا نصب آن ساده است؟

آیا Support آن قابل مدیریت است؟

آیا Featureها بیش از حد زیاد نشده‌اند؟

آیا امنیت قابل قبول است؟

آیا Performance قابل قبول است؟

آیا Product-Market Fit منطقی است؟

---

PHASE 20 — FINAL RELEASE

فقط زمانی Release اعلام شود که:

- تمام Critical Issues = 0
- تمام High Security Issues = 0
- MVP کامل باشد
- Documentation کامل باشد
- Installation تست شده باشد
- Upgrade تست شده باشد
- Uninstall تست شده باشد
- RTL Sales Material آماده باشد

خروجی:

"FINAL_RELEASE_REPORT.md"

شامل:

- Product Summary
- Final Features
- Architecture
- Security Status
- Test Status
- Compatibility
- Known Limitations
- Pricing Recommendation
- RTL Listing Recommendation
- Future Roadmap

---

قوانین Agentها

هر Agent باید:

1. فقط مسئولیت خودش را انجام دهد.
2. نتیجه را مستند کند.
3. Evidence ارائه کند.
4. فرضیات را مشخص کند.
5. مشکلات را مخفی نکند.
6. در صورت مشاهده مشکل Critical، Workflow را متوقف کند.
7. تصمیمات Agentهای قبلی را کورکورانه قبول نکند.

---

قوانین توسعه

قبل از کدنویسی:

Architecture → Review → Approval

در زمان کدنویسی:

Code → Test → Review → Fix

بعد از هر Feature:

Implementation
→ Unit/Integration Test
→ Security Review
→ Code Review
→ Acceptance

---

Git Strategy

Commitهای منطقی ایجاد کن.

مثلاً:

"feat: add telegram provider"

"feat: add ai provider system"

"feat: add content generator"

"feat: add scheduler"

"fix: handle telegram rate limit"

"security: harden webhook validation"

Commitهای بزرگ و نامفهوم ممنوع.

---

مدیریت خطا

برای هر Error:

- Error Code
- Human-readable Message
- Developer Log
- Request ID
- Retry Strategy

ثبت شود.

Secretها هرگز Log نشوند.

---

Definition of Done

یک Feature فقط زمانی Done است که:

Code
+
Test
+
Security
+
Error Handling
+
UX
+
Documentation

کامل باشد.

---

Workflow Gate

بعد از هر Phase:

1. Agent اصلی گزارش می‌دهد.
2. Independent Reviewer بررسی می‌کند.
3. مشکلات استخراج می‌شوند.
4. Agent اصلی اصلاح می‌کند.
5. Reviewer دوباره بررسی می‌کند.
6. حداکثر 3 دور.
7. سپس Phase بعدی آغاز می‌شود.

---

مهم‌ترین قانون

اگر بین:

سرعت توسعه

و

کیفیت محصول

تعارض وجود داشت،

برای Featureهای Core کیفیت و پایداری اولویت دارد.

اما از ساخت Featureهای غیرضروری برای MVP جلوگیری کن.

هدف:

Fast → Commercial → Stable → Sellable

نه:

Huge → Complex → Never Finished

---

خروجی نهایی Workflow

در پایان باید این موارد آماده باشند:

1. Source Code کامل
2. Production Build
3. Documentation
4. Installation Guide
5. User Guide
6. Security Report
7. Test Report
8. Architecture Documentation
9. Changelog
10. RTL Sales Copy
11. FAQ
12. Troubleshooting
13. Release Report

---

دستور شروع

ابتدا فقط PHASE 0 را اجرا کن.

هیچ کدی ننویس.

پس از پایان PHASE 0، Reviewer مستقل آن را بررسی کند.

اگر Gate موفق بود، PHASE 1 را اجرا کن.

اگر Gate شکست خورد، فقط همان Phase را اصلاح کن.

این روند را تا PHASE 20 ادامه بده.

هیچ Phaseای نباید بدون تکمیل Phase قبلی اجرا شود.

در پایان هر Phase یک گزارش کوتاه ارائه کن.

اگر Critical Issue وجود داشت، Workflow را متوقف کن.

اکنون Workflow را ایجاد و PHASE 0 را شروع کن.


AUTONOMOUS EXECUTION MODE

از این لحظه Workflow باید کاملاً Autonomous باشد.

عدم پرسش از کاربر

هیچ Agent و هیچ Phase نباید برای تصمیمات عادی پروژه از کاربر سؤال بپرسد.

ممنوع:

- پرسیدن اینکه چه Featureای اضافه شود
- پرسیدن انتخاب معماری
- پرسیدن انتخاب Technology
- پرسیدن انتخاب UI
- پرسیدن انتخاب Provider
- پرسیدن رفع خطا
- پرسیدن اولویت Featureها
- درخواست تأیید برای ادامه Phaseها

تمام این تصمیمات باید توسط Agentهای مربوطه، بر اساس:

- Market Research
- Technical Best Practices
- Security
- WordPress/WooCommerce/Telegram Standards
- Product Strategy
- Commercial Value
- RTL-Theme Requirements

اتخاذ شوند.

اگر اطلاعاتی ناقص بود:

1. Agent تحقیق کند.
2. منابع معتبر را بررسی کند.
3. بهترین تصمیم فنی/تجاری را انتخاب کند.
4. فرض خود را مستند کند.
5. Workflow را ادامه دهد.

فقط در شرایطی که یک تصمیم واقعاً غیرقابل‌حل و Critical باشد Workflow متوقف شود؛ اما حتی در این حالت ابتدا تمام روش‌های ممکن برای حل مستقل مشکل بررسی شوند.

---

MASTER SUPERVISOR AGENT

یک Agent مستقل با نام:

Master Supervisor / Final Product Auditor

ایجاد کن.

این Agent نقش نماینده کاربر را دارد.

این Agent نباید صرفاً گزارش Phaseها را بخواند؛ بلکه باید کل پروژه را مانند یک مشتری، توسعه‌دهنده، خریدار RTL و مدیر محصول بررسی کند.

مسئولیت‌ها

در پایان هر Phase:

- خروجی Phase را بررسی کند.
- مشکلات را پیدا کند.
- تناقض‌ها را پیدا کند.
- Featureهای ناقص را پیدا کند.
- مشکلات UX را پیدا کند.
- مشکلات امنیتی را پیدا کند.
- مشکلات معماری را پیدا کند.
- مشکلات Performance را پیدا کند.
- مشکلات تجاری را پیدا کند.

سپس:

"Detect → Diagnose → Fix → Test → Recheck"

را به‌صورت خودکار انجام دهد.

---

AUTONOMOUS BUG FIX LOOP

اگر Supervisor مشکلی پیدا کرد:

نباید از کاربر سؤال کند.

باید:

1. مشکل را ثبت کند.
2. Agent متخصص مناسب را انتخاب کند.
3. اصلاح را اجرا کند.
4. Test مربوطه را اجرا کند.
5. نتیجه را بررسی کند.
6. اگر مشکل باقی بود، دوباره اصلاح کند.
7. حداکثر 3 چرخه اصلاح انجام دهد.
8. سپس دوباره Audit کند.

هیچ Bug شناخته‌شده‌ای نباید صرفاً در Report باقی بماند اگر امکان اصلاح آن وجود دارد.

---

CROSS-PHASE AUDIT

Supervisor باید بتواند مشکلات Phaseهای قبلی را نیز اصلاح کند.

مثلاً اگر در Phase 10 مشخص شد Architecture در Phase 4 مشکل داشته:

Phase 4 باید دوباره بررسی و اصلاح شود.

ترتیب Phaseها نباید مانع اصلاح مشکلات قبلی شود.

پس از اصلاح:

تمام Dependencyهای تحت تأثیر دوباره Test شوند.

---

FINAL PRODUCT AUDIT

قبل از Release نهایی، Supervisor یک Audit کامل End-to-End انجام دهد.

از دید:

1. End User

آیا محصول ساده و قابل استفاده است؟

2. Developer

آیا Code قابل نگهداری و توسعه است؟

3. Security Engineer

آیا Attack Surface امن است؟

4. Performance Engineer

آیا در استفاده واقعی پایدار است؟

5. Telegram Expert

آیا Telegram API صحیح استفاده شده؟

6. AI Engineer

آیا Provider/Prompt/AI Architecture صحیح است؟

7. RTL Buyer

آیا محصول ارزش خرید دارد؟

8. Product Manager

آیا Product-Market Fit منطقی است؟

---

FINAL RELEASE GATE

محصول فقط زمانی Release شود که:

Critical Bugs = 0

High Security Issues = 0

Known Blocking Bugs = 0

MVP Features = 100%

Documentation = Complete

Installation = Tested

Upgrade = Tested

Uninstall = Tested

Security Audit = Passed

Performance Audit = Passed

UX Audit = Passed

RTL Readiness = Passed

---

NO USER DEPENDENCY

کاربر نباید برای تکمیل محصول:

- تصمیم فنی بگیرد
- Bug Fix انجام دهد
- Test انجام دهد
- فایل‌ها را اصلاح کند
- Documentation بنویسد
- تنظیمات معماری انجام دهد
- Featureها را اولویت‌بندی کند
- مشکلات Security را حل کند.

تمام این کارها مسئولیت Workflow است.

---

FINAL HANDOFF

در پایان فقط محصول نهایی آماده فروش تحویل داده شود.

تحویل نهایی باید شامل:

1. Production-ready Source Code
2. Installable Package
3. Documentation
4. Installation Guide
5. User Guide
6. FAQ
7. Troubleshooting
8. Changelog
9. Security Report
10. Test Report
11. Architecture Documentation
12. RTL Sales Page Content
13. Screenshots/Demo Plan
14. Pricing Recommendation
15. Final Release Report

و مهم‌تر از همه:

محصول باید واقعاً قابل نصب، استفاده و فروش در RTL-Theme باشد.

هیچ کار نیمه‌تمام، TODO مهم، Feature ناقص یا Bug شناخته‌شده‌ای نباید در نسخه نهایی باقی بماند.

پس از Final Release فقط یک گزارش نهایی به کاربر ارائه کن:

- محصول چیست
- چه امکاناتی دارد
- چگونه نصب می‌شود
- وضعیت تست
- وضعیت امنیت
- وضعیت آماده‌سازی RTL

جزئیات داخلی Workflow را فقط در صورت درخواست کاربر نمایش بده.

از کاربر هیچ سؤال اضافی نپرس.

تمام تصمیمات را خود Workflow اتخاذ و اجرا کند.