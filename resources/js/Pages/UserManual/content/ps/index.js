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
        badge: 'نکست‌بوک — د مالي او اداري مدیریت سیستم',
        title: 'د کارن لارښود',
        subtitle: 'د کارنانو لپاره ماژول‌په‌ماژول لارښود',
        version: 'نسخه ۲.۰ · وږی ۱۴۰۵',
        howToRead:
            'د خپل لارښود لپاره لاندې لړلیک څخه یو ماژول وټاکئ. که نوی یاست، له «پیل او عمومي کتنه» پیل وکړئ. هر لارښود خپل د څپرکو فهرست لري او په یوازې توګه لوستل کېږي.',
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
