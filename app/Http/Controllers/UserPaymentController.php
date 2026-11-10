<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentProvider;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\Tier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserPaymentController extends Controller
{
    public function __construct(
        protected PaymentProvider $paymentProvider
    ) {}

    /**
     * List user payments with status history.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $payments = Payment::query()
            ->where('user_id', $user->id)
            ->with(['tier:id,name_en,name_am,slug,card_quota,price_amount', 'method:id,label,type'])
            ->latest('id')
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'payment_code' => $payment->payment_code,
                    'status' => $payment->status,
                    'amount_expected' => (float) $payment->amount_expected,
                    'amount_verified' => $payment->amount_verified ? (float) $payment->amount_verified : null,
                    'reference_number' => $payment->reference_number,
                    'has_receipt' => (bool) $payment->receipt_path,
                    'receipt_url' => $payment->getReceiptUrl(),
                    'tier' => $payment->tier,
                    'method' => $payment->method,
                    'user_note' => $payment->user_note,
                    'admin_note' => $payment->admin_note,
                    'rejection_reason' => $payment->rejection_reason,
                    'submitted_at' => $payment->submitted_at?->toIso8601String(),
                    'reviewed_at' => $payment->reviewed_at?->toIso8601String(),
                    'created_at' => $payment->created_at->toIso8601String(),
                ];
            });

        $activeMethods = PaymentMethod::where('is_active', true)->orderBy('sort')->get();

        return Inertia::render('Payments/Index', [
            'payments' => $payments,
            'paymentMethods' => $activeMethods,
        ]);
    }

    /**
     * Show details of a specific payment.
     */
    public function show(Request $request, Payment $payment): Response
    {
        abort_unless($payment->user_id === $request->user()->id, 403);

        $payment->load(['tier', 'method', 'events']);

        $activeMethods = PaymentMethod::where('is_active', true)->orderBy('sort')->get();
        $instructionsEn = Setting::get('payment_instructions_en', 'Transfer to our bank or Telebirr account and include your payment code.');
        $instructionsAm = Setting::get('payment_instructions_am', 'በባንክ ወይም በቴሌብር ይክፈሉ እና የክፍያ ኮድዎን ያስገቡ።');

        return Inertia::render('Payments/Show', [
            'payment' => [
                'id' => $payment->id,
                'payment_code' => $payment->payment_code,
                'status' => $payment->status,
                'amount_expected' => (float) $payment->amount_expected,
                'amount_declared' => $payment->amount_declared ? (float) $payment->amount_declared : null,
                'amount_verified' => $payment->amount_verified ? (float) $payment->amount_verified : null,
                'reference_number' => $payment->reference_number,
                'paid_from' => $payment->paid_from,
                'paid_at_declared' => $payment->paid_at_declared?->toIso8601String(),
                'user_note' => $payment->user_note,
                'admin_note' => $payment->admin_note,
                'rejection_reason' => $payment->rejection_reason,
                'has_receipt' => (bool) $payment->receipt_path,
                'receipt_url' => $payment->getReceiptUrl(),
                'tier' => $payment->tier,
                'method' => $payment->method,
                'events' => $payment->events,
                'submitted_at' => $payment->submitted_at?->toIso8601String(),
                'created_at' => $payment->created_at->toIso8601String(),
            ],
            'paymentMethods' => $activeMethods,
            'instructions' => [
                'en' => $instructionsEn,
                'am' => $instructionsAm,
            ],
        ]);
    }

    /**
     * Initiate payment for a tier. Returns JSON with payment code & active methods.
     */
    public function initiate(Request $request): JsonResponse
    {
        $request->validate([
            'tier_id' => 'required|exists:tiers,id',
        ]);

        $tier = Tier::findOrFail($request->tier_id);
        $user = $request->user();

        $payment = $this->paymentProvider->initiate($user, $tier);
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('sort')->get();

        $instructionsEn = Setting::get('payment_instructions_en', 'Transfer to our bank or Telebirr account and write your payment code in the note.');
        $instructionsAm = Setting::get('payment_instructions_am', 'በባንክ ወይም በቴሌብር ይክፈሉ እና በምክንያት ቦታ ላይ የክፍያ ኮድዎን ያስገቡ።');

        return response()->json([
            'payment' => [
                'id' => $payment->id,
                'payment_code' => $payment->payment_code,
                'amount' => (float) $payment->amount_expected,
                'currency' => $payment->currency,
                'tier' => [
                    'id' => $tier->id,
                    'name_en' => $tier->name_en,
                    'name_am' => $tier->name_am,
                    'card_quota' => $tier->card_quota,
                    'validity_days' => $tier->validity_days,
                ],
            ],
            'methods' => $paymentMethods,
            'instructions' => [
                'en' => $instructionsEn,
                'am' => $instructionsAm,
            ],
        ]);
    }

    /**
     * Submit payment proof (receipt image and/or reference number).
     */
    public function submitProof(Request $request, Payment $payment): RedirectResponse|JsonResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 403);

        $request->validate([
            'reference_number' => 'nullable|string|max:100',
            'receipt' => 'nullable|file|mimes:jpeg,png,webp,jpg|max:5120',
            'method_id' => 'nullable|exists:payment_methods,id',
            'amount_declared' => 'nullable|numeric|min:0',
            'paid_at_declared' => 'nullable|date',
            'paid_from' => 'nullable|string|max:150',
            'user_note' => 'nullable|string|max:1000',
        ]);

        $updatedPayment = $this->paymentProvider->submitProof($payment, [
            'reference_number' => $request->reference_number,
            'receipt' => $request->file('receipt'),
            'method_id' => $request->method_id,
            'amount_declared' => $request->amount_declared,
            'paid_at_declared' => $request->paid_at_declared,
            'paid_from' => $request->paid_from,
            'user_note' => $request->user_note,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'payment' => $updatedPayment,
                'message' => 'Payment details submitted for verification.',
            ]);
        }

        return redirect()->route('payments.show', $payment->id)->with(
            'success',
            'Payment submitted for verification. We will verify your payment within 24 hours.'
        );
    }
}
