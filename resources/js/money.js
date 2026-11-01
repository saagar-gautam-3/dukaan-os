// Decimal-string helpers for money (scale 2) and quantities (scale 3).
// All parsing/formatting uses integer (BigInt) math; no floating point.

export function parseDecimal(value, scale) {
    const raw = String(value ?? '').trim();
    if (raw === '' || raw === '-' || raw === '+') {
        return 0n;
    }
    const negative = raw.startsWith('-');
    const unsigned = negative || raw.startsWith('+') ? raw.slice(1) : raw;
    const [whole, fraction = ''] = unsigned.split('.');
    const digits = ((whole || '0') + (fraction || '').padEnd(scale, '0').slice(0, scale)).replace(/^0+(?=\d)/, '') || '0';
    const parsed = BigInt(digits);
    return negative ? -parsed : parsed;
}

export function parseMoney(value) {
    return parseDecimal(value, 2);
}

export function parseQuantity(value) {
    return parseDecimal(value, 3);
}

export function formatDecimal(units, scale) {
    const negative = units < 0n;
    const absolute = negative ? -units : units;
    const text = absolute.toString().padStart(scale + 1, '0');
    const whole = text.slice(0, -scale) || '0';
    const fraction = text.slice(-scale);
    return `${negative ? '-' : ''}${whole}${scale > 0 ? '.' + fraction : ''}`;
}

export function formatMoney(units) {
    return formatDecimal(units, 2);
}

export function formatQuantity(units) {
    return formatDecimal(units, 3);
}
