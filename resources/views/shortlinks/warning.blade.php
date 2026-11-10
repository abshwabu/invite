<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warning: External Website - Teri</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .card {
            background-color: #ffffff;
            max-width: 480px;
            width: 100%;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            border: 1px solid #fee2e2;
            padding: 32px;
            text-align: center;
        }
        .icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        h1 {
            font-size: 20px;
            font-weight: 700;
            color: #dc2626;
            margin: 0 0 12px 0;
        }
        p {
            font-size: 14px;
            color: #475569;
            line-height: 1.5;
            margin: 0 0 20px 0;
        }
        .target-box {
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            font-family: monospace;
            font-size: 13px;
            color: #1e293b;
            word-break: break-all;
            margin-bottom: 24px;
        }
        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .btn-proceed {
            background-color: #dc2626;
            color: #ffffff;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
        .btn-cancel {
            background-color: #e2e8f0;
            color: #334155;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">⚠️</div>
        <h1>External Website Warning</h1>
        <p>
            You are leaving Teri to visit an unverified external website. Never share your telebirr PIN, bank passwords, or SMS verification codes on unverified websites.
        </p>

        <div class="target-box">
            {{ $targetUrl }}
        </div>

        <div class="actions">
            <a href="javascript:history.back()" class="btn-cancel">Return to Safety</a>
            <a href="{{ $proceedUrl }}" class="btn-proceed">Proceed to Website</a>
        </div>
    </div>
</body>
</html>
