<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Soporte Pide Facil Raul</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f5f7fb;
            --ink: #162033;
            --muted: #5f6f86;
            --line: #d9e2ee;
            --accent: #175cd3;
            --accent-dark: #123f91;
            --panel: #ffffff;
        }

        * { box-sizing: border-box; }

        body {
            align-items: center;
            background:
                linear-gradient(135deg, rgba(23, 92, 211, 0.11), transparent 42%),
                linear-gradient(315deg, rgba(19, 122, 75, 0.10), transparent 38%),
                var(--bg);
            color: var(--ink);
            display: flex;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            justify-content: center;
            margin: 0;
            min-height: 100vh;
            padding: 24px;
        }

        main {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 24px 70px rgba(17, 24, 39, 0.12);
            max-width: 620px;
            padding: 42px;
            width: 100%;
        }

        .tag {
            color: var(--accent);
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        h1 {
            font-size: clamp(34px, 8vw, 58px);
            letter-spacing: 0;
            line-height: .98;
            margin: 14px 0 16px;
        }

        p {
            color: var(--muted);
            font-size: 18px;
            line-height: 1.55;
            margin: 0 0 28px;
        }

        a {
            align-items: center;
            background: var(--accent);
            border-radius: 7px;
            color: #fff;
            display: inline-flex;
            font-weight: 800;
            min-height: 44px;
            padding: 0 18px;
            text-decoration: none;
        }

        a:hover {
            background: var(--accent-dark);
        }

        .status {
            border-top: 1px solid var(--line);
            color: var(--muted);
            display: flex;
            gap: 10px;
            margin-top: 34px;
            padding-top: 18px;
        }

        .dot {
            background: #137a4b;
            border-radius: 99px;
            flex: 0 0 auto;
            height: 10px;
            margin-top: 5px;
            width: 10px;
        }

        @media (max-width: 560px) {
            main { padding: 28px; }
            p { font-size: 16px; }
            a { justify-content: center; width: 100%; }
        }
    </style>
</head>
<body>
    <main>
        <div class="tag">Sincronizacion local</div>
        <h1>Soporte Pide Facil Raul</h1>
        <p>Panel interno para revisar cola, ejecutar tandas y atender fallidos de sincronizacion con el ecommerce.</p>

        <a href="{{ route('support.dashboard') }}">Ir al soporte</a>

        <div class="status">
            <span class="dot"></span>
            <span>API Raul lista para operar con Firebird y el ecommerce configurado.</span>
        </div>
    </main>
</body>
</html>
