<?php

namespace App\Services\Payment;

use App\Contracts\PaymentProvider;
use App\Models\Payment;
use App\Models\SuperAdmin;
use App\Models\Tier;
use App\Models\User;
use RuntimeException;

/**
 * Chapa Payment Gateway Provider (Stub for future integration).
 *
 * When Chapa merchant credentials are ready, this provider will handle:
 * 1. Checkout session initialization via Chapa API (POST https://api.chapa.co/v1/transaction/initialize)
 * 2. Webhook verification for instant payment callbacks (POST /api/webhooks/chapa)
 * 3. Automatic tier activation without manual super admin intervention
 */
class ChapaProvider implements PaymentProvider
{
    public function initiate(User $user, Tier $tier): Payment
    {
        throw new RuntimeException(
            'Chapa gateway integration is pending merchant credentials. Please use ManualPaymentProvider.'
        );
    }

    public function submitProof(Payment $payment, array $proofData): Payment
    {
        throw new RuntimeException('Chapa payments are verified automatically via webhook.');
    }

    public function approve(
        Payment $payment,
        ?float $amountVerified = null,
        ?SuperAdmin $reviewer = null,
        ?string $note = null
    ): Payment {
        throw new RuntimeException('Chapa transactions are automatically approved upon webhook confirmation.');
    }

    public function reject(
        Payment $payment,
        string $reason,
        ?SuperAdmin $reviewer = null
    ): Payment {
        throw new RuntimeException('Chapa transactions cannot be manually rejected via this provider.');
    }

    public function requestInfo(
        Payment $payment,
        string $message,
        ?SuperAdmin $reviewer = null
    ): Payment {
        throw new RuntimeException('Chapa transactions do not require manual info requests.');
    }

    public function refund(
        Payment $payment,
        ?SuperAdmin $reviewer = null,
        ?string $reason = null
    ): Payment {
        throw new RuntimeException('Chapa refund API integration is pending.');
    }
}
