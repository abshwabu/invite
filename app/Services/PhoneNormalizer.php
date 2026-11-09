<?php

namespace App\Services;

class PhoneNormalizer
{
    /**
     * Normalize an Ethiopian phone number to international format:
     * +2519XXXXXXXX or +2517XXXXXXXX
     */
    public static function normalize(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Clean spaces, hyphens, parentheses, dots
        $cleaned = preg_replace('/[^\d+]/', '', trim($phone));

        if (empty($cleaned)) {
            return null;
        }

        // Replace leading 00 with +
        if (str_starts_with($cleaned, '00')) {
            $cleaned = '+' . substr($cleaned, 2);
        }

        // Handle +251 prefix
        if (str_starts_with($cleaned, '+251')) {
            $national = substr($cleaned, 4);
        } elseif (str_starts_with($cleaned, '251')) {
            $national = substr($cleaned, 3);
        } elseif (str_starts_with($cleaned, '0')) {
            $national = substr($cleaned, 1);
        } else {
            $national = $cleaned;
        }

        // Ethiopian mobile numbers must be 9 digits and start with 9 or 7
        if (preg_match('/^[97]\d{8}$/', $national)) {
            return '+251' . $national;
        }

        return null;
    }

    /**
     * Check if a phone number is a valid Ethiopian mobile number.
     */
    public static function isValid(?string $phone): bool
    {
        return self::normalize($phone) !== null;
    }
}
