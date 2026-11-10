<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: sans-serif; color: #1C2140; line-height: 1.6; padding: 20px;">
    <h2>Payments Waiting for Verification (> 12h)</h2>
    <p>The following {{ $payments->count() }} payments have been waiting for verification for more than 12 hours:</p>
    <ul>
        @foreach($payments as $p)
            <li>
                <strong>Code:</strong> {{ $p->payment_code }} |
                <strong>Tier:</strong> {{ $p->tier?->name_en }} ({{ number_format((float) $p->amount_expected) }} ETB) |
                <strong>User:</strong> {{ $p->user?->name }} ({{ $p->user?->email }}) |
                <strong>Waiting:</strong> {{ $p->submitted_at ? $p->submitted_at->diffInHours() : 0 }}h
            </li>
        @endforeach
    </ul>
    <p>Please log in to the Super Admin panel to review and verify them.</p>
</body>
</html>
