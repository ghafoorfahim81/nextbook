export function formatGregorianDate(date = new Date()) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

export function formatJalaliDate(date = new Date()) {
  try {
    const parts = new Intl.DateTimeFormat('en-u-ca-persian', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).formatToParts(date)

    const year = parts.find((part) => part.type === 'year')?.value
    const month = parts.find((part) => part.type === 'month')?.value
    const day = parts.find((part) => part.type === 'day')?.value

    if (year && month && day) {
      return `${year}-${month}-${day}`
    }
  } catch (error) {
    // Fall back to Gregorian when Persian calendar formatting is unavailable.
  }

  return formatGregorianDate(date)
}

export function todayValueForCalendar(calendarType = 'gregorian') {
  return calendarType === 'jalali' ? formatJalaliDate() : formatGregorianDate()
}

/* ---------------- CALENDAR ALIGNMENT ----------------
 * Date values reach the pickers from two directions: forms seed them client-side in the
 * user's calendar, while server props arrive in whatever calendar the endpoint chose. A
 * Jalali picker handed "2026-08-01" reads it as Jalali (year 2026, month عقرب), so before
 * displaying we detect the calendar a value is written in — the same 1300–1499 year test
 * the backend's DateConversionService uses — and convert when it disagrees with the app's. */

const JALALI_YEAR_MIN = 1300
const JALALI_YEAR_MAX = 1499

export function parseDateParts(value) {
  const match = /^(\d{4})-(\d{1,2})-(\d{1,2})(?:[T ].*)?$/.exec(String(value ?? '').trim())
  if (!match) return null

  return { year: Number(match[1]), month: Number(match[2]), day: Number(match[3]) }
}

export function isJalaliYear(year) {
  return year >= JALALI_YEAR_MIN && year <= JALALI_YEAR_MAX
}

const pad = (value) => String(value).padStart(2, '0')
const formatParts = ({ year, month, day }) => `${year}-${pad(month)}-${pad(day)}`

/**
 * Jalali → Gregorian. Intl covers the other direction, but has no inverse, so this is the
 * classic day-count conversion (exact across the Jalali years this app can produce).
 */
export function jalaliToGregorianParts({ year, month, day }) {
  const jy = year + 1595
  let days = -355668
    + 365 * jy
    + Math.trunc(jy / 33) * 8
    + Math.trunc(((jy % 33) + 3) / 4)
    + day
    + (month < 7 ? (month - 1) * 31 : (month - 7) * 30 + 186)

  let gy = 400 * Math.trunc(days / 146097)
  days %= 146097

  if (days > 36524) {
    gy += 100 * Math.trunc(--days / 36524)
    days %= 36524
    if (days >= 365) days++
  }

  gy += 4 * Math.trunc(days / 1461)
  days %= 1461

  if (days > 365) {
    gy += Math.trunc((days - 1) / 365)
    days = (days - 1) % 365
  }

  const isLeap = (gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0
  const monthLengths = [31, isLeap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]

  let gd = days + 1
  let gm = 0
  while (gm < 12 && gd > monthLengths[gm]) {
    gd -= monthLengths[gm]
    gm++
  }

  return { year: gy, month: gm + 1, day: gd }
}

/**
 * Return `value` written in `calendarType`, converting only when it is written in the other
 * calendar. Values that are not plain Y-M-D dates are handed back untouched.
 */
export function alignDateToCalendar(value, calendarType = 'gregorian') {
  const parts = parseDateParts(value)
  if (!parts) return value

  const valueIsJalali = isJalaliYear(parts.year)

  if (calendarType === 'jalali') {
    return valueIsJalali
      ? formatParts(parts)
      : formatJalaliDate(new Date(parts.year, parts.month - 1, parts.day))
  }

  return valueIsJalali ? formatParts(jalaliToGregorianParts(parts)) : formatParts(parts)
}
