@props(['title' => 'Çap', 'width' => 80, 'autoprint' => true, 'preview' => false])
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        @page { size: {{ $width }}mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 12px/1.35 ui-monospace, 'Courier New', monospace; color: #000; background: #eee; }
        .paper { width: {{ $width }}mm; margin: 12px auto; padding: 4mm; background: #fff; }
        .c { text-align: center; } .r { text-align: right; } .b { font-weight: 700; }
        .big { font-size: 28px; font-weight: 800; } .lg { font-size: 15px; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; } td { vertical-align: top; padding: 1px 0; }
        .muted { color: #444; font-size: 11px; }
        .actions { text-align: center; margin: 12px; font-family: sans-serif; }
        .actions button { padding: 8px 16px; border-radius: 8px; border: 1px solid #ccc; background: #4f9a3f; color: #fff; font-weight: 700; cursor: pointer; }
        @media print { body { background: #fff; } .paper { margin: 0; } .actions { display: none; } }
    </style>
</head>
<body>
@unless ($preview)<div class="actions"><button onclick="window.print()">Çap et</button> <button onclick="window.close()" style="background:#777">Bağla</button></div>@endunless
<div class="paper">{{ $slot }}</div>
{{-- Yüklənən kimi çap (loqo/QR şəkilləri load-dan sonra hazırdır); iframe-də açılanda ayrıca pəncərə olmur --}}
@if ($autoprint && ! request()->boolean('noprint'))<script>window.addEventListener('load', () => setTimeout(() => window.print(), 50));</script>@endif
</body>
</html>
