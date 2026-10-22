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
| ۱۲ | گسترش policy به همهٔ ۱۰۰ جدول کمپنی‌محور | ✅ |
| — | **استقرار روی staging و تولید** | ⬜ **قدم بعدی — سمت کاربر** |

> ⚠️ **استقرار فقط روی دیتابیس تازه.** گام‌های ۱ تا ۹ migrationهای موجود را در جای
> خود ویرایش کردند (طبقهٔ «دیتای زنده نیست» در
> [phase-2-table-classification-fa.md](phase-2-table-classification-fa.md))،
> پس دیتابیسی که پیش از فاز ۲ migrate شده آن تغییرات را با `migrate` نمی‌گیرد —
> فقط دو migration جدید (`ledger_branches` و RLS) را اجرا می‌کند و روی اسکیمای کهنه
> می‌افتد. دیتابیس توسعهٔ محلی (`nextbook`) خودش همین وضع را دارد: مثلاً
> `sale_items.branch_id` هنوز در آن هست. روی staging/تولید: دیتابیس خالی +
> `migrate --force --database=pgsql` + seed، یا `migrate:fresh` اگر داده‌اش ارزش ندارد.

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

## ۴-ب. گام ۱۲ — انجام شد: policy روی همهٔ ۱۰۰ جدول

فهرست جدول‌ها **دستی نیست** — migration از کاتالوگ پستگرس مشتقش می‌کند، و تست
پوشش هم از همان اسکیما می‌خواند، پس این دو نمی‌توانند از هم جدا بیفتند. دلیلش
footgun فهرست دستی است: جدولی که migration بعدی می‌سازد بدون policy می‌رسد و
تنها نشانه‌اش **نبودن** policy است. حالا جدول کمپنی‌محور جدید به‌خودیِ‌خود
محافظت می‌شود و جدولی که نباید محافظت شود باید در `EXCLUDED_TABLES` بنشیند —
جایی که دلیلش نوشته می‌شود.

فقط `users` و `branches` بیرون‌اند؛ هر دو پیش از آنکه کمپنی‌ای وجود داشته باشد
خوانده می‌شوند.

### سه شکل policy لازم شد، نه یکی

| شکل | جدول‌ها | قاعده |
|---|---|---|
| متقارن | ۹۷ جدول | خودت را بخوان، خودت را بنویس |
| مرجع مشترک | `customer_groups`، `payment_terms` | مشترک یا خودت را بخوان، **فقط** خودت را بنویس |
| مسیر رسیدگی | `activity_logs` | بی‌صاحب را بنویس، **فقط** خودت را بخوان |

دو مورد آخر هر کدام **چهار** policy دارند نه یکی، و دلیلش `DELETE` است: فقط با
`USING` سنجیده می‌شود (ردیف جدیدی نیست که بررسی شود)، پس یک قاعدهٔ واحد که برای
خواندن باز باشد اجازه می‌دهد هر کمپنی ردیف‌های مشترک را حذف کند.

`ReferenceDataSeeder` عمداً این دو جدول را با `company_id = null` می‌نویسد و
`ActivityLogService` وقتی چیزی کمپنی را تعیین نکند `null` می‌گذارد. policy
تساوی‌محور ساده روی اولی کل فهرست مشترک را پنهان می‌کرد و روی دومی **ساخت
کاربر را کامل می‌شکست**.

### چه چیزی پیدا شد (۹ شکست، یک علت)

هر ۹ تا یک علت داشتند: activity log‌ای که به کمپنی‌ای نسبت داده می‌شد که اتصال
برای آن کار نمی‌کرد. هشت تای آن‌ها تست‌های عمدی «جداسازی بین کمپنی» بودند و
نهمی `StockServiceTest` که `bootstrapErpContext()` را دو بار صدا می‌زند.

درس اصلی و قابل تعمیم: **binding کانتینر و اتصال دیتابیس باید با هم جابه‌جا
شوند.** `HasCompany` کمپنی را از کانتینر می‌پرسد (و `active_branch_id` را بر
`active_company_id` ترجیح می‌دهد) و `ActivityLogService` هم. اگر فقط اتصال را
عوض کنی، ردیفی ساخته می‌شود که کمپنی‌اش با اتصال نمی‌خواند و policy ردش می‌کند.
helper مشترکش `BuildsErpContext::actingInCompany()` است و هر سه binding را
جابه‌جا می‌کند. برای شعبهٔ کمپنی دیگر هم
`BuildsErpContext::branchInAnotherCompany()` هست.

⚠️ یک تلهٔ ظریف در همین مسیر: `BranchFactory` در `created_by` یک
`User::factory()->create()` می‌سازد، و آن کاربر کمپنی را از `active_branch_id`
می‌گیرد — یعنی شعبهٔ کمپنی **قبلی**. به همین دلیل `actingInCompany()` شعبهٔ
فعال را پاک می‌کند، نه اینکه فقط کمپنی را عوض کند.

---

## ۵. کارهای باز کوچک (اختیاری، بعد از گام ۱۰ یا بین کارها)

| کار | توضیح |
|---|---|
| ~~قفل نمبرگذاری~~ | ✅ انجام شد — `HasSequentialNumber::lockNumberSeries()` با `pg_advisory_xact_lock(hashtext(table \|\| company))`. فقط داخل تراکنش قفل می‌گیرد؛ صفحهٔ create که نمبر بعدی را نشان می‌دهد پشت یک ثبت در جریان صف نمی‌کشد |
| ~~کلیدهای `'branch_id'` بی‌اثر~~ | ✅ انجام شد — ۱۲ payload موجودی (`stock_movements`/`stock_balances` کالم ندارند و `stock_lots` از هیچ payloadی ساخته نمی‌شود)، ۲ کلید `UnitMeasureController`، و `branch_id` از `$fillable`/casts مدل‌های `TransactionLine` و `AccountBalance`. کلیدهای باقی‌مانده همه به جدول دارای شعبه، voucher خوانده‌شده (`$voucher['branch_id']`)، داده‌های اعلان یا activity log می‌روند و زنده‌اند. Resourceهای مستر دیتا (`Brand`/`Currency`/`Size`/…) هنوز `branch_id` همیشه null برمی‌گردانند چون فرم‌های فرانت آن را برمی‌فرستند — بی‌خطر |
| ~~متن دو پیام~~ | ✅ انجام شد — کلیدها به `contra_parties_must_share_a_company` و `counterpart_must_share_a_company` تغییر کردند و متنشان در هر سه زبان «شرکت» شد |
| ~~ایندکس‌های بدون تصمیم~~ | ✅ تصمیم‌ها گرفته شد — جدول پایین |
| ~~ثبت‌نشدن ورود در activity log~~ | ✅ اصلاح شد (۱۴۰۵/۰۷/۳۰) — در تمرین محلی با نقش `nextbook_app` پیدا شد. درخواست ورود پیش از احراز هویت شروع می‌شود، پس کانکشن کمپنی ندارد؛ ردیف ورود که به کمپنی کاربر نسبت داده می‌شود را policy رد می‌کرد و `BatchActivityLog` خطا را بی‌صدا می‌بلعید — هیچ ورودی ثبت نمی‌شد. حالا `ActivityLogService::persist()` وقتی کانکشن کمپنی ندارد، زیر همان کمپنیِ resolve‌شده می‌نویسد. تست: `test_signing_in_records_the_login_against_the_users_company`. سوئیت این را نمی‌دید چون `bootstrapErpContext()` کاربر را از قبل وارد می‌کند |
| ~~نام `BranchContext`~~ | ✅ انجام شد — حالا `App\Support\CompanyContext` (۵۴ فایل، فقط نام؛ متدها همان‌اند). در سندهای قدیمی‌تر و نام ابزار `branchcontext_callers.php` نام قدیم مانده است |
| سه trait موازی شعبه | `BranchSpecific`، `HasBranch`، `BelongsToBranch` هم‌پوشانی دارند |
| ~~`ledgers.company_id` nullable~~ | ✅ انجام شد — `NOT NULL` شد. سه کالم nullable باقی‌مانده (`customer_groups`، `payment_terms`، `activity_logs`) عمدی‌اند و policy مخصوص خودشان را دارند |

---

### ایندکس‌های HR — تصمیم‌ها (۱۴۰۵/۰۷/۲۹)

| ایندکس | سطح | چرا |
|---|---|---|
| `employees.code` | **شعبه** | تصمیم کاربر: سری کد مال خود شعبه است، کابل و هرات هر دو می‌توانند E-001 بدهند |
| `employees.national_id` | شرکت | از گام ۸ — هویت شخص است |
| `employees.user_id` | **شرکت** | با `national_id` هم‌جنس است نه با `code`: یک حساب کاربری مال یک نفر است، و اگر دوبار در یک شرکت وصل شود حاضری خودکار دو کارمند برای انتخاب دارد |
| `attendance_devices.code` | شعبه | بدون تغییر — دستگاه فیزیکی در یک شعبه نصب است |
| `leave_allocations` | **شرکت** | `branch_id` در کلید زائد بود (کارمند خودش شعبه را تعیین می‌کند) و یک راه دور زدن باز می‌گذاشت: انتقال کارمند به شعبهٔ دیگر وسط سال، تخصیص دوم همان نوع رخصتی برای همان دوره |

⚠️ یک **باگ واقعی** در همین مسیر پیدا شد: `EmployeeStoreRequest` کد را شعبه‌محور
یکتا می‌خواست ولی `EmployeeUpdateRequest` شرکت‌محور. یعنی کاربر می‌توانست کارمندی
بسازد که بعداً قابل ذخیره نبود. هر دو حالا با ایندکس هم‌خوان‌اند.

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
