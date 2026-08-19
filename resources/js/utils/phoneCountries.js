/**
 * Country dial codes for NextPhoneInput.
 *
 * `len` lists the valid national-number lengths (the digits after the dial
 * code, trunk "0" removed). It is only filled in where we are confident about
 * the numbering plan; everything else falls back to the E.164 ceiling of 15
 * total digits, which is what stops a field from swallowing an unbounded
 * number of keystrokes.
 *
 * `main` marks the primary country for a dial code shared by several
 * territories (+1, +7, +44 ...), so parsing an incoming number picks a stable
 * one instead of whichever happens to be first alphabetically.
 */
export const PHONE_COUNTRIES = [
    { iso: 'AF', name: 'Afghanistan', dial: '93', len: [9] },
    { iso: 'AL', name: 'Albania', dial: '355' },
    { iso: 'DZ', name: 'Algeria', dial: '213', len: [9] },
    { iso: 'AD', name: 'Andorra', dial: '376', len: [6] },
    { iso: 'AO', name: 'Angola', dial: '244', len: [9] },
    { iso: 'AG', name: 'Antigua and Barbuda', dial: '1268', len: [7] },
    { iso: 'AR', name: 'Argentina', dial: '54' },
    { iso: 'AM', name: 'Armenia', dial: '374', len: [8] },
    { iso: 'AU', name: 'Australia', dial: '61', len: [9] },
    { iso: 'AT', name: 'Austria', dial: '43' },
    { iso: 'AZ', name: 'Azerbaijan', dial: '994', len: [9] },
    { iso: 'BS', name: 'Bahamas', dial: '1242', len: [7] },
    { iso: 'BH', name: 'Bahrain', dial: '973', len: [8] },
    { iso: 'BD', name: 'Bangladesh', dial: '880', len: [10] },
    { iso: 'BB', name: 'Barbados', dial: '1246', len: [7] },
    { iso: 'BY', name: 'Belarus', dial: '375', len: [9] },
    { iso: 'BE', name: 'Belgium', dial: '32', len: [9] },
    { iso: 'BZ', name: 'Belize', dial: '501', len: [7] },
    { iso: 'BJ', name: 'Benin', dial: '229' },
    { iso: 'BT', name: 'Bhutan', dial: '975', len: [8] },
    { iso: 'BO', name: 'Bolivia', dial: '591', len: [8] },
    { iso: 'BA', name: 'Bosnia and Herzegovina', dial: '387' },
    { iso: 'BW', name: 'Botswana', dial: '267', len: [8] },
    { iso: 'BR', name: 'Brazil', dial: '55', len: [10, 11] },
    { iso: 'BN', name: 'Brunei', dial: '673', len: [7] },
    { iso: 'BG', name: 'Bulgaria', dial: '359' },
    { iso: 'BF', name: 'Burkina Faso', dial: '226', len: [8] },
    { iso: 'BI', name: 'Burundi', dial: '257', len: [8] },
    { iso: 'KH', name: 'Cambodia', dial: '855' },
    { iso: 'CM', name: 'Cameroon', dial: '237', len: [9] },
    { iso: 'CA', name: 'Canada', dial: '1', len: [10] },
    { iso: 'CV', name: 'Cape Verde', dial: '238', len: [7] },
    { iso: 'CF', name: 'Central African Republic', dial: '236', len: [8] },
    { iso: 'TD', name: 'Chad', dial: '235', len: [8] },
    { iso: 'CL', name: 'Chile', dial: '56', len: [9] },
    { iso: 'CN', name: 'China', dial: '86', len: [11] },
    { iso: 'CO', name: 'Colombia', dial: '57', len: [10] },
    { iso: 'KM', name: 'Comoros', dial: '269', len: [7] },
    { iso: 'CG', name: 'Congo', dial: '242', len: [9] },
    { iso: 'CD', name: 'Congo (DRC)', dial: '243', len: [9] },
    { iso: 'CR', name: 'Costa Rica', dial: '506', len: [8] },
    { iso: 'CI', name: "Côte d'Ivoire", dial: '225', len: [10] },
    { iso: 'HR', name: 'Croatia', dial: '385' },
    { iso: 'CU', name: 'Cuba', dial: '53', len: [8] },
    { iso: 'CY', name: 'Cyprus', dial: '357', len: [8] },
    { iso: 'CZ', name: 'Czechia', dial: '420', len: [9] },
    { iso: 'DK', name: 'Denmark', dial: '45', len: [8] },
    { iso: 'DJ', name: 'Djibouti', dial: '253', len: [8] },
    { iso: 'DM', name: 'Dominica', dial: '1767', len: [7] },
    { iso: 'DO', name: 'Dominican Republic', dial: '1809', len: [7] },
    { iso: 'EC', name: 'Ecuador', dial: '593', len: [9] },
    { iso: 'EG', name: 'Egypt', dial: '20', len: [10] },
    { iso: 'SV', name: 'El Salvador', dial: '503', len: [8] },
    { iso: 'GQ', name: 'Equatorial Guinea', dial: '240', len: [9] },
    { iso: 'ER', name: 'Eritrea', dial: '291', len: [7] },
    { iso: 'EE', name: 'Estonia', dial: '372' },
    { iso: 'ET', name: 'Ethiopia', dial: '251', len: [9] },
    { iso: 'FJ', name: 'Fiji', dial: '679', len: [7] },
    { iso: 'FI', name: 'Finland', dial: '358' },
    { iso: 'FR', name: 'France', dial: '33', len: [9] },
    { iso: 'GA', name: 'Gabon', dial: '241' },
    { iso: 'GM', name: 'Gambia', dial: '220', len: [7] },
    { iso: 'GE', name: 'Georgia', dial: '995', len: [9] },
    { iso: 'DE', name: 'Germany', dial: '49' },
    { iso: 'GH', name: 'Ghana', dial: '233', len: [9] },
    { iso: 'GR', name: 'Greece', dial: '30', len: [10] },
    { iso: 'GD', name: 'Grenada', dial: '1473', len: [7] },
    { iso: 'GT', name: 'Guatemala', dial: '502', len: [8] },
    { iso: 'GN', name: 'Guinea', dial: '224', len: [9] },
    { iso: 'GW', name: 'Guinea-Bissau', dial: '245', len: [9] },
    { iso: 'GY', name: 'Guyana', dial: '592', len: [7] },
    { iso: 'HT', name: 'Haiti', dial: '509', len: [8] },
    { iso: 'HN', name: 'Honduras', dial: '504', len: [8] },
    { iso: 'HK', name: 'Hong Kong', dial: '852', len: [8] },
    { iso: 'HU', name: 'Hungary', dial: '36', len: [9] },
    { iso: 'IS', name: 'Iceland', dial: '354', len: [7] },
    { iso: 'IN', name: 'India', dial: '91', len: [10] },
    { iso: 'ID', name: 'Indonesia', dial: '62' },
    { iso: 'IR', name: 'Iran', dial: '98', len: [10] },
    { iso: 'IQ', name: 'Iraq', dial: '964', len: [10] },
    { iso: 'IE', name: 'Ireland', dial: '353', len: [9] },
    { iso: 'IL', name: 'Israel', dial: '972', len: [9] },
    { iso: 'IT', name: 'Italy', dial: '39', len: [9, 10] },
    { iso: 'JM', name: 'Jamaica', dial: '1876', len: [7] },
    { iso: 'JP', name: 'Japan', dial: '81', len: [10] },
    { iso: 'JO', name: 'Jordan', dial: '962', len: [9] },
    { iso: 'KZ', name: 'Kazakhstan', dial: '7', len: [10] },
    { iso: 'KE', name: 'Kenya', dial: '254', len: [9] },
    { iso: 'KW', name: 'Kuwait', dial: '965', len: [8] },
    { iso: 'KG', name: 'Kyrgyzstan', dial: '996', len: [9] },
    { iso: 'LA', name: 'Laos', dial: '856' },
    { iso: 'LV', name: 'Latvia', dial: '371', len: [8] },
    { iso: 'LB', name: 'Lebanon', dial: '961' },
    { iso: 'LS', name: 'Lesotho', dial: '266', len: [8] },
    { iso: 'LR', name: 'Liberia', dial: '231' },
    { iso: 'LY', name: 'Libya', dial: '218', len: [9] },
    { iso: 'LI', name: 'Liechtenstein', dial: '423', len: [7] },
    { iso: 'LT', name: 'Lithuania', dial: '370', len: [8] },
    { iso: 'LU', name: 'Luxembourg', dial: '352' },
    { iso: 'MO', name: 'Macao', dial: '853', len: [8] },
    { iso: 'MG', name: 'Madagascar', dial: '261', len: [9] },
    { iso: 'MW', name: 'Malawi', dial: '265', len: [9] },
    { iso: 'MY', name: 'Malaysia', dial: '60' },
    { iso: 'MV', name: 'Maldives', dial: '960', len: [7] },
    { iso: 'ML', name: 'Mali', dial: '223', len: [8] },
    { iso: 'MT', name: 'Malta', dial: '356', len: [8] },
    { iso: 'MR', name: 'Mauritania', dial: '222', len: [8] },
    { iso: 'MU', name: 'Mauritius', dial: '230', len: [8] },
    { iso: 'MX', name: 'Mexico', dial: '52', len: [10] },
    { iso: 'MD', name: 'Moldova', dial: '373', len: [8] },
    { iso: 'MC', name: 'Monaco', dial: '377' },
    { iso: 'MN', name: 'Mongolia', dial: '976', len: [8] },
    { iso: 'ME', name: 'Montenegro', dial: '382' },
    { iso: 'MA', name: 'Morocco', dial: '212', len: [9] },
    { iso: 'MZ', name: 'Mozambique', dial: '258', len: [9] },
    { iso: 'MM', name: 'Myanmar', dial: '95' },
    { iso: 'NA', name: 'Namibia', dial: '264', len: [9] },
    { iso: 'NP', name: 'Nepal', dial: '977', len: [10] },
    { iso: 'NL', name: 'Netherlands', dial: '31', len: [9] },
    { iso: 'NZ', name: 'New Zealand', dial: '64' },
    { iso: 'NI', name: 'Nicaragua', dial: '505', len: [8] },
    { iso: 'NE', name: 'Niger', dial: '227', len: [8] },
    { iso: 'NG', name: 'Nigeria', dial: '234', len: [10] },
    { iso: 'MK', name: 'North Macedonia', dial: '389', len: [8] },
    { iso: 'NO', name: 'Norway', dial: '47', len: [8] },
    { iso: 'OM', name: 'Oman', dial: '968', len: [8] },
    { iso: 'PK', name: 'Pakistan', dial: '92', len: [10] },
    { iso: 'PS', name: 'Palestine', dial: '970', len: [9] },
    { iso: 'PA', name: 'Panama', dial: '507', len: [8] },
    { iso: 'PG', name: 'Papua New Guinea', dial: '675', len: [8] },
    { iso: 'PY', name: 'Paraguay', dial: '595', len: [9] },
    { iso: 'PE', name: 'Peru', dial: '51', len: [9] },
    { iso: 'PH', name: 'Philippines', dial: '63', len: [10] },
    { iso: 'PL', name: 'Poland', dial: '48', len: [9] },
    { iso: 'PT', name: 'Portugal', dial: '351', len: [9] },
    { iso: 'QA', name: 'Qatar', dial: '974', len: [8] },
    { iso: 'RO', name: 'Romania', dial: '40', len: [9] },
    { iso: 'RU', name: 'Russia', dial: '7', main: true, len: [10] },
    { iso: 'RW', name: 'Rwanda', dial: '250', len: [9] },
    { iso: 'SA', name: 'Saudi Arabia', dial: '966', len: [9] },
    { iso: 'SN', name: 'Senegal', dial: '221', len: [9] },
    { iso: 'RS', name: 'Serbia', dial: '381' },
    { iso: 'SC', name: 'Seychelles', dial: '248', len: [7] },
    { iso: 'SL', name: 'Sierra Leone', dial: '232', len: [8] },
    { iso: 'SG', name: 'Singapore', dial: '65', len: [8] },
    { iso: 'SK', name: 'Slovakia', dial: '421', len: [9] },
    { iso: 'SI', name: 'Slovenia', dial: '386', len: [8] },
    { iso: 'SO', name: 'Somalia', dial: '252' },
    { iso: 'ZA', name: 'South Africa', dial: '27', len: [9] },
    { iso: 'KR', name: 'South Korea', dial: '82' },
    { iso: 'SS', name: 'South Sudan', dial: '211', len: [9] },
    { iso: 'ES', name: 'Spain', dial: '34', len: [9] },
    { iso: 'LK', name: 'Sri Lanka', dial: '94', len: [9] },
    { iso: 'SD', name: 'Sudan', dial: '249', len: [9] },
    { iso: 'SR', name: 'Suriname', dial: '597' },
    { iso: 'SE', name: 'Sweden', dial: '46' },
    { iso: 'CH', name: 'Switzerland', dial: '41', len: [9] },
    { iso: 'SY', name: 'Syria', dial: '963', len: [9] },
    { iso: 'TW', name: 'Taiwan', dial: '886', len: [9] },
    { iso: 'TJ', name: 'Tajikistan', dial: '992', len: [9] },
    { iso: 'TZ', name: 'Tanzania', dial: '255', len: [9] },
    { iso: 'TH', name: 'Thailand', dial: '66', len: [9] },
    { iso: 'TG', name: 'Togo', dial: '228', len: [8] },
    { iso: 'TT', name: 'Trinidad and Tobago', dial: '1868', len: [7] },
    { iso: 'TN', name: 'Tunisia', dial: '216', len: [8] },
    { iso: 'TR', name: 'Türkiye', dial: '90', len: [10] },
    { iso: 'TM', name: 'Turkmenistan', dial: '993', len: [8] },
    { iso: 'UG', name: 'Uganda', dial: '256', len: [9] },
    { iso: 'UA', name: 'Ukraine', dial: '380', len: [9] },
    { iso: 'AE', name: 'United Arab Emirates', dial: '971', len: [9] },
    { iso: 'GB', name: 'United Kingdom', dial: '44', main: true, len: [10] },
    { iso: 'US', name: 'United States', dial: '1', main: true, len: [10] },
    { iso: 'UY', name: 'Uruguay', dial: '598', len: [8] },
    { iso: 'UZ', name: 'Uzbekistan', dial: '998', len: [9] },
    { iso: 'VE', name: 'Venezuela', dial: '58', len: [10] },
    { iso: 'VN', name: 'Vietnam', dial: '84' },
    { iso: 'YE', name: 'Yemen', dial: '967' },
    { iso: 'ZM', name: 'Zambia', dial: '260', len: [9] },
    { iso: 'ZW', name: 'Zimbabwe', dial: '263', len: [9] },
];

/** E.164 caps a number at 15 digits, dial code included. */
export const E164_MAX_DIGITS = 15;

const BY_ISO = new Map(PHONE_COUNTRIES.map((country) => [country.iso, country]));

// Longest dial code first so "+1268" (Antigua) wins over "+1" (US) when parsing.
const BY_DIAL_LENGTH = [...PHONE_COUNTRIES].sort((a, b) => b.dial.length - a.dial.length);

/** Regional-indicator flag, derived from the ISO code instead of stored per row. */
export function countryFlag(iso) {
    return String(iso || '')
        .toUpperCase()
        .replace(/[A-Z]/g, (char) => String.fromCodePoint(0x1f1e6 + char.charCodeAt(0) - 65));
}

export function findCountry(iso) {
    return BY_ISO.get(String(iso || '').toUpperCase()) ?? null;
}

/** Longest matching dial code for a bare digit string (no leading + or 00). */
export function findCountryByDial(digits) {
    const value = String(digits || '');

    return BY_DIAL_LENGTH.find((country) => value.startsWith(country.dial)) ?? null;
}

/** How many national digits this country accepts before the field stops taking input. */
export function maxNationalDigits(country) {
    if (!country) return E164_MAX_DIGITS;
    if (country.len?.length) return Math.max(...country.len);

    return Math.max(1, E164_MAX_DIGITS - country.dial.length);
}

export function minNationalDigits(country) {
    if (country?.len?.length) return Math.min(...country.len);

    // ITU numbers shorter than this are short codes, never subscriber numbers.
    return 4;
}

/**
 * Split a national number into readable groups: threes from the left, with a
 * trailing single digit folded back into the previous group (773502152 →
 * "773 502 152", 2025550147 → "202 555 0147").
 */
export function groupNationalDigits(digits) {
    const value = String(digits || '');
    if (value.length <= 3) return value;

    const groups = value.match(/.{1,3}/g) ?? [];

    if (groups.length > 1 && groups[groups.length - 1].length === 1) {
        groups[groups.length - 2] += groups.pop();
    }

    return groups.join(' ');
}
