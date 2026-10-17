# ادامهٔ کار فاز ۲ — وضعیت و نقشهٔ راه

تاریخ: ۱۴۰۵/۰۷/۲۵ (2026-10-17)
برنچ: `refactor-items-and-company-wise`
سند دسته‌بندی: [phase-2-table-classification-fa.md](phase-2-table-classification-fa.md)
سند مادر: [multi-company-plan-fa.md](multi-company-plan-fa.md)

این سند برای ادامه دادن کار روی ماشین دیگر نوشته شده. هدفش این است که بدون
خواندن تاریخچهٔ گفت‌وگو بتوان از همین نقطه ادامه داد.

---

## ۱. راه‌اندازی ماشین جدید

```bash
git clone <repo> && cd nextbook
git checkout refactor-items-and-company-wise
composer install
npm install
cp .env.example .env     # و تنظیم اتصال پستگرس
php artisan key:generate
```

### ⚠️ دو نکتهٔ محیطی که وقت می‌گیرند

**۱. رمز دیتابیس تست قدیمی است.**
فایل `.env.testing` رمز `mark` دارد ولی سرور توسعه رمز دیگری می‌خواهد. تا وقتی
این را درست نکنید دستور تست هیچ کاری نمی‌کند:

```bash
# در .env.testing، مقدار DB_PASSWORD را با مقدار .env یکی کنید
php artisan test            # بدون این اصلاح کار نمی‌کند
```

**۲. دیتابیس تست باید وجود داشته باشد.**

```sql
CREATE DATABASE nextbook_test;
```

---

## ۲. وضعیت فعلی در یک نگاه

| گام | کار | وضعیت |
|---|---|---|
| ۱ | جدول کمپنی‌ها به ابتدای ترتیب + soft delete + قیدهای معوق | ✅ commit شد |
| ۲ | کالم کمپنی روی جدول شعبات + composite FK + حذف زیردامنه | ✅ commit شد |
| ۳ | میان‌افزار کمپنی فعال + trait دامنه + بستن حفرهٔ سویچ شعبه | ✅ commit شد |
| ۴ | ۳۰ جدول مستر دیتا به دامنهٔ کمپنی | 🟡 **نیمه‌تمام** |
| ۵ | تجزیهٔ سرویس provisioning | ✅ داخل گام ۴ |
| ۶ | دستهٔ ب، ج، د | ⬜ شروع نشده |
| ۷ | بازنویسی ۵۷ ایندکس یکتا به شکل partial | ⬜ |
| ۸ | جدول واسط مشتری‑شعبه | ⬜ |
| ۹ | فعال‌سازی RLS | ⬜ |

### چه چیزی سبز است

```bash
php tools/phase-2/mkdb.php
DB_DATABASE=nextbook_phase2_check php artisan migrate --force   # ✅ همهٔ ۱۸۳ تا
DB_DATABASE=nextbook_phase2_check php artisan db:seed --force   # ✅ کامل
```

### چه چیزی قرمز است

```
php artisan test --testsuite=Feature
→ حدود ۱۵۰ پاس، حدود ۷۰۰ ناموفق
```

**این انتظار می‌رود و دلیلش در بخش ۳ توضیح داده شده.** کار نیمه‌تمام است، نه
خراب.

### پنج تست که از قبل خراب بوده‌اند

این‌ها ربطی به این کار ندارند — روی commit `8fe9e423` یعنی قبل از شروع هم
شکست می‌خورند. وقت صرفشان نکنید:

```
DiscountRuleTest > a deleted rule cannot be restored
SidebarMenuPreferencesTest > hiding menus is honoured exactly
OpenItemsAfterReturnReversalTest > documents the unapplied credit note gap
CashPositionByCurrencyReportTest > it returns one line per currency
ReportsFeatureTest > reports export streams inventory valuation xlsx
```

---

## ۳. 🔴 مهم‌ترین چیزی که باید بدانید

### چرا تست‌ها قرمزند

جدول‌های مستر (مشتری، جنس، اکونت، اسعار...) حالا `company_id` دارند، ولی
اسناد (فروش، خرید، تراکنش) هنوز فقط `branch_id` دارند.

کوئری‌هایی که این **دو سطح را به هم وصل می‌کنند** دیگر جای اتصال ندارند:

```php
// app/Models/Ledger/Ledger.php
->whereColumn('transactions.branch_id', 'ledgers.branch_id')
//                                       ^^^^^^^^^^^^^^^^^ دیگر وجود ندارد
```

مشتری مال کمپنی است، تراکنش مال شعبه. تا وقتی تراکنش `company_id` نگیرد، این
اتصال قابل نوشتن نیست.

### نتیجهٔ عملی برای ترتیب کار

**تلاش نکنید تست‌ها را در این حالت سبز کنید.** راه درست این است که اول
`company_id` به بقیهٔ جدول‌ها اضافه شود (گام ۶)، و بعد همهٔ کوئری‌ها با هم
درست شوند.

درس گرفته‌شده: ترتیب درست **افزایشی** است —

```
۱. company_id به همهٔ جدول‌ها اضافه شود، branch_id سر جایش بماند   ← هیچ چیز نمی‌شکند
۲. مدل‌ها و کوئری‌ها دامنه‌به‌دامنه منتقل شوند                      ← هر دامنه جدا سبز می‌شود
۳. branch_id از ۵۷ جدولی که لازم ندارند حذف شود                   ← پاک‌سازی نهایی
```

گام ۴ به‌جای این، یکباره حذف و اضافه کرد و حالت نیمه‌شکسته ساخت.

### سه نقطهٔ خراب که باید اول درست شوند

همه در `app/Models/Ledger/Ledger.php` و با کامنت `BROKEN` علامت خورده‌اند:

| خط تقریبی | مشکل | اصلاح در گام ۶ |
|---|---|---|
| ۷۹ | **خطا نمی‌دهد، فقط صفر ردیف برمی‌گرداند** — کالم شعبه با شناسهٔ کمپنی مقایسه شده | `transactions.company_id` |
| ۲۶۳ | ارجاع به کالم حذف‌شده در SQL خام | `t.company_id = ledgers.company_id` |
| ۲۸۳ | همان، در query builder | `('transactions.company_id', 'ledgers.company_id')` |

خط ۷۹ خطرناک‌ترین است چون صورتحساب مشتری را خالی نشان می‌دهد بدون هیچ خطایی.

---

## ۴. قدم بعدی — گام ۶

### ۶-الف: کالم کمپنی روی دستهٔ ب (۴۳ جدول)

برای هر جدول دستهٔ ب (فهرست کامل در سند دسته‌بندی، بخش ۵):

1. `company_id` اضافه شود، `branch_id` **بماند**
2. قید `UNIQUE (company_id, id)` اضافه شود
3. قید ترکیبی به شعبه:

```php
$table->foreign(['company_id', 'branch_id'])
    ->references(['company_id', 'id'])
    ->on('branches');
```

4. برای هر ارجاع به جدول دستهٔ الف، قید ترکیبی:

```php
$table->foreign(['company_id', 'customer_id'])
    ->references(['company_id', 'id'])->on('ledgers');
$table->foreign(['company_id', 'item_id'])
    ->references(['company_id', 'id'])->on('items');
```

اسکریپت `rename_migrations.php` را می‌شود بازاستفاده کرد — فقط فهرست
`TABLES` عوض شود و به‌جای تعویض کالم، اضافه کردن.

### ۶-ب: دستهٔ ج و د

`company_id` اضافه، `branch_id` حذف (شعبه از والد یا از گدام می‌آید).

### ۶-ج: مدل‌ها

مدل‌های دستهٔ ب هر دو trait را می‌گیرند:

```php
use CompanySpecific, BranchSpecific, HasCompany, HasBranch;
```

### ۶-د: کوئری‌های باقی‌مانده

```bash
php tools/phase-2/scan.php
```

امروز ۷۹ خط در ۵۹ فایل برمی‌گرداند. بیشترشان در `app/Services/ReportService.php`
هستند که نام‌های مستعار SQL دارد (`a.branch_id`، `iv.branch_id`) و **باید دستی**
بررسی شود — تشخیص اینکه هر مستعار به کدام جدول اشاره می‌کند خودکارپذیر نیست.

---

## ۵. ابزارها

همه در `tools/phase-2/` و از ریشهٔ پروژه اجرا می‌شوند. مسیر یا رمز ثابت ندارند؛
اتصال دیتابیس را از `.env` می‌خوانند.

| اسکریپت | کار |
|---|---|
| `scan.php` | خط‌هایی که هنوز یک مدل دستهٔ الف را شعبه‌محور می‌کنند |
| `anchored_rewrite.php` | تبدیل خودکار، با تشخیص مدل هر statement |
| `rename_migrations.php` | تعویض کالم دامنه در بلاک‌های migration |
| `rename_models.php` | تعویض trait و کالم در مدل‌ها |
| `rename_factories.php` | تعویض در factoryها |
| `rename_seeders.php` | تعویض در seederها |
| `map.php` | نگاشت جدول ← migration ← مدل ← trait |
| `classify.php` | فهرست جدول‌ها با کلیدهای خارجی |
| `uniques.php` | فهرست ایندکس‌های یکتای حاوی کالم دامنه |
| `mkdb.php` | ساخت/حذف دیتابیس یکبارمصرف برای راستی‌آزمایی |

### ⚠️ درس مهم دربارهٔ این اسکریپت‌ها

`anchored_rewrite.php` مدل هر statement را از چند خط بالاتر حدس می‌زند. در ۲۳۹
خط درست عمل کرد و در ۲۷ مورد خودش اعلام کرد که مطمئن نیست.

**ولی یک اشتباه بی‌صدا تولید کرد** (همان خط ۷۹). بعد از هر اجرا حتماً:

```bash
git diff | grep -nE "branch_id.*company_id|company_id.*branch_id"
```

هر خطی که **هر دو** کالم را دارد مشکوک است.

---

## ۶. دستورهای راستی‌آزمایی

```bash
# ۱. schema از صفر ساخته می‌شود؟
php tools/phase-2/mkdb.php
DB_DATABASE=nextbook_phase2_check php artisan migrate --force
DB_DATABASE=nextbook_phase2_check php artisan db:seed --force
php tools/phase-2/mkdb.php nextbook_phase2_check drop

# ۲. مرز کمپنی در دیتابیس اعمال می‌شود؟
php artisan test tests/Feature/Tenancy/CompanyScopeTest.php

# ۳. چیزی نشکسته؟
php artisan test --testsuite=Feature
```

تست مرحلهٔ ۲ همیشه باید سبز بماند — چهار سناریوی پذیرش امنیتی را پوشش می‌دهد و
هیچ ربطی به کار نیمه‌تمام گام ۴ ندارد.

---

## ۷. تصمیمات قطعی‌شده

برای اینکه دوباره بحث نشود:

| تصمیم | انتخاب |
|---|---|
| مشتری در چند شعبه | یک رکورد، یک بیلانس + جدول واسط `ledger_branches` برای تعیین شعبه‌های مجاز |
| نمبرگذاری سند | کمپنی‌محور — یک سری به ازای کمپنی و نوع سند |
| نرخ ارز | کمپنی‌محور با شعبهٔ nullable برای override |
| کالم کمپنی | روی همهٔ جدول‌ها |
| کالم شعبه | فقط جایی که واقعیت سطر است، نه جایی که قابل استخراج است |
| ایندکس مستقل روی کالم کمپنی | ساخته نشود — فقط ستون اول ایندکس ترکیبی |
| استراتژی migration | ویرایش migrationهای موجود در جای خود (دیتای زنده نداریم) |
| جدول `transaction_line_currencies` | حذف شد (مرده بود) |

---

## ۸. کارهای باز که هنوز برنامه‌ریزی نشده‌اند

- `ReportService.php` — حدود ۱۵ کوئری با نام مستعار SQL که دستی می‌خواهد
- سه trait موازی شعبه (`BranchSpecific`، `HasBranch`، `BelongsToBranch`) که
  هم‌پوشانی دارند و بهتر است یکی شوند
- `BranchSpecific` در console و job از scope بیرون می‌زند (بدهی بخش ۱۱ سند مادر)
- الگوی `MAX+1` در `HasSequentialNumber` و نبود قید یکتا روی نمبر فروش، خرید و
  تراکنش (بخش ۱۳ سند دسته‌بندی)
