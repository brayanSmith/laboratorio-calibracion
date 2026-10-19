@php
    // Mismo acento que la plataforma: el color de tema de la empresa (o el negro por defecto).
    $acento = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($empresa?->color_tema)) ? $empresa->color_tema : '#171717';
    [$r, $g, $b] = sscanf($acento, '#%02x%02x%02x');
    $sobreAcento = (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#171717' : '#ffffff';
    $numero = fn ($valor) => $valor === null ? '—' : number_format((float) $valor, 2, ',', '');
    $fecha = fn ($valor) => $valor ? \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y') : '—';
    $conforme = $resultado === 'APROBADO';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Certificado de Calibración {{ $numeroCertificado }}</title>
    <style>
        @page { margin: 34px 40px 54px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #171717; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; text-align: left; }
        .encabezado td { padding: 0; vertical-align: middle; }
        .empresa { text-align: right; }
        .empresa strong { font-size: 12px; }
        .empresa span { font-size: 8px; color: #737373; }
        .banda { margin-top: 12px; background: {{ $acento }}; color: {{ $sobreAcento }}; border-radius: 6px; padding: 12px 16px; }
        .banda td { padding: 0; vertical-align: middle; }
        .banda h1 { font-size: 17px; margin: 0; color: {{ $sobreAcento }}; }
        .banda .num { text-align: right; font-size: 9px; }
        .banda .num strong { font-size: 11px; }
        .emision td { padding: 12px 2px 8px; }
        .derecha { text-align: right; }
        .muted { color: #737373; }
        .chip { padding: 2px 8px; font-weight: bold; font-size: 8.5px; border-radius: 5px; }
        .badge { padding: 3px 12px; font-weight: bold; font-size: 9px; color: #ffffff; border-radius: 6px; }
        .tarjeta { border: 1px solid #e5e5e5; border-radius: 6px; }
        .tarjeta .titulo { background: #f5f5f5; padding: 8px 12px; font-weight: bold; font-size: 10px; border-bottom: 1px solid #e5e5e5; border-radius: 6px 6px 0 0; }
        .tarjeta .cuerpo { padding: 4px 0 6px; }
        .tarjeta td { padding: 2.5px 12px; }
        .tarjeta td.etiqueta { color: #737373; width: 48%; }
        .tarjeta td.valor { font-weight: bold; }
        .caja { border: 1px solid #e5e5e5; border-radius: 6px; }
        .tabla { border-collapse: separate; border-spacing: 0; }
        .tabla th { background: {{ $acento }}; color: {{ $sobreAcento }}; padding: 7px 8px; font-size: 9px; text-align: center; }
        .tabla th.inicio { border-radius: 5px 0 0 0; }
        .tabla th.fin { border-radius: 0 5px 0 0; }
        .tabla td { padding: 4px 8px; text-align: center; border-bottom: 1px solid #f0f0f0; }
        .tabla tr.par td { background: #fafafa; }
        .tabla tr.ultima td { border-bottom: 0; }
        h3 { font-size: 10.5px; margin: 12px 0 5px; }
        .grafico { text-align: center; margin-top: 12px; }
        .grafico img { width: 100%; }
        .firma { text-align: center; margin-top: 6px; page-break-inside: avoid; }
        .firma .linea { width: 220px; border-top: 1px solid #a3a3a3; margin: 30px auto 4px; }
        .pie { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8px; color: #737373; border-top: 1px solid #e5e5e5; padding-top: 6px; }
        .pie td { padding: 0; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td>@if ($logo)<img src="{{ $logo }}" style="max-height: 90px; max-width: 240px;">@endif</td>
            <td class="empresa">
                <strong>{{ $empresa?->nombre }}</strong><br>
                <span>{{ $empresa?->direccion }}</span>
            </td>
        </tr>
    </table>

    <div class="banda">
        <table>
            <tr>
                <td><h1>Certificado de Calibración</h1></td>
                <td class="num"><strong>N° {{ $numeroCertificado }}</strong><br>Emitido el {{ $fechaEmision->format('d/m/Y') }}</td>
            </tr>
        </table>
    </div>

    <table class="emision">
        <tr>
            <td>
                <strong>Solicitante:</strong><br>
                {{ $calibracion->solicitante?->nombre ?? '—' }}
            </td>
            <td class="derecha">
                <strong>Resultado de Calibración:</strong>
                @if ($resultado)
                    <span class="badge" style="background: {{ $conforme ? '#15803d' : '#b91c1c' }};">{{ $resultado }}</span>
                @else
                    <span class="muted">Sin resultados</span>
                @endif
            </td>
        </tr>
    </table>

    <table style="margin-top: 4px;">
        <tr>
            <td style="width: 49%;">
                <div class="tarjeta">
                    <div class="titulo">Instrumento de medición</div>
                    <div class="cuerpo"><table>
                        <tr><td class="etiqueta">ID de Equipo</td><td class="valor">{{ data_get($equipo->ficha_tecnica, 'numero_activo') ?: $equipo->codigo }}</td></tr>
                        <tr><td class="etiqueta">Descripción</td><td class="valor">{{ $equipo->tipoEquipo->nombre }}</td></tr>
                        <tr><td class="etiqueta">Fabricante</td><td class="valor">{{ $equipo->fabricante->nombre }}</td></tr>
                        <tr><td class="etiqueta">Modelo</td><td class="valor">{{ $equipo->modelo }}</td></tr>
                        <tr><td class="etiqueta">Número de Serie</td><td class="valor">{{ $equipo->numero_serie }}</td></tr>
                        <tr><td class="etiqueta">Alcance de Indicación</td><td class="valor">{{ $especificacion?->alcance_indicacion ?? '—' }}</td></tr>
                        <tr><td class="etiqueta">Resolución</td><td class="valor">{{ $especificacion?->resolucion ?? '—' }}</td></tr>
                        <tr><td class="etiqueta">Precisión</td><td class="valor">{{ $especificacion?->precision ?? '—' }}</td></tr>
                    </table></div>
                </div>
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 49%;">
                <div class="tarjeta">
                    <div class="titulo">Información General</div>
                    <div class="cuerpo"><table>
                        <tr><td class="etiqueta">Lugar de Calibración:</td><td class="valor">{{ $calibracion->laboratorio?->nombre ?? '—' }}</td></tr>
                        <tr><td class="etiqueta">Fecha de Calibración:</td><td class="valor">{{ $fecha($calibracion->fecha_calibracion) }}</td></tr>
                        <tr><td class="etiqueta">Condiciones ambientales (°C y % HR):</td><td class="valor">{{ $calibracion->temperatura !== null ? rtrim(rtrim((string) $calibracion->temperatura, '0'), '.') : '—' }} / {{ $calibracion->humedad !== null ? rtrim(rtrim((string) $calibracion->humedad, '0'), '.') : '—' }}</td></tr>
                        <tr><td class="etiqueta">Técnico de Laboratorio</td><td class="valor">{{ $calibracion->tecnico->name }}</td></tr>
                    </table></div>
                </div>
            </td>
        </tr>
    </table>

    <h3>Método de Calibración: <span style="font-weight: normal;">{{ $calibracion->procedimiento?->nombre ?? '—' }}</span></h3>

    <h3>Trazabilidad:</h3>
    <div class="caja"><table class="tabla">
        <thead>
            <tr><th class="inicio">ID</th><th>Descripción</th><th>Número de Modelo</th><th>Número de Serie</th><th class="fin">Fecha Vencimiento Calibración</th></tr>
        </thead>
        <tbody>
            <tr class="ultima">
                <td>{{ data_get($equipo->ficha_tecnica, 'numero_activo') ?: $equipo->codigo }}</td>
                <td>{{ $equipo->tipoEquipo->nombre }} - {{ $equipo->codigo }}</td>
                <td>{{ $equipo->modelo }}</td>
                <td>{{ $equipo->numero_serie }}</td>
                <td>{{ $fecha($fechaVencimiento) }}</td>
            </tr>
        </tbody>
    </table></div>

    <h3>Resultados de medición:</h3>
    <div class="caja"><table class="tabla">
        <thead>
            <tr><th class="inicio">Valor Referencia</th><th>Unidad</th><th>Valor Instrumento</th><th>Error Encontrado</th><th>EMP</th><th>Incertidumbre</th><th class="fin">Resultado</th></tr>
        </thead>
        <tbody>
            @forelse ($detalles as $detalle)
                <tr class="{{ $loop->even ? 'par' : '' }} {{ $loop->last ? 'ultima' : '' }}">
                    <td>{{ $numero($detalle->valor_referencia) }}</td>
                    <td>{{ $detalle->unidadMedida->simbolo }}</td>
                    <td>{{ $numero($detalle->valor_instrumento) }}</td>
                    <td>{{ $numero($detalle->error_encontrado) }}</td>
                    <td>{{ $numero($detalle->emp) }}</td>
                    <td>{{ $numero($detalle->incertidumbre) }}</td>
                    <td>
                        @if ($detalle->resultado_calibracion)
                            @php $aprobado = $detalle->resultado_calibracion === 'APROBADO'; @endphp
                            <span class="chip" style="color: #ffffff; background: {{ $aprobado ? '#15803d' : '#b91c1c' }};">{{ $aprobado ? 'Aprobado' : 'No aprobado' }}</span>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Esta calibración no tiene resultados de medición.</td></tr>
            @endforelse
        </tbody>
    </table></div>

    @if ($grafico)
        <div class="grafico"><img src="{{ $grafico }}"></div>
    @endif

    <div class="firma">
        @if ($firma)
            <img src="{{ $firma }}" style="max-height: 60px; max-width: 220px;">
        @endif
        <div class="linea" @if ($firma) style="margin-top: 4px;" @endif></div>
        {{ $calibracion->tecnico->name }}
    </div>

    <div class="pie">
        <table>
            <tr>
                <td>{{ $empresa?->nombre }}</td>
                <td class="derecha">{{ $empresa?->direccion }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
