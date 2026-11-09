<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrService
{
    /**
     * Generate an HMAC-signed admission payload for a guest.
     * Contains event_id + guest_id + nonce and sha256 signature.
     */
    public static function generatePayload(int|string $eventId, int|string $guestId, ?string $nonce = null): string
    {
        $nonce = $nonce ?? bin2hex(random_bytes(8));
        $data = "{$eventId}:{$guestId}:{$nonce}";
        $signature = hash_hmac('sha256', $data, (string) config('app.key'));

        return "INVITE:{$data}:{$signature}";
    }

    /**
     * Verify an admission payload.
     *
     * @return array{valid: bool, event_id?: int, guest_id?: int, nonce?: string}
     */
    public static function verifyPayload(string $payload): array
    {
        $parts = explode(':', $payload);
        if (count($parts) !== 5 || $parts[0] !== 'INVITE') {
            return ['valid' => false];
        }

        [$prefix, $eventId, $guestId, $nonce, $providedSig] = $parts;
        $data = "{$eventId}:{$guestId}:{$nonce}";
        $expectedSig = hash_hmac('sha256', $data, (string) config('app.key'));

        if (! hash_equals($expectedSig, $providedSig)) {
            return ['valid' => false];
        }

        return [
            'valid' => true,
            'event_id' => (int) $eventId,
            'guest_id' => (int) $guestId,
            'nonce' => $nonce,
        ];
    }

    /**
     * Generate a standalone SVG string for the QR code.
     */
    public static function generateSvg(string $payload, int $size = 280): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);

        return $writer->writeString($payload);
    }
}
