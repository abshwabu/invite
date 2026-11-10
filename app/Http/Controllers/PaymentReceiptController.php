<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentReceiptController extends Controller
{
    /**
     * Stream private receipt image securely via signed URL.
     */
    public function show(Request $request, Payment $payment): StreamedResponse
    {
        // Must be signed URL
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired receipt signature.');
        }

        // Authorize: payment owner OR superadmin
        $isOwner = Auth::check() && Auth::id() === $payment->user_id;
        $isSuperAdmin = Auth::guard('superadmin')->check();

        if (! $isOwner && ! $isSuperAdmin) {
            abort(403, 'Unauthorized access to receipt image.');
        }

        if (! $payment->receipt_path || ! Storage::disk('local')->exists($payment->receipt_path)) {
            abort(404, 'Receipt image not found or purged.');
        }

        $path = $payment->receipt_path;
        $mime = Storage::disk('local')->mimeType($path) ?: 'image/webp';
        $size = Storage::disk('local')->size($path);

        return response()->stream(
            function () use ($path) {
                $stream = Storage::disk('local')->readStream($path);
                if ($stream) {
                    fpassthru($stream);
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => $mime,
                'Content-Length' => $size,
                'Content-Disposition' => 'inline; filename="receipt-' . $payment->payment_code . '.webp"',
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            ]
        );
    }
}
