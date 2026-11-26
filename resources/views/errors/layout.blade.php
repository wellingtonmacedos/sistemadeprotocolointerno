<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Erro') · {{ config('app.name') }}</title>
    <style>
        :root {
            --canvas: #f6f7fb;
            --surface: #ffffff;
            --line: #e6e8ef;
            --body: #1f2430;
            --muted: #6b7280;
            --brand: #4f46e5;
            --brand-dark: #4338ca;
            --accent: #d4a12a;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --canvas: #0f1117;
                --surface: #171a22;
                --line: #262b36;
                --body: #e7e9ee;
                --muted: #9aa1ae;
                --brand: #818cf8;
                --brand-dark: #6366f1;
                --accent: #e2b84a;
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2rem;
            padding: 2rem 1.25rem;
            background: radial-gradient(circle at 20% 0%, rgba(79, 70, 229, .10), transparent 45%), var(--canvas);
            color: var(--body);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            text-align: center;
        }

        .card {
            width: 100%;
            max-width: 32rem;
            padding: 2.5rem 2rem;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 1rem;
            box-shadow: 0 18px 45px -25px rgba(17, 24, 39, .35);
        }

        .code {
            display: inline-block;
            padding: .35rem .9rem;
            border-radius: 999px;
            background: rgba(79, 70, 229, .10);
            color: var(--brand);
            font-size: .8rem;
            font-weight: 700;
            letter-spacing: .12em;
        }

        h1 {
            margin: 1.1rem 0 .5rem;
            font-size: 1.6rem;
            font-weight: 650;
            line-height: 1.25;
        }

        p {
            margin: 0 auto;
            max-width: 26rem;
            color: var(--muted);
            font-size: .95rem;
            line-height: 1.6;
        }

        .actions {
            margin-top: 1.75rem;
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            justify-content: center;
        }

        .btn {
            display: inline-block;
            padding: .65rem 1.25rem;
            border-radius: .6rem;
            font-size: .875rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid transparent;
            transition: opacity .15s ease, background-color .15s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            color: #fff;
        }

        .btn-secondary {
            background: transparent;
            border-color: var(--line);
            color: var(--body);
        }

        .btn:hover { opacity: .9; }

        footer {
            font-size: .8rem;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <main class="card">
        <span class="code">@yield('code')</span>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>

        <div class="actions">
            <a href="{{ url('/') }}" class="btn btn-primary">Ir para o início</a>
            <a href="javascript:history.back()" class="btn btn-secondary">Voltar</a>
        </div>
    </main>

    <footer>Desenvolvido por W-Tech Solutions</footer>
</body>
</html>
