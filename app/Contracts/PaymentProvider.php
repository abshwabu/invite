<?php

namespace App\Contracts;

use App\Models\Payment;
use App\Models\SuperAdmin;
use App\Models\Tier;
use App\Models\User;

interface PaymentProvider
{
    /**
     * Initiate a payment for a tier purchase.
     */
    public function initiate(User $user, Tier $tier): Payment;

    /**
     * Submit proof of payment (receipt screenshot and/or transaction reference).
     */
    public function submitProof(Payment $payment, array $proofData): Payment;

    /**
     * Approve a payment and activate tier subscription.
     */
    public function approve(
        Payment $payment,
        ?float $amountVerified = null,
        ?SuperAdmin $reviewer = null,
        ?string $note = null
    ): Payment;

    /**
     * Reject a payment with a required reason.
     */
    public function reject(
        Payment $payment,
        string $reason,
        ?SuperAdmin $reviewer = null
    ): Payment;

    /**
     * Request additional information from the user for verification.
     */
    public function requestInfo(
        Payment $payment,
        string $message,
        ?SuperAdmin $reviewer = null
    ): Payment;

    /**
     * Refund payment and revoke/adjust subscription quota.
     */
    public function refund(
        Payment $payment,
        ?SuperAdmin $reviewer = null,
        ?string $reason = null
    ): Payment;
}
