@php
    // Verifica a existência do logotipo no disco privado antes de embutir.
    $logoData = null;
    if (! empty($institution?->logo_path) && \Illuminate\Support\Facades\Storage::disk('local')->exists($institution->logo_path)) {
        $mime = \Illuminate\Support\Facades\Storage::disk('local')->mimeType($institution->logo_path) ?: 'image/png';
        $logoData = 'data:'.$mime.';base64,'.base64_encode(\Illuminate\Support\Facades\Storage::disk('local')->get($institution->logo_path));
    }

    $addressLine = collect([
        $institution?->address,
        $institution?->city,
        $institution?->state,
        $institution?->zip,
    ])->filter()->implode(', ');
@endphp

<table style="width:100%; border-bottom:2px solid #1e293b; margin-bottom:16px;">
    <tr>
        @if ($logoData)
            <td style="width:80px; vertical-align:middle; padding:6px 10px 6px 0;">
                <img src="{{ $logoData }}" alt="Logotipo" style="max-width:70px; max-height:70px;">
            </td>
        @endif
        <td style="vertical-align:middle; padding:6px 0;">
            <div style="font-size:16px; font-weight:bold; color:#0f172a;">
                {{ $institution?->name ?? config('app.name') }}
            </div>
            @if ($institution?->cnpj)
                <div style="font-size:11px; color:#475569;">CNPJ: {{ $institution->cnpj }}</div>
            @endif
            @if ($addressLine)
                <div style="font-size:11px; color:#475569;">{{ $addressLine }}</div>
            @endif
            <div style="font-size:11px; color:#475569;">
                @if ($institution?->phone)Tel: {{ $institution->phone }}@endif
                @if ($institution?->email){{ $institution->phone ? ' · ' : '' }}E-mail: {{ $institution->email }}@endif
            </div>
        </td>
    </tr>
</table>
