@php
    $azul = '#005aa7';
    $numero = fn ($valor) => $valor === null ? '—' : number_format((float) $valor, 2, ',', '');
    $fecha = fn ($valor) => $valor ? \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y') : '—';
    $conforme = $resultado === 'CONFORME';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Certificado de Calibración {{ $numeroCertificado }}</title>
    <style>
        @page { margin: 30px 42px 50px 42px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #1f2933; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; text-align: left; }
        .encabezado td { padding: 0; }
        .empresa { text-align: right; }
        .empresa strong { font-size: 12px; }
        .empresa span { font-size: 8px; color: #6b7280; }
        h1 { text-align: center; color: {{ $azul }}; font-size: 19px; margin: 12px 0 0; padding-bottom: 6px; border-bottom: 4px solid {{ $azul }}; }
        .emision td { padding: 12px 0 8px; }
        .derecha { text-align: right; }
        .muted { color: #6b7280; }
        .badge { padding: 2px 10px; font-weight: bold; font-size: 9px; color: #ffffff; }
        .tarjeta { border: 1px solid #c9ccd1; }
        .tarjeta .titulo { background: #ececec; padding: 7px 10px; font-weight: bold; font-size: 10px; border-bottom: 1px solid #c9ccd1; }
        .tarjeta td { padding: 2px 10px; }
        .tarjeta td.etiqueta { color: #374151; width: 48%; }
        .tarjeta td.valor { font-weight: bold; }
        .tabla th { background: {{ $azul }}; color: #ffffff; padding: 6px 8px; font-size: 9px; text-align: center; }
        .tabla td { padding: 3px 8px; text-align: center; border-bottom: 1px solid #e2e5e9; }
        .tabla tr.par td { background: #f3f4f6; }
        h3 { font-size: 10.5px; margin: 9px 0 4px; }
        .grafico { text-align: center; margin-top: 8px; }
        .grafico img { width: 400px; border: 1px solid #c9ccd1; padding: 4px; }
        .firma { text-align: center; margin-top: 6px; page-break-inside: avoid; }
        .firma .linea { width: 220px; border-top: 1px solid #1f2933; margin: 30px auto 4px; }
        .pie { position: fixed; bottom: -28px; left: 0; right: 0; font-size: 8px; color: #6b7280; border-top: 1px solid #c9ccd1; padding-top: 5px; }
        .pie td { padding: 0; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td>@if ($logo)<img src="{{ $logo }}" style="max-height: 56px; max-width: 150px;">@endif</td>
            <td class="empresa">
                <strong>{{ $empresa?->nombre }}</strong><br>
                <span>{{ $empresa?->direccion }}</span>
            </td>
        </tr>
    </table>

    <h1>Certificado de Calibración</h1>

    <table class="emision">
        <tr>
            <td>
                <strong>Solicitante:</strong><br>
                {{ $calibracion->solicitante?->nombre ?? '—' }}
            </td>
            <td class="derecha">
                <strong>Certificado N°: {{ $numeroCertificado }}</strong><br>
                <span class="muted">Fecha de Emisión: {{ $fechaEmision->format('d/m/Y') }}</span><br>
                <strong>Resultado de Calibración:</strong>
                @if ($resultado)
                    <span class="badge" style="background: {{ $conforme ? '#2ea84f' : '#c0392b' }};">{{ $resultado }}</span>
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
                    <table>
                        <tr><td class="etiqueta">ID de Equipo</td><td class="valor">{{ data_get($equipo->ficha_tecnica, 'numero_activo') ?: $equipo->codigo }}</td></tr>
                        <tr><td class="etiqueta">Descripción</td><td class="valor">{{ $equipo->tipoEquipo->nombre }}</td></tr>
                        <tr><td class="etiqueta">Fabricante</td><td class="valor">{{ $equipo->fabricante->nombre }}</td></tr>
                        <tr><td class="etiqueta">Modelo</td><td class="valor">{{ $equipo->modelo }}</td></tr>
                        <tr><td class="etiqueta">Número de Serie</td><td class="valor">{{ $equipo->numero_serie }}</td></tr>
                        <tr><td class="etiqueta">Alcance de Indicación</td><td class="valor">{{ $especificacion?->alcance_indicacion ?? '—' }}</td></tr>
                        <tr><td class="etiqueta">Resolución</td><td class="valor">{{ $especificacion?->resolucion ?? '—' }}</td></tr>
                        <tr><td class="etiqueta">Precisión</td><td class="valor">{{ $especificacion?->precision ?? '—' }}</td></tr>
                    </table>
                </div>
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 49%;">
                <div class="tarjeta">
                    <div class="titulo">Información General</div>
                    <table>
                        <tr><td class="etiqueta">Lugar de Calibración:</td><td class="valor">{{ $calibracion->laboratorio?->nombre ?? '—' }}</td></tr>
                        <tr><td class="etiqueta">Fecha de Calibración:</td><td class="valor">{{ $fecha($calibracion->fecha_calibracion) }}</td></tr>
                        <tr><td class="etiqueta">Condiciones ambientales (°C y % HR):</td><td class="valor">{{ $calibracion->temperatura !== null ? rtrim(rtrim((string) $calibracion->temperatura, '0'), '.') : '—' }} / {{ $calibracion->humedad !== null ? rtrim(rtrim((string) $calibracion->humedad, '0'), '.') : '—' }}</td></tr>
                        <tr><td class="etiqueta">Técnico de Laboratorio</td><td class="valor">{{ $calibracion->tecnico->name }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <h3>Método de Calibración: <span style="font-weight: normal;">{{ $calibracion->procedimiento?->nombre ?? '—' }}</span></h3>

    <h3>Trazabilidad:</h3>
    <table class="tabla">
        <thead>
            <tr><th>ID</th><th>Descripción</th><th>Número de Modelo</th><th>Número de Serie</th><th>Fecha Vencimiento Calibración</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ data_get($equipo->ficha_tecnica, 'numero_activo') ?: $equipo->codigo }}</td>
                <td>{{ $equipo->tipoEquipo->nombre }} - {{ $equipo->codigo }}</td>
                <td>{{ $equipo->modelo }}</td>
                <td>{{ $equipo->numero_serie }}</td>
                <td>{{ $fecha($fechaVencimiento) }}</td>
            </tr>
        </tbody>
    </table>

    <h3>Resultados de medición:</h3>
    <table class="tabla">
        <thead>
            <tr><th>Valor Referencia</th><th>Unidad</th><th>Valor Instrumento</th><th>Error Encontrado</th><th>EMP</th><th>Incertidumbre</th></tr>
        </thead>
        <tbody>
            @forelse ($detalles as $detalle)
                <tr class="{{ $loop->even ? 'par' : '' }}">
                    <td>{{ $numero($detalle->valor_referencia) }}</td>
                    <td>{{ $detalle->unidadMedida->simbolo }}</td>
                    <td>{{ $numero($detalle->valor_instrumento) }}</td>
                    <td>{{ $numero($detalle->error_encontrado) }}</td>
                    <td>{{ $numero($detalle->emp) }}</td>
                    <td>{{ $numero($detalle->incertidumbre) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Esta calibración no tiene resultados de medición.</td></tr>
            @endforelse
        </tbody>
    </table>

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
