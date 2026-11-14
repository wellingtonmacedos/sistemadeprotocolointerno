<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $proposition->official_number ?? $proposition->title }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; margin: 0; }
        .header { border-bottom: 2px solid #1f2937; padding-bottom: 10px; margin-bottom: 18px; text-align: center; }
        .header .institution { font-size: 15px; font-weight: bold; text-transform: uppercase; }
        .header .meta { font-size: 10px; color: #4b5563; margin-top: 4px; }
        .official { text-align: right; font-size: 12px; font-weight: bold; margin-bottom: 12px; }
        h1 { font-size: 14px; text-align: center; margin: 14px 0; text-transform: uppercase; }
        .section { margin-bottom: 12px; }
        .section .label { font-size: 10px; font-weight: bold; text-transform: uppercase; color: #6b7280; letter-spacing: .5px; }
        .section .value { margin-top: 2px; text-align: justify; white-space: pre-line; line-height: 1.5; }
        .footer { margin-top: 28px; padding-top: 8px; border-top: 1px solid #d1d5db; font-size: 9px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="institution">{{ $institution?->name ?? config('app.name') }}</div>
        <div class="meta">
            @if ($institution?->cnpj) CNPJ: {{ $institution->cnpj }} @endif
            @php
                $address = collect([
                    $institution?->address,
                    $institution?->city,
                    $institution?->state,
                    $institution?->zip,
                ])->filter()->implode(', ');
            @endphp
            @if ($address) · {{ $address }} @endif
            @if ($institution?->phone) · Tel: {{ $institution->phone }} @endif
            @if ($institution?->email) · {{ $institution->email }} @endif
        </div>
    </div>

    @if ($proposition->official_number)
        <div class="official">{{ $proposition->official_number }}</div>
    @endif

    <h1>{{ $proposition->title }}</h1>

    <div class="section">
        <div class="label">Modalidade</div>
        <div class="value">{{ $proposition->modality->label() }}</div>
    </div>

    <div class="section">
        <div class="label">Origem</div>
        <div class="value">{{ $proposition->origin->label() }}</div>
    </div>

    <div class="section">
        <div class="label">Autor</div>
        <div class="value">{{ $proposition->authorName() }}</div>
    </div>

    @php
        $coauthors = $proposition->authors->where('pivot.is_primary', false)->pluck('name');
    @endphp
    @if ($coauthors->isNotEmpty())
        <div class="section">
            <div class="label">Coautores</div>
            <div class="value">{{ $coauthors->join(', ') }}</div>
        </div>
    @endif

    <div class="section">
        <div class="label">Ementa</div>
        <div class="value">{{ $proposition->ementa }}</div>
    </div>

    @if ($proposition->text)
        <div class="section">
            <div class="label">Texto</div>
            <div class="value">{{ $proposition->text }}</div>
        </div>
    @endif

    @if ($proposition->justification)
        <div class="section">
            <div class="label">Justificativa</div>
            <div class="value">{{ $proposition->justification }}</div>
        </div>
    @endif

    <div class="footer">
        Documento emitido em {{ now()->format('d/m/Y H:i') }} — {{ config('app.name') }}
    </div>
</body>
</html>
