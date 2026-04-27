<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import AppLayout from '@/Layouts/Layout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import { Badge } from '@/Components/ui/badge'
import {
  LayoutDashboard, BookOpen, FileText, Package,
  ShoppingCart, ShoppingBag, Receipt, CreditCard,
  ArrowLeftRight, Loader2, Search, Sparkles,
  CircleDollarSign, Scale, CloudSun, CalendarDays,
  MapPin, ArrowUpRight, NotebookText, ArrowRight,
  Calculator, StickyNote, Database, Star, Trash2,
  Plus, X, Check, AlertCircle, HardDrive, HelpCircle,
  ChevronDown, ChevronUp,
} from 'lucide-vue-next'

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps<{
  currencies: Array<{
    id: string; name: string; code: string; symbol: string
    exchange_rate: number; is_base_currency: boolean; is_main: boolean; flag: string | null
  }>
  unitMeasures: Array<{
    id: string; name: string; unit: string; symbol: string
    value: number; is_main: boolean; quantity_id: string
    quantity: { id: string; quantity: string; slug: string; symbol: string } | null
  }>
}>()

const { t, locale } = useI18n()
const page = usePage<any>()
const isRTL = computed(() => ['fa', 'ps', 'pa'].includes(locale.value) || page.props.direction === 'rtl')

// calendar_type comes from company settings (auth.user.calendar_type).
// PHP backed enums serialize as plain strings in JSON, but guard against
// the object form {value:"jalali"} just in case.
const calendarType = computed<string>(() => {
  const raw = page.props.auth?.user?.calendar_type
  // Handle enum object form: { value: "jalali" }
  const val = (raw && typeof raw === 'object' && 'value' in raw) ? raw.value : raw
  return (typeof val === 'string' && val) ? val : 'gregorian'
})

const firstName = computed(() => {
  const name = page.props.auth?.user?.name?.trim()
  return name ? name.split(/\s+/)[0] : t('home.title')
})

const activeBranchName = computed(() =>
  page.props.activeBranchName
  || page.props.auth?.user?.branch_name
  || t('home.hero.branch_fallback'),
)

const calendarModeLabel = computed(() =>
  calendarType.value === 'jalali'
    ? t('home.hero.calendar_modes.jalali')
    : t('home.hero.calendar_modes.gregorian'),
)

// ═══════════════════════════════════════════════════════════════════════════════
// ── JALALI CONVERSION  ────────────────────────────────────────────────────────
// Algorithm: direct port of jalaali-js (MIT licence)
// Verified: 2026-04-26 → 1405/02/06  ✓
// ═══════════════════════════════════════════════════════════════════════════════

function toJalali(gy: number, gm: number, gd: number): [number, number, number] {
  let jy: number, jm: number, jd: number
  let g_d_no: number, j_d_no: number
  let j_np: number
  let i: number

  gy -= 1600
  gm -= 1
  gd -= 1

  g_d_no = 365 * gy + Math.floor((gy + 3) / 4) - Math.floor((gy + 99) / 100) + Math.floor((gy + 399) / 400)

  const gMonthDays = [31, (gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0 ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]
  for (i = 0; i < gm; ++i) g_d_no += gMonthDays[i]
  g_d_no += gd

  j_d_no = g_d_no - 79

  j_np = Math.floor(j_d_no / 12053)
  j_d_no %= 12053

  jy = 979 + 33 * j_np + 4 * Math.floor(j_d_no / 1461)
  j_d_no %= 1461

  if (j_d_no >= 366) {
    jy += Math.floor((j_d_no - 1) / 365)
    j_d_no = (j_d_no - 1) % 365
  }

  const jMonthDays = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29]
  for (i = 0; i < 11 && j_d_no >= jMonthDays[i]; ++i) {
    j_d_no -= jMonthDays[i]
  }
  jm = i + 1
  jd = j_d_no + 1

  return [jy, jm, jd]
}

function fromJalali(jy: number, jm: number, jd: number): [number, number, number] {
  let gy: number, gm: number, gd: number
  let g_d_no: number, j_d_no: number
  let i: number

  jy -= 979
  jm -= 1
  jd -= 1

  j_d_no = 365 * jy + Math.floor(jy / 33) * 8 + Math.floor((jy % 33 + 3) / 4)
  const jMonthDays = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29]
  for (i = 0; i < jm; ++i) j_d_no += jMonthDays[i]
  j_d_no += jd

  g_d_no = j_d_no + 79

  gy = 1600 + 400 * Math.floor(g_d_no / 146097)
  g_d_no = g_d_no % 146097

  let leap = true
  if (g_d_no >= 36525) {
    g_d_no--
    gy += 100 * Math.floor(g_d_no / 36524)
    g_d_no = g_d_no % 36524

    if (g_d_no >= 365) g_d_no++
    else leap = false
  }

  gy += 4 * Math.floor(g_d_no / 1461)
  g_d_no %= 1461

  if (g_d_no >= 366) {
    leap = false
    g_d_no--
    gy += Math.floor(g_d_no / 365)
    g_d_no = g_d_no % 365
  }

  const gMonthDays = [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]
  for (i = 0; g_d_no >= gMonthDays[i]; i++) g_d_no -= gMonthDays[i]
  gm = i + 1
  gd = g_d_no + 1

  return [gy, gm, gd]
}

function jalaliDaysInMonth(jy: number, jm: number): number {
  if (jm <= 6) return 31
  if (jm <= 11) return 30
  // month 12 — leap check
  const jy0 = jy - 979
  const leapBase = 365 * jy0 + Math.floor(jy0 / 33) * 8 + Math.floor((jy0 % 33 + 3) / 4)
  const nextBase = 365 * (jy0 + 1) + Math.floor((jy0 + 1) / 33) * 8 + Math.floor(((jy0 + 1) % 33 + 3) / 4)
  return nextBase - leapBase === 366 ? 30 : 29
}

// ═══════════════════════════════════════════════════════════════════════════════
// ── 1. ANALOG CLOCK + DIGITAL TIME + TRIPLE DATE ──────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

const clockCanvas = ref<HTMLCanvasElement | null>(null)
const digitalTime  = ref('')
const digitalMeridiem = ref('')
const dayName      = ref('')
let clockTimer: ReturnType<typeof setInterval> | null = null

// ── Date display objects ──────────────────────────────────────────────────────
interface DateDisplay {
  label: string      // e.g. "میلادی"
  icon: string       // emoji
  weekDay: string    // e.g. "Sunday" / "یکشنبه"
  day: number
  monthName: string
  full: string       // e.g. "26/4/2026"
}

const gregorianDisplay = ref<DateDisplay>({ label:'', icon:'📅', weekDay:'', day:0, monthName:'', full:'' })
const hijriDisplay     = ref<DateDisplay>({ label:'', icon:'🌙', weekDay:'', day:0, monthName:'', full:'' })
const jalaliDisplay    = ref<DateDisplay>({ label:'', icon:'☀️', weekDay:'', day:0, monthName:'', full:'' })

const jalaliMonthNames = [
  'حمل','ثور','جوزا','سرطان','اسد','سنبله',
  'میزان','عقرب','قوس','جدی','دلو','حوت',
]
const gregorianMonthNames = [
  'January','February','March','April','May','June',
  'July','August','September','October','November','December',
]
const hijriMonthNames = [
  'محرم','صفر','ربیع الاول','ربیع الثانی','جمادی الاول','جمادی الثانی',
  'رجب','شعبان','رمضان','شوال','ذوالقعده','ذوالحجه',
]
const persianWeekDays = ['یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه','شنبه']
const englishWeekDays = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']
const arabicWeekDays  = ['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت']

// Gregorian → Hijri (tabular Islamic calendar, civil epoch)
function toHijri(gy: number, gm: number, gd: number): [number, number, number] {
  // Step 1: Gregorian → Julian Day Number
  const a = Math.floor((14 - gm) / 12)
  const y = gy + 4800 - a
  const m = gm + 12 * a - 3
  const jdn = gd + Math.floor((153 * m + 2) / 5) + 365 * y
    + Math.floor(y / 4) - Math.floor(y / 100) + Math.floor(y / 400) - 32045

  // Step 2: Julian Day → Hijri (Fliegel & Van Flandern tabular)
  const z  = jdn - 1948438
  const a2 = Math.floor((z - 1) / 10631)
  const b  = z - 10631 * a2
  const c  = Math.floor((b - 1) / 354)
  const d  = b - 354 * c
  const e  = Math.floor((11 * c + 3) / 30)
  const hm = Math.floor((d - e) / 29.5) + 1
  const hd = d - Math.floor(29.5 * (hm - 1))
  const hy = 30 * a2 + c + 1
  return [hy, Math.min(hm, 12), hd]
}

function updateClock() {
  const now = new Date()
  const h = now.getHours(), m = now.getMinutes(), s = now.getSeconds()
  const hour12 = h % 12 || 12
  digitalTime.value = `${String(hour12).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`
  digitalMeridiem.value = h >= 12 ? 'PM' : 'AM'
  dayName.value = persianWeekDays[now.getDay()]

  const gy = now.getFullYear(), gm = now.getMonth() + 1, gd = now.getDate()
  const dow = now.getDay()

  // Gregorian
  gregorianDisplay.value = {
    label: 'میلادی', icon: '📅',
    weekDay: englishWeekDays[dow],
    day: gd,
    monthName: gregorianMonthNames[gm - 1],
    full: `${gd}/${gm}/${gy}`,
  }

  // Hijri
  const [hy, hm, hd] = toHijri(gy, gm, gd)
  hijriDisplay.value = {
    label: 'قمری', icon: '🌙',
    weekDay: arabicWeekDays[dow],
    day: hd,
    monthName: hijriMonthNames[hm - 1],
    full: `${hy}/${String(hm).padStart(2,'0')}/${String(hd).padStart(2,'0')}`,
  }

  // Jalali
  const [jy, jm, jd] = toJalali(gy, gm, gd)
  jalaliDisplay.value = {
    label: 'شمسی', icon: '☀️',
    weekDay: persianWeekDays[dow],
    day: jd,
    monthName: jalaliMonthNames[jm - 1],
    full: `${jy}/${String(jm).padStart(2,'0')}/${String(jd).padStart(2,'0')}`,
  }

  drawClock(now)
}

function drawClock(now: Date) {
  const canvas = clockCanvas.value
  if (!canvas) return
  const ctx = canvas.getContext('2d')
  if (!ctx) return
  const W = canvas.width, H = canvas.height
  const cx = W / 2, cy = H / 2
  const r = Math.min(cx, cy) - 8
  const isDark = document.documentElement.classList.contains('dark')

  ctx.clearRect(0, 0, W, H)

  // Base clock face
  ctx.beginPath()
  ctx.arc(cx, cy, r, 0, Math.PI * 2)
  ctx.fillStyle = isDark ? '#0f172a' : '#ffffff'
  ctx.fill()
  ctx.strokeStyle = isDark ? '#334155' : '#d4d4d8'
  ctx.lineWidth = 2
  ctx.stroke()

  // Minute and hour ticks
  for (let i = 0; i < 60; i++) {
    const angle = (i * Math.PI) / 30
    const isHour = i % 5 === 0
    const outer = r - 10
    const inner = outer - (isHour ? 14 : 7)
    const x1 = cx + Math.sin(angle) * inner
    const y1 = cy - Math.cos(angle) * inner
    const x2 = cx + Math.sin(angle) * outer
    const y2 = cy - Math.cos(angle) * outer
    ctx.beginPath()
    ctx.moveTo(x1, y1)
    ctx.lineTo(x2, y2)
    ctx.strokeStyle = isDark ? '#f8fafc' : '#111111'
    ctx.lineWidth = isHour ? 3.4 : 1.4
    ctx.lineCap = 'round'
    ctx.stroke()
  }

  // Hour numbers 1-12
  ctx.font = `700 ${Math.floor(r * 0.16)}px sans-serif`
  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'
  ctx.fillStyle = isDark ? '#f8fafc' : '#111111'
  for (let i = 1; i <= 12; i++) {
    const angle = (i * Math.PI) / 6
    const dist = r - 34
    ctx.fillText(String(i), cx + Math.sin(angle) * dist, cy - Math.cos(angle) * dist)
  }

  // Center label
  ctx.font = `500 ${Math.floor(r * 0.12)}px sans-serif`
  ctx.fillStyle = isDark ? '#cbd5e1' : '#1f2937'
  ctx.fillText(t('home.clock.center_label'), cx, cy + r * 0.22)

  const sec = now.getSeconds()
  const min = now.getMinutes() + sec / 60
  const hr  = now.getHours() % 12 + min / 60

  // Hour hand
  drawHand(ctx, cx, cy, (hr * Math.PI) / 6, r * 0.45, 8, isDark ? '#f8fafc' : '#222222', r * 0.12)
  // Minute hand
  drawHand(ctx, cx, cy, (min * Math.PI) / 30, r * 0.7, 5.5, isDark ? '#f8fafc' : '#222222', r * 0.18)
  // Second hand
  drawHand(ctx, cx, cy, (sec * Math.PI) / 30, r * 0.8, 2.2, '#f59e0b', r * 0.3)
  // Center dot
  ctx.beginPath()
  ctx.arc(cx, cy, 8, 0, Math.PI * 2)
  ctx.fillStyle = '#f59e0b'
  ctx.fill()
  ctx.beginPath()
  ctx.arc(cx, cy, 3.5, 0, Math.PI * 2)
  ctx.fillStyle = isDark ? '#0f172a' : '#ffffff'
  ctx.fill()
}

function drawHand(
  ctx: CanvasRenderingContext2D,
  cx: number,
  cy: number,
  angle: number,
  length: number,
  width: number,
  color: string,
  tail = 0,
) {
  ctx.beginPath()
  ctx.moveTo(cx - Math.sin(angle) * tail, cy + Math.cos(angle) * tail)
  ctx.lineTo(cx + Math.sin(angle) * length, cy - Math.cos(angle) * length)
  ctx.strokeStyle = color
  ctx.lineWidth = width
  ctx.lineCap = 'round'
  ctx.stroke()
}

onMounted(() => {
  updateClock()
  clockTimer = setInterval(updateClock, 1000)
})
onBeforeUnmount(() => { if (clockTimer) clearInterval(clockTimer) })

// ═══════════════════════════════════════════════════════════════════════════════
// ── 2. CALENDAR (dynamic based on calendarType) ────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

const calendarViewDate = ref(new Date())

// Current Jalali view state (for Jalali mode)
const calViewJY = ref(0)
const calViewJM = ref(0)

// Initialise Jalali view from today
function initJalaliView() {
  const now = new Date()
  const [jy, jm] = toJalali(now.getFullYear(), now.getMonth() + 1, now.getDate())
  calViewJY.value = jy
  calViewJM.value = jm
}
initJalaliView()

const calendarTitle = computed(() => {
  if (calendarType.value === 'jalali') {
    return `${jalaliMonthNames[calViewJM.value - 1]} ${calViewJY.value}`
  }
  return calendarViewDate.value.toLocaleString('en', { month: 'long', year: 'numeric' })
})
function prevMonth() {
  if (calendarType.value === 'jalali') {
    if (calViewJM.value === 1) { calViewJM.value = 12; calViewJY.value-- }
    else calViewJM.value--
  } else {
    const d = new Date(calendarViewDate.value)
    d.setDate(1); d.setMonth(d.getMonth() - 1)
    calendarViewDate.value = d
  }
}
function nextMonth() {
  if (calendarType.value === 'jalali') {
    if (calViewJM.value === 12) { calViewJM.value = 1; calViewJY.value++ }
    else calViewJM.value++
  } else {
    const d = new Date(calendarViewDate.value)
    d.setDate(1); d.setMonth(d.getMonth() + 1)
    calendarViewDate.value = d
  }
}
function goToday() {
  calendarViewDate.value = new Date()
  initJalaliView()
}

interface CalCell { day: number; currentMonth: boolean; isToday: boolean }

const calendarDays = computed<CalCell[]>(() => {
  if (calendarType.value === 'jalali') {
    return buildJalaliCalendar(calViewJY.value, calViewJM.value)
  }
  return buildGregorianCalendar(calendarViewDate.value.getFullYear(), calendarViewDate.value.getMonth())
})

const weekDays = computed(() =>
  calendarType.value === 'jalali'
    ? ['ش','ی','د','س','چ','پ','ج']
    : ['Su','Mo','Tu','We','Th','Fr','Sa'],
)

function buildGregorianCalendar(year: number, month: number): CalCell[] {
  const today = new Date()
  const firstDow = new Date(year, month, 1).getDay()
  const daysInMonth = new Date(year, month + 1, 0).getDate()
  const cells: CalCell[] = []
  for (let i = 0; i < firstDow; i++) cells.push({ day: 0, currentMonth: false, isToday: false })
  for (let d = 1; d <= daysInMonth; d++) {
    const isToday = year === today.getFullYear() && month === today.getMonth() && d === today.getDate()
    cells.push({ day: d, currentMonth: true, isToday })
  }
  while (cells.length % 7 !== 0) cells.push({ day: 0, currentMonth: false, isToday: false })
  return cells
}

function buildJalaliCalendar(jy: number, jm: number): CalCell[] {
  const today = new Date()
  const [tjy, tjm, tjd] = toJalali(today.getFullYear(), today.getMonth() + 1, today.getDate())
  const [gy, gm, gd] = fromJalali(jy, jm, 1)
  const firstDate = new Date(gy, gm - 1, gd)
  // Jalali week: Sat=0 Sun=1 Mon=2 Tue=3 Wed=4 Thu=5 Fri=6
  const dow = firstDate.getDay() // 0=Sun..6=Sat
  const jalaliDow = (dow + 1) % 7 // Sat=0
  const daysInMonth = jalaliDaysInMonth(jy, jm)
  const cells: CalCell[] = []
  for (let i = 0; i < jalaliDow; i++) cells.push({ day: 0, currentMonth: false, isToday: false })
  for (let d = 1; d <= daysInMonth; d++) {
    const isToday = jy === tjy && jm === tjm && d === tjd
    cells.push({ day: d, currentMonth: true, isToday })
  }
  while (cells.length % 7 !== 0) cells.push({ day: 0, currentMonth: false, isToday: false })
  return cells
}

// ═══════════════════════════════════════════════════════════════════════════════
// ── 3. QUICK LINKS ────────────────────────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

const quickLinks = computed(() => [
  { label: t('home.quick_links.dashboard'),         url: '/dashboard',         icon: LayoutDashboard, shortcut: 'Alt + D' },
  { label: t('home.quick_links.chart_of_accounts'), url: '/chart-of-accounts', icon: BookOpen, shortcut: 'Alt + A' },
  { label: t('home.quick_links.journal_entry'),     url: '/journal-entries',   icon: FileText, shortcut: 'Alt + J' },
  { label: t('home.quick_links.items'),             url: '/items',             icon: Package, shortcut: 'Alt + I' },
  { label: t('home.quick_links.sales'),             url: '/sales',             icon: ShoppingCart, shortcut: 'Alt + S' },
  { label: t('home.quick_links.purchases'),         url: '/purchases',         icon: ShoppingBag, shortcut: 'Alt + P' },
  { label: t('home.quick_links.receipts'),          url: '/receipts',          icon: Receipt, shortcut: 'Alt + R' },
  { label: t('home.quick_links.payments'),          url: '/payments',          icon: CreditCard, shortcut: 'Alt + M' },
])

// ═══════════════════════════════════════════════════════════════════════════════
// ── 4 & 5. CURRENCY RATES & EXCHANGE ─────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

const exchangeAmount = ref<number>(1000)
const exchangeFrom   = ref<string>('')
const exchangeTo     = ref<string>('')
const exchangeResult = ref<string | null>(null)
const exchangeLoading = ref(false)
const exchangeError   = ref('')

watch(() => props.currencies, (list) => {
  if (list.length && !exchangeFrom.value) {
    const base = list.find(c => c.is_base_currency) || list[0]
    exchangeFrom.value = base.code
    exchangeTo.value   = list.find(c => !c.is_base_currency)?.code || list[0].code
  }
}, { immediate: true })

async function doExchange() {
  if (!exchangeFrom.value || !exchangeTo.value) return
  exchangeLoading.value = true; exchangeError.value = ''; exchangeResult.value = null
  try {
    const res = await axios.post('/home/exchange', {
      amount: exchangeAmount.value, from_code: exchangeFrom.value, to_code: exchangeTo.value,
    })
    const toCur = props.currencies.find(c => c.code === exchangeTo.value)
    exchangeResult.value = `${toCur?.symbol ?? ''} ${Number(res.data.result).toLocaleString(undefined, { maximumFractionDigits: 4 })}`
  } catch (e: any) {
    exchangeError.value = e?.response?.data?.error || 'Error'
  } finally { exchangeLoading.value = false }
}

function swapCurrencies() {
  const tmp = exchangeFrom.value; exchangeFrom.value = exchangeTo.value; exchangeTo.value = tmp
  exchangeResult.value = null
}

// ═══════════════════════════════════════════════════════════════════════════════
// ── 6. WEATHER (Open-Meteo, no API key) ───────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

const afghaniProvinces = [
  'Kabul','Kandahar','Herat','Mazar-i-Sharif','Jalalabad','Kunduz','Ghazni',
  'Lashkar Gah','Pul-e-Khumri','Faizabad','Taloqan','Zaranj','Sheberghan',
  'Mehtarlam','Charikar','Mahmud-i-Raqi','Asadabad','Qalat','Nili','Bamyan',
  'Chaghcharan','Gardez','Khost','Maidan Shar','Pul-e-Alam',
]

const weatherCity    = ref('Kabul')
const weatherSearch  = ref('Kabul')
const weatherData    = ref<any>(null)
const weatherLoading = ref(false)
const weatherError   = ref('')

// WMO weather code → emoji icon + description
function wmoInfo(code: number, isDay = true): { emoji: string; label: string } {
  if (code === 0)              return { emoji: isDay ? '☀️' : '🌙', label: 'Clear sky' }
  if (code <= 2)               return { emoji: '⛅', label: 'Partly cloudy' }
  if (code === 3)              return { emoji: '☁️', label: 'Overcast' }
  if (code <= 48)              return { emoji: '🌫️', label: 'Fog' }
  if (code <= 57)              return { emoji: '🌦️', label: 'Drizzle' }
  if (code <= 67)              return { emoji: '🌧️', label: 'Rain' }
  if (code <= 77)              return { emoji: '❄️', label: 'Snow' }
  if (code <= 82)              return { emoji: '🌦️', label: 'Rain showers' }
  if (code <= 86)              return { emoji: '🌨️', label: 'Snow showers' }
  if (code === 95)             return { emoji: '⛈️', label: 'Thunderstorm' }
  if (code >= 96)              return { emoji: '⛈️', label: 'Thunderstorm w/ hail' }
  return { emoji: '🌡️', label: 'Unknown' }
}

const dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']

function forecastDayLabel(dateStr: string, idx: number): string {
  if (idx === 0) return t('home.weather.today')
  const d = new Date(dateStr + 'T00:00:00')
  return dayNames[d.getDay()]
}

function forecastDateLabel(dateStr: string): string {
  const d = new Date(dateStr + 'T00:00:00')
  if (calendarType.value === 'jalali') {
    const [jy, jm, jd] = toJalali(d.getFullYear(), d.getMonth() + 1, d.getDate())
    return `${jd} ${jalaliMonthNames[jm - 1]}`
  }
  return `${d.getDate()} ${gregorianMonthNames[d.getMonth()].slice(0, 3)}`
}

async function fetchWeather(city?: string) {
  const target = city || weatherCity.value
  weatherLoading.value = true; weatherError.value = ''
  try {
    const res = await axios.get('/home/weather', { params: { city: target } })
    weatherData.value = res.data
    weatherCity.value = target
  } catch (e: any) {
    weatherError.value = e?.response?.data?.error || t('home.weather.error')
    weatherData.value = null
  } finally { weatherLoading.value = false }
}

function searchWeather() {
  if (weatherSearch.value.trim()) fetchWeather(weatherSearch.value.trim())
}

onMounted(() => fetchWeather())

const weatherSummary = computed(() => {
  if (weatherData.value?.temp != null) {
    return `${Math.round(weatherData.value.temp)}°C · ${weatherData.value.city}`
  }

  if (weatherLoading.value) {
    return t('home.hero.weather_pending')
  }

  return weatherCity.value
})

const dateConversionOptions = computed(() => [
  {
    key: 'to_jalali',
    from: t('home.date_conversion.gregorian'),
    to: t('home.date_conversion.jalali'),
  },
  {
    key: 'to_gregorian',
    from: t('home.date_conversion.jalali'),
    to: t('home.date_conversion.gregorian'),
  },
])

const heroStats = computed(() => [
  { label: t('home.hero.stats.shortcuts'), value: String(quickLinks.value.length).padStart(2, '0'), icon: Sparkles },
  { label: t('home.hero.stats.currencies'), value: String(props.currencies.length), icon: CircleDollarSign },
  { label: t('home.hero.stats.units'), value: String(props.unitMeasures.length), icon: Scale },
  { label: t('home.hero.stats.city'), value: weatherCity.value, icon: CloudSun },
])

const heroPanelItems = computed(() => [
  { label: t('home.hero.branch_label'), value: activeBranchName.value, icon: MapPin },
  { label: t('home.hero.weather_label'), value: weatherSummary.value, icon: CloudSun },
  { label: t('home.hero.calendar_label'), value: calendarModeLabel.value, icon: CalendarDays },
])

// ═══════════════════════════════════════════════════════════════════════════════
// ── 7. DATE CONVERSION ────────────────────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

const dateConvMode   = ref<'to_jalali' | 'to_gregorian'>('to_jalali')
const dateConvInput  = ref('')
const dateConvResult = ref('')
const dateConvError  = ref('')

function convertDate() {
  dateConvError.value = ''; dateConvResult.value = ''
  const raw = dateConvInput.value.trim()
  if (!raw) return
  try {
    const parts = raw.split(/[-\/]/).map(Number)
    if (parts.length !== 3 || parts.some(isNaN)) throw new Error('bad format')
    if (dateConvMode.value === 'to_jalali') {
      const [gy, gm, gd] = parts
      if (gm < 1 || gm > 12 || gd < 1 || gd > 31) throw new Error('out of range')
      const [jy, jm, jd] = toJalali(gy, gm, gd)
      dateConvResult.value = `${jy}/${String(jm).padStart(2,'0')}/${String(jd).padStart(2,'0')}`
    } else {
      const [jy, jm, jd] = parts
      if (jm < 1 || jm > 12 || jd < 1 || jd > 31) throw new Error('out of range')
      const [gy, gm, gd] = fromJalali(jy, jm, jd)
      dateConvResult.value = `${gy}-${String(gm).padStart(2,'0')}-${String(gd).padStart(2,'0')}`
    }
  } catch {
    dateConvError.value = t('home.date_conversion.invalid')
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
// ── 8. UNIT EXCHANGE ──────────────────────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

const unitAmount  = ref<number>(1)
const unitFromId  = ref<string>('')
const unitToId    = ref<string>('')
const unitResult  = ref<string | null>(null)
const unitLoading = ref(false)
const unitError   = ref('')

const quantityGroups = computed(() => {
  const groups: Record<string, { label: string; units: typeof props.unitMeasures }> = {}
  for (const u of props.unitMeasures) {
    const key = u.quantity_id
    if (!groups[key]) groups[key] = { label: u.quantity?.quantity || key, units: [] }
    groups[key].units.push(u)
  }
  return groups
})

const selectedQuantityId = ref<string>('')

const unitsForQuantity = computed(() =>
  selectedQuantityId.value
    ? props.unitMeasures.filter(u => u.quantity_id === selectedQuantityId.value)
    : [],
)

watch(() => props.unitMeasures, (list) => {
  if (list.length && !selectedQuantityId.value) {
    selectedQuantityId.value = list[0].quantity_id
  }
}, { immediate: true })

watch(selectedQuantityId, () => {
  unitFromId.value = ''; unitToId.value = ''; unitResult.value = null
  const units = unitsForQuantity.value
  if (units.length >= 1) unitFromId.value = units[0].id
  if (units.length >= 2) unitToId.value   = units[1].id
})

async function doUnitConvert() {
  if (!unitFromId.value || !unitToId.value) return
  unitLoading.value = true; unitError.value = ''; unitResult.value = null
  try {
    const res = await axios.post('/home/unit-convert', {
      amount: unitAmount.value, from_id: unitFromId.value, to_id: unitToId.value,
    })
    unitResult.value = `${Number(res.data.result).toLocaleString(undefined, { maximumFractionDigits: 6 })} ${res.data.to_symbol}`
  } catch (e: any) {
    unitError.value = e?.response?.data?.error || 'Error'
  } finally { unitLoading.value = false }
}

// ═══════════════════════════════════════════════════════════════════════════════
// ── 9. CALCULATOR WIDGET ──────────────────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

const calcDisplay = ref('0')
const calcPrevValue = ref<number | null>(null)
const calcOperation = ref<string | null>(null)
const calcNewNumber = ref(true)

function calcAppendNumber(num: string) {
  if (calcNewNumber.value) {
    calcDisplay.value = num
    calcNewNumber.value = false
  } else {
    calcDisplay.value = calcDisplay.value === '0' ? num : calcDisplay.value + num
  }
}

function calcAppendDecimal() {
  if (calcNewNumber.value) {
    calcDisplay.value = '0.'
    calcNewNumber.value = false
  } else if (!calcDisplay.value.includes('.')) {
    calcDisplay.value += '.'
  }
}

function calcClear() {
  calcDisplay.value = '0'
  calcPrevValue.value = null
  calcOperation.value = null
  calcNewNumber.value = true
}

function calcSetOperation(op: string) {
  if (calcPrevValue.value !== null && calcOperation.value && !calcNewNumber.value) {
    calcEquals()
  }
  calcPrevValue.value = parseFloat(calcDisplay.value)
  calcOperation.value = op
  calcNewNumber.value = true
}

function calcEquals() {
  if (calcPrevValue.value === null || calcOperation.value === null) return
  const current = parseFloat(calcDisplay.value)
  let result = 0
  switch (calcOperation.value) {
    case '+': result = calcPrevValue.value + current; break
    case '-': result = calcPrevValue.value - current; break
    case '*': result = calcPrevValue.value * current; break
    case '/': result = current !== 0 ? calcPrevValue.value / current : 0; break
  }
  calcDisplay.value = String(result)
  calcPrevValue.value = null
  calcOperation.value = null
  calcNewNumber.value = true
}

// ═══════════════════════════════════════════════════════════════════════════════
// ── 10. NOTEPAD / STICKY NOTES ────────────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

interface Note {
  id: string
  text: string
  createdAt: Date
}

const notes = ref<Note[]>([])
const newNoteText = ref('')

// Load notes from localStorage
onMounted(() => {
  const stored = localStorage.getItem('home_notes')
  if (stored) {
    try {
      const parsed = JSON.parse(stored)
      notes.value = parsed.map((n: any) => ({ ...n, createdAt: new Date(n.createdAt) }))
    } catch (e) {
      notes.value = []
    }
  }
})

function saveNotes() {
  localStorage.setItem('home_notes', JSON.stringify(notes.value))
}

function addNote() {
  if (!newNoteText.value.trim()) return
  notes.value.unshift({
    id: Date.now().toString(),
    text: newNoteText.value.trim(),
    createdAt: new Date(),
  })
  newNoteText.value = ''
  saveNotes()
}

function deleteNote(id: string) {
  notes.value = notes.value.filter(n => n.id !== id)
  saveNotes()
}

// ═══════════════════════════════════════════════════════════════════════════════
// ── 11. SYSTEM STATUS ─────────────────────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

const systemStatus = ref({
  lastBackup: '2026-04-26 14:30',
  backupStatus: 'success',
  diskUsage: 45,
  updatesAvailable: 2,
})

// ═══════════════════════════════════════════════════════════════════════════════
// ── 12. SHORTCUTS TO FAVORITES ───────────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

interface Favorite {
  id: string
  label: string
  url: string
  icon: string
}

const favorites = ref<Favorite[]>([
  { id: '1', label: 'Dashboard', url: '/dashboard', icon: 'LayoutDashboard' },
  { id: '2', label: 'Sales', url: '/sales', icon: 'ShoppingCart' },
])

const showAddFavorite = ref(false)
const newFavLabel = ref('')
const newFavUrl = ref('')

// Load favorites from localStorage
onMounted(() => {
  const stored = localStorage.getItem('home_favorites')
  if (stored) {
    try {
      favorites.value = JSON.parse(stored)
    } catch (e) {
      // Keep defaults
    }
  }
})

function saveFavorites() {
  localStorage.setItem('home_favorites', JSON.stringify(favorites.value))
}

function addFavorite() {
  if (!newFavLabel.value.trim() || !newFavUrl.value.trim()) return
  favorites.value.push({
    id: Date.now().toString(),
    label: newFavLabel.value.trim(),
    url: newFavUrl.value.trim(),
    icon: 'Star',
  })
  newFavLabel.value = ''
  newFavUrl.value = ''
  showAddFavorite.value = false
  saveFavorites()
}

function removeFavorite(id: string) {
  favorites.value = favorites.value.filter(f => f.id !== id)
  saveFavorites()
}

const iconMap: Record<string, any> = {
  LayoutDashboard, ShoppingCart, ShoppingBag, Package, FileText, BookOpen, Receipt, CreditCard, Star,
}

function getIcon(iconName: string) {
  return iconMap[iconName] || Star
}

// ═══════════════════════════════════════════════════════════════════════════════
// ── 13. FAQ WIDGET ────────────────────────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

interface FAQItem {
  id: string
  question: string
  answer: string
  expanded?: boolean
}

const faqItems = ref<FAQItem[]>([])
const faqSearch = ref('')

// Load FAQ from locale files
onMounted(async () => {
  try {
    const faqModule = await import(`./locales/${locale.value}/faq.json`)
    faqItems.value = faqModule.default.items.map((item: FAQItem) => ({ ...item, expanded: false }))
  } catch (e) {
    console.error('Failed to load FAQ:', e)
    faqItems.value = []
  }
})

const filteredFAQs = computed(() => {
  if (!faqSearch.value.trim()) return faqItems.value
  const search = faqSearch.value.toLowerCase()
  return faqItems.value.filter(item =>
    item.question.toLowerCase().includes(search) ||
    item.answer.toLowerCase().includes(search)
  )
})

function toggleFAQ(id: string) {
  const item = faqItems.value.find(f => f.id === id)
  if (item) item.expanded = !item.expanded
}
</script>

<template>
  <AppLayout>
    <Head :title="t('home.title')" />

    <div class="space-y-5 text-foreground">
      <section class="relative overflow-hidden rounded-[32px] border border-border bg-gradient-to-br from-primary/15 via-background to-secondary/10 shadow-sm">
        <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-primary/40 to-transparent" />
        <div class="absolute -top-24 end-0 h-64 w-64 rounded-full bg-primary/10 blur-3xl" />
        <div class="absolute -bottom-24 start-0 h-56 w-56 rounded-full bg-secondary/15 blur-3xl" />

        <div class="relative grid gap-6 px-5 py-6 lg:px-7 lg:py-7 xl:grid-cols-[1.35fr_.95fr]">
          <div class="space-y-6">
            <div class="flex flex-wrap items-center gap-2">
              <Badge class="rounded-full bg-primary/15 px-3 py-1 text-primary hover:bg-primary/15">
                {{ t('home.hero.eyebrow') }}
              </Badge>
              <Badge variant="secondary" class="rounded-full px-3 py-1 text-xs">
                {{ activeBranchName }}
              </Badge>
            </div>

            <div class="space-y-3">
              <h1 class="text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">
                {{ t('home.hero.greeting', { name: firstName }) }}
              </h1>
              <p class="max-w-2xl text-sm leading-7 text-muted-foreground sm:text-base">
                {{ t('home.hero.description') }}
              </p>
            </div>

            <div class="mx-auto grid max-w-5xl gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <div
                v-for="stat in heroStats"
                :key="stat.label"
                class="rounded-2xl border border-border/70 bg-background/80 p-4 shadow-sm backdrop-blur"
              >
                <div class="flex justify-center">
                  <component :is="stat.icon" class="size-5 text-primary" />
                </div>
                <p class="mt-4 text-2xl font-semibold tracking-tight text-foreground text-center">{{ stat.value }}</p>
                <p class="mt-1 text-xs text-muted-foreground text-center">{{ stat.label }}</p>
              </div>
            </div>

            <div class="flex flex-wrap gap-3">
              <Link
                href="/dashboard"
                class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90"
              >
                <LayoutDashboard class="size-4" />
                {{ t('home.hero.open_dashboard') }}
              </Link>
              <Link
                href="/reports"
                class="inline-flex items-center gap-2 rounded-xl border border-border bg-background/90 px-4 py-2.5 text-sm font-medium text-foreground transition hover:bg-muted"
              >
                <FileText class="size-4" />
                {{ t('home.hero.open_reports') }}
              </Link>
            </div>
          </div>

          <div class="rounded-[28px] border border-white/60 bg-background/85 p-5 shadow-sm backdrop-blur dark:border-white/10 dark:bg-background/70">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-primary">
                  {{ t('home.hero.workspace') }}
                </p>
                <h2 class="mt-1 text-xl font-semibold tracking-tight">{{ t('home.hero.live_snapshot') }}</h2>
              </div>
              <Badge variant="secondary" class="rounded-full px-3 py-1">
                {{ calendarModeLabel }}
              </Badge>
            </div>

            <div class="mt-5 grid gap-5 sm:grid-cols-[auto,1fr] sm:items-center">
              <div class="mx-auto flex flex-col items-center gap-2">
                <canvas ref="clockCanvas" width="200" height="200" class="rounded-full" />
                <div class="text-center">
                  <div class="flex items-end justify-center gap-2 font-mono text-foreground" dir="ltr">
                    <p class="text-3xl font-semibold tracking-[0.18em]">
                      {{ digitalTime || '--:--:--' }}
                    </p>
                    <span class="pb-0.5 text-xl font-semibold">{{ digitalMeridiem }}</span>
                  </div>
                  <p class="mt-1 text-sm font-medium text-muted-foreground">{{ dayName }}</p>
                  <p class="mt-1 text-[11px] text-muted-foreground">{{ t('home.clock.timezone') }}</p>
                </div>
              </div>

              <div
                class="w-full space-y-4"
                :class="isRTL ? '' : 'mx-auto max-w-[25rem]'"
              >
                <div class="grid grid-cols-3 gap-2">
                  <div class="min-h-[9rem] rounded-2xl border border-border/70 bg-muted/35 px-2 py-3 text-center">
                    <p class="text-base leading-none">{{ gregorianDisplay.icon }}</p>
                    <p class="mt-1 text-[10px] text-muted-foreground">{{ gregorianDisplay.label }}</p>
                    <p class="mt-2 text-xl font-semibold leading-none">{{ gregorianDisplay.day }}</p>
                    <p class="mt-1 text-[11px] font-medium">{{ gregorianDisplay.monthName }}</p>
                    <p class="mt-1 text-[10px] text-muted-foreground" dir="ltr">{{ gregorianDisplay.full }}</p>
                  </div>
                  <div class="min-h-[9rem] rounded-2xl border border-border/70 bg-muted/35 px-2 py-3 text-center">
                    <p class="text-base leading-none">{{ hijriDisplay.icon }}</p>
                    <p class="mt-1 text-[10px] text-muted-foreground">{{ hijriDisplay.label }}</p>
                    <p class="mt-2 text-xl font-semibold leading-none">{{ hijriDisplay.day }}</p>
                    <p class="mt-1 text-[11px] font-medium">{{ hijriDisplay.monthName }}</p>
                    <p class="mt-1 text-[10px] text-muted-foreground" dir="ltr">{{ hijriDisplay.full }}</p>
                  </div>
                  <div class="min-h-[9rem] rounded-2xl border border-border/70 bg-muted/35 px-2 py-3 text-center">
                    <p class="text-base leading-none">{{ jalaliDisplay.icon }}</p>
                    <p class="mt-1 text-[10px] text-muted-foreground">{{ jalaliDisplay.label }}</p>
                    <p class="mt-2 text-xl font-semibold leading-none">{{ jalaliDisplay.day }}</p>
                    <p class="mt-1 text-[11px] font-medium">{{ jalaliDisplay.monthName }}</p>
                    <p class="mt-1 text-[10px] text-muted-foreground" dir="ltr">{{ jalaliDisplay.full }}</p>
                  </div>
                </div>

                <div class="grid gap-2 sm:grid-cols-3">
                  <div
                    v-for="item in heroPanelItems"
                    :key="item.label"
                    class="flex min-h-[7.25rem] flex-col items-center justify-center rounded-2xl border border-border/70 bg-background/70 p-3 text-center"
                  >
                    <div class="flex flex-col items-center gap-1 text-xs text-muted-foreground">
                      <component :is="item.icon" class="size-3.5 text-primary" />
                      <span>{{ item.label }}</span>
                    </div>
                    <p class="mt-2 text-sm font-medium leading-6 text-foreground">{{ item.value }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
        <!-- Column 1: Quick Links, Currency & Unit Exchange, Weather -->
        <div class="space-y-4">
          <Card class="overflow-hidden rounded-3xl border border-border/80 bg-card/95 shadow-sm">
            <CardHeader class="border-b border-border/60 pb-4">
              <div class="flex items-start gap-3">
                <div class="flex size-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                  <Sparkles class="size-5" />
                </div>
                <div class="space-y-1">
                  <CardTitle class="text-base">{{ t('home.quick_links.title') }}</CardTitle>
                  <CardDescription>{{ t('home.quick_links.description') }}</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="pt-3">
              <div class="grid gap-2 sm:grid-cols-2">
                <Link
                  v-for="link in quickLinks"
                  :key="link.url"
                  :href="link.url"
                  class="group rounded-2xl border border-border/70 bg-background/70 p-2 transition hover:-translate-y-0.5 hover:border-primary/40 hover:bg-muted/60"
                >
                  <div class="flex items-start justify-between gap-3">
                    <div class="flex size-9 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                      <component :is="link.icon" class="size-4" />
                    </div>
                    <ArrowUpRight class="size- text-muted-foreground transition group-hover:text-primary" />
                  </div>
                  <div class="mt-">
                    <p class="text-sm font-medium text-foreground">{{ link.label }}</p>
                    <!-- <p class="mt-1 text-xs text-muted-foreground">{{ t('home.quick_links.open_module') }}</p> -->
                    <p class="mt-1 text-xs text-muted-foreground"><Kbd>{{ link.shortcut }}</Kbd></p>
                  </div>
                </Link>
              </div>
            </CardContent>
          </Card>

          <div class="grid gap-4">
            <Card class="rounded-3xl border border-border/80 bg-card/95 shadow-sm">
              <CardHeader class="pb-4">
                <div class="flex items-start gap-3">
                  <div class="flex size-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <ArrowLeftRight class="size-5" />
                  </div>
                  <div class="space-y-1">
                    <CardTitle class="text-base">{{ t('home.currency_exchange.title') }}</CardTitle>
                    <CardDescription>{{ t('home.currency_exchange.description') }}</CardDescription>
                  </div>
                </div>
              </CardHeader>
              <CardContent class="space-y-4">
                <div>
                  <label class="mb-1 block text-xs text-muted-foreground">{{ t('home.currency_exchange.amount') }}</label>
                  <Input v-model.number="exchangeAmount" type="number" min="0" class="h-10 text-sm" />
                </div>
                <div class="grid gap-3 sm:grid-cols-[1fr_auto_1fr] sm:items-end">
                  <div>
                    <label class="mb-1 block text-xs text-muted-foreground">{{ t('home.currency_exchange.from') }}</label>
                    <Select v-model="exchangeFrom">
                      <SelectTrigger class="h-10 text-sm">
                        <SelectValue :placeholder="t('home.currency_exchange.select_currency')" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="cur in currencies" :key="cur.code" :value="cur.code">
                          {{ cur.code }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <Button variant="ghost" size="icon" class="h-10 w-10 shrink-0 rounded-xl border border-border" @click="swapCurrencies">
                    <ArrowLeftRight class="size-4" />
                  </Button>
                  <div>
                    <label class="mb-1 block text-xs text-muted-foreground">{{ t('home.currency_exchange.to') }}</label>
                    <Select v-model="exchangeTo">
                      <SelectTrigger class="h-10 text-sm">
                        <SelectValue :placeholder="t('home.currency_exchange.select_currency')" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="cur in currencies" :key="cur.code" :value="cur.code">
                          {{ cur.code }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>
                <Button class="h-10 w-full text-sm" :disabled="exchangeLoading" @click="doExchange">
                  <Loader2 v-if="exchangeLoading" class="me-1 size-4 animate-spin" />
                  {{ t('home.currency_exchange.convert') }}
                </Button>
                <div v-if="exchangeResult" class="rounded-2xl bg-muted px-3 py-3 text-center text-sm font-semibold">
                  {{ exchangeResult }}
                </div>
                <p v-if="exchangeError" class="text-center text-xs text-destructive">{{ exchangeError }}</p>
              </CardContent>
            </Card>

            <Card class="rounded-3xl border border-border/80 bg-card/95 shadow-sm">
              <CardHeader class="pb-4">
                <div class="flex items-start gap-3">
                  <div class="flex size-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <Scale class="size-5" />
                  </div>
                  <div class="space-y-1">
                    <CardTitle class="text-base">{{ t('home.unit_exchange.title') }}</CardTitle>
                    <CardDescription>{{ t('home.unit_exchange.description') }}</CardDescription>
                  </div>
                </div>
              </CardHeader>
              <CardContent class="space-y-4">
                <div v-if="props.unitMeasures.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                  {{ t('home.unit_exchange.no_units') }}
                </div>
                <template v-else>
                  <div>
                    <label class="mb-1 block text-xs text-muted-foreground">{{ t('home.unit_exchange.type') }}</label>
                    <Select v-model="selectedQuantityId">
                      <SelectTrigger class="h-10 text-sm">
                        <SelectValue :placeholder="t('home.unit_exchange.select_type')" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="(group, qid) in quantityGroups" :key="String(qid)" :value="String(qid)">
                          {{ group.label }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div>
                    <label class="mb-1 block text-xs text-muted-foreground">{{ t('home.unit_exchange.amount') }}</label>
                    <Input v-model.number="unitAmount" type="number" class="h-10 text-sm" />
                  </div>
                  <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                      <label class="mb-1 block text-xs text-muted-foreground">{{ t('home.unit_exchange.from') }}</label>
                      <Select v-model="unitFromId">
                        <SelectTrigger class="h-10 text-sm">
                          <SelectValue :placeholder="t('home.unit_exchange.select_unit')" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem v-for="u in unitsForQuantity" :key="u.id" :value="u.id">
                            {{ u.name }} ({{ u.symbol }})
                          </SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                    <div>
                      <label class="mb-1 block text-xs text-muted-foreground">{{ t('home.unit_exchange.to') }}</label>
                      <Select v-model="unitToId">
                        <SelectTrigger class="h-10 text-sm">
                          <SelectValue :placeholder="t('home.unit_exchange.select_unit')" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem v-for="u in unitsForQuantity" :key="u.id" :value="u.id">
                            {{ u.name }} ({{ u.symbol }})
                          </SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>
                  <Button class="h-10 w-full text-sm" :disabled="unitLoading" @click="doUnitConvert">
                    <Loader2 v-if="unitLoading" class="me-1 size-4 animate-spin" />
                    {{ t('home.unit_exchange.convert') }}
                  </Button>
                  <div v-if="unitResult" class="rounded-2xl bg-muted px-3 py-3 text-center text-sm font-semibold font-mono">
                    {{ unitResult }}
                  </div>
                  <p v-if="unitError" class="text-center text-xs text-destructive">{{ unitError }}</p>
                </template>
              </CardContent>
            </Card>
          </div>

          <Card class="overflow-hidden rounded-3xl border border-border/80 bg-card/95 shadow-sm">
            <CardHeader class="border-b border-border/60 pb-4">
              <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-start gap-3">
                  <div class="flex size-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <CloudSun class="size-5" />
                  </div>
                  <div class="space-y-1">
                    <CardTitle class="text-base">
                      {{ weatherData ? `${weatherData.city}, ${weatherData.country}` : t('home.weather.title') }}
                    </CardTitle>
                    <CardDescription>{{ t('home.weather.description') }}</CardDescription>
                  </div>
                </div>

                <div class="flex gap-2">
                  <Select v-model="weatherSearch" class="w-40">
                    <SelectTrigger class="h-9 min-w-[150px] text-xs">
                      <SelectValue :placeholder="t('home.weather.city')" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="p in afghaniProvinces" :key="p" :value="p">{{ p }}</SelectItem>
                    </SelectContent>
                  </Select>
                  <Button size="icon" variant="outline" class="h-9 w-9 rounded-xl" :disabled="weatherLoading" @click="searchWeather">
                    <Loader2 v-if="weatherLoading" class="size-4 animate-spin" />
                    <Search v-else class="size-4" />
                  </Button>
                </div>
              </div>
            </CardHeader>

            <CardContent class="pt-5">
              <div v-if="weatherError" class="text-sm text-destructive">{{ weatherError }}</div>

              <div v-else-if="weatherLoading && !weatherData" class="text-sm text-muted-foreground">
                {{ t('home.weather.loading') }}
              </div>

              <div v-else-if="weatherData" class="space-y-4">
                <div class="rounded-[24px] bg-muted/50 p-4">
                  <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                      <p class="text-4xl font-semibold tracking-tight">{{ weatherData.temp?.toFixed(1) }}°C</p>
                      <span class="mt-2 inline-flex rounded-full bg-background px-3 py-1 text-xs font-medium text-muted-foreground">
                        {{ wmoInfo(weatherData.code, weatherData.is_day).label }}
                      </span>
                    </div>
                    <span class="text-6xl leading-none">{{ wmoInfo(weatherData.code, weatherData.is_day).emoji }}</span>
                  </div>
                  <div v-if="weatherData.forecast?.length" class="mt-4 flex flex-wrap gap-2">
                    <span class="rounded-full bg-background px-3 py-1 text-xs">
                      ↑ {{ weatherData.forecast[0].max?.toFixed(0) }}°
                    </span>
                    <span class="rounded-full bg-background px-3 py-1 text-xs">
                      ↓ {{ weatherData.forecast[0].min?.toFixed(0) }}°
                    </span>
                  </div>
                </div>

                <div v-if="weatherData.forecast?.length">
                  <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                    {{ t('home.weather.forecast') }}
                  </p>
                  <div class="flex gap-2 overflow-x-auto pb-1">
                    <div
                      v-for="(day, idx) in weatherData.forecast"
                      :key="day.date"
                      class="flex min-w-[70px] flex-shrink-0 flex-col items-center gap-1 rounded-2xl border px-3 py-3 text-center text-xs"
                      :class="idx === 0 ? 'border-primary bg-primary/10' : 'border-border bg-muted/40'"
                    >
                      <span class="font-semibold">{{ forecastDayLabel(day.date, Number(idx)) }}</span>
                      <span class="text-[10px] text-muted-foreground">{{ forecastDateLabel(day.date) }}</span>
                      <span class="text-2xl leading-none">{{ wmoInfo(day.code).emoji }}</span>
                      <div class="flex gap-1 text-[11px]">
                        <span class="font-bold">{{ day.max?.toFixed(0) }}°</span>
                        <span class="text-muted-foreground">{{ day.min?.toFixed(0) }}°</span>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                  <div class="rounded-2xl bg-muted/50 p-3 text-center text-xs">
                    <p class="text-muted-foreground">{{ t('home.weather.feels_like') }}</p>
                    <p class="mt-1 text-sm font-semibold">{{ weatherData.feels_like?.toFixed(0) }}°C</p>
                  </div>
                  <div class="rounded-2xl bg-muted/50 p-3 text-center text-xs">
                    <p class="text-muted-foreground">{{ t('home.weather.humidity') }}</p>
                    <p class="mt-1 text-sm font-semibold">{{ weatherData.humidity }}%</p>
                  </div>
                  <div class="rounded-2xl bg-muted/50 p-3 text-center text-xs">
                    <p class="text-muted-foreground">{{ t('home.weather.wind') }}</p>
                    <p class="mt-1 text-sm font-semibold">{{ weatherData.wind_speed?.toFixed(1) }} km/h</p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Column 2: Calendar, Currency Rates, Calculator, Notes -->
        <div class="space-y-4">
          <Card class="rounded-3xl border border-border/80 bg-card/95 shadow-sm">
            <CardHeader class="pb-4">
              <div class="flex items-start gap-3">
                <div class="flex size-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                  <CalendarDays class="size-5" />
                </div>
                <div class="space-y-1">
                  <CardTitle class="text-base">{{ t('home.calendar.title') }}</CardTitle>
                  <CardDescription>{{ t('home.calendar.description') }}</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="flex items-center justify-between">
                <Button
                  variant="ghost"
                  size="icon"
                  class="h-8 w-8 rounded-full"
                  :aria-label="t('home.calendar.prev')"
                  @click="prevMonth"
                >
                  ‹
                </Button>
                <div class="text-center">
                  <p class="text-sm font-medium">{{ calendarTitle }}</p>
                  <p class="text-xs text-muted-foreground">{{ calendarModeLabel }}</p>
                </div>
                <Button
                  variant="ghost"
                  size="icon"
                  class="h-8 w-8 rounded-full"
                  :aria-label="t('home.calendar.next')"
                  @click="nextMonth"
                >
                  ›
                </Button>
              </div>

              <div class="grid grid-cols-7 gap-y-1">
                <div v-for="wd in weekDays" :key="wd" class="py-1 text-center text-xs font-medium text-muted-foreground">
                  {{ wd }}
                </div>
                <div
                  v-for="(cell, idx) in calendarDays"
                  :key="idx"
                  class="flex h-9 items-center justify-center rounded-xl text-xs transition-colors"
                  :class="{
                    'text-muted-foreground/30': !cell.currentMonth,
                    'bg-primary text-primary-foreground font-bold shadow-sm': cell.isToday,
                    'hover:bg-muted cursor-pointer': cell.currentMonth && !cell.isToday,
                  }"
                >
                  {{ cell.day || '' }}
                </div>
              </div>

              <Button variant="outline" size="sm" class="h-9 w-full rounded-xl text-sm" @click="goToday">
                {{ t('home.calendar.today') }}
              </Button>
            </CardContent>
          </Card>

          <Card class="rounded-3xl border border-border/80 bg-card/95 shadow-sm">
            <CardHeader class="pb-4">
              <div class="flex items-start gap-3">
                <div class="flex size-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                  <CircleDollarSign class="size-5" />
                </div>
                <div class="space-y-1">
                  <CardTitle class="text-base">{{ t('home.currency_rates.title') }}</CardTitle>
                  <CardDescription>{{ t('home.currency_rates.description') }}</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <div v-if="currencies.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                {{ t('home.currency_rates.no_data') }}
              </div>
              <div v-else class="max-h-72 overflow-auto">
                <table class="w-full text-xs">
                  <thead>
                    <tr class="border-b border-border">
                      <th class="px-1 py-2 text-start font-medium text-muted-foreground">{{ t('home.currency_rates.code') }}</th>
                      <th class="px-1 py-2 text-start font-medium text-muted-foreground">{{ t('home.currency_rates.name') }}</th>
                      <th class="px-1 py-2 text-end font-medium text-muted-foreground">{{ t('home.currency_rates.rate') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="cur in currencies" :key="cur.id" class="border-b border-border/50 last:border-0 hover:bg-muted/40">
                      <td class="px-1 py-2">
                        <div class="flex items-center gap-1.5">
                          <span class="font-mono font-semibold">{{ cur.code }}</span>
                          <Badge v-if="cur.is_base_currency" variant="secondary" class="h-4 px-1 py-0 text-[10px]">
                            {{ t('home.currency_rates.base') }}
                          </Badge>
                        </div>
                      </td>
                      <td class="px-1 py-2 text-muted-foreground">{{ cur.name }}</td>
                      <td class="px-1 py-2 text-end font-mono">
                        {{ cur.symbol }} {{ Number(cur.exchange_rate).toLocaleString(undefined, { maximumFractionDigits: 4 }) }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
<!-- 
          <Card class="rounded-3xl border border-border/80 bg-card/95 shadow-sm">
            <CardHeader class="pb-4">
              <div class="flex items-start gap-3">
                <div class="flex size-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                  <NotebookText class="size-5" />
                </div>
                <div class="space-y-1">
                  <CardTitle class="text-base">{{ t('home.date_conversion.title') }}</CardTitle>
                  <CardDescription>{{ t('home.date_conversion.description') }}</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="flex overflow-hidden rounded-xl border border-border text-xs">
                <button
                  v-for="option in dateConversionOptions"
                  :key="option.key"
                  class="flex flex-1 items-center justify-center gap-2 py-2 transition-colors"
                  :class="dateConvMode === option.key ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                  @click="dateConvMode = option.key as 'to_jalali' | 'to_gregorian'; dateConvResult = ''; dateConvError = ''"
                >
                  <ArrowRight v-if="isRTL" class="size-3.5 shrink-0" />
                  <span>{{ option.from }}</span>
                  <ArrowRight v-if="!isRTL" class="size-3.5 shrink-0" />
                  <span>{{ option.to }}</span>
                </button>
              </div>
              <div>
                <label class="mb-1 block text-xs text-muted-foreground">
                  {{ dateConvMode === 'to_jalali' ? 'YYYY-MM-DD (Gregorian)' : 'YYYY/MM/DD (Jalali)' }}
                </label>
                <Input
                  v-model="dateConvInput"
                  :placeholder="dateConvMode === 'to_jalali' ? '2024-03-20' : '1402/12/29'"
                  class="h-10 text-sm font-mono"
                  @keyup.enter="convertDate"
                />
              </div>
              <Button class="h-10 w-full text-sm" @click="convertDate">
                {{ t('home.date_conversion.convert') }}
              </Button>
              <div v-if="dateConvResult" class="rounded-2xl bg-muted px-3 py-3 text-center font-mono text-sm font-semibold">
                {{ dateConvResult }}
              </div>
              <p v-if="dateConvError" class="text-center text-xs text-destructive">{{ dateConvError }}</p>
            </CardContent>
          </Card> -->

          <!-- Calculator Widget -->
          <!-- <Card class="rounded-3xl border border-border/80 bg-card/95 shadow-sm">
            <CardHeader class="pb-4">
              <div class="flex items-start gap-3">
                <div class="flex size-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                  <Calculator class="size-5" />
                </div>
                <div class="space-y-1">
                  <CardTitle class="text-base">{{ t('home.calculator.title') }}</CardTitle>
                  <CardDescription>{{ t('home.calculator.description') }}</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="space-y-3">
              <div class="rounded-2xl bg-muted/50 p-4 text-end font-mono text-2xl font-semibold">
                {{ calcDisplay }}
              </div>
              <div class="grid grid-cols-4 gap-2">
                <Button variant="outline" class="h-12 text-base" @click="calcAppendNumber('7')">7</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcAppendNumber('8')">8</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcAppendNumber('9')">9</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcSetOperation('/')">/</Button>
                
                <Button variant="outline" class="h-12 text-base" @click="calcAppendNumber('4')">4</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcAppendNumber('5')">5</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcAppendNumber('6')">6</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcSetOperation('*')">×</Button>
                
                <Button variant="outline" class="h-12 text-base" @click="calcAppendNumber('1')">1</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcAppendNumber('2')">2</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcAppendNumber('3')">3</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcSetOperation('-')">-</Button>
                
                <Button variant="outline" class="h-12 text-base" @click="calcAppendNumber('0')">0</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcAppendDecimal()">.</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcClear()">C</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcSetOperation('+')">+</Button>
              </div>
              <Button class="h-12 w-full text-base" @click="calcEquals()">=</Button>
            </CardContent>
          </Card> -->

          <!-- Notepad / Sticky Notes Widget -->
          <!-- <Card class="rounded-3xl border border-border/80 bg-card/95 shadow-sm">
            <CardHeader class="pb-4">
              <div class="flex items-start gap-3">
                <div class="flex size-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                  <StickyNote class="size-5" />
                </div>
                <div class="space-y-1">
                  <CardTitle class="text-base">{{ t('home.notes.title') }}</CardTitle>
                  <CardDescription>{{ t('home.notes.description') }}</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="space-y-3">
              <div class="flex gap-2">
                <Input
                  v-model="newNoteText"
                  :placeholder="t('home.notes.add_placeholder')"
                  class="h-10 text-sm"
                  @keyup.enter="addNote"
                />
                <Button size="icon" class="h-10 w-10 shrink-0" @click="addNote">
                  <Plus class="size-4" />
                </Button>
              </div>
              
              <div v-if="notes.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                {{ t('home.notes.no_notes') }}
              </div>
              
              <div v-else class="space-y-2 max-h-[300px] overflow-y-auto">
                <div
                  v-for="note in notes"
                  :key="note.id"
                  class="group rounded-2xl border border-border/70 bg-muted/30 p-3 transition hover:bg-muted/50"
                >
                  <div class="flex items-start justify-between gap-2">
                    <p class="flex-1 text-sm">{{ note.text }}</p>
                    <Button
                      variant="ghost"
                      size="icon"
                      class="h-6 w-6 shrink-0 opacity-0 transition group-hover:opacity-100"
                      @click="deleteNote(note.id)"
                    >
                      <Trash2 class="size-3.5" />
                    </Button>
                  </div>
                  <p class="mt-1 text-[10px] text-muted-foreground">
                    {{ note.createdAt.toLocaleString() }}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>  -->

          <!-- Shortcuts to Favorites Widget -->
          <Card class="rounded-3xl border border-border/80 bg-card/95 shadow-sm">
            <CardHeader class="pb-4">
              <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                  <div class="flex size-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <Star class="size-5" />
                  </div>
                  <div class="space-y-1">
                    <CardTitle class="text-base">{{ t('home.favorites.title') }}</CardTitle>
                    <CardDescription>{{ t('home.favorites.description') }}</CardDescription>
                  </div>
                </div>
                <Button
                  size="icon"
                  variant="outline"
                  class="h-8 w-8 shrink-0 rounded-xl"
                  @click="showAddFavorite = !showAddFavorite"
                >
                  <Plus v-if="!showAddFavorite" class="size-4" />
                  <X v-else class="size-4" />
                </Button>
              </div>
            </CardHeader>
            <CardContent class="space-y-3">
              <div v-if="showAddFavorite" class="space-y-2 rounded-2xl border border-border/70 bg-muted/30 p-3">
                <Input
                  v-model="newFavLabel"
                  :placeholder="t('home.favorites.add_label')"
                  class="h-9 text-sm"
                />
                <Input
                  v-model="newFavUrl"
                  :placeholder="t('home.favorites.add_url')"
                  class="h-9 text-sm"
                />
                <Button size="sm" class="h-9 w-full" @click="addFavorite">
                  <Check class="me-1 size-3.5" />
                  {{ t('home.favorites.add_button') }}
                </Button>
              </div>

              <div v-if="favorites.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                {{ t('home.favorites.no_favorites') }}
              </div>

              <div v-else class="grid gap-2 sm:grid-cols-2">
                <Link
                  v-for="fav in favorites"
                  :key="fav.id"
                  :href="fav.url"
                  class="group relative rounded-2xl border border-border/70 bg-background/70 p-3 transition hover:-translate-y-0.5 hover:border-primary/40 hover:bg-muted/60"
                >
                  <div class="flex items-center gap-3">
                    <div class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                      <component :is="getIcon(fav.icon)" class="size-4" />
                    </div>
                    <p class="flex-1 text-sm font-medium">{{ fav.label }}</p>
                    <Button
                      variant="ghost"
                      size="icon"
                      class="h-6 w-6 shrink-0 opacity-0 transition group-hover:opacity-100"
                      @click.prevent="removeFavorite(fav.id)"
                    >
                      <X class="size-3.5" />
                    </Button>
                  </div>
                </Link>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Column 3: Date Conversion, Calculator, Notes, System Status, FAQ -->
        <div class="space-y-4">
          <Card class="rounded-3xl border border-border/80 bg-card/95 shadow-sm">
            <CardHeader class="pb-4">
              <div class="flex items-start gap-3">
                <div class="flex size-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                  <NotebookText class="size-5" />
                </div>
                <div class="space-y-1">
                  <CardTitle class="text-base">{{ t('home.date_conversion.title') }}</CardTitle>
                  <CardDescription>{{ t('home.date_conversion.description') }}</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="flex overflow-hidden rounded-xl border border-border text-xs">
                <button
                  v-for="option in dateConversionOptions"
                  :key="option.key"
                  class="flex flex-1 items-center justify-center gap-2 py-2 transition-colors"
                  :class="dateConvMode === option.key ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                  @click="dateConvMode = option.key as 'to_jalali' | 'to_gregorian'; dateConvResult = ''; dateConvError = ''"
                >
                  <ArrowRight v-if="isRTL" class="size-3.5 shrink-0" />
                  <span>{{ option.from }}</span>
                  <ArrowRight v-if="!isRTL" class="size-3.5 shrink-0" />
                  <span>{{ option.to }}</span>
                </button>
              </div>
              <div>
                <label class="mb-1 block text-xs text-muted-foreground">
                  {{ dateConvMode === 'to_jalali' ? 'YYYY-MM-DD (Gregorian)' : 'YYYY/MM/DD (Jalali)' }}
                </label>
                <Input
                  v-model="dateConvInput"
                  :placeholder="dateConvMode === 'to_jalali' ? '2024-03-20' : '1402/12/29'"
                  class="h-10 text-sm font-mono"
                  @keyup.enter="convertDate"
                />
              </div>
              <Button class="h-10 w-full text-sm" @click="convertDate">
                {{ t('home.date_conversion.convert') }}
              </Button>
              <div v-if="dateConvResult" class="rounded-2xl bg-muted px-3 py-3 text-center font-mono text-sm font-semibold">
                {{ dateConvResult }}
              </div>
              <p v-if="dateConvError" class="text-center text-xs text-destructive">{{ dateConvError }}</p>
            </CardContent>
          </Card>

          <!-- Calculator Widget -->
          <!-- <Card class="rounded-3xl border border-border/80 bg-card/95 shadow-sm">
            <CardHeader class="pb-4">
              <div class="flex items-start gap-3">
                <div class="flex size-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                  <Calculator class="size-5" />
                </div>
                <div class="space-y-1">
                  <CardTitle class="text-base">{{ t('home.calculator.title') }}</CardTitle>
                  <CardDescription>{{ t('home.calculator.description') }}</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="space-y-3">
              <div class="rounded-2xl bg-muted/50 p-4 text-end font-mono text-2xl font-semibold">
                {{ calcDisplay }}
              </div>
              <div class="grid grid-cols-4 gap-2">
                <Button variant="outline" class="h-12 text-base" @click="calcAppendNumber('7')">7</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcAppendNumber('8')">8</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcAppendNumber('9')">9</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcSetOperation('/')">/</Button>
                
                <Button variant="outline" class="h-12 text-base" @click="calcAppendNumber('4')">4</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcAppendNumber('5')">5</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcAppendNumber('6')">6</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcSetOperation('*')">×</Button>
                
                <Button variant="outline" class="h-12 text-base" @click="calcAppendNumber('1')">1</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcAppendNumber('2')">2</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcAppendNumber('3')">3</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcSetOperation('-')">-</Button>
                
                <Button variant="outline" class="h-12 text-base" @click="calcAppendNumber('0')">0</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcAppendDecimal()">.</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcClear()">C</Button>
                <Button variant="outline" class="h-12 text-base" @click="calcSetOperation('+')">+</Button>
              </div>
              <Button class="h-12 w-full text-base" @click="calcEquals()">=</Button>
            </CardContent>
          </Card> -->

          <!-- Notepad / Sticky Notes Widget -->
          <Card class="rounded-3xl border border-border/80 bg-card/95 shadow-sm">
            <CardHeader class="pb-4">
              <div class="flex items-start gap-3">
                <div class="flex size-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                  <StickyNote class="size-5" />
                </div>
                <div class="space-y-1">
                  <CardTitle class="text-base">{{ t('home.notes.title') }}</CardTitle>
                  <CardDescription>{{ t('home.notes.description') }}</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="space-y-3">
              <div class="flex gap-2">
                <Input
                  v-model="newNoteText"
                  :placeholder="t('home.notes.add_placeholder')"
                  class="h-10 text-sm"
                  @keyup.enter="addNote"
                />
                <Button size="icon" class="h-10 w-10 shrink-0" @click="addNote">
                  <Plus class="size-4" />
                </Button>
              </div>
              
              <div v-if="notes.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                {{ t('home.notes.no_notes') }}
              </div>
              
              <div v-else class="space-y-2 max-h-[300px] overflow-y-auto">
                <div
                  v-for="note in notes"
                  :key="note.id"
                  class="group rounded-2xl border border-border/70 bg-muted/30 p-3 transition hover:bg-muted/50"
                >
                  <div class="flex items-start justify-between gap-2">
                    <p class="flex-1 text-sm">{{ note.text }}</p>
                    <Button
                      variant="ghost"
                      size="icon"
                      class="h-6 w-6 shrink-0 opacity-0 transition group-hover:opacity-100"
                      @click="deleteNote(note.id)"
                    >
                      <Trash2 class="size-3.5" />
                    </Button>
                  </div>
                  <p class="mt-1 text-[10px] text-muted-foreground">
                    {{ note.createdAt.toLocaleString() }}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- System Status Widget -->
          <Card class="rounded-3xl border border-border/80 bg-card/95 shadow-sm">
            <CardHeader class="pb-4">
              <div class="flex items-start gap-3">
                <div class="flex size-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                  <Database class="size-5" />
                </div>
                <div class="space-y-1">
                  <CardTitle class="text-base">{{ t('home.system_status.title') }}</CardTitle>
                  <CardDescription>{{ t('home.system_status.description') }}</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="space-y-3">
              <div class="rounded-2xl border border-border/70 bg-muted/30 p-3">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <div class="flex size-8 items-center justify-center rounded-full bg-green-500/10">
                      <Check class="size-4 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                      <p class="text-sm font-medium">{{ t('home.system_status.backup') }}</p>
                      <p class="text-xs text-muted-foreground">{{ systemStatus.lastBackup }}</p>
                    </div>
                  </div>
                  <Badge variant="secondary" class="rounded-full">
                    {{ systemStatus.backupStatus === 'success' ? t('home.system_status.backup_success') : t('home.system_status.backup_pending') }}
                  </Badge>
                </div>
              </div>

              <div class="rounded-2xl border border-border/70 bg-muted/30 p-3">
                <div class="flex items-center justify-between mb-2">
                  <div class="flex items-center gap-2">
                    <HardDrive class="size-4 text-muted-foreground" />
                    <p class="text-sm font-medium">{{ t('home.system_status.disk_usage') }}</p>
                  </div>
                  <span class="text-sm font-semibold">{{ systemStatus.diskUsage }}%</span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                  <div
                    class="h-full rounded-full transition-all"
                    :class="systemStatus.diskUsage > 80 ? 'bg-destructive' : systemStatus.diskUsage > 60 ? 'bg-yellow-500' : 'bg-primary'"
                    :style="{ width: `${systemStatus.diskUsage}%` }"
                  />
                </div>
              </div>

              <div class="rounded-2xl border border-border/70 bg-muted/30 p-3">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <div class="flex size-8 items-center justify-center rounded-full bg-blue-500/10">
                      <AlertCircle class="size-4 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                      <p class="text-sm font-medium">{{ t('home.system_status.updates') }}</p>
                      <p class="text-xs text-muted-foreground">
                        {{ systemStatus.updatesAvailable }} update{{ systemStatus.updatesAvailable !== 1 ? 's' : '' }} available
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- FAQ Widget -->
          <Card class="rounded-3xl border border-border/80 bg-card/95 shadow-sm">
            <CardHeader class="pb-4">
              <div class="flex items-start gap-3">
                <div class="flex size-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                  <HelpCircle class="size-5" />
                </div>
                <div class="space-y-1">
                  <CardTitle class="text-base">Frequently Asked Questions</CardTitle>
                  <CardDescription>Quick answers to common questions</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="space-y-3">
              <div class="relative">
                <Search class="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  v-model="faqSearch"
                  placeholder="Search FAQs..."
                  class="h-10 ps-9 text-sm"
                />
              </div>

              <div v-if="filteredFAQs.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                No FAQs found
              </div>

              <div v-else class="space-y-2 max-h-[400px] overflow-y-auto">
                <div
                  v-for="faq in filteredFAQs"
                  :key="faq.id"
                  class="rounded-2xl border border-border/70 bg-muted/30 transition hover:bg-muted/50"
                >
                  <button
                    class="flex w-full items-start justify-between gap-2 p-3 text-start"
                    @click="toggleFAQ(faq.id)"
                  >
                    <p class="flex-1 text-sm font-medium">{{ faq.question }}</p>
                    <ChevronDown v-if="!faq.expanded" class="size-4 shrink-0 text-muted-foreground" />
                    <ChevronUp v-else class="size-4 shrink-0 text-muted-foreground" />
                  </button>
                  <div v-if="faq.expanded" class="border-t border-border/50 px-3 pb-3 pt-2">
                    <p class="text-xs text-muted-foreground leading-relaxed">{{ faq.answer }}</p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
