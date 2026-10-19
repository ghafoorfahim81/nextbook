import {
    AlertTriangle,
    BadgeCheck,
    BellRing,
    Boxes,
    CalendarClock,
    CircleAlert,
    ClipboardCheck,
    FileText,
    Landmark,
    Package,
    ReceiptText,
    ShoppingCart,
    Tags,
    Users,
    Wallet,
} from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'

// Keep in sync with App\Enums\NotificationCategory on the backend.
export const NOTIFICATION_CATEGORIES = ['inventory', 'purchases', 'sales', 'accounting', 'hr', 'reports', 'system']

const CATEGORY_META = {
    inventory: { icon: Boxes, class: 'bg-orange-100 text-orange-700 dark:bg-orange-950/50 dark:text-orange-300', dot: 'bg-orange-500' },
    purchases: { icon: ShoppingCart, class: 'bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300', dot: 'bg-sky-500' },
    sales: { icon: Tags, class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300', dot: 'bg-emerald-500' },
    accounting: { icon: Landmark, class: 'bg-violet-100 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300', dot: 'bg-violet-500' },
    hr: { icon: Users, class: 'bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300', dot: 'bg-blue-500' },
    reports: { icon: ClipboardCheck, class: 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300', dot: 'bg-amber-500' },
    system: { icon: BellRing, class: 'bg-primary/10 text-primary', dot: 'bg-primary' },
}

const TYPE_STYLES = {
    low_balance: { icon: Wallet, class: 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' },
    low_stock: { icon: Package, class: 'bg-orange-100 text-orange-700 dark:bg-orange-950/50 dark:text-orange-300' },
    nearest_expiry: { icon: AlertTriangle, class: 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300' },
    overdue_purchase: { icon: ReceiptText, class: 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300' },
    overdue_sale: { icon: ReceiptText, class: 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300' },
    overdue_invoice: { icon: ReceiptText, class: 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300' },
    sale_paid: { icon: BadgeCheck, class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' },
    purchase_paid: { icon: BadgeCheck, class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' },
    new_transaction: { icon: Landmark, class: 'bg-violet-100 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300' },
    daily_summary: { icon: ClipboardCheck, class: 'bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300' },
    weekly_summary: { icon: ClipboardCheck, class: 'bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300' },
    contract_expiring: { icon: CalendarClock, class: 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' },
    document_expiring: { icon: FileText, class: 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' },
    probation_ending: { icon: CalendarClock, class: 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' },
    leave_request_pending: { icon: BellRing, class: 'bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300' },
    leave_approved: { icon: BadgeCheck, class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' },
    leave_rejected: { icon: CircleAlert, class: 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300' },
}

const DEFAULT_STYLE = { icon: BellRing, class: 'bg-primary/10 text-primary' }

function legacyExpiryItem(notification) {
    return notification.data?.item_name
        ?? notification.message?.match(/^Item (.+) expires on /)?.[1]
        ?? ''
}

export function useNotificationPresentation() {
    const { t, locale } = useI18n()

    function messageParams(notification) {
        const data = notification.data || {}

        return {
            ...data,
            item: data.item_name ?? legacyExpiryItem(notification),
            account: data.account_name ?? '',
            available: data.quantity ?? '',
            minimum: data.minimum_stock ?? '',
            amount: data.balance ?? data.outstanding_amount ?? '',
            number: data.number ?? data.voucher_number ?? '',
            date: data.expire_date ?? data.date ?? '',
            batch: data.batch ? ` (${data.batch})` : '',
            employee: data.employee ?? '',
            document: data.document_type ?? '',
            days: data.days ?? '',
        }
    }

    function present(notification) {
        const key = `notifications.types.${notification.type}`
        const params = messageParams(notification)
        const hasTranslation = t(`${key}.title`) !== `${key}.title`

        return {
            title: hasTranslation ? t(`${key}.title`, params) : notification.title,
            message: hasTranslation ? t(`${key}.message`, params) : notification.message,
            ...(TYPE_STYLES[notification.type] || DEFAULT_STYLE),
        }
    }

    function category(notification) {
        const key = NOTIFICATION_CATEGORIES.includes(notification?.category) ? notification.category : 'system'
        const labelKey = `notifications.categories.${key}`

        return {
            key,
            label: t(labelKey) === labelKey ? key : t(labelKey),
            ...CATEGORY_META[key],
        }
    }

    function relativeTime(value) {
        if (!value) return ''

        const seconds = Math.round((new Date(value).getTime() - Date.now()) / 1000)
        const units = [
            ['year', 31_536_000],
            ['month', 2_592_000],
            ['week', 604_800],
            ['day', 86_400],
            ['hour', 3_600],
            ['minute', 60],
        ]
        const [unit, size] = units.find(([, secondsPerUnit]) => Math.abs(seconds) >= secondsPerUnit) || ['second', 1]

        return new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' }).format(
            Math.round(seconds / size),
            unit,
        )
    }

    return { present, category, relativeTime }
}
