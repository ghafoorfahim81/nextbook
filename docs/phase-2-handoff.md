# ادامهٔ کار فاز ۲ — وضعیت و نقشهٔ راه

تاریخ: ۱۴۰۵/۰۷/۲۷ (2026-10-19)
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

**۳. افزونهٔ bcmath و build فرانت‌اند.**
بدون `php-bcmath` حدود ۱۲۰ تست با `Call to undefined function bcmul()` و بدون
`npm run build` حدود ۷۰ تست با `Vite manifest not found` شکست می‌خورند. هیچ‌کدام
باگ کد نیستند:

```bash
php -m | grep bcmath        # باید چیزی چاپ کند
npm install && npm run build
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
| ۶ | کالم کمپنی روی ۷۲ جدول باقی‌مانده + انتقال کوئری‌ها | ✅ |
| ۷ | حذف `branch_id` از ۵۶ جدولی که لازمش ندارند | ✅ |
| ۸ | بازنویسی ایندکس‌های یکتا به شکل partial + قید نمبر سند | ✅ |
| ۹ | جدول واسط مشتری‑شعبه | ✅ |
| ۱۰ | فعال‌سازی RLS | ⬜ **قدم بعدی** |

### چه چیزی سبز است

```bash
php tools/phase-2/mkdb.php
DB_DATABASE=nextbook_phase2_check php artisan migrate --force   # ✅ همهٔ ۱۸۳ تا
DB_DATABASE=nextbook_phase2_check php artisan db:seed --force   # ✅ کامل

php artisan test tests/Feature/Tenancy                          # ✅ ۴ سناریوی امنیتی
```

```bash
php artisan test            # ✅ ۸۹۴ پاس — Unit + Feature + Integration
```

### چه چیزی قرمز است

فقط پنج تستی که پایین‌تر آمده و از قبل از این کار خراب بوده‌اند.

بعد از گام ۷ هم: **۸۹۴ پاس**، همان پنج تای قدیمی.

روند Feature تا اینجا: **۰ ← ۱۶۲ ← ۵۰۶ ← ۵۳۶ ← ۶۳۱ ← ۶۵۶ ← ۶۷۲ ← ۸۴۳**
(از ۸۴۸؛ پنج تای باقی همان پنج تای قدیمی‌اند). Integration هم ۲۹ از ۲۹.

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

کلاس سوم حالا اسکنر دارد: `signature_mismatch.php` هر تابعی که پارامتر
`$companyId` دارد را جمع می‌کند و فراخوانی‌هایی را گزارش می‌دهد که در همان جایگاه
چیزی شعبه‌ای می‌دهند. اولین اجرایش شش مورد پیدا کرد — از جمله رخصتی‌هایی که هرگز
از روزهای رخصتی کم نمی‌شدند و مالیاتی که روی هیچ معاشی کسر نمی‌شد.

### کلاس چهارم: خواندن `->branch_id` از مدل کمپنی‌محور

```php
->where('t.branch_id', '=', $ledger->branch_id)   // ← همیشه null
if ($customer->branch_id !== $supplier->branch_id) // ← null !== null، همیشه رد می‌شود
```

مدل کالم ندارد و Eloquent برای attribute ناموجود بی‌صدا `null` می‌دهد. صورتحساب
مشتری خالی نشان داده می‌شد و بررسی «طرفین باید در یک شعبه باشند» هیچ‌وقت چیزی را
رد نمی‌کرد. اسکنر ندارد؛ این grep بر اساس نام متغیر پیدایش می‌کند:

```bash
grep -rnE "\$(category|brand|item|account|ledger|customer|supplier|currency|department|designation|shift|holiday|size)\??->branch_id\b" app
```

### پنج شکل دیگر که اسکنرهای قبلی نمی‌دیدند

- قوانین validation: `existsInBranch('departments')` و `uniqueInBranch('items')`
  روی جدول‌های کمپنی‌محور (۵۶ مورد). validator کوئری خودش را می‌سازد، پس از مدل رد
  نمی‌شود. حالا `existsInCompany` / `uniqueInCompany`.
- رشته‌های select رابطه: `'department:id,name,branch_id'`.
- eager-load رابطهٔ `branch` روی مدلی که دیگر آن را ندارد (`Account`, `Ledger`).
- `alias.branch_id` در DashboardService و NotificationService — همان مشکل
  ReportService، در فایل‌هایی که هیچ تست Feature به آن نمی‌رسید.
- درج خام یا `withoutEvents` در تست‌ها که `company_id` نمی‌گذارند؛ `raw_inserts.php`
  فقط درج‌هایی را می‌بیند که `branch_id` دارند.

### ⚠️ بعد از هر تغییر خودکار حتماً اینها را اجرا کنید

```bash
php tools/phase-2/undefined_company.php   # کلاس اول و دوم — باید صفر باشد
php tools/phase-2/signature_mismatch.php  # کلاس سوم — باید صفر باشد
php tools/phase-2/aliased_branch.php      # alias.branch_id روی جدول کمپنی — باید صفر باشد
php tools/phase-2/raw_inserts.php         # درج‌های خامی که از مدل رد می‌شوند
php tools/phase-2/branchcontext_callers.php --dry
php tools/phase-2/select_lists.php
git diff | grep -nE "branch_id.*company_id|company_id.*branch_id"
```

`aliased_branch.php` ده مورد «مبهم» در ReportService گزارش می‌دهد (`s` هم sales
است هم shifts و…). هر ده تا بررسی شده‌اند و همه جدول سندی‌اند — درست‌اند.

---

## ۴. قدم بعدی

### ۴-الف: گام ۷ — انجام شد

- **دستهٔ الف** (۳۰ جدول مستر دیتا): از قبل در گام ۴ انجام شده بود.
- **دستهٔ ج** (۲۰ جدول خطوط سند و فرزند): کالم و FK حذف، ایندکس‌های ترکیبی که با
  شعبه شروع می‌شدند حالا با کمپنی شروع می‌شوند. مدل‌ها `HasCompanyFromParent`
  با رابطهٔ والد خودشان. گزارش‌ها شعبهٔ سند والد را فیلتر می‌کنند (`si.branch_id`
  ← `s.branch_id`). قرارداد و سند کارمند از طریق شعبهٔ کارمند پیدا می‌شوند.
  (`account_balances` و `transaction_lines` و `journal_entry_lines` و
  `landed_cost_purchases` از قبل شعبه نداشتند یا وجود ندارند.)
- **دستهٔ د** (۳ جدول موجودی): شعبه از گدام. جزئیات در بخش ۷ سند دسته‌بندی.
- **`stock_lots`** به دستهٔ ب رفت — کالم گدام ندارد و قیمت هر محموله را نگه
  می‌دارد. پس شمار حذف‌ها ۵۶ است نه ۵۷.

ابزار: `drop_branch_migrations.php` بلاک‌به‌بلاک migration را بازنویسی می‌کند و
هر چیز دیگری که `branch_id` دارد را فقط گزارش می‌دهد.

**یک تغییر در `HasCompanyFromParent`:** والد حالا بدون global scope خوانده می‌شود.
انتقال جنس به گدام شعبهٔ دیگر می‌رود و scope شعبه آن گدام را پنهان می‌کرد.

**پاکسازی باقی‌مانده (بی‌خطر، ولی گمراه‌کننده):** حدود ۳۰ جای کد هنوز
`'branch_id' => ...` را در payload موجودی به `StockService::post` یا در آرایهٔ
خطوط سند می‌فرستند. از `$fillable` حذف شده و بی‌صدا نادیده گرفته می‌شود. چون همان
فایل‌ها سندهای دستهٔ ب (که شعبه لازم دارند) را هم می‌سازند، حذف خودکار امن نبود.

### ۴-ب: گام ۸ — انجام شد

**بخش ۱ — ۴۷ ایندکس شکسته.** همه به شکل partial در migrationهای سازندهٔ خودشان
بازنویسی شدند (`tools/phase-2/partial_uniques.php`، از روی `pg_index` واقعی، نه
فهرست سند — ۴۷ بود نه ۵۷، چون بعضی را migrationهای اصلاحی قبلاً درست کرده بودند).

تصمیم‌های دامنه که اعمال شد:

| ایندکس | سطح |
|---|---|
| نمبر معاش، پرداخت معاش، قرضه، درخواست رخصتی، قرارداد | کمپنی |
| `users.email`, `users.username` | کل دیتابیس |
| `employees.national_id` | کمپنی |
| `employees.code`, `employees.user_id`، دستگاه حاضری، تخصیص رخصتی | شعبه — در سند تصمیمی نبود، فقط باگ NULL اصلاح شد |

**ایندکس‌ها فوراً یک باگ واقعی گرفتند:** چارت حساب پیش‌فرض ۹۰۴۰ و ۹۰۵۰ را دو بار
داشت و هر کمپنی با حساب تکراری ساخته می‌شد.

**بخش ۲ — نمبر سند.** جزئیات در بخش ۱۳ سند دسته‌بندی. خلاصه: ۱۵ جدول سند قید
`(company_id, number)` گرفتند، نمبرگذاری کمپنی‌محور شد، و `voucher_number` عمداً
بدون قید ماند.

**تغییر رفتار که کاربر می‌بیند:** نمبر رسید، پرداخت، فروش، خرید و… حالا در کل
کمپنی یک سری است. شعبهٔ دوم سری شعبهٔ اول را ادامه می‌دهد، نه از ۱ شروع کند.

### ۴-ج: گام ۹ — انجام شد

جدول `ledger_branches (company_id, ledger_id, branch_id)` با دو composite FK، پس
مشتری فقط به شعبه‌های کمپنی خودش باز می‌شود.

| تصمیم (تأیید شده) | انتخاب |
|---|---|
| فیلتر کجا اعمال شود | **فقط picker‌ها** — فورم‌های فروش، خرید، برگشت، سفارش، پیشنهاد، و endpoint جستجو. صفحهٔ فهرست مشتریان، صورتحساب و گزارش‌ها همهٔ مشتریان کمپنی را نشان می‌دهند |
| کدام نوع لجر | **فقط مشتری و فروشنده.** لجر کارمند و سیستمی ردیف ندارند و مثل قبل دیده می‌شوند |

رفتار:

- مشتری جدید فقط در شعبهٔ سازنده باز است. بدون شعبهٔ فعال (seeder، import،
  job) در همهٔ شعبه‌های کمپنی باز می‌شود — همان کاری که migration برای مشتریان
  موجود می‌کند.
- فورم مشتری/فروشنده فیلد «قابل استفاده در شعبه‌ها» دارد. خالی پذیرفته نمی‌شود.
- در ویرایش فروش، مشتری خود سند همیشه در فهرست می‌ماند حتی اگر از آن شعبه
  برداشته شده باشد.
- scope: `Ledger::availableInBranch($branchId)`.

دو اصلاح کناری:

- endpoint جستجو هنوز `where('branch_id', ...)` روی لجر داشت (کالم از گام ۴ نیست).
- پاک‌کردن کش picker لجرها فقط کش شعبهٔ جاری را پاک می‌کرد؛ لجر از گام ۴
  کمپنی‌محور است، پس حالا کش همهٔ شعبه‌ها پاک می‌شود.

### ۴-د: نشت جدول شعبات بین کمپنی‌ها — اصلاح شد

مدل `Branch` scope کمپنی نداشت. صفحهٔ فهرست شعبات و dropdownها شعبات همهٔ
کمپنی‌ها را نشان می‌دادند، «شعبهٔ اصلی» در `LookupShared` می‌توانست مال کمپنی دیگر
باشد، و سه fallback از نوع `Branch::query()->value('id')` می‌توانستند سند یا
گزارش را به شعبهٔ کمپنی دیگری ببرند. حالا `CompanySpecific` دارد و یک ردیف در
`tests/Feature/Tenancy/CompanyScopeTest.php` آن را نگه می‌دارد.

### ۴-هـ: گام ۱۰ — RLS (قدم بعدی، هنوز شروع نشده)

پیش از شروع باید دانست: کاربر `postgres` که تست‌ها و احتمالاً اپ با آن وصل
می‌شوند **superuser** است و RLS را دور می‌زند. RLS فقط با یک نقش غیر superuser
و `FORCE ROW LEVEL SECURITY` اثر دارد، و هر اتصال باید `app.company_id` را تنظیم
کند (برای PgBouncer در حالت transaction با `SET LOCAL` در هر تراکنش).

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
| `signature_mismatch.php` | شعبه‌ای که به پارامتر `$companyId` داده می‌شود — **باید صفر باشد** |
| `aliased_branch.php` | `alias.branch_id` روی جدول کمپنی‌محور در کل `app/` — **باید صفر باشد** |
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
- الگوی `MAX+1` در `HasSequentialNumber` هنوز قفل ندارد. قید یکتا حالا جلوی
  نمبر تکراری را می‌گیرد، ولی دو ذخیرهٔ هم‌زمان یکی‌شان خطا می‌گیرد به‌جای نمبر
  بعدی. راه‌حل: `pg_advisory_xact_lock` روی (کمپنی، نوع سند) هنگام ذخیره
- کلید ترجمهٔ `contra_parties_must_share_a_branch` و
  `counterpart_must_share_a_branch` حالا کمپنی را بررسی می‌کنند؛ متن پیام هنوز
  «شعبه» می‌گوید
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
