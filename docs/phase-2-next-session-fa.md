# فاز ۲ — نقطهٔ ادامه برای جلسهٔ بعد

تاریخ: ۱۴۰۵/۰۷/۲۷ (2026-10-19)
برنچ اصلی کار: `main` (همهٔ گام‌های ۱ تا ۹ در آن merge شده یا با PR جاری merge می‌شوند)

> **به سیستم/جلسهٔ بعدی:** این فایل را اول بخوان، بعد
> [phase-2-handoff.md](phase-2-handoff.md) (جزئیات فنی، ابزارها، درس‌ها) و
> [phase-2-table-classification-fa.md](phase-2-table-classification-fa.md)
> (دسته‌بندی جدول‌ها و تصمیم‌ها). سند مادر:
> [multi-company-plan-fa.md](multi-company-plan-fa.md).
>
> **پیش از نوشتن هر کدی، سؤال‌های بخش ۴ را از کاربر بپرس.** گام ۱۰ تصمیم‌هایی
> دارد که فقط کاربر می‌تواند بگیرد.

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
| **۱۰** | **RLS پستگرس** | ⬜ **قدم بعدی — شروع نشده** |

وضعیت تست‌ها در پایان این جلسه (`php artisan test`، هر سه suite):

```
901 passed, 5 failed, 1 incomplete
```

آن **۵ شکست از قبل از فاز ۲ خراب بوده‌اند** و ربطی به این کار ندارند (فهرست در
بخش ۲ handoff). هر عددی بیشتر از ۵ یعنی چیزی شکسته است.

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
php artisan test                  # باید ۹۰۱ پاس / ۵ شکست قدیمی بدهد
```

اگر عدد بالا را نگرفتی، **اول محیط را درست کن** و بعد کار را شروع کن.

⚠️ هرگز دو اجرای `php artisan test` را هم‌زمان روی `nextbook_test` نزن؛ دیتابیس
خراب می‌شود و صدها تست با «relation migrations does not exist» می‌افتند. در آن
حالت: `php tools/phase-2/mkdb.php nextbook_test` و دوباره.

---

## ۳. گام ۱۰ — RLS: چه باید ساخته شود

هدف: حتی اگر کد اپ یک `where company_id` را جا بیندازد، خود پستگرس ردیف کمپنی
دیگر را برنگرداند.

### واقعیتی که همه‌چیز را تعیین می‌کند

اپ و تست‌ها امروز با کاربر **`postgres`** وصل می‌شوند که **superuser** است.
superuser همیشه RLS را دور می‌زند. یعنی بدون نقش جدید، policyها هیچ اثری ندارند
و تست‌ها هم چیزی را ثابت نمی‌کنند.

### چهار بخش کار (تخمین کل: ۵ تا ۸ ساعت)

| بخش | کار | تخمین |
|---|---|---|
| ۱. نقش دیتابیس | نقش `nextbook_app` بدون superuser و بدون `BYPASSRLS`؛ migration با owner اجرا شود، اپ با `nextbook_app`. GRANTها روی جدول‌ها و sequenceها. یک connection دوم در `config/database.php` | ~۱ ساعت |
| ۲. تنظیم کمپنی در هر اتصال | middleware بعد از احراز هویت: `SELECT set_config('app.company_id', ?, false)`. job و دستورهای console هم باید تنظیم کنند. اگر Octane یا PgBouncer (حالت transaction) هست، باید `SET LOCAL` داخل تراکنش یا reset بعد از هر request باشد | ۱–۲ ساعت |
| ۳. policyها | `ENABLE` + `FORCE ROW LEVEL SECURITY` و `USING (company_id = current_setting('app.company_id', true))` با `WITH CHECK` همان | ۱–۲ ساعت |
| ۴. تست‌ها | تست‌ها باید با `nextbook_app` وصل شوند وگرنه چیزی آزموده نمی‌شود. تست‌هایی که دیتای کمپنی دوم می‌سازند با `WITH CHECK` رد می‌شوند و باید اصلاح شوند. **این بخش نامعلوم‌ترین است** | ۲–۴ ساعت |

### جدول‌های پرارزش (اگر کاربر «اول چند جدول» را انتخاب کرد)

```
transactions   transaction_lines   sales   purchases
ledgers        accounts            items   stock_movements   stock_balances
```

### نکات فنی که باید رعایت شود

- `current_setting('app.company_id', true)` — آرگومان دوم `true` است تا وقتی
  تنظیم نشده خطا ندهد و `NULL` برگرداند.
- policy روی `companies` و `branches` با دقت: فرایند login و سویچ شعبه پیش از
  تنظیم کمپنی به آن‌ها نیاز دارند.
- `activity_logs.company_id` nullable است (رویدادهای پلتفرم). policy آن باید
  ردیف‌های `NULL` را هم در نظر بگیرد.
- migrationهای backfill (مثل `2026_10_19_000001_create_ledger_branches_table`)
  با owner اجرا می‌شوند و نباید تحت تأثیر RLS باشند.
- تست امنیتی جدید: کاربر کمپنی A با `app.company_id = A`، کوئری خام
  `SELECT * FROM sales` فقط ردیف‌های A را بدهد.

---

## ۴. ❓ سؤال‌هایی که **پیش از شروع** باید از کاربر پرسیده شود

1. **دامنه:** RLS اول روی ۵ تا ۹ جدول پرارزش (حدود ۳–۴ ساعت)، یا همهٔ جدول‌ها
   یکجا (۵–۸ ساعت)؟ (پیشنهاد: اول جدول‌های پرارزش)
2. **دستورها و jobها بدون کمپنی:** وقتی `app.company_id` تنظیم نشده، دیتابیس
   - همه را **رد کند** (امن‌تر؛ هر job و command باید کمپنی را صریحاً تنظیم کند)، یا
   - همه را **مجاز کند** (ساده‌تر؛ ولی یک راه دور زدن باز می‌ماند)؟
3. **سرور تولید:** آیا پشت **PgBouncer** است؟ اگر بله، در کدام حالت
   (`session` یا `transaction`)؟ آیا **Laravel Octane** در تولید روشن است؟
   (برنچ `laravel-octane` در repo هست.) این دو روش تنظیم متغیر جلسه را تعیین
   می‌کنند.
4. **دسترسی به سرور:** کاربر می‌تواند نقش جدید دیتابیس را روی سرور تولید و
   staging بسازد و `.env` را عوض کند؟ (حدود ۳۰ دقیقه کار سمت کاربر.) دستورهای
   SQL لازم را آماده کن تا کاربر اجرا کند.
5. **نام نقش:** `nextbook_app` قبول است یا نام دیگری؟

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
```

راستی‌آزمایی schema از صفر (هرگز روی دیتابیس توسعه):

```bash
php tools/phase-2/mkdb.php
DB_DATABASE=nextbook_phase2_check php artisan migrate --force
DB_DATABASE=nextbook_phase2_check php artisan db:seed --force
php tools/phase-2/mkdb.php nextbook_phase2_check drop
```
