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
