<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <link rel="icon" type="image/svg+xml" href="{{ asset(config('starter.logo')) }}" />
    <link rel="apple-touch-icon" href="{{ asset(config('starter.logo')) }}" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ config('app.name') }}</title>
    <meta name="description" content="{{ config('app.name') }}" />
    <script id="starter-config" type="application/json" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">{!! json_encode(['name' => config('app.name'), 'logo' => config('starter.logo'), 'documentationUrl' => config('starter.documentation_url')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script src="{{ asset('initial-theme.js') }}" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}"></script>
    <link rel="stylesheet" type="text/css" href="{{ asset('loader.css') }}" />
    @vite(['resources/ts/main.ts'])
    <script id="starter-broadcast-config" type="application/json" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">{!! json_encode([
        'driver' => config('broadcasting.default'),
        'key' => config('broadcasting.connections.'.config('broadcasting.default').'.key'),
        'host' => config('broadcasting.connections.reverb.options.host'),
        'port' => config('broadcasting.connections.reverb.options.port'),
        'scheme' => config('broadcasting.connections.reverb.options.scheme'),
        'cluster' => config('broadcasting.connections.pusher.options.cluster'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</head>

<body>
    <div id="app">
        <div id="loading-bg" role="status" aria-label="{{ __('Chargement') }}">
            <img class="starter-loading-logo" src="{{ asset(config('starter.logo')) }}"
                alt="{{ config('app.name') }}" width="286" height="110" fetchpriority="high" />
        </div>
    </div>


</body>

</html>
