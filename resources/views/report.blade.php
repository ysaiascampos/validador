<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report['title'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            background: #f3f5f9;
            color: #1f2937;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.45;
        }
        .page { max-width: 1100px; margin: 0 auto; }
        .header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 20px 24px;
            margin-bottom: 16px;
        }
        .header h1 { margin: 0 0 4px; font-size: 22px; }
        .muted { color: #6b7280; }
        .cards {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }
        .card {
            flex: 1 1 150px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 14px 16px;
        }
        .card-label { display: block; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .card strong { display: block; margin-top: 4px; font-size: 22px; }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 14px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 16px;
        }
        .toolbar label { display: inline-flex; align-items: center; gap: 6px; color: #374151; }
        .button {
            display: inline-block;
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #1f2937;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .button:hover { background: #f9fafb; }
        .button-primary { background: #2563eb; border-color: #2563eb; color: #ffffff; }
        .button-primary:hover { background: #1d4ed8; }
        .notice {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 16px;
        }
        .panel {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-left: 5px solid #9ca3af;
            border-radius: 10px;
            padding: 18px 22px;
            margin-bottom: 16px;
        }
        .panel-ok { border-left-color: #16a34a; }
        .panel-fail { border-left-color: #dc2626; }
        .panel-header {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 8px;
        }
        .panel-header h2 { margin: 0; font-size: 17px; }
        .panel h3 {
            margin: 18px 0 8px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #6b7280;
        }
        .messages { margin: 8px 0 0; padding-left: 20px; }
        .messages li { margin-bottom: 4px; }
        .messages-errors li { color: #b91c1c; }
        .messages-warnings li { color: #b45309; }
        .badge {
            display: inline-block;
            border-radius: 999px;
            padding: 3px 10px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.4;
            white-space: nowrap;
        }
        .badge-lg { font-size: 14px; padding: 8px 16px; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-muted { background: #e5e7eb; color: #374151; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #f0f2f5; vertical-align: top; }
        th { color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        tr:last-child td { border-bottom: none; }
        .chips { list-style: none; margin: 6px 0 0; padding: 0; display: flex; flex-wrap: wrap; gap: 6px; }
        .chips li { background: #f3f4f6; border-radius: 6px; padding: 4px 10px; font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 12px; }
        pre {
            margin: 6px 0 0;
            padding: 12px;
            background: #111827;
            color: #e5e7eb;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 12px;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .footer { color: #9ca3af; text-align: center; font-size: 12px; margin-top: 24px; }
        @page { margin: 14mm; }
        @media print {
            body { background: #ffffff; padding: 0; font-size: 11px; }
            .page { max-width: none; }
            .no-print { display: none !important; }
            .header, .card, .panel, .toolbar { border-radius: 0; box-shadow: none; }
            .panel { page-break-inside: avoid; }
            pre { background: #f3f4f6; color: #111827; border: 1px solid #e5e7eb; }
            .badge { border: 1px solid currentColor; }
        }
    </style>
</head>
<body>
<div class="page">

    <header class="header">
        <div>
            <h1>{{ $report['title'] }}</h1>
            <div class="muted">
                Generado: {{ $report['generated_at'] }}
                @if (!empty($report['environment']))
                    &middot; Ambiente: <strong>{{ $report['environment'] }}</strong>
                @endif
            </div>
        </div>
        <span class="badge badge-lg {{ $report['success'] ? 'badge-success' : 'badge-danger' }}">
            {{ $report['status_label'] }}
        </span>
    </header>

    @if (!empty($pdf_message))
        <div class="notice">{{ $pdf_message }}</div>
    @endif

    <section class="cards">
        <div class="card">
            <span class="card-label">Validadores</span>
            <strong>{{ $report['counts']['validators'] }}</strong>
        </div>
        <div class="card">
            <span class="card-label">Errores</span>
            <strong>{{ $report['counts']['errors'] }}</strong>
        </div>
        <div class="card">
            <span class="card-label">Advertencias</span>
            <strong>{{ $report['counts']['warnings'] }}</strong>
        </div>
        <div class="card">
            <span class="card-label">Tiempo total</span>
            <strong>{{ $report['execution_time'] }} ms</strong>
        </div>
    </section>

    <form class="toolbar no-print" method="GET" action="{{ route('validator.report') }}">
        <input type="hidden" name="enable[database]" value="0">
        <label>
            <input type="checkbox" name="enable[database]" value="1" {{ !empty($options['database']) ? 'checked' : '' }}>
            Base de datos
        </label>
        <input type="hidden" name="enable[env]" value="0">
        <label>
            <input type="checkbox" name="enable[env]" value="1" {{ !empty($options['env']) ? 'checked' : '' }}>
            Variables de entorno
        </label>
        <input type="hidden" name="enable[git]" value="0">
        <label>
            <input type="checkbox" name="enable[git]" value="1" {{ !empty($options['git']) ? 'checked' : '' }}>
            Git
        </label>
        <button type="submit" class="button button-primary">Actualizar</button>
        <button type="button" class="button" onclick="window.print()">Imprimir / Guardar PDF</button>
        @if ($pdf_available)
            <a class="button" href="{{ route('validator.report.pdf', $query) }}">Descargar PDF</a>
        @endif
    </form>

    @if (!empty($report['errors']))
        <section class="panel panel-fail">
            <div class="panel-header"><h2>Errores generales</h2></div>
            <ul class="messages messages-errors">
                @foreach ($report['errors'] as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if (!empty($report['warnings']))
        <section class="panel">
            <div class="panel-header"><h2>Advertencias generales</h2></div>
            <ul class="messages messages-warnings">
                @foreach ($report['warnings'] as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if (empty($report['validators']))
        <section class="panel">
            <div class="panel-header"><h2>Sin resultados</h2></div>
            <p class="muted">No se ejecutaron validadores. Verifica la configuración del paquete.</p>
        </section>
    @endif

    @foreach ($report['validators'] as $validator)
        <article class="panel {{ $validator['success'] ? 'panel-ok' : 'panel-fail' }}">
            <div class="panel-header">
                <h2>{{ $validator['label'] }}</h2>
                <span class="badge badge-{{ $validator['tone'] }}">{{ $validator['status_label'] }}</span>
                <span class="muted">{{ $validator['execution_time'] }} ms</span>
            </div>

            @if (!empty($validator['errors']))
                <h3>Errores</h3>
                <ul class="messages messages-errors">
                    @foreach ($validator['errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            @if (!empty($validator['warnings']))
                <h3>Advertencias</h3>
                <ul class="messages messages-warnings">
                    @foreach ($validator['warnings'] as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            @endif

            @foreach ($validator['sections'] as $section)
                <h3>{{ $section['title'] }}</h3>

                @if ($section['type'] === 'table')
                    <table>
                        <thead>
                            <tr>
                                @foreach ($section['columns'] as $column)
                                    <th>{{ $column }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($section['rows'] as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td><span class="badge badge-{{ $row['tone'] }}">{{ $row['value'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @elseif ($section['type'] === 'list')
                    <ul class="chips">
                        @foreach ($section['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @else
                    <pre>{{ $section['text'] }}</pre>
                @endif
            @endforeach
        </article>
    @endforeach

    <div class="footer">Generado por validador/core</div>
</div>
</body>
</html>
