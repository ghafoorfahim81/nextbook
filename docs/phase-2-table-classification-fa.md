# نقشهٔ کار فاز ۲ — دسته‌بندی جدول‌ها و دامنهٔ کمپنی/شعبه

وضعیت: **تصویب‌شده، در حال تطبیق**
تاریخ: ۱۴۰۵/۰۷/۲۵ (2026-10-17)
برنچ: `refactor-items-and-company-wise`
سند مادر: [multi-company-plan-fa.md](multi-company-plan-fa.md) — فاز ۲

این سند مشخص می‌کند هر یک از ۱۰۹ جدول اپلیکیشنی کدام کالم دامنه را می‌گیرد،
چرا، و با چه قید دیتابیسی محافظت می‌شود.

---

## ۰. تغییر زمینه نسبت به سند مادر

**هیچ مشتری فعالی روی سیستم نیست.**

نتیجهٔ مستقیم این واقعیت:

| بخش سند مادر | وضعیت جدید |
|---|---|
| کامند `company:audit` | منتفی — دیتایی برای سنجش نیست |
| فاز ۳ (ادغام master data) | **حذف شد** — ادغامی لازم نیست، از اول درست ساخته می‌شود |
| بخش ۱۳ (معیار تطابق قبل/بعد) | منتفی |
| فاز ۲ | از «مهاجرت پرخطر» به «اصلاح schema قبل از اولین مشتری» تبدیل شد |

تنها باقی‌ماندهٔ فاز ۳: تجزیهٔ سرویس provisioning به دو بخش کمپنی و شعبه.

---

## ۱. تصمیمات گرفته‌شده

| سؤال | تصمیم |
|---|---|
| مشتری در چند شعبه | **یک رکورد، یک بیلانس**، با جدول واسط برای تعیین شعبه‌های مجاز |
| نمبرگذاری سند | **کمپنی‌محور** — یک سری به ازای کمپنی و نوع سند |
| نرخ ارز | کمپنی‌محور با امکان override هر شعبه |
| استراتژی migration | ویرایش migrationهای موجود در جای خود (چون دیتای زنده نیست) |

---

## ۲. دو قاعدهٔ حاکم

### قاعدهٔ اول — کالم کمپنی

روی **همهٔ** جدول‌های اپلیکیشنی قرار می‌گیرد. سه دلیل، به ترتیب اهمیت:

۱. **RLS پستگرس.** policy باید predicate ساده و قابل استفاده با ایندکس باشد.
   شکل دوم روی جدول‌های بزرگ فاجعهٔ performance است:

```sql
-- درست
USING (company_id = current_setting('app.company_id'))

-- غلط
USING (branch_id IN (SELECT id FROM branches WHERE company_id = ...))
```

۲. **composite FK.** بدون این کالم، قید زیر اصلاً قابل نوشتن نیست و تنها
   جایگزینش trigger است — کند و قابل دور زدن:

```sql
FOREIGN KEY (company_id, customer_id) REFERENCES ledgers (company_id, id)
```

۳. **صفحه‌بندی مرتب‌شده.** یک اسکن ایندکس به‌جای merge چند اسکن شعبه‌ای.

### قاعدهٔ دوم — کالم شعبه

یک سؤال برای هر جدول:

> اگر این سطر را از شعبه‌اش جدا کنیم، **معلوماتی از دست می‌رود**،
> یا از کالم دیگری در همان سطر قابل استخراج است؟

- معلومات از دست می‌رود → **واقعیت سطر** است، ذخیره شود
- از کالم دیگری قابل استخراج است → ذخیره **نشود**

مثال مثبت: فروش در شعبهٔ کابل ثبت شده — این از هیچ‌جای دیگر در نمی‌آید.
مثال منفی: موجودی گدام کابل — شعبه از خود گدام می‌آید.

---

## ۳. ⚠️ قاعدهٔ ایندکس — مهم‌ترین نکتهٔ performance

در مدل دیتابیس-به-ازای-مشتری، **اکثر مشتریان یک کمپنی دارند**. یعنی کالم کمپنی
برای آنها cardinality برابر ۱ دارد.

| کار | تصمیم |
|---|---|
| ایندکس مستقل روی کالم کمپنی | ❌ **ساخته نشود** — پلنر نادیده می‌گیرد، فقط هزینهٔ نوشتن است |
| کالم کمپنی به‌عنوان **ستون اول** ایندکس ترکیبی | ✅ ساخته شود |

```sql
-- نه این
CREATE INDEX ON sales (company_id);

-- بلکه این
CREATE INDEX ON sales (company_id, date DESC);
CREATE INDEX ON sales (company_id, customer_id);
CREATE INDEX ON transaction_lines (company_id, account_id);
```

با رعایت این قاعده، هزینهٔ performance افزودن کالم کمپنی تقریباً **صفر** می‌شود.

---

## ۴. دستهٔ الف — کالم کمپنی تنها، کالم شعبه **حذف** (۳۰ جدول)

مستر دیتا و کانفیگ که طبق بخش ۴ سند مادر سطح کمپنی‌اند و بین شعبه‌ها مشترک:

```
account_types   accounts
items           item_variants   brands          categories
unit_measures   quantities      sizes
currencies
ledgers         customer_groups payment_terms   discount_rules
expense_categories              journal_classes landed_cost_categories
financial_periods               owners
departments     designations    shifts          leave_types
holidays        hr_settings
salary_components               salary_structures
salary_structure_lines          tax_bracket_sets
tax_brackets
```

مهم‌ترین عضو این دسته جدول مشتریان و فروشندگان است — همان «یک مشتری، یک بیلانس»
بخش ۴ سند مادر.

---

## ۵. دستهٔ ب — **هر دو** کالم + composite FK (۴۳ جدول)

اسناد و عملیات شعبه‌محور. شعبه در اینها واقعیت سطر است:

```
sales            sale_returns      sale_orders       sale_quotations
sale_receives
purchases        purchase_returns  purchase_orders   purchase_quotations
purchase_payments
transactions     payments          receipts          expenses
journal_entries
item_transfers   stock_adjustments stock_openings
item_opening_transactions
ledger_openings  ledger_transactions                 account_transfers
settlements      contra_settlements                  landed_costs
drawings
payrolls         salary_payments   employee_loans    employee_loan_repayments
leave_requests   leave_allocations attendances       attendance_punches
attendance_devices                 attendance_device_users
job_openings     job_applications  interviews
employees        warehouses        users             pos_sessions
activity_logs    currency_rate_updates
```

### دو جدول با شعبهٔ nullable

| جدول | چرا nullable |
|---|---|
| `warehouses` | «تخصیص عملیاتی» طبق بخش ۳ سند مادر — گدام مال کمپنی است، شعبه فقط اداره‌اش می‌کند |
| `users` | دسترسی واقعی از جدول‌های access می‌آید؛ این کالم فقط «شعبهٔ پیش‌فرض» است |
| `currency_rate_updates` | خالی یعنی نرخ کل کمپنی، پرشده یعنی override آن شعبه |

---

## ۶. دستهٔ ج — کالم کمپنی تنها، شعبه از والد (۲۳ جدول)

خطوط سند و رکوردهای فرزند:

```
sale_items              sale_order_items        sale_quotation_items
sale_return_items
purchase_items          purchase_order_items    purchase_quotation_items
purchase_return_items
stock_adjustment_items  item_transfer_items     expense_details
journal_entry_lines     transaction_lines
payroll_lines           payroll_line_components salary_payment_lines
landed_cost_items       landed_cost_category_allocations
landed_cost_purchases
employee_documents      employee_contracts      interview_panelists
attachments             account_balances
```

### سه مورد خاص در این دسته

**`transaction_lines`** — امروز **نه کالم شعبه دارد نه کمپنی**. تنها جدول مالی
اصلی بدون هیچ کالم دامنه. و دقیقاً جدولی است که اکونت را نگه می‌دارد، پس بدون
کالم کمپنی هیچ قیدی نمی‌تواند جلوی اشارهٔ خط سند کمپنی A به اکونت کمپنی B را
بگیرد:

```sql
FOREIGN KEY (company_id, account_id) REFERENCES accounts (company_id, id)
```

این داغ‌ترین جدول مالی سیستم است و policy مؤثر RLS روی آن حتماً باید
predicate ساده باشد.

**`attachments`** — شعبه از رکورد والد (attachable) می‌آید، پس کالم شعبه‌اش
حذف می‌شود.

**`landed_cost_purchases`** — جدول واسط. فقط کالم کمپنی، و دو قید که **همان یک
کالم** را به اشتراک می‌گذارند. نتیجه: اتصال هزینهٔ کمپنی A به خرید کمپنی B در
سطح دیتابیس ناممکن می‌شود:

```sql
FOREIGN KEY (company_id, landed_cost_id) REFERENCES landed_costs (company_id, id)
FOREIGN KEY (company_id, purchase_id)    REFERENCES purchases   (company_id, id)
```

---

## ۷. دستهٔ د — کالم کمپنی + گدام، شعبه **حذف** (۴ جدول)

```
stock_balances   stock_lots   stock_pieces   stock_movements
```

دلیل اول: هر چهار جدول گدام دارند و گدام خودش به شعبه وصل است — طبق قاعدهٔ دوم،
شعبه معلومات جدیدی اضافه نمی‌کند.

دلیل دوم و مهم‌تر: **اینجا composite FK محافظت نمی‌کند.** تنها قید ممکن این است:

```sql
FOREIGN KEY (company_id, warehouse_id) REFERENCES warehouses (company_id, id)
```

این فقط تضمین می‌کند گدام مال همان کمپنی است. هیچ قیدی نمی‌تواند تضمین کند کالم
شعبهٔ سطر با شعبهٔ گدام یکی **بماند**. اگر روزی گدامی به شعبهٔ دیگری منتقل شود،
میلیون‌ها سطر موجودی شعبهٔ قدیمی را نگه می‌دارند و بی‌صدا دروغ می‌گویند.

این برخلاف دستهٔ ب است: آنجا قید جلوی واگرایی را می‌گیرد، اینجا نمی‌گیرد. پس
آنجا denormalization امن است و اینجا نیست.

گزارش موجودی به تفکیک شعبه یک join به جدول گدام‌ها می‌خواهد — جدولی کوچک که
همیشه در کش است.

---

## ۸. دستهٔ ه — بدون کالم دامنه (۵ جدول)

```
companies        ریشهٔ درخت
branches         کالم کمپنی می‌گیرد — این تغییر بنیادی فاز ۲ است
invoice_formats  از قبل کالم کمپنی دارد
favorites        وابسته به کاربر
password_histories  وابسته به کاربر
```

---

## ۹. موارد حذفی

| مورد | وضعیت | اقدام |
|---|---|---|
| `transaction_line_currencies` | جدول **مرده** — فقط `id` و `timestamps`، بدون هیچ کالم دیگر؛ هیچ کنترلر، سرویس یا تستی از آن استفاده نمی‌کند | جدول و مدل `TransactionLineCurrency` حذف شوند |
| `branches.sub_domain` | بلااستفاده؛ در SaaS زیردامنه مال مشتری است نه شعبه | حذف |
| `tenant_id` در `Account` و `Currency` | کالم در هیچ migration ساخته نشده | از مدل‌ها و `CurrencyResource` حذف |

---

## ۱۰. مشتری در چند شعبه — طراحی تصویب‌شده

```
ledgers           ← سطح کمپنی: یک رکورد، یک بیلانس
ledger_branches   ← جدول واسط: کدام شعبه‌ها اجازهٔ استفاده دارند
```

رفتار:

- مشتری در شعبهٔ کابل ساخته می‌شود → پیش‌فرض فقط عضویت کابل
- اگر در هرات هم لازم شد → یک گزینه در فرم، هرات اضافه می‌شود
- فهرست انتخاب هر شعبه فقط مشتریان مجاز خودش را نشان می‌دهد
- بیلانس همیشه یکی است؛ پرداخت در هرات قرض کابل را تسویه می‌کند

### ⚠️ عارضهٔ جانبی که باید بدانیم

ایندکس‌های یکتای امروز شعبه‌محورند:

```
ledgers (branch_id, phone_no, deleted_at)
ledgers (branch_id, email,    deleted_at)
ledgers (branch_id, code,     deleted_at)
ledgers (branch_id, name,     deleted_at)
```

بعد از انتقال به سطح کمپنی، ثبت یک شماره تلفن **دو بار در دو شعبه غیرممکن
می‌شود**. این رفتار مطلوب است (جلوی مشتری تکراری را می‌گیرد) ولی باید آگاهانه
انتخاب شده باشد.

**همچنین:** جدول مشتریان برای فروشنده و کارمند هم استفاده می‌شود (جدول کارمندان
به آن لینک دارد)، پس این تصمیم روی ماژول HR هم اثر دارد.

---

## ۱۱. نرخ ارز — سه سطح با fallback

دو چیز جدا هستند:

| جدول | سطح |
|---|---|
| `currencies` | کمپنی — فهرست اسعار و ارز پایه در کل کمپنی یکی است |
| `currency_rate_updates` | کمپنی + شعبهٔ nullable |

```
نرخ مرکزی (از دیتابیس مرکزی، بخش ۱۵ سند مادر)
      ↓  اگر نبود
نرخ کمپنی   (شعبه خالی)
      ↓  اگر بود، برنده است
نرخ شعبه    (شعبهٔ هرات)
```

```sql
CREATE UNIQUE INDEX ON currency_rate_updates
    (company_id, currency_id, effective_date, branch_id)
    NULLS NOT DISTINCT;
```

عبارت `NULLS NOT DISTINCT` (پستگرس ۱۵ به بعد) باعث می‌شود فقط **یک** سطر با
شعبهٔ خالی برای هر تاریخ ممکن باشد.

**نکتهٔ حسابداری:** طبق بخش ۷ سند مادر هر سند نرخ خودش را در لحظهٔ ثبت ذخیره
می‌کند. پس این جدول فقط **منبع پیش‌فرض فرم ورود** است و متفاوت بودن نرخ هرات
هیچ خطری برای اسناد گذشته ندارد.

---

## ۱۲. ایندکس‌های یکتا — ۵۷ مورد

### 🔴 هر ۵۷ مورد یک باگ مشترک دارند

الگوی `UNIQUE (branch_id, x, deleted_at)` که در کل این schema استفاده شده
**در پستگرس اصلاً کار نمی‌کند**. چون `NULL` با `NULL` برابر نیست و ستون
`deleted_at` روی هر سطر زنده خالی است، قید هرگز فعال نمی‌شود و رکورد تکراری
عبور می‌کند.

این در عمل تأیید شد: دو شعبه با نام یکسان در یک کمپنی قبول شدند تا وقتی که
ایندکس به شکل partial بازنویسی شد.

پروژه این باگ را از قبل می‌شناسد و در
`2026_08_05_000007_fix_soft_delete_unique_constraints` برای شش جدول
(items, categories, brands, warehouses, sizes, unit_measures) حل کرده — ولی
الگوی معیوب روی بقیه باقی مانده است.

**پس بازنویسی ۵۷ ایندکس فقط تعویض کالم نیست؛ هر کدام باید به شکل partial
در بیایند:**

```sql
-- غلط (قید هرگز فعال نمی‌شود)
UNIQUE (company_id, code, deleted_at)

-- درست
CREATE UNIQUE INDEX <name> ON <table> (company_id, code)
    WHERE deleted_at IS NULL;
```

برای کالم‌های nullable مثل بارکد، شرط `AND <column> IS NOT NULL` هم اضافه شود.

### ۴۰ مورد روی جدول‌های دستهٔ الف

تبدیل مستقیم: کالم شعبه به کمپنی، به‌علاوهٔ اصلاح partial بالا. یعنی کد جنس در
کل کمپنی یکتا می‌شود — دقیقاً رفتار مطلوب.

### ۱۷ مورد روی جدول‌های دستهٔ ب

**نمبرگذاری سند — کمپنی‌محور (تصمیم گرفته‌شده):**

یک سری به ازای کمپنی و نوع سند. کابل نمبر ۰۰۱ می‌گیرد، هرات ۰۰۲.

```sql
UNIQUE (company_id, number, deleted_at)
```

شامل این جدول‌ها:

```
payrolls.number            salary_payments.number
employee_loans.number      leave_requests.number
employee_contracts.contract_number
```

**سه مورد با تصمیم متفاوت:**

| ایندکس | سطح | چرا |
|---|---|---|
| `users.username` | کل دیتابیس | یک نفر با یک نام کاربری در همهٔ کمپنی‌ها |
| `users.email` | کل دیتابیس | همان |
| `employees.national_id` | کمپنی | یک شخص دو بار در یک کمپنی استخدام نمی‌شود |

---

## ۱۳. 🔴 شکاف بحرانی — نمبر سند بدون قید یکتایی

جست‌وجو نشان داد **هیچ** ایندکس یکتایی روی نمبر این جدول‌ها وجود ندارد:

```
sales   purchases   transactions
```

فقط جدول‌های HR قید یکتایی دارند. فاکتور فروش و خرید هیچ محافظتی در دیتابیس
ندارند.

این با مشکل موجود در `HasSequentialNumber` جمع می‌شود که هنوز از الگوی
«بیشترین مقدار به‌علاوهٔ یک» استفاده می‌کند بدون قفل. نتیجه: دو فروش هم‌زمان
همان نمبر را می‌گیرند و **دیتابیس جلویشان را نمی‌گیرد**.

ردیف آزمون پذیرش بخش ۱۳ سند مادر («نمبر تکراری صادر نشود») امروز شکست می‌خورد.

**اقدام:** همراه با تبدیل به نمبرگذاری کمپنی‌محور، روی هر سه جدول قید یکتایی
اضافه شود و الگوی شماره‌دهی به شمارندهٔ قفل‌دار تبدیل گردد.

---

## ۱۴. 🔴 مشکل ترتیب migrationها

جدول شعبات در اولین migration ساخته می‌شود و جدول کمپنی‌ها در
`2025_08_26_163520` — یعنی بعد از تقریباً همه چیز.

زنجیرهٔ وابستگی امروز:

```
branches  →  currencies (کالم شعبه دارد)  →  companies (کالم ارز دارد)
```

افزودن کالم کمپنی به جدول شعبات یک **حلقه** می‌سازد:

```
branches → companies → currencies → branches
```

### راه‌حل

بعد از انتقال اسعار به سطح کمپنی، حلقهٔ شعبات شکسته می‌شود و فقط یک وابستگی
دوطرفهٔ کمپنی‑اسعار می‌ماند که با جدا کردن قید از ساخت جدول حل می‌شود:

```
۱. companies      ساخته شود، کالم ارز nullable و بدون قید
۲. branches       کالم کمپنی + قید
۳. currencies     کالم کمپنی + قید
۴. companies      حالا قید ارز اضافه شود
```

یعنی migration جدول کمپنی‌ها باید به **ابتدای** ترتیب منتقل شود. چون دیتای زنده
نداریم، این کار با تغییر نام فایل انجام می‌شود.

---

## ۱۵. ترتیب اجرا

| گام | کار | وابسته به |
|---|---|---|
| ۱ | انتقال جدول کمپنی‌ها به ابتدای ترتیب + افزودن soft delete | — |
| ۲ | کالم کمپنی روی جدول شعبات + قید یکتای نام در سطح کمپنی + حذف زیردامنه | ۱ |
| ۳ | قید `UNIQUE (company_id, id)` روی جدول‌های والد تا composite FK ممکن شود | ۲ |
| ۴ | دستهٔ الف — کالم کمپنی جای شعبه | ۳ |
| ۵ | دستهٔ ج و د — کالم کمپنی، حذف شعبه | ۴ |
| ۶ | دستهٔ ب — افزودن کالم کمپنی + composite FK | ۴ |
| ۷ | بازنویسی ۵۷ ایندکس یکتا + قید نمبر سند | ۶ |
| ۸ | جدول واسط مشتری‑شعبه | ۴ |
| ۹ | حذف موارد مردهٔ بخش ۹ | — |
| ۱۰ | میان‌افزار تعیین کمپنی فعال + بستن حفرهٔ سویچ شعبه | ۲ |
| ۱۱ | تجزیهٔ سرویس provisioning به کمپنی و شعبه | ۴ |
| ۱۲ | فعال‌سازی RLS روی جدول‌های پرارزش | ۶ |

گام ۱۰ همان حفرهٔ امنیتی بخش ۱۰ سند مادر را می‌بندد.

---

## ۱۶. خلاصهٔ اعداد

| | تعداد |
|---|---|
| جدول اپلیکیشنی | ۱۰۹ |
| دارای کالم شعبه امروز | ۱۰۱ |
| کالم شعبه **حذف** می‌شود | ۵۷ |
| کالم شعبه **باقی** می‌ماند | ۴۳ |
| کالم کمپنی اضافه می‌شود | ۱۰۴ |
| ایندکس یکتای نیازمند بازنویسی | ۵۷ |
