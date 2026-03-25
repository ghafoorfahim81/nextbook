const ENGLISH = {
  zero: 'zero',
  ones: ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'],
  teens: ['ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'],
  tens: ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'],
  scales: ['', 'thousand', 'million', 'billion', 'trillion'],
  joiner: ' and ',
  point: 'point',
}

const PERSIAN = {
  zero: 'صفر',
  ones: ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'],
  teens: ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'],
  tens: ['', '', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'],
  hundreds: ['', 'صد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'],
  scales: ['', 'هزار', 'میلیون', 'میلیارد', 'تریلیون'],
  joiner: ' و ',
  point: 'ممیز',
}

const normalizeLocale = (locale = 'en') => {
  const value = String(locale || 'en').toLowerCase()
  if (value.startsWith('fa') || value.startsWith('ps') || value.startsWith('pa')) {
    return 'fa'
  }

  return 'en'
}

const toSafeNumber = (value) => {
  const normalized = typeof value === 'string' ? value.replace(/,/g, '').trim() : value
  const parsed = Number(normalized)
  return Number.isFinite(parsed) ? parsed : 0
}

const integerToEnglishWords = (number) => {
  if (number === 0) return ENGLISH.zero

  const chunkToWords = (chunk) => {
    const words = []
    const hundreds = Math.floor(chunk / 100)
    const remainder = chunk % 100

    if (hundreds > 0) {
      words.push(`${ENGLISH.ones[hundreds]} hundred`)
    }

    if (remainder >= 20) {
      const ten = Math.floor(remainder / 10)
      const one = remainder % 10
      words.push(ENGLISH.tens[ten] + (one ? `-${ENGLISH.ones[one]}` : ''))
    } else if (remainder >= 10) {
      words.push(ENGLISH.teens[remainder - 10])
    } else if (remainder > 0) {
      words.push(ENGLISH.ones[remainder])
    }

    return words.join(ENGLISH.joiner)
  }

  const parts = []
  let working = number
  let scaleIndex = 0

  while (working > 0) {
    const chunk = working % 1000
    if (chunk > 0) {
      parts.unshift([chunkToWords(chunk), ENGLISH.scales[scaleIndex]].filter(Boolean).join(' '))
    }
    working = Math.floor(working / 1000)
    scaleIndex += 1
  }

  return parts.join(ENGLISH.joiner)
}

const integerToPersianWords = (number) => {
  if (number === 0) return PERSIAN.zero

  const chunkToWords = (chunk) => {
    const words = []
    const hundreds = Math.floor(chunk / 100)
    const remainder = chunk % 100

    if (hundreds > 0) {
      words.push(PERSIAN.hundreds[hundreds])
    }

    if (remainder >= 20) {
      const ten = Math.floor(remainder / 10)
      const one = remainder % 10
      words.push(PERSIAN.tens[ten])
      if (one > 0) words.push(PERSIAN.ones[one])
    } else if (remainder >= 10) {
      words.push(PERSIAN.teens[remainder - 10])
    } else if (remainder > 0) {
      words.push(PERSIAN.ones[remainder])
    }

    return words.join(PERSIAN.joiner)
  }

  const parts = []
  let working = number
  let scaleIndex = 0

  while (working > 0) {
    const chunk = working % 1000
    if (chunk > 0) {
      parts.unshift([chunkToWords(chunk), PERSIAN.scales[scaleIndex]].filter(Boolean).join(' '))
    }
    working = Math.floor(working / 1000)
    scaleIndex += 1
  }

  return parts.join(PERSIAN.joiner)
}

const fractionToWords = (fraction, locale) => {
  if (!fraction) return ''

  const integerFraction = Number(String(fraction).replace(/^0\./, ''))
  if (!Number.isFinite(integerFraction) || integerFraction === 0) return ''

  return locale === 'fa'
    ? `${PERSIAN.point} ${integerToPersianWords(integerFraction)}`
    : `${ENGLISH.point} ${String(fraction).replace(/^0\./, '').split('').map((digit) => ENGLISH.ones[Number(digit)]).join(' ')}`
}

export function numberToWords(value, locale = 'en') {
  const normalizedLocale = normalizeLocale(locale)
  const parsed = toSafeNumber(value)
  const negative = parsed < 0 ? parsed * -1 : parsed
  const whole = Math.floor(negative)
  const fraction = Number((negative - whole).toFixed(2))

  const wholeWords = normalizedLocale === 'fa'
    ? integerToPersianWords(whole)
    : integerToEnglishWords(whole)

  const fractionWords = fractionToWords(fraction, normalizedLocale)
  const negativePrefix = parsed < 0
    ? normalizedLocale === 'fa' ? 'منفی ' : 'minus '
    : ''

  return `${negativePrefix}${wholeWords}${fractionWords ? ` ${fractionWords}` : ''}`.trim()
}

export default numberToWords
