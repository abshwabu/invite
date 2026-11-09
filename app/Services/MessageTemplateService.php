<?php

namespace App\Services;

class MessageTemplateService
{
    /**
     * Standard GSM 7-bit default alphabet.
     */
    protected const GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ\x1bÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    /**
     * Standard GSM 7-bit extended alphabet (each costs 2 characters/septets).
     */
    protected const GSM_EXTENDED = "^{}\\[~]|€";

    /**
     * Default invitation message templates.
     */
    public const DEFAULT_INVITATION_EN = '{host_names} invites you to {event_title} on {date} at {time}, {venue}. View your invitation: {link}';
    public const DEFAULT_INVITATION_AM = '{host_names} ወደ {event_title} በ {date} {time} {venue} ጋብዘዎታል። ካርድዎን ይመልከቱ፡ {link}';

    /**
     * Replace merge fields in a template string.
     * Supports both single braces {key} and double braces {{key}}.
     */
    public function render(string $template, array $data): string
    {
        $replacements = [];

        foreach ($data as $key => $value) {
            $val = (string) ($value ?? '');
            $replacements['{' . $key . '}'] = $val;
            $replacements['{{' . $key . '}}'] = $val;
        }

        return strtr($template, $replacements);
    }

    /**
     * Check if a string contains only characters from the GSM 7-bit character set.
     */
    public function isGsm7(string $text): bool
    {
        $len = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            if (mb_strpos(self::GSM_BASIC, $char, 0, 'UTF-8') === false &&
                mb_strpos(self::GSM_EXTENDED, $char, 0, 'UTF-8') === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate SMS segments, character counts, and encoding type (GSM-7 vs Unicode).
     *
     * GSM-7:
     * - Single segment: up to 160 characters
     * - Multi-part segment: 153 characters per segment
     * - Extended characters count as 2
     *
     * Unicode (e.g. Amharic Ge'ez):
     * - Single segment: up to 70 characters
     * - Multi-part segment: 67 characters per segment
     *
     * @return array{
     *     encoding: 'gsm7'|'unicode',
     *     character_count: int,
     *     segment_count: int,
     *     remaining: int,
     *     single_limit: int,
     *     multi_limit: int
     * }
     */
    public function calculateSegments(string $text): array
    {
        $length = mb_strlen($text, 'UTF-8');

        if ($length === 0) {
            return [
                'encoding' => 'gsm7',
                'character_count' => 0,
                'segment_count' => 1,
                'remaining' => 160,
                'single_limit' => 160,
                'multi_limit' => 153,
            ];
        }

        $isGsm = $this->isGsm7($text);

        if (! $isGsm) {
            // Unicode (Amharic or special characters)
            $singleLimit = 70;
            $multiLimit = 67;

            if ($length <= $singleLimit) {
                $segmentCount = 1;
                $remaining = $singleLimit - $length;
            } else {
                $segmentCount = (int) ceil($length / $multiLimit);
                $remaining = ($segmentCount * $multiLimit) - $length;
            }

            return [
                'encoding' => 'unicode',
                'character_count' => $length,
                'segment_count' => $segmentCount,
                'remaining' => $remaining,
                'single_limit' => $singleLimit,
                'multi_limit' => $multiLimit,
            ];
        }

        // GSM-7: Calculate effective length including extended characters (which cost 2)
        $effectiveLength = 0;
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            if (mb_strpos(self::GSM_EXTENDED, $char, 0, 'UTF-8') !== false) {
                $effectiveLength += 2;
            } else {
                $effectiveLength += 1;
            }
        }

        $singleLimit = 160;
        $multiLimit = 153;

        if ($effectiveLength <= $singleLimit) {
            $segmentCount = 1;
            $remaining = $singleLimit - $effectiveLength;
        } else {
            $segmentCount = (int) ceil($effectiveLength / $multiLimit);
            $remaining = ($segmentCount * $multiLimit) - $effectiveLength;
        }

        return [
            'encoding' => 'gsm7',
            'character_count' => $effectiveLength,
            'segment_count' => $segmentCount,
            'remaining' => $remaining,
            'single_limit' => $singleLimit,
            'multi_limit' => $multiLimit,
        ];
    }
}
