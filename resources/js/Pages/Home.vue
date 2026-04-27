<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import AppLayout from '@/Layouts/Layout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import { Badge } from '@/Components/ui/badge'
import { Link } from '@inertiajs/vue3'
import {
  LayoutDashboard, BookOpen, FileText, Package,
  ShoppingCart, ShoppingBag, Receipt, CreditCard,
  ArrowLeftRight, RefreshCw, Loader2, Search,
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

const { t } = useI18n()
const page = usePage<any>()

// calendar_type comes from company settings (auth.user.calendar_type).
// PHP backed enums serialize as plain strings in JSON, but guard against
// the object form {value:"jalali"} just in case.
const calendarType = computed<string>(() => {
  const raw = page.props.auth?.user?.calendar_type
  // Handle enum object form: { value: "jalali" }
  const val = (raw && typeof raw === 'object' && 'value' in raw) ? raw.value : raw
  return (typeof val === 'string' && val) ? val : 'gregorian'
})

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
  digitalTime.value = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`
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

  // Face gradient
  const grad = ctx.createRadialGradient(cx, cy, r * 0.1, cx, cy, r)
  grad.addColorStop(0, isDark ? '#1e3a2f' : '#e8f5e9')
  grad.addColorStop(1, isDark ? '#0d1f17' : '#c8e6c9')
  ctx.beginPath()
  ctx.arc(cx, cy, r, 0, Math.PI * 2)
  ctx.fillStyle = grad
  ctx.fill()
  ctx.strokeStyle = isDark ? '#2d6a4f' : '#388e3c'
  ctx.lineWidth = 3
  ctx.stroke()

  // Minute dots
  for (let i = 0; i < 60; i++) {
    const angle = (i * Math.PI) / 30
    const isHour = i % 5 === 0
    const dotR = isHour ? 3 : 1.5
    const dist = r - (isHour ? 12 : 8)
    const x = cx + Math.sin(angle) * dist
    const y = cy - Math.cos(angle) * dist
    ctx.beginPath()
    ctx.arc(x, y, dotR, 0, Math.PI * 2)
    ctx.fillStyle = isDark ? (isHour ? '#a8d5b5' : '#4a7c59') : (isHour ? '#2e7d32' : '#81c784')
    ctx.fill()
  }

  // Persian hour numbers (12, 3, 6, 9)
  const persianNums: Record<number, string> = { 0: '۱۲', 3: '۳', 6: '۶', 9: '۹' }
  ctx.font = `bold ${Math.floor(r * 0.14)}px sans-serif`
  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'
  ctx.fillStyle = isDark ? '#b7e4c7' : '#1b5e20'
  for (const [idx, label] of Object.entries(persianNums)) {
    const angle = (Number(idx) * Math.PI) / 6
    const dist = r - 22
    ctx.fillText(label, cx + Math.sin(angle) * dist, cy - Math.cos(angle) * dist)
  }

  const sec = now.getSeconds()
  const min = now.getMinutes() + sec / 60
  const hr  = now.getHours() % 12 + min / 60

  // Hour hand
  drawHand(ctx, cx, cy, (hr * Math.PI) / 6, r * 0.52, 5, isDark ? '#e8f5e9' : '#1b5e20')
  // Minute hand
  drawHand(ctx, cx, cy, (min * Math.PI) / 30, r * 0.72, 3.5, isDark ? '#e8f5e9' : '#1b5e20')
  // Second hand (gold/yellow like screenshot)
  drawHand(ctx, cx, cy, (sec * Math.PI) / 30, r * 0.82, 1.5, '#f9a825')
  // Center dot
  ctx.beginPath()
  ctx.arc(cx, cy, 5, 0, Math.PI * 2)
  ctx.fillStyle = '#f9a825'
  ctx.fill()
  ctx.beginPath()
  ctx.arc(cx, cy, 2.5, 0, Math.PI * 2)
  ctx.fillStyle = '#fff'
  ctx.fill()
}

function drawHand(ctx: CanvasRenderingContext2D, cx: number, cy: number, angle: number, length: number, width: number, color: string) {
  ctx.beginPath()
  ctx.moveTo(cx, cy)
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
  if (idx === 0) return 'Today'
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
    dateConvError.value = 'Invalid date. Use YYYY-MM-DD format.'
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
</script>

<template>
  <AppLayout>
    <Head :title="t('home.title')" />

    <div class="p-4 md:p-3 space-y-3">
      <h1 class="text-2xl font-semibold tracking-tight">{{ t('home.title') }}</h1>

      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">

        <!-- ══ 1. ANALOG CLOCK ══════════════════════════════════════════ -->
        <Card class="flex flex-col">
          <CardHeader class="pb-1 pt-4">
            <CardTitle class="text-base">{{ t('home.clock.title') }}</CardTitle>
          </CardHeader>
          <CardContent class="flex flex-col items-center gap-1.5 px-4 pb-4">
            <!-- Analog clock -->
            <canvas ref="clockCanvas" width="150" height="150" class="rounded-full" />

            <!-- Timezone + digital time -->
            <p class="text-[10px] text-muted-foreground leading-none">ساعت کابل · UTC +4:30</p>
            <p class="text-2xl font-bold tracking-widest tabular-nums font-mono leading-none" dir="ltr">{{ digitalTime }}</p>

            <!-- Day name -->
            <p class="text-xs font-semibold text-muted-foreground leading-none">{{ dayName }}</p>

            <!-- Three date sub-cards -->
            <div class="grid grid-cols-3 gap-2 w-full mt-1">
              <!-- Gregorian -->
              <div class="flex flex-col items-center gap-0.5 rounded-lg border border-border bg-muted/30 px-1.5 py-2 text-center">
                <span class="text-base leading-none">{{ gregorianDisplay.icon }}</span>
                <span class="text-[9px] text-muted-foreground">{{ gregorianDisplay.label }}</span>
                <span class="rounded bg-muted px-1.5 py-0.5 text-[10px] font-semibold">{{ gregorianDisplay.weekDay }}</span>
                <span class="text-xl font-bold leading-tight">{{ gregorianDisplay.day }}</span>
                <span class="text-[11px] font-medium">{{ gregorianDisplay.monthName }}</span>
                <span class="text-[9px] text-muted-foreground" dir="ltr">{{ gregorianDisplay.full }}</span>
              </div>
              <!-- Hijri -->
              <div class="flex flex-col items-center gap-0.5 rounded-lg border border-border bg-muted/30 px-1.5 py-2 text-center">
                <span class="text-base leading-none">{{ hijriDisplay.icon }}</span>
                <span class="text-[9px] text-muted-foreground">{{ hijriDisplay.label }}</span>
                <span class="rounded bg-muted px-1.5 py-0.5 text-[10px] font-semibold">{{ hijriDisplay.weekDay }}</span>
                <span class="text-xl font-bold leading-tight">{{ hijriDisplay.day }}</span>
                <span class="text-[11px] font-medium">{{ hijriDisplay.monthName }}</span>
                <span class="text-[9px] text-muted-foreground" dir="ltr">{{ hijriDisplay.full }}</span>
              </div>
              <!-- Jalali -->
              <div class="flex flex-col items-center gap-0.5 rounded-lg border border-border bg-muted/30 px-1.5 py-2 text-center">
                <span class="text-base leading-none">{{ jalaliDisplay.icon }}</span>
                <span class="text-[9px] text-muted-foreground">{{ jalaliDisplay.label }}</span>
                <span class="rounded bg-muted px-1.5 py-0.5 text-[10px] font-semibold">{{ jalaliDisplay.weekDay }}</span>
                <span class="text-xl font-bold leading-tight">{{ jalaliDisplay.day }}</span>
                <span class="text-[11px] font-medium">{{ jalaliDisplay.monthName }}</span>
                <span class="text-[9px] text-muted-foreground" dir="ltr">{{ jalaliDisplay.full }}</span>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- ══ 2. CALENDAR ══════════════════════════════════════════════ -->
        <Card class="flex flex-col">
          <CardHeader class="pb-2">
            <CardTitle class="text-base">{{ t('home.calendar.title') }}</CardTitle>
          </CardHeader>
          <CardContent class="px-3 pb-3">
            <div class="flex items-center justify-between mb-2">
              <Button variant="ghost" size="icon" class="h-7 w-7" @click="prevMonth">‹</Button>
              <span class="text-sm font-medium">{{ calendarTitle }}</span>
              <Button variant="ghost" size="icon" class="h-7 w-7" @click="nextMonth">›</Button>
            </div>
            <div class="grid grid-cols-7 mb-1">
              <div v-for="wd in weekDays" :key="wd" class="text-center text-xs text-muted-foreground font-medium py-1">
                {{ wd }}
              </div>
            </div>
            <div class="grid grid-cols-7 gap-y-0.5">
              <div
                v-for="(cell, idx) in calendarDays"
                :key="idx"
                class="text-center text-xs py-1 rounded-md select-none"
                :class="{
                  'text-muted-foreground/30': !cell.currentMonth,
                  'bg-primary text-primary-foreground font-bold rounded-full': cell.isToday,
                  'hover:bg-muted cursor-pointer': cell.currentMonth && !cell.isToday,
                }"
              >
                {{ cell.day || '' }}
              </div>
            </div>
            <div class="mt-2 flex justify-center">
              <Button variant="outline" size="sm" class="h-7 text-xs" @click="goToday">
                {{ t('home.calendar.today') }}
              </Button>
            </div>
          </CardContent>
        </Card>

        <!-- ══ 3. QUICK LINKS ═══════════════════════════════════════════ -->
        <Card class="flex flex-col">
          <CardHeader class="pb-2">
            <CardTitle class="text-base">{{ t('home.quick_links.title') }}</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="grid grid-cols-2 gap-2">
              <Link
                v-for="link in quickLinks"
                :key="link.url"
                :href="link.url"
                class="flex flex-col items-center gap-1.5 rounded-lg border border-border bg-muted/40 p-3 text-center text-xs font-medium hover:bg-muted transition-colors"
              >
                <component :is="link.icon" class="size-5 text-primary" />
                <span>{{ link.label }}</span>
              </Link>
            </div>
          </CardContent>
        </Card>

        <!-- ══ 4. CURRENCY RATES ════════════════════════════════════════ -->
        <Card class="flex flex-col md:col-span-2 xl:col-span-1">
          <CardHeader class="pb-2">
            <CardTitle class="text-base">{{ t('home.currency_rates.title') }}</CardTitle>
          </CardHeader>
          <CardContent class="px-3 pb-3">
            <div v-if="currencies.length === 0" class="text-sm text-muted-foreground text-center py-4">
              {{ t('home.currency_rates.no_data') }}
            </div>
            <div v-else class="overflow-auto max-h-56">
              <table class="w-full text-xs">
                <thead>
                  <tr class="border-b border-border">
                    <th class="text-start py-1.5 px-1 font-medium text-muted-foreground">{{ t('home.currency_rates.code') }}</th>
                    <th class="text-start py-1.5 px-1 font-medium text-muted-foreground">{{ t('home.currency_rates.name') }}</th>
                    <th class="text-end py-1.5 px-1 font-medium text-muted-foreground">{{ t('home.currency_rates.rate') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="cur in currencies" :key="cur.id" class="border-b border-border/50 last:border-0 hover:bg-muted/40">
                    <td class="py-1.5 px-1">
                      <div class="flex items-center gap-1.5">
                        <span class="font-mono font-semibold">{{ cur.code }}</span>
                        <Badge v-if="cur.is_base_currency" variant="secondary" class="text-[10px] px-1 py-0 h-4">
                          {{ t('home.currency_rates.base') }}
                        </Badge>
                      </div>
                    </td>
                    <td class="py-1.5 px-1 text-muted-foreground">{{ cur.name }}</td>
                    <td class="py-1.5 px-1 text-end font-mono">
                      {{ cur.symbol }} {{ Number(cur.exchange_rate).toLocaleString(undefined, { maximumFractionDigits: 4 }) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>

        <!-- ══ 5. CURRENCY EXCHANGE ═════════════════════════════════════ -->
        <Card class="flex flex-col">
          <CardHeader class="pb-2">
            <CardTitle class="text-base">{{ t('home.currency_exchange.title') }}</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3">
            <div>
              <label class="text-xs text-muted-foreground mb-1 block">{{ t('home.currency_exchange.amount') }}</label>
              <Input v-model.number="exchangeAmount" type="number" min="0" class="h-8 text-sm" />
            </div>
            <div class="flex items-end gap-2">
              <div class="flex-1">
                <label class="text-xs text-muted-foreground mb-1 block">{{ t('home.currency_exchange.from') }}</label>
                <Select v-model="exchangeFrom">
                  <SelectTrigger class="h-8 text-sm">
                    <SelectValue :placeholder="t('home.currency_exchange.select_currency')" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="cur in currencies" :key="cur.code" :value="cur.code">
                      {{ cur.code }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <Button variant="ghost" size="icon" class="h-8 w-8 mb-0.5 shrink-0" @click="swapCurrencies">
                <ArrowLeftRight class="size-4" />
              </Button>
              <div class="flex-1">
                <label class="text-xs text-muted-foreground mb-1 block">{{ t('home.currency_exchange.to') }}</label>
                <Select v-model="exchangeTo">
                  <SelectTrigger class="h-8 text-sm">
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
            <Button class="w-full h-8 text-sm" :disabled="exchangeLoading" @click="doExchange">
              <Loader2 v-if="exchangeLoading" class="size-4 animate-spin me-1" />
              {{ t('home.currency_exchange.convert') }}
            </Button>
            <div v-if="exchangeResult" class="rounded-md bg-muted px-3 py-2 text-sm font-semibold text-center">
              {{ exchangeResult }}
            </div>
            <p v-if="exchangeError" class="text-xs text-destructive text-center">{{ exchangeError }}</p>
          </CardContent>
        </Card>

        <!-- ══ 6. WEATHER (Open-Meteo) ══════════════════════════════════ -->
        <Card class="flex flex-col overflow-hidden">
          <CardContent class="p-0">
            <!-- Header bar -->
            <div class="flex items-start justify-between p-4 pb-2">
              <div>
                <p class="text-base font-bold">
                  {{ weatherData ? `${weatherData.city}, ${weatherData.country}` : t('home.weather.title') }}
                </p>
                <p class="text-xs text-muted-foreground mt-0.5">{{ t('home.weather.title') }}</p>
              </div>
              <!-- City search -->
              <div class="flex gap-1.5">
                <Select v-model="weatherSearch" class="w-36">
                  <SelectTrigger class="h-7 text-xs">
                    <SelectValue :placeholder="t('home.weather.city')" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="p in afghaniProvinces" :key="p" :value="p">{{ p }}</SelectItem>
                  </SelectContent>
                </Select>
                <Button
                  size="icon"
                  variant="outline"
                  class="h-7 w-7"
                  :disabled="weatherLoading"
                  @click="searchWeather"
                >
                  <Loader2 v-if="weatherLoading" class="size-3.5 animate-spin" />
                  <Search v-else class="size-3.5" />
                </Button>
              </div>
            </div>

            <!-- Error state -->
            <div v-if="weatherError" class="px-4 pb-4 text-sm text-destructive">{{ weatherError }}</div>

            <!-- Loading -->
            <div v-else-if="weatherLoading && !weatherData" class="px-4 pb-4 text-sm text-muted-foreground">
              {{ t('home.weather.loading') }}
            </div>

            <!-- Current weather card -->
            <div v-else-if="weatherData" class="px-3 pb-3 space-y-3">
              <!-- Main current block -->
              <div class="rounded-xl bg-muted/50 p-3">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-4xl font-bold tracking-tight">{{ weatherData.temp?.toFixed(1) }}°C</p>
                    <span class="mt-1 inline-block rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium">
                      {{ wmoInfo(weatherData.code, weatherData.is_day).label }}
                    </span>
                  </div>
                  <span class="text-5xl leading-none">{{ wmoInfo(weatherData.code, weatherData.is_day).emoji }}</span>
                </div>
                <!-- High / Low from today's forecast -->
                <div class="mt-2 flex gap-2" v-if="weatherData.forecast?.length">
                  <span class="rounded-full bg-muted px-3 py-1 text-xs">
                    ↑ {{ weatherData.forecast[0].max?.toFixed(0) }}°
                  </span>
                  <span class="rounded-full bg-muted px-3 py-1 text-xs">
                    ↓ {{ weatherData.forecast[0].min?.toFixed(0) }}°
                  </span>
                </div>
              </div>

              <!-- 7-day forecast strip -->
              <div v-if="weatherData.forecast?.length">
                <p class="text-xs font-semibold text-muted-foreground mb-2">7-Day Forecast</p>
                <div class="flex gap-2 overflow-x-auto pb-1">
                  <div
                    v-for="(day, idx) in weatherData.forecast"
                    :key="day.date"
                    class="flex-shrink-0 flex flex-col items-center gap-1 rounded-xl border px-2.5 py-2 text-center text-xs min-w-[60px]"
                    :class="idx === 0 ? 'border-primary bg-primary/10' : 'border-border bg-muted/40'"
                  >
                    <span class="font-semibold">{{ forecastDayLabel(day.date, idx) }}</span>
                    <span class="text-[10px] text-muted-foreground">{{ forecastDateLabel(day.date) }}</span>
                    <span class="text-xl leading-none">{{ wmoInfo(day.code).emoji }}</span>
                    <div class="flex gap-1 text-[11px]">
                      <span class="font-bold">{{ day.max?.toFixed(0) }}°</span>
                      <span class="text-muted-foreground">{{ day.min?.toFixed(0) }}°</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Extra stats -->
              <div class="grid grid-cols-3 gap-2 text-xs text-center">
                <div class="rounded-lg bg-muted/50 p-2">
                  <p class="text-muted-foreground">{{ t('home.weather.feels_like') }}</p>
                  <p class="font-semibold">{{ weatherData.feels_like?.toFixed(0) }}°C</p>
                </div>
                <div class="rounded-lg bg-muted/50 p-2">
                  <p class="text-muted-foreground">{{ t('home.weather.humidity') }}</p>
                  <p class="font-semibold">{{ weatherData.humidity }}%</p>
                </div>
                <div class="rounded-lg bg-muted/50 p-2">
                  <p class="text-muted-foreground">{{ t('home.weather.wind') }}</p>
                  <p class="font-semibold">{{ weatherData.wind_speed?.toFixed(1) }} km/h</p>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- ══ 7. DATE CONVERSION ═══════════════════════════════════════ -->
        <Card class="flex flex-col">
          <CardHeader class="pb-2">
            <CardTitle class="text-base">{{ t('home.date_conversion.title') }}</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3">
            <div class="flex rounded-md overflow-hidden border border-border text-xs">
              <button
                class="flex-1 py-1.5 transition-colors"
                :class="dateConvMode === 'to_jalali' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                @click="dateConvMode = 'to_jalali'; dateConvResult = ''; dateConvError = ''"
              >
                {{ t('home.date_conversion.gregorian') }} → {{ t('home.date_conversion.jalali') }}
              </button>
              <button
                class="flex-1 py-1.5 transition-colors"
                :class="dateConvMode === 'to_gregorian' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                @click="dateConvMode = 'to_gregorian'; dateConvResult = ''; dateConvError = ''"
              >
                {{ t('home.date_conversion.jalali') }} → {{ t('home.date_conversion.gregorian') }}
              </button>
            </div>
            <div>
              <label class="text-xs text-muted-foreground mb-1 block">
                {{ dateConvMode === 'to_jalali' ? 'YYYY-MM-DD (Gregorian)' : 'YYYY/MM/DD (Jalali)' }}
              </label>
              <Input
                v-model="dateConvInput"
                :placeholder="dateConvMode === 'to_jalali' ? '2024-03-20' : '1402/12/29'"
                class="h-8 text-sm font-mono"
                @keyup.enter="convertDate"
              />
            </div>
            <Button class="w-full h-8 text-sm" @click="convertDate">
              {{ t('home.date_conversion.convert') }}
            </Button>
            <div v-if="dateConvResult" class="rounded-md bg-muted px-3 py-2 text-sm font-mono font-semibold text-center">
              {{ dateConvResult }}
            </div>
            <p v-if="dateConvError" class="text-xs text-destructive text-center">{{ dateConvError }}</p>
          </CardContent>
        </Card>

        <!-- ══ 8. UNIT EXCHANGE ═════════════════════════════════════════ -->
        <Card class="flex flex-col">
          <CardHeader class="pb-2">
            <CardTitle class="text-base">{{ t('home.unit_exchange.title') }}</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3">
            <div v-if="props.unitMeasures.length === 0" class="text-sm text-muted-foreground text-center py-4">
              {{ t('home.unit_exchange.no_units') }}
            </div>
            <template v-else>
              <div>
                <label class="text-xs text-muted-foreground mb-1 block">Type</label>
                <Select v-model="selectedQuantityId">
                  <SelectTrigger class="h-8 text-sm">
                    <SelectValue placeholder="Select type" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="(group, qid) in quantityGroups" :key="String(qid)" :value="String(qid)">
                      {{ group.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <label class="text-xs text-muted-foreground mb-1 block">{{ t('home.unit_exchange.amount') }}</label>
                <Input v-model.number="unitAmount" type="number" class="h-8 text-sm" />
              </div>
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <label class="text-xs text-muted-foreground mb-1 block">{{ t('home.unit_exchange.from') }}</label>
                  <Select v-model="unitFromId">
                    <SelectTrigger class="h-8 text-sm">
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
                  <label class="text-xs text-muted-foreground mb-1 block">{{ t('home.unit_exchange.to') }}</label>
                  <Select v-model="unitToId">
                    <SelectTrigger class="h-8 text-sm">
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
              <Button class="w-full h-8 text-sm" :disabled="unitLoading" @click="doUnitConvert">
                <Loader2 v-if="unitLoading" class="size-4 animate-spin me-1" />
                {{ t('home.unit_exchange.convert') }}
              </Button>
              <div v-if="unitResult" class="rounded-md bg-muted px-3 py-2 text-sm font-semibold font-mono text-center">
                {{ unitResult }}
              </div>
              <p v-if="unitError" class="text-xs text-destructive text-center">{{ unitError }}</p>
            </template>
          </CardContent>
        </Card>

      </div>
    </div>
  </AppLayout>
</template>
