<!DOCTYPE html>
<html lang="{{ $locale }}">

<head>
    <meta charset="UTF-8" />
    <title>{{ __('auth::mail.reset.subject', ['app' => $appName]) }}</title>
    <style>
        body {
            background: #f4f6f8;
            font-family: Helvetica, Arial, sans-serif;
            color: #333;
            margin: 0;
            padding: 0
        }

        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0, 0, 0, .08)
        }

        .header {
            background: #004aad;
            color: #fff;
            text-align: center;
            padding: 24px
        }

        .header img {
            max-width: 100px;
            margin-bottom: 10px
        }

        .content {
            padding: 30px;
            line-height: 1.6
        }

        .btn {
            display: inline-block;
            background: #004aad;
            color: #fff;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 700;
            margin: 20px 0
        }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #777;
            padding: 20px;
            border-top: 1px solid #eee
        }

        @media (prefers-color-scheme: dark) {
            body {
                background: #0f1115;
                color: #eaeaea
            }

            .container {
                background: #161a22
            }

            .header {
                background: #0b5bd3
            }

            .content {
                color: #eaeaea
            }

            .footer {
                color: #a5a5a5;
                border-top-color: #222
            }

            .btn {
                background: #0b5bd3
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <img src="{{ $logoUrl }}" alt="{{ $appName }} Logo">
            <h2>{{ $appName }}</h2>
        </div>

        <div class="content">
            <p>{{ __('auth::mail.reset.hello', ['name' => $user->name ?? __('Utilisateur')]) }}</p>

            <p>{{ __('auth::mail.reset.line1') }}</p>

            <p style="text-align:center;">
                <a href="{{ $url }}" class="btn">{{ __('auth::mail.reset.cta') }}</a>
            </p>

            <p>{{ __('auth::mail.reset.expire', ['minutes' => $minutes]) }}</p>
            <p>{{ __('auth::mail.reset.ignore') }}</p>
        </div>

        <div class="footer">
            <p>{{ __('auth::mail.reset.footer', ['year' => date('Y'), 'app' => $appName]) }}</p>
        </div>
    </div>
</body>

</html>
