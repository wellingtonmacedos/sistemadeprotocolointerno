<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Espelho de Tramitação {{ $protocol->formatted_number }}</title>
    <style>
        * { font-family: DejaVu Sans, Arial, sans-serif; }
        body { font-size: 11px; color: #1e293b; margin: 24px; }
        h1 { font-size: 15px; text-align: center; margin: 8px 0 16px; text-transform: uppercase; letter-spacing: 1px; }
        h2 { font-size: 11px; margin: 14px 0 6px; color: #334155; text-transform: uppercase; letter-spacing: 0.5px; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.data td { border: 1px solid #cbd5e1; padding: 5px 7px; vertical-align: top; }
        table.data td.label { background: #f1f5f9; width: 25%; font-weight: bold; color: #475569; }
        table.movimentos { width: 100%; border-collapse: collapse; }
        table.movimentos th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 5px 7px; font-size: 10px; text-align: left; color: #475569; text-transform: uppercase; }
        table.movimentos td { border: 1px solid #cbd5e1; padding: 5px 7px; vertical-align: top; }
        .rodape { margin-top: 20px; font-size: 10px; color: #64748b; text-align: center; }
    </style>
</head>
<body>
    @include('protocols.pdf._header', ['institution' => $institution])

    <h1>Espelho de Tramitação</h1>

    <h2>Identificação</h2>
    <table class="data">
        <tr>
            <td class="label">Número</td>
            <td>{{ $protocol->formatted_number }}</td>
            <td class="label">Tipo</td>
            <td>{{ $protocol->document_type->label() }}</td>
        </tr>
        <tr>
            <td class="label">Assunto</td>
            <td colspan="3">{{ $protocol->subject }}</td>
        </tr>
        <tr>
            <td class="label">Interessado</td>
            <td>{{ $protocol->sender_name }}</td>
            <td class="label">Prioridade</td>
            <td>{{ $protocol->priority->label() }}</td>
        </tr>
        <tr>
            <td class="label">Situação</td>
            <td>{{ $protocol->status->label() }}</td>
            <td class="label">Setor de destino</td>
            <td>{{ $protocol->destinationSector?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Protocolado em</td>
            <td>{{ $protocol->protocoled_at?->format('d/m/Y H:i') ?? '—' }}</td>
            <td class="label">Setor atual</td>
            <td>{{ $protocol->currentSector?->name ?? '—' }}</td>
        </tr>
    </table>

    <h2>Tramitação</h2>
    <table class="movimentos">
        <thead>
            <tr>
                <th style="width:90px;">Data/hora</th>
                <th style="width:120px;">Tipo</th>
                <th>Origem</th>
                <th>Destino</th>
                <th>Usuário</th>
                <th style="width:100px;">Situação</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($movements as $movement)
                <tr>
                    <td>{{ $movement->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>
                        {{ $movement->type->label() }}
                        @if ($movement->description)
                            <br><span style="font-size:10px; color:#64748b;">{{ $movement->description }}</span>
                        @endif
                    </td>
                    <td>{{ $movement->fromSector?->name ?? 'Origem externa' }}</td>
                    <td>{{ $movement->toSector?->name ?? '—' }}</td>
                    <td>{{ $movement->fromUser?->name ?? 'Sistema' }}</td>
                    <td>{{ $movement->status->label() }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center; color:#94a3b8;">Nenhuma tramitação registrada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="rodape">
        Espelho gerado eletronicamente em {{ now()->format('d/m/Y H:i') }}.
    </div>
</body>
</html>
