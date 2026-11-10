<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class PaymentInvoiceController extends Controller
{
    /**
     * Render a clean, printable HTML receipt/invoice for approved payments.
     */
    public function show(Request $request, Payment $payment): Response
    {
        $isOwner = Auth::check() && Auth::id() === $payment->user_id;
        $isSuperAdmin = Auth::guard('superadmin')->check();

        if (! $isOwner && ! $isSuperAdmin) {
            abort(403, 'Unauthorized.');
        }

        abort_unless($payment->isApproved(), 404, 'Invoice only available for approved payments.');

        $payment->load(['tier', 'user', 'method']);

        $appName = config('app.name', 'Invite');
        $amount = number_format((float) ($payment->amount_verified ?: $payment->amount_expected), 2);
        $date = $payment->reviewed_at ? $payment->reviewed_at->format('M d, Y H:i') : $payment->created_at->format('M d, Y H:i');
        $code = htmlspecialchars($payment->payment_code);
        $tierName = htmlspecialchars($payment->tier->name_en);
        $quota = number_format($payment->tier->card_quota);
        $customerName = htmlspecialchars($payment->user->name);
        $customerEmail = htmlspecialchars($payment->user->email);
        $customerPhone = htmlspecialchars($payment->user->phone ?? 'N/A');
        $methodLabel = htmlspecialchars($payment->method ? $payment->method->label : 'Bank / Telebirr');
        $reference = htmlspecialchars($payment->reference_number ?: 'Verified by Admin');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt - {$code}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #1C2140; margin: 40px; background: #fff; }
        .invoice-box { max-width: 700px; margin: auto; padding: 30px; border: 1px solid #E4E3EE; border-radius: 8px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #F4B400; padding-bottom: 20px; }
        .title { font-size: 28px; font-weight: bold; color: #1C2140; margin: 0; }
        .badge { display: inline-block; padding: 4px 12px; background: #1E5B47; color: #fff; border-radius: 9999px; font-size: 12px; font-weight: 600; }
        .details { margin: 30px 0; display: flex; justify-content: space-between; }
        .section-title { font-size: 11px; text-transform: uppercase; color: #525875; letter-spacing: 1px; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { text-align: left; padding: 10px; background: #F6F5FA; border-bottom: 1px solid #E4E3EE; font-size: 12px; }
        td { padding: 12px 10px; border-bottom: 1px solid #E4E3EE; font-size: 14px; }
        .total { text-align: right; font-size: 20px; font-weight: bold; color: #1C2140; padding-top: 20px; }
        .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #525875; border-top: 1px solid #E4E3EE; padding-top: 20px; }
        @media print {
            body { margin: 0; }
            .invoice-box { border: none; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="invoice-box">
        <div class="header">
            <div>
                <h1 class="title">{$appName}</h1>
                <p style="margin: 4px 0 0 0; color: #525875; font-size: 14px;">Official Payment Receipt</p>
            </div>
            <div style="text-align: right;">
                <span class="badge">PAID & VERIFIED</span>
                <p style="margin: 8px 0 0 0; font-size: 13px; font-weight: 600;">Code: {$code}</p>
            </div>
        </div>

        <div class="details">
            <div>
                <div class="section-title">Billed To</div>
                <div style="font-weight: 600;">{$customerName}</div>
                <div style="font-size: 13px; color: #525875;">{$customerEmail}</div>
                <div style="font-size: 13px; color: #525875;">Phone: {$customerPhone}</div>
            </div>
            <div style="text-align: right;">
                <div class="section-title">Payment Info</div>
                <div style="font-size: 13px;">Date: <strong>{$date}</strong></div>
                <div style="font-size: 13px;">Method: <strong>{$methodLabel}</strong></div>
                <div style="font-size: 13px;">Ref: <strong>{$reference}</strong></div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Item / Description</th>
                    <th>Quota</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>{$tierName} Package</strong><br>
                        <span style="font-size: 12px; color: #525875;">Event invitations, SMS/Email delivery & QR scanner</span>
                    </td>
                    <td>{$quota} Cards</td>
                    <td style="text-align: right; font-weight: 600;">{$amount} ETB</td>
                </tr>
            </tbody>
        </table>

        <div class="total">
            Total Paid: {$amount} ETB
        </div>

        <div class="footer">
            <p>{$appName} Technologies • Addis Ababa, Ethiopia • support@invite.et</p>
            <button class="no-print" onclick="window.print()" style="margin-top: 10px; padding: 8px 18px; background: #1C2140; color: #fff; border: none; border-radius: 4px; cursor: pointer;">
                Print Receipt
            </button>
        </div>
    </div>
</body>
</html>
HTML;

        return response($html, 200, ['Content-Type' => 'text/html']);
    }
}
