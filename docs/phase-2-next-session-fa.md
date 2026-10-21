# فاز ۲ — نقطهٔ ادامه برای جلسهٔ بعد

تاریخ: ۱۴۰۵/۰۷/۲۷ (2026-10-19)
برنچ اصلی کار: `main` (همهٔ گام‌های ۱ تا ۹ در آن merge شده یا با PR جاری merge می‌شوند)

> **به سیستم/جلسهٔ بعدی:** این فایل را اول بخوان، بعد
> [phase-2-handoff.md](phase-2-handoff.md) (جزئیات فنی، ابزارها، درس‌ها) و
> [phase-2-table-classification-fa.md](phase-2-table-classification-fa.md)
> (دسته‌بندی جدول‌ها و تصمیم‌ها). سند مادر:
> [multi-company-plan-fa.md](multi-company-plan-fa.md).
>
> **گام ۱۰ انجام شد** (۱۴۰۵/۰۷/۲۹ — 2026-10-21). سؤال‌های بخش ۴ پرسیده و
> پاسخ‌هایشان در بخش ۳ ثبت شده‌اند؛ دوباره نپرس. بخش ۴ حالا سؤال باز ندارد.

---

## ۱. وضعیت گام‌ها

| گام | کار | وضعیت |
|---|---|---|
| ۱ | جدول کمپنی‌ها به ابتدای ترتیب + soft delete + قیدهای معوق | ✅ |
| ۲ | کالم کمپنی روی جدول شعبات + composite FK | ✅ |
| ۳ | میان‌افزار کمپنی فعال + trait دامنه + بستن حفرهٔ سویچ شعبه | ✅ |
| ۴ | ۳۰ جدول مستر دیتا به دامنهٔ کمپنی | ✅ |
| ۵ | تجزیهٔ provisioning به کمپنی/شعبه | ✅ |
| ۶ | کالم کمپنی روی همهٔ جدول‌ها + انتقال کوئری‌ها | ✅ |
| ۷ | حذف `branch_id` از خطوط سند (۲۰ جدول) و موجودی (۳ جدول) | ✅ |
| ۸ | ۴۷ ایندکس یکتای partial + نمبرگذاری کمپنی‌محور با قید یکتا | ✅ |
| ۹ | جدول `ledger_branches` — مشتری/فروشنده به تفکیک شعبه در picker | ✅ |
| — | اصلاح نشت: مدل `Branch` scope کمپنی نداشت | ✅ |
| ۱۰ | RLS پستگرس روی ۹ جدول پرارزش | ✅ — جزئیات در [row-level-security.md](row-level-security.md) |
| ۱۱ | اجرای کل تست‌ها با نقش اپ | ✅ |
| **۱۲** | **گسترش policy به بقیهٔ جدول‌ها** | ⬜ **قدم بعدی** |

وضعیت تست‌ها در پایان این جلسه (`php artisan test`، هر سه suite):

```
916 passed, 5 failed, 1 incomplete
```

آن **۵ شکست از قبل از فاز ۲ خراب بوده‌اند** و ربطی به این کار ندارند (فهرست در
بخش ۲ handoff). هر عددی بیشتر از ۵ یعنی چیزی شکسته است.

> عدد از ۹۰۱ به ۹۱۶ رسید: ۱۰ تست RLS جدید، به‌علاوهٔ ۵ تست `CompanyScopeTest`
> که از قبل وجود داشتند ولی `tests/Feature/Tenancy` در `phpunit.xml` فهرست
> نشده بود و در اجرای پیش‌فرض هرگز اجرا نمی‌شدند.

---

## ۲. راه‌اندازی ماشین جدید (پیش از هر کار)

```bash
git checkout main && git pull
composer install
npm install && npm run build      # بدون build حدود ۷۰ تست با «Vite manifest not found» می‌افتند
php -m | grep bcmath              # باید چیزی چاپ کند؛ بدون آن ~۱۲۰ تست با bcmul() می‌افتند
cp .env.example .env              # DB_CONNECTION=pgsql و رمز را تنظیم کن
php artisan key:generate
# .env.testing: DB_PASSWORD را با .env یکی کن
php tools/phase-2/mkdb.php nextbook_test
php artisan test                  # باید ۹۱۶ پاس / ۵ شکست قدیمی بدهد
```

اگر عدد بالا را نگرفتی، **اول محیط را درست کن** و بعد کار را شروع کن.

⚠️ هرگز دو اجرای `php artisan test` را هم‌زمان روی `nextbook_test` نزن؛ دیتابیس
خراب می‌شود و صدها تست با «relation migrations does not exist» می‌افتند. در آن
حالت: `php tools/phase-2/mkdb.php nextbook_test` و دوباره.

---

## ۳. گام ۱۰ — RLS: چه ساخته شد

شرح کامل فنی، همراه با دستورهای استقرار و چهار نکته‌ای که پیش از گسترش دادن
policyها باید دانست، در [row-level-security.md](row-level-security.md) است.
این بخش فقط تصمیم‌ها و خلاصه را نگه می‌دارد.

### پاسخ‌های کاربر به سؤال‌های بخش ۴ (قطعی — دوباره نپرس)

| سؤال | تصمیم |
|---|---|
| دامنه | **۹ جدول پرارزش** اول (فهرست پایین) |
| `app.company_id` تنظیم‌نشده | **همه رد شود.** هر job و command باید کمپنی را صریح بگوید |
| محیط تولید | نه PgBouncer، نه Octane — **php-fpm ساده** → یک `set_config(..., false)` در میان‌افزار کافی است |
| دسترسی سرور | کاربر دارد؛ `database/sql/nextbook_app_role.sql` برای اجرای دستی آماده شد |
| نام نقش | `nextbook_app` |

### جدول‌های تحت policy

```
transactions   transaction_lines   sales   purchases
ledgers        accounts            items   stock_movements   stock_balances
```

`companies` و `branches` و `users` عمداً بیرون ماندند: login و سویچ شعبه پیش از
تنظیم کمپنی به آن‌ها نیاز دارند و policy روی آن‌ها همه را از اپ بیرون می‌انداخت.

### یک انحراف از طرح اولیه که باید بدانی

طرح `FORCE ROW LEVEL SECURITY` گفته بود. **`FORCE` گذاشته نشد** و `ENABLE`
تنها کافی است، چون صاحب اسکیما و نقش اپ دو نقش جداگانه‌اند:

- `FORCE` صاحب جدول را هم مشمول policy می‌کند، و آن‌وقت هر migration با
  backfill چندکمپنی‌ای (مثل `2026_10_19_000001_create_ledger_branches_table`)
  می‌شکست.
- بدون `FORCE`، صاحب جدول معاف است — یعنی دقیقاً همان معافیتی که migrationها
  لازم دارند — و نقش اپ که مالک نیست، کامل مشمول policy می‌ماند.

`activity_logs` هم در دامنه نیست، پس مسئلهٔ `company_id` nullable آن فعلاً
مطرح نمی‌شود.

---

## ۴. گام ۱۱ — انجام شد: کل سوئیت زیر policy

`Tests\TestCase::setUp()` بعد از `parent::setUp()` یک `SET ROLE nextbook_app`
می‌زند، پس هر چه تست می‌کند مشمول policy است.

**نقشهٔ اولیهٔ این سند (عوض‌کردن اتصال پیش‌فرض + override کردن
`migrateFreshUsing()`) کار نمی‌کند.** دو دلیل مستقل:

- هر ۱۳۲ کلاس تست خودشان `use RefreshDatabase` دارند، و متدِ trait در کلاس
  فرزند بر متد ارث‌بری‌شده اولویت دارد — پس override در `TestCase` هرگز صدا
  زده نمی‌شود.
- و اگر اتصال بعد از `parent::setUp()` عوض شود، تراکنش هر تست روی اتصال قبلی
  می‌ماند؛ fixtureها بیرون از آن commit می‌شوند و هیچ‌وقت rollback نمی‌خورند.

با `SET ROLE` اتصال و جلسه و تراکنش دست‌نخورده می‌مانند و فقط نقشی که policy با
آن سنجیده می‌شود عوض می‌شود. چون `SET` داخل تراکنش است، rollback خودش برش
می‌گرداند.

### تنها راه خروج

`TestCase::asSchemaOwner(callable)` — **فقط برای DDL**: تستی که جدولی را که
migration بعدی drop می‌کند بازمی‌سازد، یا constraint را برمی‌گرداند. در تولید
همان مسیر یک migration است که با owner اجرا می‌شود، پس این بازتاب واقعیت است نه
دور زدن policy.

تستی که نمی‌تواند **ردیف بنویسد** مشکل privilege ندارد؛ به مرز کمپنی خورده و
راه‌حلش `CompanyDatabaseContext::runFor()` است، نه این.

⚠️ یک تله: `$this->artisan(...)` یک `PendingCommand` برمی‌گرداند که دستور را در
`__destruct` اجرا می‌کند — یعنی بعد از بسته‌شدن closure و برگشتن نقش. داخل
`asSchemaOwner` باید صریح `->run()` بزنی.

### چه چیزی پیدا شد (۴۴ شکست، چهار علت، هیچ‌کدام باگ policy نبود)

| علت | تعداد | توضیح |
|---|---|---|
| دادهٔ تست بی‌معنی | ۲۹ | `ItemFactory` سه اکونت را با `Account::factory()` ساده می‌ساخت و `AccountFactory` کمپنی خودش را می‌سازد — هر آیتم تست به سه اکونت در سه کمپنی دیگر اشاره می‌کرد، از روز اول |
| بازپخش DDL مهاجرت | ۱۱ | حالا در `asSchemaOwner` |
| ساخت عمدی کمپنی دوم | ۳ | fixture در کانتکست همان کمپنی ساخته شد؛ تست سخت‌گیرتر از قبل شد |
| دستور چندکمپنی‌ای در پروسه | ۱ | `RequiresCrossCompanyAccess` درست ردش کرد |

---

## ۴-ب. گام ۱۲ — گسترش policy به بقیهٔ جدول‌ها

پیش از شروع:

```bash
php tools/phase-2/factory_related_company.php --all
```

۲۳ مورد روی جدول‌هایی که **همین حالا** policy دارند باقی است و فقط به این دلیل
بی‌خطرند که هیچ تستی فعلاً پیش‌فرضشان را لمس نمی‌کند. هر کدام لحظه‌ای که
جدولش policy بگیرد یا تستی رویش بنویسد، یک تست شکسته می‌شود. الگوی اصلاح همان
`ItemFactory` است: `company_id` را اول تعریف بگذار و ردیف وابسته را از آن مشتق
کن (Laravel تعریف را به ترتیب expand می‌کند و به هر closure مقادیر حل‌شدهٔ قبلی
را می‌دهد).

بعد: افزودن به `TABLES` در migration گام ۱۰ و `PROTECTED_TABLES` در تستش.
GRANT لازم نیست — که یعنی جدول جدید **بدون policy** می‌آید.

---

## ۵. کارهای باز کوچک (اختیاری، بعد از گام ۱۰ یا بین کارها)

| کار | توضیح |
|---|---|
| قفل نمبرگذاری | `HasSequentialNumber` هنوز «بیشترین + ۱» بدون قفل است. قید یکتا جلوی تکرار را می‌گیرد ولی دو ذخیرهٔ هم‌زمان یکی‌شان خطا می‌گیرد. راه‌حل: `pg_advisory_xact_lock(hashtext(company_id \|\| type))` هنگام ذخیره |
| کلیدهای `'branch_id'` بی‌اثر | حدود ۳۰ جا هنوز `'branch_id' => ...` را در payload موجودی یا آرایهٔ خطوط سند می‌فرستند؛ از `$fillable` حذف شده و نادیده گرفته می‌شود. بی‌خطر ولی گمراه‌کننده. حذف خودکار امن نیست چون همان فایل‌ها سندهای دارای شعبه هم می‌سازند |
| متن دو پیام | `contra_parties_must_share_a_branch` و `counterpart_must_share_a_branch` حالا کمپنی را بررسی می‌کنند ولی متنشان «شعبه» می‌گوید |
| ایندکس‌های بدون تصمیم | `employees.code`، `employees.user_id`، دستگاه حاضری و تخصیص رخصتی هنوز شعبه‌محورند؛ در سند تصمیمی برایشان نبود |
| نام `BranchContext` | محتوایش کمپنی‌محور است ولی نامش شعبه؛ تغییر نام ۶۳ جا را لمس می‌کند |
| سه trait موازی شعبه | `BranchSpecific`، `HasBranch`، `BelongsToBranch` هم‌پوشانی دارند |
| `ledgers.company_id` nullable | تنها جدول تحت RLS که کالم کمپنی‌اش nullable است. ردیف با کمپنی `NULL` برای همه نامرئی می‌شود (policy تساوی می‌سنجد). امروز چنین ردیفی در توسعه و seeder نیست، ولی بهتر است `NOT NULL` شود |
| گسترش RLS به بقیهٔ جدول‌ها | فهرست `TABLES` در migration گام ۱۰ و `PROTECTED_TABLES` در تستش را اضافه کن. GRANT لازم نیست (default privileges داده شده) — که یعنی جدول جدید **بدون policy** می‌آید |

---

## ۶. تصمیم‌هایی که در این جلسه گرفته شد (دوباره نپرس)

| موضوع | تصمیم کاربر |
|---|---|
| `stock_lots` | شعبه را **نگه می‌دارد** (به دستهٔ ب رفت) — قیمت هر محموله را دارد و کالم گدام ندارد |
| فیلتر `ledger_branches` | **فقط picker‌ها** — صفحهٔ فهرست، صورتحساب و گزارش‌ها همهٔ مشتریان کمپنی را نشان می‌دهند |
| نوع لجرهای عضو شعبه | **فقط مشتری و فروشنده** — لجر کارمند و سیستمی فیلتر نمی‌شوند |
| نمبرگذاری سند | یک سری برای هر کمپنی و نوع سند (شعبهٔ دوم سری اول را ادامه می‌دهد) |
| `transactions.voucher_number` | قید یکتا نمی‌گیرد — برچسب است نه سریال |
| ایمیل و نام کاربری | یکتا در کل دیتابیس |
| شمارهٔ تذکره کارمند | یکتا در کمپنی |

---

## ۷. ابزارهای بررسی — بعد از هر تغییر اجرا کن

```bash
php tools/phase-2/undefined_company.php    # باید ۰ باشد
php tools/phase-2/signature_mismatch.php   # باید ۰ باشد
php tools/phase-2/aliased_branch.php       # «COMPANY TABLE FILTERED BY BRANCH» باید ۰ باشد (۱۰ مورد مبهم بررسی شده و درست‌اند)
php tools/phase-2/raw_inserts.php
php tools/phase-2/select_lists.php
php tools/phase-2/factory_related_company.php   # ردیف وابسته بدون کمپنی در factoryها
```

بعد از گام ۱۰ یک بررسی دستی هم اضافه شد: هر job، دستور console یا مسیری که از
گروه میان‌افزار `web` رد نمی‌شود و جدول تحت RLS می‌خواند، باید کمپنی را خودش
تنظیم کند وگرنه در تولید نتیجهٔ خالی می‌گیرد — و تستش سبز می‌ماند. چهار موردی که
در گام ۱۰ پیدا و اصلاح شد: job‌های اطلاع‌رسانی،
`RunDeletedRecordsCleanupJob`، `CompanyProvisioningService::provision()` و
`/api/search/*` (که از گروه `web` رد نمی‌شود).

راستی‌آزمایی schema از صفر (هرگز روی دیتابیس توسعه):

```bash
php tools/phase-2/mkdb.php
DB_DATABASE=nextbook_phase2_check php artisan migrate --force
DB_DATABASE=nextbook_phase2_check php artisan db:seed --force
php tools/phase-2/mkdb.php nextbook_phase2_check drop
```
