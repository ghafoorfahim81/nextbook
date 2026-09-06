import overview from './overview'
import sales from './sales'
import purchases from './purchases'
import inventory from './inventory'
import accounting from './accounting'
import cash from './cash'
import expenses from './expenses'
import employees from './employees'
import attendance from './attendance'
import leave from './leave'
import payroll from './payroll'
import recruitment from './recruitment'
import reports from './reports'
import administration from './administration'

export default {
    meta: {
        badge: 'نکست‌بوک — سیستم مدیریت مالی و اداری',
        title: 'راهنمای کاربر',
        subtitle: 'راهنمای ماژول‌به‌ماژول برای کاربران',
        version: 'نسخهٔ ۲٫۰ · سنبله ۱۴۰۵',
        howToRead:
            'یک ماژول را از فهرست زیر انتخاب کنید تا راهنمای کامل آن باز شود. اگر تازه با سیستم کار می‌کنید، از «شروع کار و نمای کلی» آغاز کنید. هر راهنما فهرست فصل‌های خودش را دارد و مستقل قابل خواندن است.',
    },
    guides: [
        overview,
        sales,
        purchases,
        inventory,
        accounting,
        cash,
        expenses,
        employees,
        attendance,
        leave,
        payroll,
        recruitment,
        reports,
        administration,
    ],
}
