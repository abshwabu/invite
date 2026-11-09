<?php

namespace App\Services;

use DateTimeInterface;

class EthiopianCalendar
{
    public const MONTHS_AM = [
        1 => 'መስከረም',
        2 => 'ጥቅምት',
        3 => 'ኅዳር',
        4 => 'ታኅሣሥ',
        5 => 'ጥር',
        6 => 'የካቲት',
        7 => 'መጋቢት',
        8 => 'ሚያዝያ',
        9 => 'ግንቦት',
        10 => 'ሰኔ',
        11 => 'ሐምሌ',
        12 => 'ነሐሴ',
        13 => 'ጳጉሜ',
    ];

    public const MONTHS_EN = [
        1 => 'Meskerem',
        2 => 'Tikimt',
        3 => 'Hidar',
        4 => 'Tahsas',
        5 => 'Tir',
        6 => 'Yakatit',
        7 => 'Megabit',
        8 => 'Miyazya',
        9 => 'Ginbot',
        10 => 'Sene',
        11 => 'Hamle',
        12 => 'Nehase',
        13 => 'Pagume',
    ];

    /**
     * Convert Gregorian date to Ethiopian calendar date.
     *
     * @return array{year: int, month: int, day: int, month_name_am: string, month_name_en: string, formatted_am: string, formatted_en: string}
     */
    public static function fromGregorian(DateTimeInterface|string|null $date): ?array
    {
        if (empty($date)) {
            return null;
        }

        if (is_string($date)) {
            try {
                $date = new \DateTimeImmutable($date);
            } catch (\Throwable) {
                return null;
            }
        }

        $gy = (int) $date->format('Y');
        $gm = (int) $date->format('n');
        $gd = (int) $date->format('j');

        // Julian Day Number algorithm for Gregorian date
        $a = intdiv(14 - $gm, 12);
        $y = $gy + 4800 - $a;
        $m = $gm + 12 * $a - 3;
        $jdn = $gd + intdiv(153 * $m + 2, 5) + 365 * $y + intdiv($y, 4) - intdiv($y, 100) + intdiv($y, 400) - 32045;

        // Ethiopian calendar era start JDN = 1723856 (August 29, 8 AD Julian)
        $r = ($jdn - 1723856) % 1461;
        $n = ($r % 365) + 365 * intdiv($r, 1460);
        $ey = 4 * intdiv($jdn - 1723856, 1461) + intdiv($r, 365) - intdiv($r, 1460);
        $em = intdiv($n, 30) + 1;
        $ed = ($n % 30) + 1;

        $monthAm = self::MONTHS_AM[$em] ?? '';
        $monthEn = self::MONTHS_EN[$em] ?? '';

        return [
            'year' => $ey,
            'month' => $em,
            'day' => $ed,
            'month_name_am' => $monthAm,
            'month_name_en' => $monthEn,
            'formatted_am' => "{$monthAm} {$ed}, {$ey}",
            'formatted_en' => "{$monthEn} {$ed}, {$ey}",
        ];
    }
}
