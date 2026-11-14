<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Comprovante {{ $protocol->formatted_number }}</title>
    <style>
        * { font-family: DejaVu Sans, Arial, sans-serif; }
        body { font-size: 12px; color: #1e293b; margin: 24px; }
        h1 { font-size: 16px; text-align: center; margin: 8px 0 16px; text-transform: uppercase; letter-spacing: 1px; }
        h2 { font-size: 12px; margin: 16px 0 6px; color: #334155; text-transform: uppercase; letter-spacing: 0.5px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data td { border: 1px solid #cbd5e1; padding: 6px 8px; vertical-align: top; }
        table.data td.label { background: #f1f5f9; width: 30%; font-weight: bold; color: #475569; }
        .rodape { margin-top: 28px; font-size: 10px; color: #64748b; text-align: center; }
        .assinatura { margin-top: 40px; text-align: center; }
        .assinatura .linha { border-top: 1px solid #94a3b8; width: 60%; margin: 0 auto 4px; }
    </style>
</head>
<body>
    @include('protocols.pdf._header', ['institution' => $institution])

    <h1>Comprovante de Protocolo</h1>

    <h2>Dados do protocolo</h2>
    <table class="data">
        <tr>
            <td class="label">Número</td>
            <td>{{ $protocol->formatted_number }}</td>
        </tr>
        <tr>
            <td class="label">Tipo de documento</td>
            <td>{{ $protocol->document_type->label() }}</td>
        </tr>
        <tr>
            <td class="label">Assunto</td>
            <td>{{ $protocol->subject }}</td>
        </tr>
        @if ($protocol->description)
            <tr>
                <td class="label">Descrição</td>
                <td>{{ $protocol->description }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Prioridade</td>
            <td>{{ $protocol->priority->label() }}</td>
        </tr>
        <tr>
            <td class="label">Situação</td>
            <td>{{ $protocol->status->label() }}</td>
        </tr>
        <tr>
            <td class="label">Setor de destino</td>
            <td>{{ $protocol->destinationSector?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Data do protocolo</td>
            <td>{{ $protocol->protocoled_at?->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
    </table>

    <h2>Interessado</h2>
    <table class="data">
        <tr>
            <td class="label">Nome</td>
            <td>{{ $protocol->sender_name }}</td>
        </tr>
        <tr>
            <td class="label">CPF/CNPJ</td>
            <td>{{ $protocol->sender_document ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">E-mail</td>
            <td>{{ $protocol->sender_email ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Telefone</td>
            <td>{{ $protocol->sender_phone ?? '—' }}</td>
        </tr>
    </table>

    <div class="assinatura">
        <div class="linha"></div>
        <div>{{ $protocol->creator?->name ?? 'Servidor responsável' }}</div>
        <div style="font-size:10px; color:#64748b;">Servidor responsável pelo registro</div>
    </div>

    <div class="rodape">
        Documento gerado eletronicamente em {{ now()->format('d/m/Y H:i') }}.
    </div>
</body>
</html>
