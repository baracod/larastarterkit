<!DOCTYPE html>
<html lang="{{ $emailLocale }}">
<head><meta charset="utf-8"><title>{{ $subject }}</title></head>
<body style="margin:0;background:#f4f6f8;color:#253247;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;">
<tr><td align="center" style="padding:24px;border-bottom:3px solid #bd942e;">
<img src="{{ isset($message) ? $message->embed(public_path(ltrim(config('starter.logo'), '/'))) : $logoUrl }}" alt="{{ config('app.name') }}" width="140" style="max-width:140px;height:auto;">
</td></tr>
<tr><td style="padding:28px;line-height:1.6;">
<h1 style="font-size:22px;">{{ $greeting }}</h1>
@foreach ($introLines as $line)<p>{{ $line }}</p>@endforeach
@if ($actionText)<p style="margin:28px 0;"><a href="{{ $actionUrl }}" style="display:inline-block;background-color:#004aad;color:#ffffff !important;padding:12px 20px;text-decoration:none;border-radius:8px;font-weight:700;"><span style="color:#ffffff !important;text-decoration:none;">{{ $actionText }}</span></a></p>@endif
@foreach ($outroLines as $line)<p>{{ $line }}</p>@endforeach
@if ($actionText)<p style="font-size:12px;word-break:break-all;"><a href="{{ $actionUrl }}">{{ $actionUrl }}</a></p>@endif
</td></tr>
<tr><td align="center" style="padding:20px;border-top:1px solid #eeeeee;color:#667085;">{{ config('app.name') }} · {{ date('Y') }}</td></tr>
</table></td></tr></table>
</body></html>
