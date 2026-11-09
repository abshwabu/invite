export const ETHIOPIAN_MONTHS_AM = [
    '', 'መስከረም', 'ጥቅምት', 'ኅዳር', 'ታኅሣሥ', 'ጥር', 'የካቲት',
    'መጋቢት', 'ሚያዝያ', 'ግንቦት', 'ሰኔ', 'ሐምሌ', 'ነሐሴ', 'ጳጉሜ'
];

export const ETHIOPIAN_MONTHS_EN = [
    '', 'Meskerem', 'Tikimt', 'Hidar', 'Tahsas', 'Tir', 'Yakatit',
    'Megabit', 'Miyazya', 'Ginbot', 'Sene', 'Hamle', 'Nehase', 'Pagume'
];

export function toEthiopian(dateInput) {
    if (!dateInput) return null;

    const date = typeof dateInput === 'string' ? new Date(dateInput) : dateInput;
    if (isNaN(date.getTime())) return null;

    const gy = date.getFullYear();
    const gm = date.getMonth() + 1;
    const gd = date.getDate();

    // JDN calculation
    const a = Math.floor((14 - gm) / 12);
    const y = gy + 4800 - a;
    const m = gm + 12 * a - 3;
    const jdn = gd + Math.floor((153 * m + 2) / 5) + 365 * y + Math.floor(y / 4) - Math.floor(y / 100) + Math.floor(y / 400) - 32045;

    const r = (jdn - 1723856) % 1461;
    const n = (r % 365) + 365 * Math.floor(r / 1460);
    const ey = 4 * Math.floor((jdn - 1723856) / 1461) + Math.floor(r / 365) - Math.floor(r / 1460);
    const em = Math.floor(n / 30) + 1;
    const ed = (n % 30) + 1;

    const monthAm = ETHIOPIAN_MONTHS_AM[em] || '';
    const monthEn = ETHIOPIAN_MONTHS_EN[em] || '';

    return {
        year: ey,
        month: em,
        day: ed,
        monthNameAm: monthAm,
        monthNameEn: monthEn,
        formattedAm: `${monthAm} ${ed}, ${ey}`,
        formattedEn: `${monthEn} ${ed}, ${ey}`,
    };
}
