<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import AppLayout from '@/Layouts/Layout.vue'
import { Card } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import { Badge } from '@/Components/ui/badge'
import {
  LayoutDashboard, BookOpen, FileText, Package,
  ShoppingCart, ShoppingBag, Receipt, CreditCard,
  ArrowLeftRight, Loader2, RefreshCw,
  CircleDollarSign, Scale, CalendarDays,
  ChevronLeft, ChevronRight, ArrowRight, Clock3,
  Coins, RotateCcw, ArrowUp, ArrowDown,
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

// Shared card elevation — rests low, lifts on hover. Dark mode needs an
// explicit shadow colour, otherwise the default black shadow is invisible
// against the dark surface.
const panel = 'shadow-sm transition-shadow duration-200 hover:shadow-md dark:shadow-black/50 dark:hover:shadow-black/70'
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

const dateStrip = computed(() => [gregorianDisplay.value, jalaliDisplay.value, hijriDisplay.value])

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
  const r = Math.min(cx, cy) - 4
  const isDark = document.documentElement.classList.contains('dark')

  ctx.clearRect(0, 0, W, H)

  // Base clock face
  ctx.beginPath()
  ctx.arc(cx, cy, r, 0, Math.PI * 2)
  ctx.fillStyle = isDark ? '#0f172a' : '#ffffff'
  ctx.fill()
  ctx.strokeStyle = isDark ? '#334155' : '#d4d4d8'
  ctx.lineWidth = 1.5
  ctx.stroke()

  // Minute and hour ticks — sized relative to the radius so the face stays
  // legible at the compact size used on the home page.
  for (let i = 0; i < 60; i++) {
    const angle = (i * Math.PI) / 30
    const isHour = i % 5 === 0
    const outer = r * 0.93
    const inner = outer - r * (isHour ? 0.13 : 0.06)
    const x1 = cx + Math.sin(angle) * inner
    const y1 = cy - Math.cos(angle) * inner
    const x2 = cx + Math.sin(angle) * outer
    const y2 = cy - Math.cos(angle) * outer
    ctx.beginPath()
    ctx.moveTo(x1, y1)
    ctx.lineTo(x2, y2)
    ctx.strokeStyle = isDark ? '#e2e8f0' : '#3f3f46'
    ctx.lineWidth = isHour ? r * 0.035 : r * 0.015
    ctx.lineCap = 'round'
    ctx.stroke()
  }

  // Hour numbers 3/6/9/12 only — a full 1-12 ring is unreadable at this size
  ctx.font = `600 ${Math.floor(r * 0.2)}px sans-serif`
  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'
  ctx.fillStyle = isDark ? '#f8fafc' : '#18181b'
  for (const i of [12, 3, 6, 9]) {
    const angle = (i * Math.PI) / 6
    const dist = r * 0.7
    ctx.fillText(String(i), cx + Math.sin(angle) * dist, cy - Math.cos(angle) * dist)
  }

  const sec = now.getSeconds()
  const min = now.getMinutes() + sec / 60
  const hr  = now.getHours() % 12 + min / 60

  // Hour hand
  drawHand(ctx, cx, cy, (hr * Math.PI) / 6, r * 0.45, r * 0.06, isDark ? '#f8fafc' : '#27272a', r * 0.12)
  // Minute hand
  drawHand(ctx, cx, cy, (min * Math.PI) / 30, r * 0.68, r * 0.045, isDark ? '#f8fafc' : '#27272a', r * 0.16)
  // Second hand
  drawHand(ctx, cx, cy, (sec * Math.PI) / 30, r * 0.78, r * 0.018, '#f59e0b', r * 0.24)
  // Center dot
  ctx.beginPath()
  ctx.arc(cx, cy, r * 0.07, 0, Math.PI * 2)
  ctx.fillStyle = '#f59e0b'
  ctx.fill()
  ctx.beginPath()
  ctx.arc(cx, cy, r * 0.03, 0, Math.PI * 2)
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

interface CalCell { day: number; currentMonth: boolean; isToday: boolean; isWeekend: boolean }

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
  for (let i = 0; i < firstDow; i++) cells.push({ day: 0, currentMonth: false, isToday: false, isWeekend: false })
  for (let d = 1; d <= daysInMonth; d++) {
    const isToday = year === today.getFullYear() && month === today.getMonth() && d === today.getDate()
    // Column position marks the weekday: index 5 = Friday in the Gregorian grid
    const isWeekend = (firstDow + d - 1) % 7 === 5
    cells.push({ day: d, currentMonth: true, isToday, isWeekend })
  }
  while (cells.length % 7 !== 0) cells.push({ day: 0, currentMonth: false, isToday: false, isWeekend: false })
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
  for (let i = 0; i < jalaliDow; i++) cells.push({ day: 0, currentMonth: false, isToday: false, isWeekend: false })
  for (let d = 1; d <= daysInMonth; d++) {
    const isToday = jy === tjy && jm === tjm && d === tjd
    // Friday is the last column (index 6) in the Jalali grid
    const isWeekend = (jalaliDow + d - 1) % 7 === 6
    cells.push({ day: d, currentMonth: true, isToday, isWeekend })
  }
  while (cells.length % 7 !== 0) cells.push({ day: 0, currentMonth: false, isToday: false, isWeekend: false })
  return cells
}

// ═══════════════════════════════════════════════════════════════════════════════
// ── 3. QUICK LINKS ────────────────────────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

const quickLinks = computed(() => [
  { label: t('home.quick_links.dashboard'),         url: '/dashboard',         icon: LayoutDashboard },
  { label: t('home.quick_links.chart_of_accounts'), url: '/chart-of-accounts', icon: BookOpen },
  { label: t('home.quick_links.journal_entry'),     url: '/journal-entries',   icon: FileText },
  { label: t('home.quick_links.items'),             url: '/items',             icon: Package },
  { label: t('home.quick_links.sales'),             url: '/sales',             icon: ShoppingCart },
  { label: t('home.quick_links.purchases'),         url: '/purchases',         icon: ShoppingBag },
  { label: t('home.quick_links.receipts'),          url: '/receipts',          icon: Receipt },
  { label: t('home.quick_links.payments'),          url: '/payments',          icon: CreditCard },
])

// ═══════════════════════════════════════════════════════════════════════════════
// ── 4. CURRENCY EXCHANGE ──────────────────────────────────────────────────────
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
// ── 5. WEATHER (Open-Meteo, no API key) ───────────────────────────────────────
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

// ═══════════════════════════════════════════════════════════════════════════════
// ── 6. DATE CONVERSION ────────────────────────────────────────────────────────
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
// ── 7. UNIT EXCHANGE ──────────────────────────────────────────────────────────
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
// ── 8. DEBIT OR CREDIT? (accounting drill) ────────────────────────────────────
// A 60-second quick-fire round: an account is named along with the direction it
// moves, and the player calls the side it posts to. The rule being drilled is
// the one the whole ledger rests on — assets and expenses rise on the debit
// side, liabilities, equity and income rise on the credit side.
// ═══════════════════════════════════════════════════════════════════════════════

type DcCategory = 'asset' | 'liability' | 'equity' | 'income' | 'expense'
type DcSide = 'debit' | 'credit'
type DcMovement = 'increase' | 'decrease'

const DC_ACCOUNTS: Array<{ key: string; category: DcCategory }> = [
  { key: 'cash',                category: 'asset' },
  { key: 'bank',                category: 'asset' },
  { key: 'accounts_receivable', category: 'asset' },
  { key: 'inventory',           category: 'asset' },
  { key: 'equipment',           category: 'asset' },
  { key: 'accounts_payable',    category: 'liability' },
  { key: 'loan_payable',        category: 'liability' },
  { key: 'salaries_payable',    category: 'liability' },
  { key: 'owner_capital',       category: 'equity' },
  { key: 'retained_earnings',   category: 'equity' },
  { key: 'sales_revenue',       category: 'income' },
  { key: 'service_income',      category: 'income' },
  { key: 'rent_expense',        category: 'expense' },
  { key: 'salaries_expense',    category: 'expense' },
  { key: 'utilities_expense',   category: 'expense' },
  { key: 'purchases',           category: 'expense' },
]

const DC_ROUND_SECONDS = 60
const DC_FEEDBACK_MS = 850

interface DcQuestion { key: string; category: DcCategory; movement: DcMovement }

const dcStatus     = ref<'idle' | 'playing' | 'over'>('idle')
const dcTimeLeft   = ref(DC_ROUND_SECONDS)
const dcScore      = ref(0)
const dcAsked      = ref(0)
const dcStreak     = ref(0)
const dcBestStreak = ref(0)
const dcBestScore  = ref<number | null>(null)
const dcCurrent    = ref<DcQuestion | null>(null)
const dcAnswered   = ref<{ picked: DcSide; correct: boolean } | null>(null)

let dcTimer: ReturnType<typeof setInterval> | null = null
let dcAdvance: ReturnType<typeof setTimeout> | null = null

// Assets and expenses carry a debit balance; everything else carries a credit
// balance. An increase posts to the natural side, a decrease to the opposite.
function dcCorrectSide(category: DcCategory, movement: DcMovement): DcSide {
  const naturalDebit = category === 'asset' || category === 'expense'
  const risesWithDebit = naturalDebit
  if (movement === 'increase') return risesWithDebit ? 'debit' : 'credit'
  return risesWithDebit ? 'credit' : 'debit'
}

const dcAccuracy = computed(() =>
  dcAsked.value === 0 ? 0 : Math.round((dcScore.value / dcAsked.value) * 100),
)

const dcTimeProgress = computed(() => (dcTimeLeft.value / DC_ROUND_SECONDS) * 100)

const dcCorrectForCurrent = computed<DcSide | null>(() =>
  dcCurrent.value ? dcCorrectSide(dcCurrent.value.category, dcCurrent.value.movement) : null,
)

function dcClearTimers() {
  if (dcTimer) { clearInterval(dcTimer); dcTimer = null }
  if (dcAdvance) { clearTimeout(dcAdvance); dcAdvance = null }
}

function dcNextQuestion() {
  const previous = dcCurrent.value
  let next: DcQuestion
  // Re-roll rather than repeat the exact same prompt back to back
  do {
    const account = DC_ACCOUNTS[Math.floor(Math.random() * DC_ACCOUNTS.length)]
    next = {
      key: account.key,
      category: account.category,
      movement: Math.random() < 0.5 ? 'increase' : 'decrease',
    }
  } while (previous && next.key === previous.key && next.movement === previous.movement)

  dcCurrent.value = next
  dcAnswered.value = null
}

function dcEndRound() {
  dcClearTimers()
  dcStatus.value = 'over'
  if (dcBestScore.value === null || dcScore.value > dcBestScore.value) {
    dcBestScore.value = dcScore.value
  }
}

function dcStart() {
  dcClearTimers()
  dcScore.value = 0
  dcAsked.value = 0
  dcStreak.value = 0
  dcTimeLeft.value = DC_ROUND_SECONDS
  dcStatus.value = 'playing'
  dcNextQuestion()
  dcTimer = setInterval(() => {
    dcTimeLeft.value--
    if (dcTimeLeft.value <= 0) dcEndRound()
  }, 1000)
}

function dcAnswer(side: DcSide) {
  if (dcStatus.value !== 'playing' || dcAnswered.value || !dcCurrent.value) return

  const correct = side === dcCorrectSide(dcCurrent.value.category, dcCurrent.value.movement)
  dcAsked.value++
  if (correct) {
    dcScore.value++
    dcStreak.value++
    if (dcStreak.value > dcBestStreak.value) dcBestStreak.value = dcStreak.value
  } else {
    dcStreak.value = 0
  }
  dcAnswered.value = { picked: side, correct }

  dcAdvance = setTimeout(() => {
    dcAdvance = null
    if (dcStatus.value === 'playing') dcNextQuestion()
  }, DC_FEEDBACK_MS)
}

onBeforeUnmount(dcClearTimers)
</script>

<template>
  <AppLayout>
    <Head :title="t('home.title')" />

    <div class="space-y-3 text-foreground">
      <!-- ── Header bar ─────────────────────────────────────────────────── -->
      <Card :class="[panel, 'flex flex-wrap items-center justify-between gap-x-6 gap-y-3 border-s-4 border-s-primary bg-gradient-to-r from-primary/[0.06] to-card px-4 py-3']">
        <div class="min-w-0">
          <h1 class="truncate text-lg font-semibold tracking-tight">
            {{ t('home.hero.greeting', { name: firstName }) }}
          </h1>
          <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
            <span class="truncate font-medium text-foreground/80">{{ activeBranchName }}</span>
            <span class="text-border">•</span>
            <span>{{ calendarModeLabel }}</span>
            <span class="text-border">•</span>
            <span class="truncate">{{ gregorianDisplay.weekDay }}, {{ gregorianDisplay.day }} {{ gregorianDisplay.monthName }}</span>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <div class="flex items-center gap-2 rounded-md border border-border bg-muted/40 px-3 py-1.5">
            <Clock3 class="size-4 shrink-0 text-muted-foreground" />
            <div class="flex items-baseline gap-1 font-mono text-sm font-semibold tabular-nums" dir="ltr">
              <span>{{ digitalTime || '--:--:--' }}</span>
              <span class="text-[10px] font-medium text-muted-foreground">{{ digitalMeridiem }}</span>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <Button as-child size="sm" class="h-8 text-xs">
              <Link href="/dashboard">
                <LayoutDashboard class="me-1.5 size-3.5" />
                {{ t('home.hero.open_dashboard') }}
              </Link>
            </Button>
            <Button as-child size="sm" variant="outline" class="h-8 text-xs">
              <Link href="/reports">
                <FileText class="me-1.5 size-3.5" />
                {{ t('home.hero.open_reports') }}
              </Link>
            </Button>
          </div>
        </div>
      </Card>

      <!-- ── Quick links ────────────────────────────────────────────────── -->
      <Card :class="[panel, 'p-3']">
        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
          {{ t('home.quick_links.title') }}
        </p>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-8">
          <Link
            v-for="link in quickLinks"
            :key="link.url"
            :href="link.url"
            class="flex items-center gap-2 rounded-md border border-border bg-background px-2.5 py-2 text-xs font-medium shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/50 hover:bg-muted hover:shadow-md dark:shadow-black/40"
          >
            <component :is="link.icon" class="size-4 shrink-0 text-primary" />
            <span class="truncate">{{ link.label }}</span>
          </Link>
        </div>
      </Card>

      <!-- ── Main grid ──────────────────────────────────────────────────── -->
      <div class="grid gap-3 xl:grid-cols-3">
        <!-- Left column: weather + rates -->
        <div class="space-y-3 xl:col-span-2">
          <!-- Weather -->
          <Card :class="panel">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-3 py-2">
              <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                {{ t('home.weather.title') }}
              </p>
              <div class="flex items-center gap-1.5">
                <Select v-model="weatherSearch">
                  <SelectTrigger class="h-8 min-w-[140px] text-xs">
                    <SelectValue :placeholder="t('home.weather.city')" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="p in afghaniProvinces" :key="p" :value="p">{{ p }}</SelectItem>
                  </SelectContent>
                </Select>
                <Button
                  size="icon"
                  variant="outline"
                  class="h-8 w-8"
                  :disabled="weatherLoading"
                  :aria-label="t('home.weather.search')"
                  @click="searchWeather"
                >
                  <Loader2 v-if="weatherLoading" class="size-3.5 animate-spin" />
                  <RefreshCw v-else class="size-3.5" />
                </Button>
              </div>
            </div>

            <div class="p-3">
              <p v-if="weatherError" class="py-4 text-center text-sm text-destructive">{{ weatherError }}</p>

              <p v-else-if="weatherLoading && !weatherData" class="py-4 text-center text-sm text-muted-foreground">
                {{ t('home.weather.loading') }}
              </p>

              <div v-else-if="weatherData" class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <!-- Current conditions -->
                <div class="flex shrink-0 items-center gap-3 lg:w-56">
                  <span class="text-4xl leading-none">{{ wmoInfo(weatherData.code, weatherData.is_day).emoji }}</span>
                  <div class="min-w-0">
                    <p class="text-2xl font-semibold leading-none tabular-nums">{{ weatherData.temp?.toFixed(0) }}°C</p>
                    <p class="mt-1 truncate text-xs text-muted-foreground">
                      {{ weatherData.city }} · {{ wmoInfo(weatherData.code, weatherData.is_day).label }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                      {{ t('home.weather.feels_like') }} {{ weatherData.feels_like?.toFixed(0) }}° ·
                      {{ t('home.weather.humidity') }} {{ weatherData.humidity }}% ·
                      {{ t('home.weather.wind') }} {{ weatherData.wind_speed?.toFixed(0) }} km/h
                    </p>
                  </div>
                </div>

                <!-- 7-day forecast -->
                <div v-if="weatherData.forecast?.length" class="flex min-w-0 flex-1 gap-1.5 overflow-x-auto">
                  <div
                    v-for="(day, idx) in weatherData.forecast"
                    :key="day.date"
                    class="flex min-w-[62px] flex-1 flex-shrink-0 flex-col items-center gap-0.5 rounded-md border px-1.5 py-2 text-center shadow-sm transition-shadow duration-200 hover:shadow-md dark:shadow-black/40"
                    :class="idx === 0 ? 'border-primary/50 bg-primary/5' : 'border-border'"
                  >
                    <span class="text-[11px] font-semibold">{{ forecastDayLabel(day.date, Number(idx)) }}</span>
                    <span class="text-[10px] text-muted-foreground">{{ forecastDateLabel(day.date) }}</span>
                    <span class="text-lg leading-none">{{ wmoInfo(day.code).emoji }}</span>
                    <div class="flex gap-1 text-[11px] tabular-nums">
                      <span class="font-semibold">{{ day.max?.toFixed(0) }}°</span>
                      <span class="text-muted-foreground">{{ day.min?.toFixed(0) }}°</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </Card>

          <!-- Currency rates -->
          <Card :class="panel">
            <div class="flex items-center justify-between gap-2 border-b border-border px-3 py-2">
              <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                {{ t('home.currency_rates.title') }}
              </p>
              <CircleDollarSign class="size-4 text-muted-foreground" />
            </div>
            <div v-if="currencies.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground">
              {{ t('home.currency_rates.no_data') }}
            </div>
            <div v-else class="max-h-64 overflow-auto">
              <table class="w-full text-xs">
                <thead class="sticky top-0 bg-card">
                  <tr class="border-b border-border">
                    <th class="px-3 py-1.5 text-start font-medium text-muted-foreground">{{ t('home.currency_rates.code') }}</th>
                    <th class="px-3 py-1.5 text-start font-medium text-muted-foreground">{{ t('home.currency_rates.name') }}</th>
                    <th class="px-3 py-1.5 text-end font-medium text-muted-foreground">{{ t('home.currency_rates.rate') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="cur in currencies" :key="cur.id" class="border-b border-border/50 last:border-0 hover:bg-muted/40">
                    <td class="px-3 py-1.5">
                      <div class="flex items-center gap-1.5">
                        <span class="font-mono font-semibold">{{ cur.code }}</span>
                        <Badge v-if="cur.is_base_currency" variant="secondary" class="h-4 px-1 py-0 text-[10px]">
                          {{ t('home.currency_rates.base') }}
                        </Badge>
                      </div>
                    </td>
                    <td class="truncate px-3 py-1.5 text-muted-foreground">{{ cur.name }}</td>
                    <td class="px-3 py-1.5 text-end font-mono tabular-nums">
                      {{ cur.symbol }} {{ Number(cur.exchange_rate).toLocaleString(undefined, { maximumFractionDigits: 4 }) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </Card>
        </div>

        <!-- Right column: clock + calendar -->
        <div class="space-y-3">
          <!-- Clock & dates -->
          <Card :class="[panel, 'p-3']">
            <div class="flex items-center gap-3">
              <canvas ref="clockCanvas" width="112" height="112" class="shrink-0 rounded-full" />
              <div class="min-w-0 flex-1 space-y-1">
                <div
                  v-for="d in dateStrip"
                  :key="d.label"
                  class="flex items-center justify-between gap-2 rounded-md bg-muted/40 px-2 py-1.5 text-xs"
                >
                  <span class="flex items-center gap-1.5 text-muted-foreground">
                    <span class="text-[13px] leading-none">{{ d.icon }}</span>
                    <span>{{ d.label }}</span>
                  </span>
                  <span class="truncate font-medium">
                    {{ d.day }} {{ d.monthName }}
                  </span>
                </div>
              </div>
            </div>
            <p class="mt-2 text-center text-[11px] text-muted-foreground">{{ t('home.clock.timezone') }}</p>
          </Card>

          <!-- Calendar -->
          <Card :class="[panel, 'p-3']">
            <div class="mb-2 flex items-center justify-between gap-2">
              <Button
                variant="ghost"
                size="icon"
                class="size-7"
                :aria-label="t('home.calendar.prev')"
                @click="prevMonth"
              >
                <ChevronLeft class="size-4 rtl:rotate-180" />
              </Button>
              <p class="truncate text-sm font-semibold">{{ calendarTitle }}</p>
              <Button
                variant="ghost"
                size="icon"
                class="size-7"
                :aria-label="t('home.calendar.next')"
                @click="nextMonth"
              >
                <ChevronRight class="size-4 rtl:rotate-180" />
              </Button>
            </div>

            <div class="grid grid-cols-7 gap-px">
              <div
                v-for="wd in weekDays"
                :key="wd"
                class="pb-1 text-center text-[10px] font-semibold uppercase text-muted-foreground"
              >
                {{ wd }}
              </div>
              <div
                v-for="(cell, idx) in calendarDays"
                :key="idx"
                class="flex h-7 items-center justify-center rounded text-[11px] tabular-nums transition-colors"
                :class="{
                  'text-muted-foreground/25': !cell.currentMonth,
                  'bg-primary font-semibold text-primary-foreground': cell.isToday,
                  'text-destructive/80': cell.currentMonth && cell.isWeekend && !cell.isToday,
                  'cursor-default hover:bg-muted': cell.currentMonth && !cell.isToday,
                }"
              >
                {{ cell.day || '' }}
              </div>
            </div>

            <Button variant="outline" size="sm" class="mt-2 h-7 w-full text-xs" @click="goToday">
              {{ t('home.calendar.today') }}
            </Button>
          </Card>
        </div>
      </div>

      <!-- ── Converters: all three visible side by side ──────────────────── -->
      <div class="grid gap-3 lg:grid-cols-3">
        <!-- Currency exchange -->
        <Card :class="panel">
          <div class="flex items-center gap-2 border-b border-border px-3 py-2">
            <ArrowLeftRight class="size-4 shrink-0 text-primary" />
            <p class="text-xs font-semibold">{{ t('home.currency_exchange.title') }}</p>
          </div>
          <div class="space-y-2 p-3">
            <div>
              <label class="mb-1 block text-[11px] text-muted-foreground">{{ t('home.currency_exchange.amount') }}</label>
              <Input v-model.number="exchangeAmount" type="number" min="0" class="h-9 text-sm" />
            </div>
            <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-end gap-2">
              <div>
                <label class="mb-1 block text-[11px] text-muted-foreground">{{ t('home.currency_exchange.from') }}</label>
                <Select v-model="exchangeFrom">
                  <SelectTrigger class="h-9 text-sm">
                    <SelectValue :placeholder="t('home.currency_exchange.select_currency')" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="cur in currencies" :key="cur.code" :value="cur.code">
                      {{ cur.code }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <Button
                variant="outline"
                size="icon"
                class="h-9 w-9 shrink-0"
                :aria-label="t('home.tools.swap')"
                @click="swapCurrencies"
              >
                <ArrowLeftRight class="size-4" />
              </Button>
              <div>
                <label class="mb-1 block text-[11px] text-muted-foreground">{{ t('home.currency_exchange.to') }}</label>
                <Select v-model="exchangeTo">
                  <SelectTrigger class="h-9 text-sm">
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
            <div class="flex items-center gap-2 pt-0.5">
              <Button class="h-9 shrink-0 text-sm" :disabled="exchangeLoading" @click="doExchange">
                <Loader2 v-if="exchangeLoading" class="me-1.5 size-4 animate-spin" />
                {{ t('home.currency_exchange.convert') }}
              </Button>
              <div class="flex h-9 min-w-0 flex-1 items-center justify-end rounded-md border border-border bg-muted/40 px-3 font-mono text-sm font-semibold">
                <span v-if="exchangeError" class="truncate text-xs font-normal text-destructive">{{ exchangeError }}</span>
                <span v-else class="truncate" :class="exchangeResult ? '' : 'text-muted-foreground/60'">{{ exchangeResult || '—' }}</span>
              </div>
            </div>
          </div>
        </Card>

        <!-- Unit converter -->
        <Card :class="panel">
          <div class="flex items-center gap-2 border-b border-border px-3 py-2">
            <Scale class="size-4 shrink-0 text-primary" />
            <p class="text-xs font-semibold">{{ t('home.unit_exchange.title') }}</p>
          </div>
          <div class="space-y-2 p-3">
            <p v-if="props.unitMeasures.length === 0" class="py-10 text-center text-sm text-muted-foreground">
              {{ t('home.unit_exchange.no_units') }}
            </p>
            <template v-else>
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <label class="mb-1 block text-[11px] text-muted-foreground">{{ t('home.unit_exchange.type') }}</label>
                  <Select v-model="selectedQuantityId">
                    <SelectTrigger class="h-9 text-sm">
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
                  <label class="mb-1 block text-[11px] text-muted-foreground">{{ t('home.unit_exchange.amount') }}</label>
                  <Input v-model.number="unitAmount" type="number" class="h-9 text-sm" />
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <label class="mb-1 block text-[11px] text-muted-foreground">{{ t('home.unit_exchange.from') }}</label>
                  <Select v-model="unitFromId">
                    <SelectTrigger class="h-9 text-sm">
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
                  <label class="mb-1 block text-[11px] text-muted-foreground">{{ t('home.unit_exchange.to') }}</label>
                  <Select v-model="unitToId">
                    <SelectTrigger class="h-9 text-sm">
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
              <div class="flex items-center gap-2 pt-0.5">
                <Button class="h-9 shrink-0 text-sm" :disabled="unitLoading" @click="doUnitConvert">
                  <Loader2 v-if="unitLoading" class="me-1.5 size-4 animate-spin" />
                  {{ t('home.unit_exchange.convert') }}
                </Button>
                <div class="flex h-9 min-w-0 flex-1 items-center justify-end rounded-md border border-border bg-muted/40 px-3 font-mono text-sm font-semibold">
                  <span v-if="unitError" class="truncate text-xs font-normal text-destructive">{{ unitError }}</span>
                  <span v-else class="truncate" :class="unitResult ? '' : 'text-muted-foreground/60'">{{ unitResult || '—' }}</span>
                </div>
              </div>
            </template>
          </div>
        </Card>

        <!-- Date conversion -->
        <Card :class="panel">
          <div class="flex items-center gap-2 border-b border-border px-3 py-2">
            <CalendarDays class="size-4 shrink-0 text-primary" />
            <p class="text-xs font-semibold">{{ t('home.date_conversion.title') }}</p>
          </div>
          <div class="space-y-2 p-3">
            <div class="flex h-9 overflow-hidden rounded-md border border-border text-xs">
              <button
                v-for="option in dateConversionOptions"
                :key="option.key"
                type="button"
                class="flex flex-1 items-center justify-center gap-1.5 px-2 transition-colors"
                :class="dateConvMode === option.key ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                @click="dateConvMode = option.key as 'to_jalali' | 'to_gregorian'; dateConvResult = ''; dateConvError = ''"
              >
                <ArrowRight v-if="isRTL" class="size-3 shrink-0" />
                <span class="truncate">{{ option.from }}</span>
                <ArrowRight v-if="!isRTL" class="size-3 shrink-0" />
                <span class="truncate">{{ option.to }}</span>
              </button>
            </div>
            <div>
              <label class="mb-1 block text-[11px] text-muted-foreground">
                {{ dateConvMode === 'to_jalali' ? 'YYYY-MM-DD (Gregorian)' : 'YYYY/MM/DD (Jalali)' }}
              </label>
              <Input
                v-model="dateConvInput"
                :placeholder="dateConvMode === 'to_jalali' ? '2024-03-20' : '1402/12/29'"
                class="h-9 font-mono text-sm"
                @keyup.enter="convertDate"
              />
            </div>
            <div class="flex items-center gap-2 pt-0.5">
              <Button class="h-9 shrink-0 text-sm" @click="convertDate">
                {{ t('home.date_conversion.convert') }}
              </Button>
              <div class="flex h-9 min-w-0 flex-1 items-center justify-end rounded-md border border-border bg-muted/40 px-3 font-mono text-sm font-semibold">
                <span v-if="dateConvError" class="truncate text-xs font-normal text-destructive">{{ dateConvError }}</span>
                <span v-else class="truncate" :class="dateConvResult ? '' : 'text-muted-foreground/60'">{{ dateConvResult || '—' }}</span>
              </div>
            </div>
          </div>
        </Card>
      </div>

      <!-- ── Coffee break ──────────────────────────────────────────────── -->
      <div class="grid gap-3 xl:grid-cols-3">
        <div class="xl:col-span-2">
          <!-- Debit or Credit? — a short drill between tasks -->
          <Card :class="panel">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-3 py-2">
              <p class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                <Coins class="size-4 text-primary" />
                {{ t('home.game.title') }}
              </p>
              <Button
                v-if="dcStatus !== 'idle'"
                size="sm"
                variant="outline"
                class="h-8 gap-1.5 text-xs"
                @click="dcStart"
              >
                <RotateCcw class="size-3.5" />
                {{ t('home.game.restart') }}
              </Button>
            </div>

            <!-- Idle: what the drill is, and how to start -->
            <div v-if="dcStatus === 'idle'" class="flex flex-col items-center gap-3 px-3 py-6 text-center">
              <p class="max-w-md text-sm text-muted-foreground">{{ t('home.game.intro') }}</p>
              <div class="flex flex-wrap items-center justify-center gap-2 text-[11px]">
                <span class="rounded-md border border-border bg-muted/40 px-2 py-1">{{ t('home.game.rule_debit') }}</span>
                <span class="rounded-md border border-border bg-muted/40 px-2 py-1">{{ t('home.game.rule_credit') }}</span>
              </div>
              <Button class="h-9 text-sm" @click="dcStart">{{ t('home.game.start') }}</Button>
            </div>

            <!-- Playing -->
            <div v-else-if="dcStatus === 'playing'" class="space-y-3 p-3">
              <!-- Scoreboard -->
              <div class="flex items-center justify-between gap-3 text-xs">
                <span class="flex items-center gap-1.5">
                  <span class="text-muted-foreground">{{ t('home.game.score') }}</span>
                  <span class="font-mono text-sm font-semibold tabular-nums">{{ dcScore }}/{{ dcAsked }}</span>
                </span>
                <span v-if="dcStreak >= 3" class="rounded-full bg-primary/10 px-2 py-0.5 font-medium text-primary">
                  {{ t('home.game.streak', { count: dcStreak }) }}
                </span>
                <span class="flex items-center gap-1.5">
                  <Clock3 class="size-3.5 text-muted-foreground" />
                  <span class="font-mono text-sm font-semibold tabular-nums">{{ dcTimeLeft }}s</span>
                </span>
              </div>

              <!-- Time remaining -->
              <div class="h-1 overflow-hidden rounded-full bg-muted">
                <div
                  class="h-full rounded-full transition-[width] duration-1000 ease-linear"
                  :class="dcTimeLeft <= 10 ? 'bg-destructive' : 'bg-primary'"
                  :style="{ width: dcTimeProgress + '%' }"
                />
              </div>

              <!-- Prompt -->
              <div class="rounded-md border border-border bg-muted/30 px-3 py-4 text-center">
                <p class="text-[11px] uppercase tracking-wider text-muted-foreground">
                  {{ t(`home.game.categories.${dcCurrent?.category}`) }}
                </p>
                <p class="mt-1 text-lg font-semibold">{{ t(`home.game.accounts.${dcCurrent?.key}`) }}</p>
                <span
                  class="mt-2 inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                  :class="dcCurrent?.movement === 'increase'
                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                    : 'bg-amber-500/10 text-amber-600 dark:text-amber-500'"
                >
                  <ArrowUp v-if="dcCurrent?.movement === 'increase'" class="size-3.5" />
                  <ArrowDown v-else class="size-3.5" />
                  {{ t(`home.game.${dcCurrent?.movement}`) }}
                </span>
              </div>

              <!-- Answer buttons -->
              <div class="grid grid-cols-2 gap-2">
                <button
                  v-for="side in (['debit', 'credit'] as const)"
                  :key="side"
                  type="button"
                  class="rounded-md border py-2.5 text-sm font-semibold shadow-sm transition-all duration-150 disabled:cursor-default"
                  :class="dcAnswered
                    ? (side === dcCorrectForCurrent
                      ? 'border-emerald-500 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                      : (side === dcAnswered.picked
                        ? 'border-destructive bg-destructive/15 text-destructive'
                        : 'border-border text-muted-foreground'))
                    : 'border-border bg-background hover:-translate-y-0.5 hover:border-primary/50 hover:bg-muted hover:shadow-md'"
                  :disabled="!!dcAnswered"
                  @click="dcAnswer(side)"
                >
                  {{ t(`home.game.${side}`) }}
                </button>
              </div>

              <!-- Feedback -->
              <p
                class="min-h-[1.25rem] text-center text-xs"
                :class="dcAnswered
                  ? (dcAnswered.correct ? 'font-medium text-emerald-600 dark:text-emerald-400' : 'font-medium text-destructive')
                  : 'text-muted-foreground'"
              >
                <template v-if="dcAnswered">
                  {{ dcAnswered.correct ? t('home.game.correct') : t('home.game.wrong') }}
                  ·
                  {{ t('home.game.because', {
                    category: t(`home.game.categories.${dcCurrent?.category}`),
                    movement: t(`home.game.moves.${dcCurrent?.movement}`),
                    side: t(`home.game.${dcCorrectForCurrent}`),
                  }) }}
                </template>
                <template v-else>{{ t('home.game.prompt') }}</template>
              </p>
            </div>

            <!-- Round over -->
            <div v-else class="flex flex-col items-center gap-3 px-3 py-6 text-center">
              <p class="text-sm text-muted-foreground">{{ t('home.game.time_up') }}</p>
              <div class="flex flex-wrap items-center justify-center gap-2">
                <span class="rounded-md border border-border bg-muted/40 px-3 py-1.5 text-center">
                  <span class="block font-mono text-lg font-semibold tabular-nums">{{ dcScore }}/{{ dcAsked }}</span>
                  <span class="block text-[10px] uppercase tracking-wider text-muted-foreground">{{ t('home.game.score') }}</span>
                </span>
                <span class="rounded-md border border-border bg-muted/40 px-3 py-1.5 text-center">
                  <span class="block font-mono text-lg font-semibold tabular-nums">{{ dcAccuracy }}%</span>
                  <span class="block text-[10px] uppercase tracking-wider text-muted-foreground">{{ t('home.game.accuracy') }}</span>
                </span>
                <span class="rounded-md border border-border bg-muted/40 px-3 py-1.5 text-center">
                  <span class="block font-mono text-lg font-semibold tabular-nums">{{ dcBestStreak }}</span>
                  <span class="block text-[10px] uppercase tracking-wider text-muted-foreground">{{ t('home.game.best_streak') }}</span>
                </span>
                <span v-if="dcBestScore !== null" class="rounded-md border border-primary/40 bg-primary/10 px-3 py-1.5 text-center">
                  <span class="block font-mono text-lg font-semibold tabular-nums text-primary">{{ dcBestScore }}</span>
                  <span class="block text-[10px] uppercase tracking-wider text-muted-foreground">{{ t('home.game.best') }}</span>
                </span>
              </div>
              <Button class="h-9 text-sm" @click="dcStart">{{ t('home.game.play_again') }}</Button>
            </div>
          </Card>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
