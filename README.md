# AI Telegram Admin

پلتفرم تجاری مدیریت هوشمند کانال‌ها و محتوای تلگرام با هوش مصنوعی — یک افزونه‌ی وردپرس (WordPress) قابل فروش در مارکت RTL-Theme.

این محصول به‌صورت مرحله‌ای (Phase 0 تا 20) و با یک Workflow چندایجنتی، از تحقیق بازار تا کدنویسی، تست، امنیت، مستندسازی و آماده‌سازی برای فروش توسعه داده می‌شود.

## وضعیت فعلی

- **فاز طراحی (۰ تا ۷):** ✅ کامل — هر ۸ سند طراحی تولید و گیت شدند؛ گزارش نهایی در [`docs/DESIGN_REPORT.md`](docs/DESIGN_REPORT.md).
- **فاز توسعه (۸ به بعد):** آماده‌ی شروع — طبق توصیه‌ی گزارش طراحی، با اسکلت پلاگین و Infrastructure پایه.

## منبع حقیقت

تمام تصمیم‌ها طبق [SPECIFICATION.md](SPECIFICATION.md) (نسخه‌ی اصلی پرامپت Workflow) گرفته می‌شود.

## ساختار خروجی‌ها

اسناد هر فاز در پوشه‌ی [`docs/`](docs/) تولید می‌شوند؛ کد افزونه در پوشه‌های استاندارد وردپرس (`includes/`، `admin/`، `assets/` و ...) قرار می‌گیرد.

| فاز | خروجی |
| --- | --- |
| 0 — کشف محصول | `docs/PRODUCT_SPEC.md` |
| 1 — تحقیق بازار | `docs/MARKET_RESEARCH.md` |
| 2 — استراتژی محصول | `docs/PRODUCT_STRATEGY.md` |
| 3 — معماری UX/UI | `docs/UX_SPEC.md` |
| 4 — معماری سیستم | `docs/ARCHITECTURE.md` |
| 5 — معماری تلگرام | `docs/TELEGRAM_ARCHITECTURE.md` |
| 6 — معماری هوش مصنوعی | `docs/AI_ARCHITECTURE.md` |
| 7 — موتور پرامپت | `docs/PROMPT_ENGINE.md` |
| گزارش نهایی طراحی | `docs/DESIGN_REPORT.md` |

## نام‌گذاری

Namespace موقت کد تا انتخاب نام نهایی برند: `ATA` (AI Telegram Admin).