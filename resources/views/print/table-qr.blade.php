<x-layouts.print :title="'QR — '.$table->title" :width="100">
    <div class="c" style="font-family: sans-serif; padding: 8mm 0">
        <div style="font-size:20px;font-weight:800">{{ $tenant->name }}</div>
        <div style="margin:4px 0 10px">{{ $caption }}</div>
        <img src="{{ (new \chillerlan\QRCode\QRCode)->render($url) }}" alt="QR" style="width:70mm">
        <div style="font-size:26px;font-weight:900;margin-top:8px">{{ $table->title }}</div>
        @if ($table->floor)<div>{{ $table->floor }}</div>@endif
        <div class="muted" style="margin-top:8px;word-break:break-all">{{ $url }}</div>
    </div>
</x-layouts.print>
