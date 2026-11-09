// Numbers come from the server padded to their column's scale: 1.00000000, 10.0000.
// The padding says nothing about the value, so it is dropped wherever one is shown.

/** For a value going into an input or a text: 1.00000000 becomes 1. Blanks stay blank. */
export function plainNumber(value) {
    if (value === null || value === undefined || value === '') return value
    const number = Number(value)
    return Number.isNaN(number) ? value : String(number)
}

/** For display: grouped, with no padding and at most `digits` decimals. 30061.2250 becomes 30,061.225. */
export function formatPlain(value, digits = 4) {
    return Number(value ?? 0).toLocaleString(undefined, { maximumFractionDigits: digits })
}
