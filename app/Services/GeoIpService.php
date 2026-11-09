<?php

namespace App\Services;

class GeoIpService
{
    /**
     * Resolve 2-letter country code from IP address.
     * Fails gracefully to 'ET' for Ethiopia-targeted local traffic.
     */
    public static function getCountry(?string $ip): string
    {
        if (empty($ip)) {
            return 'ET';
        }

        // Fast path for Cloudflare headers
        if (request() && $header = request()->header('CF-IPCountry')) {
            return strtoupper(substr($header, 0, 2));
        }

        // Local / private network ranges default to target market 'ET'
        if (in_array($ip, ['127.0.0.1', '::1']) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return 'ET';
        }

        return 'ET';
    }
}
