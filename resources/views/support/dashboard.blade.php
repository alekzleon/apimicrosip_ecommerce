<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="30">
    <title>Soporte Sync - API Raul</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f6f8;
            --panel: #ffffff;
            --text: #17202a;
            --muted: #637083;
            --line: #d9e0e7;
            --ok: #137a4b;
            --bad: #b42318;
            --warn: #9a5b13;
            --accent: #175cd3;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 14px;
        }

        header {
            background: #111827;
            color: #fff;
            padding: 18px 24px;
        }

        header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
        }

        header p {
            margin: 4px 0 0;
            color: #cbd5e1;
        }

        main {
            width: min(1180px, calc(100% - 32px));
            margin: 20px auto 40px;
        }

        .grid {
            display: grid;
            gap: 14px;
        }

        .cards {
            grid-template-columns: repeat(5, minmax(0, 1fr));
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 16px;
        }

        .panel h2 {
            margin: 0 0 12px;
            font-size: 15px;
        }

        .metric {
            font-size: 28px;
            font-weight: 750;
            line-height: 1;
        }

        .muted {
            color: var(--muted);
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-weight: 700;
        }

        .dot {
            width: 9px;
            height: 9px;
            border-radius: 99px;
            background: var(--bad);
        }

        .dot.ok { background: var(--ok); }

        .dot.warn { background: var(--bad); }

        .actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 16px 0;
        }

        button, .button {
            border: 0;
            border-radius: 7px;
            background: var(--accent);
            color: #fff;
            cursor: pointer;
            font-weight: 700;
            padding: 10px 14px;
        }

        .button.secondary {
            background: #334155;
            text-decoration: none;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border-bottom: 1px solid var(--line);
            padding: 10px 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
        }

        .number {
            font-variant-numeric: tabular-nums;
            text-align: right;
        }

        .alert {
            border-radius: 8px;
            margin: 16px 0;
            padding: 12px 14px;
        }

        .alert.ok {
            background: #ecfdf3;
            border: 1px solid #abefc6;
            color: #085d3a;
        }

        .alert.bad {
            background: #fef3f2;
            border: 1px solid #fecdca;
            color: #912018;
        }

        .log-warning {
            color: var(--bad);
            font-weight: 800;
            margin: 8px 0 0;
        }

        .badge {
            border-radius: 999px;
            display: inline-flex;
            font-size: 12px;
            font-weight: 800;
            padding: 4px 8px;
            text-decoration: none;
        }

        .badge.ok {
            background: #ecfdf3;
            color: var(--ok);
        }

        .badge.bad {
            background: #fef3f2;
            color: var(--bad);
        }

        details summary {
            cursor: pointer;
            font-weight: 800;
        }

        .error-box {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 6px;
            color: #7c2d12;
            margin-top: 8px;
            max-width: 520px;
            overflow-wrap: anywhere;
            padding: 10px;
        }

        @media (max-width: 900px) {
            .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .actions { align-items: stretch; flex-direction: column; }
            button, .button { width: 100%; text-align: center; }
        }

        @media (max-width: 560px) {
            .cards { grid-template-columns: 1fr; }
            main { width: min(100% - 20px, 1180px); }
            th:nth-child(3), td:nth-child(3) { display: none; }
        }
    </style>
</head>
<body>
    <header>
        <h1>Soporte Sync</h1>
        <p>API Raul · refresco automatico cada 30 segundos</p>
    </header>

    <main>
        <section class="grid cards">
            <div class="panel">
                <h2>Firebird</h2>
                <div class="status">
                    <span class="dot {{ $firebird['ok'] ? 'ok' : '' }}"></span>
                    {{ $firebird['label'] }}
                </div>
                <p class="muted">{{ $firebird['detail'] }}</p>
            </div>

            <div class="panel">
                <h2>Ecommerce</h2>
                <div class="status">
                    <span class="dot {{ $ecommerce['ok'] ? 'ok' : '' }}"></span>
                    {{ $ecommerce['label'] }}
                </div>
                <p class="muted">{{ $ecommerce['detail'] }}</p>
            </div>

            <div class="panel">
                <h2>Pendientes</h2>
                <div class="metric">{{ number_format($queue['pendingTotal']) }}</div>
                <p class="muted">registros por sincronizar</p>
            </div>

            <div class="panel">
                <h2>Sincronizados</h2>
                <div class="metric">{{ number_format($queue['syncedTotal']) }}</div>
                <p class="muted">registros marcados como true</p>
            </div>

            <div class="panel">
                <h2>Laravel log</h2>
                <div class="status">
                    <span class="dot {{ $laravelLog['is_warning'] ? 'warn' : 'ok' }}"></span>
                    {{ $laravelLog['formatted'] }}
                </div>
                @if ($laravelLog['is_warning'])
                    <p class="log-warning">{{ $laravelLog['warning'] }}</p>
                @else
                    <p class="muted">storage/logs/laravel.log</p>
                @endif
                <form method="post" action="{{ route('support.logs.laravel.clear') }}" style="margin-top: 12px;">
                    @csrf
                    <button type="submit">Limpiar log</button>
                </form>
            </div>
        </section>

        @if ($lastRun)
            <div class="alert {{ $lastRun['ok'] ? 'ok' : 'bad' }}">
                <strong>{{ $lastRun['ok'] ? 'Tanda ejecutada' : 'La tanda fallo' }}:</strong>
                {{ $lastRun['message'] }}
                <br>
                Sincronizados: {{ number_format($lastRun['items_synced']) }} ·
                Fallidos: {{ number_format($lastRun['items_failed']) }} ·
                Marcados: {{ number_format($lastRun['rows_marked_as_synced']) }}
            </div>
        @endif

        @if ($lastSalesDocumentRun)
            <div class="alert {{ $lastSalesDocumentRun['ok'] ? 'ok' : 'bad' }}">
                <strong>{{ $lastSalesDocumentRun['ok'] ? 'Importacion de ventas ejecutada' : 'La importacion de ventas fallo' }}:</strong>
                {{ $lastSalesDocumentRun['message'] }}
                <br>
                Recibidos: {{ number_format($lastSalesDocumentRun['received']) }} ·
                Insertados: {{ number_format($lastSalesDocumentRun['synced']) }} ·
                Fallidos: {{ number_format($lastSalesDocumentRun['failed']) }}
            </div>
        @endif

        @if (session('support_log_result'))
            <div class="alert {{ session('support_log_result.ok') ? 'ok' : 'bad' }}">
                <strong>{{ session('support_log_result.ok') ? 'Log actualizado' : 'No se pudo limpiar el log' }}:</strong>
                {{ session('support_log_result.message') }}
            </div>
        @endif

        <div class="actions">
            <form method="post" action="{{ route('support.sync.run') }}">
                @csrf
                <button type="submit">Ejecutar tanda ahora</button>
            </form>

            <a class="button secondary" href="{{ route('support.dashboard') }}">Actualizar</a>
        </div>

        <section class="panel" style="margin-bottom: 16px;">
            <h2>Ventas ecommerce a Microsip</h2>

            <div class="grid cards" style="margin-bottom: 14px;">
                <div>
                    <div class="metric">{{ number_format($salesDocuments['synced']) }}</div>
                    <p class="muted">ventas insertadas</p>
                </div>
                <div>
                    <div class="metric">{{ number_format($salesDocuments['failed']) }}</div>
                    <p class="muted">fallidos sin resolver</p>
                </div>
                <div>
                    <div class="metric">{{ number_format($salesDocuments['skipped']) }}</div>
                    <p class="muted">omitidos por duplicado local</p>
                </div>
                <div>
                    <form method="post" action="{{ route('support.sales-documents.sync.run') }}">
                        @csrf
                        <button type="submit">Importar ventas pendientes</button>
                    </form>
                </div>
            </div>

            <h2>Ultimas ventas procesadas</h2>

            @if ($recentSalesDocumentItems === [])
                <p class="muted">Todavia no hay ventas importadas desde ecommerce.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Venta</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th class="number">Detalles</th>
                            <th>Estado</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentSalesDocumentItems as $item)
                            <tr>
                                <td>
                                    <strong>{{ $item['folio'] ?? 'Sin folio' }}</strong>
                                    <div class="muted">Orden #{{ $item['order_id'] ?? '-' }} · Tanda #{{ $item['run_id'] }}</div>
                                </td>
                                <td>{{ $item['fecha'] }} {{ $item['hora'] }}</td>
                                <td>
                                    {{ $item['cliente_id'] ?? '-' }}
                                    <div class="muted">{{ $item['clave_cliente'] }}</div>
                                </td>
                                <td class="number">{{ number_format($item['details_count']) }}</td>
                                <td>
                                    <span class="badge {{ $item['status'] === 'failed' ? 'bad' : 'ok' }}">
                                        {{ $item['status'] }}
                                    </span>
                                </td>
                                <td>
                                    @if ($item['status'] === 'failed')
                                        <details>
                                            <summary>{{ $item['error_stage'] ?: 'ver error' }}</summary>
                                            <div class="error-box">{{ $item['error'] }}</div>
                                        </details>
                                        @if (! $item['resolved_at'])
                                            <form method="post" action="{{ route('support.sales-documents.failures.resolve', $item['id']) }}" style="margin-top: 8px;">
                                                @csrf
                                                <button type="submit">Resolver</button>
                                            </form>
                                        @endif
                                    @else
                                        <span class="muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if ($recentSalesDocumentRuns !== [])
                <h2 style="margin-top: 18px;">Historial de importacion de ventas</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Tanda</th>
                            <th>Estado</th>
                            <th class="number">Recibidos</th>
                            <th class="number">Insertados</th>
                            <th class="number">Fallidos</th>
                            <th class="number">Duracion</th>
                            <th>Inicio</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentSalesDocumentRuns as $run)
                            <tr>
                                <td>#{{ $run['id'] }}</td>
                                <td>{{ $run['status'] }}</td>
                                <td class="number">{{ number_format($run['received']) }}</td>
                                <td class="number">{{ number_format($run['synced']) }}</td>
                                <td class="number">{{ number_format($run['failed']) }}</td>
                                <td class="number">{{ $run['duration_ms'] ? number_format($run['duration_ms'] / 1000, 1).'s' : '-' }}</td>
                                <td>{{ $run['started_at'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="panel">
            <h2>Cola por tipo</h2>

            @if (! $queue['ok'])
                <p class="muted">{{ $queue['error'] }}</p>
            @elseif ($queue['rows'] === [])
                <p class="muted">No hay registros en ECOMMER_SYNC.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th class="number">Pendientes</th>
                            <th class="number">Sincronizados</th>
                            <th class="number">Otros</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($queue['rows'] as $row)
                            <tr>
                                <td>{{ $row['type'] }}</td>
                                <td class="number">{{ number_format($row['pending']) }}</td>
                                <td class="number">{{ number_format($row['synced']) }}</td>
                                <td class="number">{{ number_format($row['other']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="panel" style="margin-top: 16px;">
            <h2>Historial de tandas</h2>

            @if ($recentRuns === [])
                <p class="muted">Todavia no hay auditoria registrada. Ejecuta una tanda despues de migrar la base.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Origen</th>
                            <th>Estado</th>
                            <th class="number">Tomados</th>
                            <th class="number">OK</th>
                            <th>Fallidos</th>
                            <th class="number">Duracion</th>
                            <th>Inicio</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentRuns as $run)
                            <tr>
                                <td>#{{ $run['id'] }}</td>
                                <td>{{ $run['source'] }}</td>
                                <td>{{ $run['status'] }}</td>
                                <td class="number">{{ number_format($run['pending_selected']) }}</td>
                                <td class="number">{{ number_format($run['items_synced']) }}</td>
                                <td>
                                    @if ($run['items_failed'] > 0)
                                        <a class="badge bad" href="{{ route('support.dashboard', ['failure_run' => $run['id']]) }}#fallidos">
                                            {{ number_format($run['items_failed']) }} fallidos
                                        </a>
                                    @else
                                        <span class="badge ok">0</span>
                                    @endif
                                </td>
                                <td class="number">{{ $run['duration_ms'] ? number_format($run['duration_ms'] / 1000, 1).'s' : '-' }}</td>
                                <td>{{ $run['started_at'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="panel" id="fallidos" style="margin-top: 16px;">
            <h2>
                Ultimos fallidos auditados
                @if ($failureRunId)
                    <span class="muted">· tanda #{{ $failureRunId }}</span>
                    <a class="badge ok" href="{{ route('support.dashboard') }}#fallidos">Ver todos</a>
                @endif
            </h2>

            @if ($recentFailures === [])
                <p class="muted">No hay fallidos auditados.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Tanda</th>
                            <th>Tipo</th>
                            <th>Entidad</th>
                            <th>Endpoint</th>
                            <th>Error</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentFailures as $failure)
                            <tr>
                                <td>#{{ $failure['run_id'] }}</td>
                                <td>{{ $failure['tipo'] }}</td>
                                <td>{{ $failure['entity_id'] }}</td>
                                <td>{{ $failure['endpoint'] }}</td>
                                <td>{{ $failure['error'] }}</td>
                                <td>
                                    <form method="post" action="{{ route('support.failures.resolve', $failure['id']) }}">
                                        @csrf
                                        <button type="submit">Resolver</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

    </main>
</body>
</html>
