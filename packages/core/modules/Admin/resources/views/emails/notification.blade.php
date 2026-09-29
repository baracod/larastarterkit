<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f7f9;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #f4f7f9;
            padding-bottom: 40px;
        }
        .main {
            background-color: #ffffff;
            margin: 0 auto;
            width: 100%;
            max-width: 600px;
            border-spacing: 0;
            color: #2d3748;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-top: 40px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .content {
            padding: 40px;
        }
        .sender-info {
            display: flex;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid #edf2f7;
        }
        .sender-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            margin-right: 16px;
            object-fit: cover;
            border: 2px solid #edf2f7;
        }
        .sender-details {
            display: inline-block;
            vertical-align: middle;
        }
        .sender-name {
            font-weight: 600;
            font-size: 16px;
            color: #1a202c;
            display: block;
        }
        .sender-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        h2 {
            font-size: 20px;
            font-weight: 700;
            color: #1a202c;
            margin-top: 0;
            margin-bottom: 16px;
        }
        p {
            line-height: 1.6;
            color: #4a5568;
            font-size: 16px;
            margin-bottom: 24px;
        }
        .button-wrapper {
            text-align: center;
            margin: 32px 0;
        }
        .button {
            background-color: #764ba2;
            color: #ffffff !important;
            padding: 14px 32px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            display: inline-block;
            transition: background-color 0.2s;
        }
        .footer {
            text-align: center;
            padding: 24px;
            font-size: 13px;
            color: #a0aec0;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <table class="main">
            <tr>
                <td class="header">
                    <h1>{{ config('app.name') }}</h1>
                </td>
            </tr>
            <tr>
                <td class="content">
                    @if($sender)
                        <div class="sender-info">
                            <img src="{{ $sender['avatar'] }}" alt="{{ $sender['name'] }}" class="sender-avatar">
                            <div class="sender-details">
                                <span class="sender-name">{{ $sender['name'] }}</span>
                                <span class="sender-label">Expéditeur</span>
                            </div>
                        </div>
                    @endif

                    <h2>{{ $title }}</h2>
                    <p>{{ $messageText }}</p>

                    @if($actionUrl)
                        <div class="button-wrapper">
                            <a href="{{ $actionUrl }}" class="button">{{ $actionText }}</a>
                        </div>
                    @endif

                    <p style="font-size: 14px; color: #718096; margin-top: 32px;">
                        Si vous avez des questions, n'hésitez pas à répondre directement à cet e-mail.
                    </p>
                </td>
            </tr>
            <tr>
                <td class="footer">
                    &copy; {{ date('Y') }} {{ config('app.name') }}. Tous droits réservés.
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
