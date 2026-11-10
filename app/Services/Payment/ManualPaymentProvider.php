<?php

namespace App\Services\Payment;

use App\Contracts\PaymentProvider;
use App\Contracts\SmsGateway;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Subscription;
use App\Models\SuperAdmin;
use App\Models\Tier;
use App\Models\UsageLedger;
use App\Models\User;
use App\Notifications\AdminPaymentSubmittedNotification;
use App\Notifications\PaymentApprovedNotification;
use App\Notifications\PaymentNeedsInfoNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Services\AuditLogService;
use App\Services\QuotaService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManualPaymentProvider implements PaymentProvider
{
    public function __construct(
        protected QuotaService $quotaService,
        protected SmsGateway $smsGateway
    ) {}

    /**
     * Initiate a manual payment for a tier purchase.
     */
    public function initiate(User $user, Tier $tier): Payment
    {
        // 1. Rate Limit: at most 3 open (awaiting_proof / submitted / needs_info) payments per user
        $openCount = Payment::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['awaiting_proof', 'submitted', 'needs_info'])
            ->count();

        // If an unsubmitted payment already exists for this exact tier, reuse it for a seamless UX
        $existingDraft = Payment::query()
            ->where('user_id', $user->id)
            ->where('tier_id', $tier->id)
            ->where('status', 'awaiting_proof')
            ->first();

        if ($existingDraft) {
            return $existingDraft;
        }

        $maxOpen = config('invite.payments.max_open_payments_per_user', 3);
        if ($openCount >= $maxOpen) {
            throw ValidationException::withMessages([
                'tier' => "You currently have {$openCount} open payments pending verification. Please complete or wait for them before starting a new purchase.",
            ]);
        }

        // 2. Generate unique payment code e.g. INV-7K3F9Q
        do {
            $code = 'INV-' . strtoupper(Str::random(6));
        } while (Payment::where('payment_code', $code)->exists());

        return DB::transaction(function () use ($user, $tier, $code) {
            $payment = Payment::create([
                'user_id' => $user->id,
                'tier_id' => $tier->id,
                'payment_code' => $code,
                'amount' => $tier->price_amount,
                'amount_expected' => $tier->price_amount,
                'currency' => $tier->currency ?? 'ETB',
                'provider' => 'manual',
                'status' => 'awaiting_proof',
                'flags' => [],
            ]);

            PaymentEvent::create([
                'payment_id' => $payment->id,
                'actor_type' => 'user',
                'actor_id' => $user->id,
                'event' => 'created',
                'meta' => [
                    'tier_id' => $tier->id,
                    'tier_name' => $tier->name_en,
                    'amount_expected' => $tier->price_amount,
                ],
            ]);

            return $payment;
        });
    }

    /**
     * Submit proof of payment (receipt screenshot and/or reference number).
     */
    public function submitProof(Payment $payment, array $proofData): Payment
    {
        // Rate limit: at most 5 submissions per hour per user
        $rateLimitKey = "payment_submissions_{$payment->user_id}";
        $submissionsCount = Cache::get($rateLimitKey, 0);
        $maxSubmissionsPerHour = config('invite.payments.max_submissions_per_hour', 5);

        if ($submissionsCount >= $maxSubmissionsPerHour) {
            throw ValidationException::withMessages([
                'rate_limit' => 'Too many submissions. Please wait before submitting another payment verification.',
            ]);
        }

        $receipt = $proofData['receipt'] ?? null;
        $reference = isset($proofData['reference_number']) ? trim($proofData['reference_number']) : null;

        // Validation: at least ONE is required (both is fine)
        if (empty($receipt) && empty($reference)) {
            throw ValidationException::withMessages([
                'proof' => 'Please provide either a receipt screenshot or a transaction reference number (or both).',
            ]);
        }

        $flags = $payment->flags ?? [];
        $normalizedRef = null;

        // 1. Reference number normalization & duplicate checks
        if (!empty($reference)) {
            $normalizedRef = strtoupper(preg_replace('/\s+/', '', $reference));

            // Check if this reference number was already used on an active/approved payment
            $duplicateRef = Payment::query()
                ->where('reference_number', $normalizedRef)
                ->where('id', '!=', $payment->id)
                ->whereIn('status', ['submitted', 'needs_info', 'approved'])
                ->first();

            if ($duplicateRef) {
                if ($duplicateRef->user_id !== $payment->user_id) {
                    // Hard block when used by another user
                    throw ValidationException::withMessages([
                        'reference_number' => 'This transaction reference number has already been used for another account. Please check your receipt or contact support.',
                    ]);
                } else {
                    $flags['duplicate_reference'] = true;
                }
            }
        }

        // 2. Receipt file handling & duplicate checks
        $storedReceiptPath = $payment->receipt_path;
        $receiptHash = $payment->receipt_hash;
        $receiptPhash = $payment->receipt_phash;

        if ($receipt instanceof UploadedFile) {
            $this->validateReceiptFile($receipt);

            $receiptHash = hash_file('sha256', $receipt->getRealPath());
            $receiptPhash = $this->calculatePerceptualHash($receipt->getRealPath());

            // Check for identical receipt hash across other payments
            $duplicateReceipt = Payment::query()
                ->where('id', '!=', $payment->id)
                ->where(function ($q) use ($receiptHash, $receiptPhash) {
                    $q->where('receipt_hash', $receiptHash);
                    if ($receiptPhash) {
                        $q->orWhere('receipt_phash', $receiptPhash);
                    }
                })
                ->exists();

            if ($duplicateReceipt) {
                $flags['duplicate_receipt'] = true;
            }

            // Strip EXIF, downscale if > 2000px, save clean WebP to private disk
            $storedReceiptPath = $this->processAndStoreReceipt($receipt);
        }

        // 3. Amount declared check
        $amountDeclared = isset($proofData['amount_declared']) && $proofData['amount_declared'] !== ''
            ? (float) $proofData['amount_declared']
            : null;

        if ($amountDeclared !== null) {
            if (abs($amountDeclared - (float) $payment->amount_expected) > 0.01) {
                $flags['amount_mismatch'] = true;
            }
        }

        // 4. New account check (< 1 hour old)
        if ($payment->user && $payment->user->created_at > now()->subHour()) {
            $flags['new_account'] = true;
        }

        return DB::transaction(function () use (
            $payment,
            $proofData,
            $normalizedRef,
            $storedReceiptPath,
            $receiptHash,
            $receiptPhash,
            $amountDeclared,
            $flags,
            $rateLimitKey,
            $submissionsCount
        ) {
            $isResubmission = in_array($payment->status, ['needs_info', 'rejected']);

            $payment->update([
                'status' => 'submitted',
                'method_id' => $proofData['method_id'] ?? $payment->method_id,
                'reference_number' => $normalizedRef ?? $payment->reference_number,
                'receipt_path' => $storedReceiptPath,
                'receipt_hash' => $receiptHash,
                'receipt_phash' => $receiptPhash,
                'amount_declared' => $amountDeclared,
                'paid_at_declared' => $proofData['paid_at_declared'] ?? $payment->paid_at_declared,
                'paid_from' => $proofData['paid_from'] ?? $payment->paid_from,
                'user_note' => $proofData['user_note'] ?? $payment->user_note,
                'submitted_at' => now(),
                'flags' => $flags,
            ]);

            PaymentEvent::create([
                'payment_id' => $payment->id,
                'actor_type' => 'user',
                'actor_id' => $payment->user_id,
                'event' => $isResubmission ? 'resubmitted' : 'submitted',
                'meta' => [
                    'reference_number' => $normalizedRef,
                    'has_receipt' => (bool) $storedReceiptPath,
                    'flags' => $flags,
                ],
            ]);

            // Increment hourly rate limit counter
            Cache::put($rateLimitKey, $submissionsCount + 1, now()->addHour());

            // Notify super admin(s)
            $adminEmails = config('invite.payments.admin_notification_emails', 'admin@invite.et');
            $recipients = array_filter(array_map('trim', explode(',', $adminEmails)));
            if (!empty($recipients)) {
                Notification::route('mail', $recipients)
                    ->notify(new AdminPaymentSubmittedNotification($payment));
            }

            return $payment;
        });
    }

    /**
     * Approve payment, activate tier subscription, and record usage ledger.
     */
    public function approve(
        Payment $payment,
        ?float $amountVerified = null,
        ?SuperAdmin $reviewer = null,
        ?string $note = null
    ): Payment {
        // Idempotent: if already approved, return immediately
        if ($payment->status === 'approved') {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $amountVerified, $reviewer, $note) {
            $verifiedAmount = $amountVerified !== null ? $amountVerified : (float) $payment->amount_expected;

            $payment->update([
                'status' => 'approved',
                'amount_verified' => $verifiedAmount,
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => now(),
                'paid_at' => now(),
                'admin_note' => $note ?? $payment->admin_note,
            ]);

            // Create active subscription for the purchased tier
            $startsAt = now();
            $expiresAt = $payment->tier->validity_days ? now()->addDays($payment->tier->validity_days) : null;

            $subscription = Subscription::create([
                'user_id' => $payment->user_id,
                'tier_id' => $payment->tier_id,
                'cards_total' => $payment->tier->card_quota,
                'cards_used' => 0,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => 'active',
                'source' => 'manual_payment',
                'payment_id' => $payment->id,
                'note' => "Manual payment approved (Code: {$payment->payment_code})",
            ]);

            // Write usage ledger entry for quota addition
            UsageLedger::create([
                'user_id' => $payment->user_id,
                'subscription_id' => $subscription->id,
                'event_id' => null,
                'guest_id' => null,
                'campaign_id' => null,
                'kind' => 'admin_grant',
                'channel' => 'none',
                'quantity' => $payment->tier->card_quota,
                'note' => "Tier purchase activation: {$payment->tier->name_en} (Code: {$payment->payment_code})",
                'created_at' => now(),
            ]);

            // Record timeline event
            PaymentEvent::create([
                'payment_id' => $payment->id,
                'actor_type' => $reviewer ? 'super_admin' : 'system',
                'actor_id' => $reviewer?->id,
                'event' => 'approved',
                'meta' => [
                    'amount_verified' => $verifiedAmount,
                    'subscription_id' => $subscription->id,
                    'cards_quota' => $payment->tier->card_quota,
                ],
            ]);

            // Record Super Admin Audit Log
            if ($reviewer) {
                AuditLogService::log(
                    action: 'payment.approved',
                    actor: $reviewer,
                    subject: $payment,
                    meta: [
                        'payment_code' => $payment->payment_code,
                        'user_id' => $payment->user_id,
                        'amount_verified' => $verifiedAmount,
                    ]
                );
            }

            // User Notification (In-App & Email)
            $payment->user->notify(new PaymentApprovedNotification($payment));

            // Optional SMS Notification
            if (config('invite.payments.notify_user_sms', false) && !empty($payment->user->phone)) {
                $tierName = $payment->tier->name_en;
                $quota = number_format($payment->tier->card_quota);
                $smsText = "Your {$tierName} package on Teri is active. You have {$quota} cards ready to send.";
                try {
                    $this->smsGateway->send($payment->user->phone, $smsText);
                } catch (\Throwable) {}
            }

            return $payment;
        });
    }

    /**
     * Reject a payment with a required reason.
     */
    public function reject(
        Payment $payment,
        string $reason,
        ?SuperAdmin $reviewer = null
    ): Payment {
        if ($payment->status === 'approved') {
            throw new Exception('Cannot reject a payment that has already been approved.');
        }

        return DB::transaction(function () use ($payment, $reason, $reviewer) {
            $payment->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => now(),
            ]);

            PaymentEvent::create([
                'payment_id' => $payment->id,
                'actor_type' => 'super_admin',
                'actor_id' => $reviewer?->id,
                'event' => 'rejected',
                'meta' => [
                    'reason' => $reason,
                ],
            ]);

            if ($reviewer) {
                AuditLogService::log(
                    action: 'payment.rejected',
                    actor: $reviewer,
                    subject: $payment,
                    meta: [
                        'payment_code' => $payment->payment_code,
                        'reason' => $reason,
                    ]
                );
            }

            $payment->user->notify(new PaymentRejectedNotification($payment, $reason));

            return $payment;
        });
    }

    /**
     * Request additional information from the user.
     */
    public function requestInfo(
        Payment $payment,
        string $message,
        ?SuperAdmin $reviewer = null
    ): Payment {
        return DB::transaction(function () use ($payment, $message, $reviewer) {
            $payment->update([
                'status' => 'needs_info',
                'admin_note' => $message,
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => now(),
            ]);

            PaymentEvent::create([
                'payment_id' => $payment->id,
                'actor_type' => 'super_admin',
                'actor_id' => $reviewer?->id,
                'event' => 'info_requested',
                'meta' => [
                    'message' => $message,
                ],
            ]);

            if ($reviewer) {
                AuditLogService::log(
                    action: 'payment.info_requested',
                    actor: $reviewer,
                    subject: $payment,
                    meta: [
                        'payment_code' => $payment->payment_code,
                        'message' => $message,
                    ]
                );
            }

            $payment->user->notify(new PaymentNeedsInfoNotification($payment, $message));

            return $payment;
        });
    }

    /**
     * Refund payment and revoke/adjust subscription quota.
     */
    public function refund(
        Payment $payment,
        ?SuperAdmin $reviewer = null,
        ?string $reason = null
    ): Payment {
        return DB::transaction(function () use ($payment, $reviewer, $reason) {
            // Cancel subscription if one exists
            $subscription = $payment->subscription;
            if ($subscription && $subscription->status === 'active') {
                $remainingCards = max(0, $subscription->cards_total - $subscription->cards_used);

                $subscription->update([
                    'status' => 'cancelled',
                    'note' => ($subscription->note ?? '') . ' | Cancelled on payment refund',
                ]);

                if ($remainingCards > 0) {
                    UsageLedger::create([
                        'user_id' => $payment->user_id,
                        'subscription_id' => $subscription->id,
                        'kind' => 'admin_grant',
                        'channel' => 'none',
                        'quantity' => -1 * $remainingCards,
                        'note' => "Quota revoked on payment refund (Code: {$payment->payment_code})",
                        'created_at' => now(),
                    ]);
                }
            }

            $payment->update([
                'status' => 'refunded',
                'admin_note' => $reason ? ($payment->admin_note . ' [Refund: ' . $reason . ']') : $payment->admin_note,
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => now(),
            ]);

            PaymentEvent::create([
                'payment_id' => $payment->id,
                'actor_type' => 'super_admin',
                'actor_id' => $reviewer?->id,
                'event' => 'refunded',
                'meta' => [
                    'reason' => $reason,
                ],
            ]);

            if ($reviewer) {
                AuditLogService::log(
                    action: 'payment.refunded',
                    actor: $reviewer,
                    subject: $payment,
                    meta: [
                        'payment_code' => $payment->payment_code,
                        'reason' => $reason,
                    ]
                );
            }

            return $payment;
        });
    }

    /**
     * Validate uploaded receipt image type and size.
     */
    protected function validateReceiptFile(UploadedFile $file): void
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $mime = $file->getMimeType();

        if (!in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'receipt' => 'Receipt must be a valid image (PNG, JPG, or WebP).',
            ]);
        }

        // Max 5 MB = 5 * 1024 * 1024 bytes
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'receipt' => 'Receipt image must not exceed 5 MB in size.',
            ]);
        }
    }

    /**
     * Strip EXIF, resize to max 2000px, and store in private storage.
     */
    protected function processAndStoreReceipt(UploadedFile $file): string
    {
        $rawContent = file_get_contents($file->getRealPath());
        $image = @imagecreatefromstring($rawContent);

        if (!$image) {
            throw ValidationException::withMessages([
                'receipt' => 'Could not process the uploaded image file.',
            ]);
        }

        $origW = imagesx($image);
        $origH = imagesy($image);

        $maxDim = 2000;
        if ($origW > $maxDim || $origH > $maxDim) {
            $ratio = min($maxDim / $origW, $maxDim / $origH);
            $newW = (int) round($origW * $ratio);
            $newH = (int) round($origH * $ratio);

            $resized = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
            imagedestroy($image);
            $image = $resized;
        }

        // Generate clean webp data without any EXIF
        ob_start();
        imagewebp($image, null, 85);
        $cleanBytes = ob_get_clean();
        imagedestroy($image);

        $randomName = Str::random(40) . '.webp';
        $path = 'receipts/' . $randomName;

        // Store on local private disk (storage/app/private/receipts)
        Storage::disk('local')->put($path, $cleanBytes);

        return $path;
    }

    /**
     * Compute a 64-bit perceptual hash (average hash) of an image using GD.
     */
    protected function calculatePerceptualHash(string $filePath): ?string
    {
        try {
            $raw = @file_get_contents($filePath);
            if (!$raw) return null;

            $src = @imagecreatefromstring($raw);
            if (!$src) return null;

            $w = imagesx($src);
            $h = imagesy($src);

            // Resize to 8x8 grayscale
            $tiny = imagecreatetruecolor(8, 8);
            imagecopyresampled($tiny, $src, 0, 0, 0, 0, 8, 8, $w, $h);
            imagedestroy($src);

            $pixels = [];
            $totalBrightness = 0;

            for ($y = 0; $y < 8; $y++) {
                for ($x = 0; $x < 8; $x++) {
                    $rgb = imagecolorat($tiny, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    $gray = (int) (0.299 * $r + 0.587 * $g + 0.114 * $b);
                    $pixels[] = $gray;
                    $totalBrightness += $gray;
                }
            }
            imagedestroy($tiny);

            $avg = $totalBrightness / 64;
            $bits = '';
            foreach ($pixels as $p) {
                $bits .= ($p >= $avg) ? '1' : '0';
            }

            // Convert 64-bit binary to hex
            $hex = '';
            for ($i = 0; $i < 64; $i += 4) {
                $nibble = substr($bits, $i, 4);
                $hex .= dechex(bindec($nibble));
            }

            return $hex;
        } catch (\Throwable) {
            return null;
        }
    }
}
