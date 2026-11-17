@php
    // Mismo acento que la plataforma: el color de tema de la empresa (o el negro por defecto).
    $acento = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($empresa?->color_tema)) ? $empresa->color_tema : '#171717';
    [$r, $g, $b] = sscanf($acento, '#%02x%02x%02x');
    $sobreAcento = (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#171717' : '#ffffff';
    $secciones = [
        'VENCIDO' => ['Vencidos', '#b91c1c'],
        'PROGRAMADO' => ['Programados', '#2563eb'],
    ];
    $fecha = fn (string $valor) => \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y');
    $etiquetaGrupo = $agruparPor === 'bahia' ? 'Bahía' : 'Cliente';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recordatorio de vencimiento de equipos</title>
    <style>
        @page { margin: 32px 34px 56px 34px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #171717; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 5px 8px; text-align: left; vertical-align: top; }
        .sin-borde td { border: none; padding: 0; vertical-align: middle; }
        .banda { margin-top: 10px; background: {{ $acento }}; color: {{ $sobreAcento }}; border-radius: 6px; padding: 12px 16px; }
        .banda td { padding: 0; vertical-align: middle; }
        .banda h1 { font-size: 16px; margin: 0 0 2px; color: {{ $sobreAcento }}; }
        .banda .derecha { text-align: right; color: {{ $sobreAcento }}; font-size: 8.5px; }
        .resumen { margin-top: 12px; border-collapse: separate; border-spacing: 8px 0; }
        .resumen td { border: 1px solid #e5e5e5; border-radius: 6px; padding: 8px 12px; width: 50%; }
        .resumen .numero { font-size: 16px; font-weight: bold; }
        .resumen .texto { color: #737373; font-size: 8.5px; }
        h2 { font-size: 11.5px; margin: 18px 0 4px; padding-bottom: 4px; border-bottom: 2px solid {{ $acento }}; }
        h3 { font-size: 9.5px; margin: 10px 0 5px; }
        .marca { display: inline-block; width: 8px; height: 8px; border-radius: 2px; margin-right: 4px; }
        .grid { border-collapse: separate; border-spacing: 0; }
        .grid th { background: #f5f5f5; color: #404040; font-size: 8.5px; border-bottom: 1px solid #e5e5e5; }
        .grid td { border-bottom: 1px solid #f0f0f0; }
        .grid tr.par td { background: #fafafa; }
        .derecha { text-align: right; }
        .secundario { color: #737373; font-size: 8px; margin-top: 1px; }
        .vacio { color: #737373; margin-top: 16px; }
        .pie { position: fixed; bottom: -36px; left: 0; right: 0; font-size: 8px; color: #737373; border-top: 1px solid #e5e5e5; padding-top: 6px; }
        .pie td { padding: 0; }
        .pie .pagina:before { content: counter(page); }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <table class="sin-borde">
        <tr>
            <td>
                @if ($logo)
                    <img src="{{ $logo }}" style="max-height: 42px; max-width: 160px;" alt="">
                @else
                    <strong style="font-size: 12px;">{{ $empresa?->nombre ?? config('app.name') }}</strong>
                @endif
            </td>
            <td class="derecha" style="color: #737373; font-size: 8.5px;">
                @if ($empresa)
                    {{ $empresa->nombre }}<br>
                    {{ $empresa->direccion }}<br>
                    {{ $empresa->telefono }}
                @endif
            </td>
        </tr>
    </table>

    <div class="banda">
        <table>
            <tr>
                <td>
                    <h1>Recordatorio de vencimiento de equipos</h1>
                    <div>Para: <strong>{{ $destinatario }}</strong></div>
                </td>
                <td class="derecha">
                    Emitido el <strong>{{ $fechaEmision->format('d/m/Y H:i') }} UTC</strong><br>
                    Vencidos y programados de los próximos {{ $diasAnticipacion }} días
                </td>
            </tr>
        </table>
    </div>

    <table class="resumen">
        <tr>
            @foreach ($secciones as $estado => [$titulo, $color])
                <td>
                    <div class="numero" style="color: {{ $color }};">{{ $conteo->get($estado, 0) }}</div>
                    <div class="texto">{{ $titulo }}</div>
                </td>
            @endforeach
        </tr>
    </table>

    @forelse ($grupos as $grupo => $porEstado)
        <h2>{{ $etiquetaGrupo }}: {{ $grupo }}</h2>

        @foreach ($secciones as $estado => [$titulo, $color])
            @php($filas = $porEstado->get($estado))
            @continue(! $filas)

            <h3><span class="marca" style="background: {{ $color }};"></span>{{ $titulo }} ({{ $filas->count() }})</h3>

            <table class="grid">
                <thead>
                    <tr>
                        <th>Equipo</th>
                        @if ($agruparPor === 'bahia')
                            <th>Cliente</th>
                            <th>Área</th>
                        @else
                            <th>Área / Bahía</th>
                        @endif
                        <th>Servicio</th>
                        <th>Próximo servicio</th>
                        <th class="derecha">Días</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filas as $fila)
                        <tr class="{{ $loop->even ? 'par' : '' }}">
                            {{-- Como la columna Equipo de la tabla de equipos: código y tipo arriba, modelo y serie debajo --}}
                            <td>
                                <strong>{{ $fila['codigo'] }}</strong> · {{ $fila['tipo_equipo'] }}
                                <div class="secundario">Modelo: {{ $fila['modelo'] ?: '—' }} · Serie: {{ $fila['numero_serie'] ?: '—' }}</div>
                            </td>
                            @if ($agruparPor === 'bahia')
                                <td>{{ $fila['cliente'] }}</td>
                                <td>{{ $fila['area'] }}</td>
                            @else
                                <td>
                                    {{ $fila['area'] }}
                                    <div class="secundario">Bahía: {{ $fila['bahia'] }}</div>
                                </td>
                            @endif
                            <td>{{ $fila['tipo_servicio'] }}</td>
                            <td>{{ $fecha($fila['fecha_proximo_servicio']) }}</td>
                            <td class="derecha" style="color: {{ $fila['dias'] < 0 ? '#b91c1c' : '#171717' }};">
                                @if ($fila['dias'] < 0)
                                    {{ abs($fila['dias']) }} de atraso
                                @elseif ($fila['dias'] === 0)
                                    Hoy
                                @else
                                    En {{ $fila['dias'] }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @empty
        <p class="vacio">No hay equipos vencidos ni programados para este periodo.</p>
    @endforelse

    <div class="pie">
        <table class="sin-borde">
            <tr>
                <td>Recordatorio automático generado por {{ config('app.name') }}</td>
                <td class="derecha">Página <span class="pagina"></span></td>
            </tr>
        </table>
    </div>
</body>
</html>
