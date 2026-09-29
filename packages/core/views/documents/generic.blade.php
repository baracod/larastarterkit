<!DOCTYPE html>
<html><head><meta charset="utf-8"><style>body { font-family: DejaVu Sans, sans-serif; font-size: 12px; } h1 { font-size: 22px; } dt { font-weight: bold; } img { max-width: 160px; }</style></head><body>
<h1>{{ $snapshot['definition']['title'] }}</h1>
<p>{{ $snapshot['issuer'] }} — {{ $snapshot['reference'] }} — {{ $snapshot['issued_at'] }}</p>
@foreach($snapshot['sections'] as $section)
<h2>{{ $section['title'] }}</h2><dl>@foreach($section['rows'] as $row)<dt>{{ $row['key'] }}</dt><dd>{{ $row['value'] }}</dd>@endforeach</dl>
@endforeach
<dl>@foreach($snapshot['fields'] as $key => $value)<dt>{{ $key }}</dt><dd>{{ $value }}</dd>@endforeach</dl>
@foreach($snapshot['signatures'] as $signature)<p>{{ $signature['role'] }}</p><img src="{{ $signature['image'] }}">@endforeach
</body></html>
