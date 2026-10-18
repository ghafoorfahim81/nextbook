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
| ۱ | جدول کمپنی‌ها به ابتدای ترتیب + soft delete + قیدهای معوق | ✅ |
| ۲ | کالم کمپنی روی جدول شعبات + composite FK + حذف زیردامنه | ✅ |
| ۳ | میان‌افزار کمپنی فعال + trait دامنه + بستن حفرهٔ سویچ شعبه | ✅ |
| ۴ | ۳۰ جدول مستر دیتا به دامنهٔ کمپنی | ✅ |
| ۵ | تجزیهٔ سرویس provisioning به کمپنی/شعبه | ✅ |
| ۶ | کالم کمپنی روی ۷۲ جدول باقی‌مانده + انتقال کوئری‌ها | 🟡 **در جریان** |
| ۷ | حذف `branch_id` از ۵۷ جدولی که لازمش ندارند | ⬜ |
| ۸ | بازنویسی ۵۷ ایندکس یکتا به شکل partial | ⬜ |
| ۹ | جدول واسط مشتری‑شعبه | ⬜ |
| ۱۰ | فعال‌سازی RLS | ⬜ |

### چه چیزی سبز است

```bash
php tools/phase-2/mkdb.php
DB_DATABASE=nextbook_phase2_check php artisan migrate --force   # ✅ همهٔ ۱۸۳ تا
DB_DATABASE=nextbook_phase2_check php artisan db:seed --force   # ✅ کامل

php artisan test tests/Feature/Tenancy                          # ✅ ۴ سناریوی امنیتی
```

### چه چیزی قرمز است

```
php artisan test --testsuite=Feature
→ حدود ۶۶۰ پاس از ۸۴۸
```

روند تا اینجا: **۰ ← ۱۶۲ ← ۵۰۶ ← ۵۳۶ ← ۶۳۱ ← ۶۵۶ ← ۶۶۳**

کار نیمه‌تمام است، نه خراب. بیشتر شکست‌های باقی‌مانده دیگر خطای SQL نیستند.

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

## ۳. 🔴 سه کلاس باگ که PHP هیچ‌کدام را نمی‌گیرد

این مهم‌ترین چیزی است که از این مهاجرت یاد گرفته شد. وقتی یک کالم یک سطح بالا
می‌رود، **سه چیز** باید با هم حرکت کنند و اسکریپت فقط اولی را می‌بیند:

```
۱. نام کالم        ← اسکریپت می‌گیرد
۲. مقدار پاس‌شده    ← می‌ماند و بی‌صدا غلط می‌شود
۳. امضای متد       ← می‌ماند و null می‌دهد
```

### کلاس اول: متغیر تعریف‌نشده

```php
private function systemComponents(string $branchId): array
{
    ...->where('company_id', $companyId)   // ← همیشه null
```

`php -l` ساکت است چون PHP متغیر تعریف‌نشده را خطای نحوی نمی‌داند.
۱۴ متد در این وضعیت بودند.

### کلاس دوم: کالم یک سطح، مقدار سطح دیگر

```php
->where('transactions.branch_id', $this->company_id)
```

**خطا نمی‌دهد.** SQL معتبر است و فقط صفر ردیف برمی‌گرداند — یعنی صورتحساب
مشتری خالی نشان داده می‌شود بدون هیچ پیامی.

### کلاس سوم: متدی که کمپنی می‌خواهد ولی شعبه می‌گیرد

```php
$branchId = $this->resolveBranchId($header);
$baseCurrencyId = $this->resolveBaseCurrencyId($branchId);
//                                              ↑ این متد حالا کمپنی می‌خواهد
```

### ⚠️ بعد از هر تغییر خودکار حتماً اینها را اجرا کنید

```bash
php tools/phase-2/undefined_company.php   # کلاس اول و دوم — باید صفر باشد
php tools/phase-2/raw_inserts.php         # درج‌های خامی که از مدل رد می‌شوند
php tools/phase-2/branchcontext_callers.php --dry
php tools/phase-2/select_lists.php
git diff | grep -nE "branch_id.*company_id|company_id.*branch_id"
```

کلاس سوم اسکنر ندارد — با ردیابی از روی شکست تست‌ها پیدا می‌شود.

---

## ۴. قدم بعدی

### ۴-الف: تمام کردن گام ۶

حدود ۱۸۵ تست باقی مانده. بیشترشان دیگر خطای SQL نیستند. روش کار:

```bash
php tools/phase-2/mkdb.php nextbook_test     # اول دیتابیس تست را تازه کنید
php artisan test --testsuite=Feature 2>&1 | Out-File $env:TEMP\t.txt
```

بعد خطاهای دیتابیس را گروه‌بندی کنید و از پرتکرارترین شروع کنید — تقریباً
همیشه یک ریشه ده‌ها شکست تولید می‌کند.

### ۴-ب: گام ۷ — حذف `branch_id`

از ۵۷ جدولی که لازمش ندارند (دستهٔ الف، ج، د در سند دسته‌بندی). این پاس
**بعد از سبز شدن تست‌ها** انجام شود، نه قبلش.

### ۴-ج: گام ۸ — ایندکس‌های یکتا

هر ۵۷ تا باید به شکل partial در بیایند، نه فقط تعویض کالم. دلیلش در بخش ۱۲
سند دسته‌بندی.

### چیزهایی که در این مسیر کشف شد و هنوز باز است

- `BranchContext` حالا محتوایش کمپنی‌محور است ولی نامش شعبه مانده. تغییر نامش
  ۶۳ جا را لمس می‌کند — بهتر است جدا از این کار انجام شود.
- سه trait موازی شعبه (`BranchSpecific`، `HasBranch`، `BelongsToBranch`) که
  هم‌پوشانی دارند.
- `HasCompany` و `HasBranch` هر دو روی `creating` قلاب می‌گذارند و قلاب‌ها به
  ترتیب ثبت اجرا می‌شوند. `HasCompany` عمداً به `branch_id` سطر تکیه نمی‌کند و
  مستقیم از شعبهٔ فعال می‌پرسد — به این وابستگی نامرئی دست نزنید.

---

## ۵. ابزارها

همه در `tools/phase-2/` و از ریشهٔ پروژه اجرا می‌شوند. مسیر یا رمز ثابت ندارند؛
اتصال دیتابیس را از `.env` می‌خوانند.

### تشخیصی — بعد از هر تغییر اجرا شوند

| اسکریپت | کار |
|---|---|
| `undefined_company.php` | متغیر تعریف‌نشده و ناسازگاری کالم/مقدار — **باید صفر باشد** |
| `raw_inserts.php` | درج خامی که شعبه می‌نویسد ولی کمپنی نه (`--fix` دارد) |
| `branchcontext_callers.php` | callerهایی که به جستجوی اکونت شعبه می‌دهند |
| `select_lists.php` | فهرست ستون که از جدول کمپنی‌محور شعبه می‌خواهد (`--fix` دارد) |
| `scan.php` | خط‌هایی که هنوز یک مدل کمپنی‌محور را شعبه‌محور می‌کنند |

### تبدیل انبوه

| اسکریپت | کار |
|---|---|
| `add_company_column.php` | افزودن کالم کمپنی به migrationها |
| `add_company_trait.php` | افزودن traitهای کمپنی به مدل‌ها |
| `factory_company.php` | مشتق‌کردن کمپنی از شعبه در factoryها |
| `anchored_rewrite.php` | تبدیل خودکار، با تشخیص مدل هر statement |
| `rename_migrations.php` | تعویض کالم دامنه در بلاک‌های migration |
| `rename_models.php` / `rename_factories.php` / `rename_seeders.php` | تعویض در هر لایه |

### کمکی

| اسکریپت | کار |
|---|---|
| `targets.php` | کدام جدول‌ها هنوز کالم کمپنی ندارند (محاسبه‌شده، نه فهرست دستی) |
| `map.php` | نگاشت جدول ← migration ← مدل ← trait |
| `classify.php` | فهرست جدول‌ها با کلیدهای خارجی |
| `uniques.php` | فهرست ایندکس‌های یکتای حاوی کالم دامنه |
| `report_aliases.php` | حل نام‌های مستعار SQL در سرویس گزارش |
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

- `BranchContext` نامش شعبه است ولی حساب‌های پیش‌فرض و ارز پایه‌اش کمپنی‌محور
  شده‌اند. تغییر نام ۶۳ جا را لمس می‌کند
- سه trait موازی شعبه (`BranchSpecific`، `HasBranch`، `BelongsToBranch`) که
  هم‌پوشانی دارند و بهتر است یکی شوند
- `BranchSpecific` در console و job از scope بیرون می‌زند (بدهی بخش ۱۱ سند مادر)
- الگوی `MAX+1` در `HasSequentialNumber` و نبود قید یکتا روی نمبر فروش، خرید و
  تراکنش (بخش ۱۳ سند دسته‌بندی)
- `activity_logs.company_id` تنها کالم دامنهٔ nullable است. اگر روزی همهٔ
  رویدادهای سطح پلتفرم به یک کمپنی سیستمی نسبت داده شوند، می‌شود سفتش کرد

---

## ۹. یادداشت‌های عملیاتی

**دیتابیس تست بعد از یک اجرای شکست‌خورده خراب می‌ماند.** اگر خطاهایی مثل
«جدول از قبل وجود دارد» یا «relation migrations does not exist» دیدید، این
نشانهٔ باگ در کد نیست:

```bash
php tools/phase-2/mkdb.php nextbook_test
```

**راستی‌آزمایی schema هرگز روی دیتابیس توسعه انجام نشود.** یک دیتابیس
یکبارمصرف بسازید و بعد پاکش کنید:

```bash
php tools/phase-2/mkdb.php
DB_DATABASE=nextbook_phase2_check php artisan migrate --force
DB_DATABASE=nextbook_phase2_check php artisan db:seed --force
php tools/phase-2/mkdb.php nextbook_phase2_check drop
```
