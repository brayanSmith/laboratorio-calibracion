@php
    $azul = '#1d4f7a';
    $tipoLabel = $mantenimiento->tipo_mantenimiento === 'CORRECTIVO' ? 'Correctivo (CM)' : 'Preventivo (PMI)';
    $operativo = $mantenimiento->estado_final_equipo === 'OPERATIVO';
    $condicion = $mantenimiento->estado_final_equipo === null
        ? null
        : ($operativo ? 'Operativo' : 'Inoperativo');
    $fecha = fn ($valor) => $valor ? $valor->locale('es')->isoFormat('DD/MMM/YYYY') : '—';
    $riesgo = [
        'ALTO' => ['Alto', '#a3271d', '#f6cccb'],
        'MEDIO' => ['Medio', '#96590b', '#fbe0c0'],
        'BAJO' => ['Bajo', '#7a6a06', '#f7edb8'],
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe de Inspección de Mantenimiento {{ $mantenimiento->ordenTrabajo->codigo }}</title>
    <style>
        @page { margin: 40px 38px 62px 38px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #1f2933; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 8px; text-align: left; vertical-align: top; }
        .sin-borde td { border: none; padding: 0; vertical-align: top; }
        h1 { font-size: 18px; margin: 0 0 3px; color: #1f2933; }
        .tipo { font-size: 14px; color: {{ $azul }}; font-weight: bold; margin: 0; }
        .derecha { text-align: right; color: #6b7280; }
        .derecha strong { color: #1f2933; font-size: 11px; }
        h2 { font-size: 13px; margin: 22px 0 8px; padding-bottom: 5px; color: {{ $azul }}; border-bottom: 2px solid #c9ccd1; }
        .info td { border-bottom: 1px solid #e2e5e9; }
        .info td.etiqueta { width: 38%; color: #6b7280; font-weight: bold; }
        .grid th { background: #ececec; border: 1px solid #c9ccd1; font-size: 9px; color: #1f2933; }
        .grid td { border: 1px solid #c9ccd1; }
        .centro { text-align: center; }
        .chip { padding: 2px 8px; font-weight: bold; font-size: 9px; }
        .vacio { color: #6b7280; }
        .foto { max-width: 150px; max-height: 110px; }
        .comentarios p { margin: 0 0 5px; padding-left: 10px; }
        .firma-linea { border-bottom: 1px solid #1f2933; height: 38px; width: 210px; }
        .pie { position: fixed; bottom: -40px; left: 0; right: 0; font-size: 8.5px; color: #6b7280; border-top: 1px solid #c9ccd1; padding-top: 5px; }
        .pie .pagina:before { content: counter(page); }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <table class="sin-borde">
        <tr>
            @if ($logo)
                <td style="width: 70px;"><img src="{{ $logo }}" style="max-height: 44px; max-width: 64px;"></td>
            @endif
            <td>
                <h1>Informe de Inspección de Mantenimiento</h1>
                <p class="tipo">{{ $tipoLabel }}</p>
            </td>
            <td class="derecha" style="width: 190px;">
                <strong>{{ $mantenimiento->tecnico->name }}</strong><br>
                Creado el {{ $fechaHora->locale('es')->isoFormat('dddd DD [de] MMM [del] YYYY HH:mm') }}
            </td>
        </tr>
    </table>

    <h2>I. Información General</h2>
    <table class="info">
        <tr><td class="etiqueta">Fecha y Hora:</td><td>{{ $fechaHora->locale('es')->isoFormat('dddd DD [de] MMM [del] YYYY [a las] HH:mm') }}</td></tr>
        <tr><td class="etiqueta">Ubicación:</td><td>{{ $equipo->bahia?->nombre ?? '—' }}</td></tr>
        <tr><td class="etiqueta">ID de Equipo/Activo:</td><td>{{ data_get($equipo->ficha_tecnica, 'numero_activo') ?: $equipo->codigo }}</td></tr>
        <tr><td class="etiqueta">Fabricante:</td><td>{{ $equipo->fabricante->nombre }}</td></tr>
        <tr><td class="etiqueta">Número de Modelo:</td><td>{{ $equipo->modelo }}</td></tr>
        <tr><td class="etiqueta">Número de Serie:</td><td>{{ $equipo->numero_serie }}</td></tr>
        <tr><td class="etiqueta">Tipo de Equipo:</td><td>{{ $equipo->tipoEquipo->tipo_mantenimiento }}</td></tr>
        <tr><td class="etiqueta">Fecha del Último Mantenimiento:</td><td>{{ $fecha($programacion->fecha_ultimo_servicio) }}</td></tr>
        <tr><td class="etiqueta">Próximo Mantenimiento Programado:</td><td>{{ $fecha($programacion->fecha_proximo_servicio) }}</td></tr>
        <tr>
            <td class="etiqueta">Condición Operativa:</td>
            <td>
                @if ($condicion)
                    <span class="chip" style="color: {{ $operativo ? '#1f8a4c' : '#a3271d' }}; background: {{ $operativo ? '#d9f2e3' : '#fbdada' }};">{{ $condicion }}</span>
                @else
                    <span class="vacio">Sin registrar</span>
                @endif
            </td>
        </tr>
        <tr><td class="etiqueta">Comentarios Adicionales:</td><td>{{ $mantenimiento->descripcion ?: '—' }}</td></tr>
    </table>

    @if ($mantenimiento->mantenimientoCheckLists->isNotEmpty())
        <h2>II. Lista de Verificación de Inspección</h2>
        <table class="grid">
            <thead>
                <tr><th style="width: 52%;">Ítem</th><th class="centro" style="width: 14%;">Resultado</th><th>Comentarios</th></tr>
            </thead>
            <tbody>
                @foreach ($mantenimiento->mantenimientoCheckLists as $item)
                    <tr>
                        <td>{{ $item->tipoEquipoCheckList->nombre }}</td>
                        <td class="centro">
                            <span class="chip" style="color: {{ $item->cumple ? '#1f8a4c' : '#a3271d' }}; background: {{ $item->cumple ? '#d9f2e3' : '#fbdada' }};">{{ $item->cumple ? '✓ Cumple' : '✗ No cumple' }}</span>
                        </td>
                        <td>{{ $item->observacion }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($mantenimiento->defectoIdentificados->isNotEmpty())
        <h2>III. Defectos/Problemas Identificados</h2>
        <table class="grid">
            <thead>
                <tr><th style="width: 36%;">Problema</th><th class="centro" style="width: 16%;">Nivel de Urgencia</th><th>Acción a Tomar</th></tr>
            </thead>
            <tbody>
                @foreach ($mantenimiento->defectoIdentificados as $defecto)
                    @php [$etiqueta, $texto, $fondo] = $riesgo[$defecto->nivel_riesgo] ?? [$defecto->nivel_riesgo, '#1f2933', '#ececec']; @endphp
                    <tr>
                        <td>{{ $defecto->defecto_identificado }}</td>
                        <td class="centro"><span class="chip" style="color: {{ $texto }}; background: {{ $fondo }};">{{ $etiqueta }}</span></td>
                        <td>{{ $defecto->accion_correctiva }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($mantenimiento->itemMantenimientos->isNotEmpty())
        <h2>IV. Items Utilizados</h2>
        <table class="grid">
            <thead>
                <tr><th>Item</th><th class="centro" style="width: 12%;">Cantidad</th><th class="centro" style="width: 16%;">Fecha</th></tr>
            </thead>
            <tbody>
                @foreach ($mantenimiento->itemMantenimientos as $itemUsado)
                    <tr>
                        <td>{{ $itemUsado->item->codigo }} - {{ $itemUsado->item->nombre }}</td>
                        <td class="centro">{{ rtrim(rtrim(number_format((float) $itemUsado->cantidad, 2, '.', ''), '0'), '.') }}</td>
                        <td class="centro">{{ $fecha($itemUsado->created_at) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (count($fotos) > 0)
        <h2>V. Galería de Imágenes</h2>
        <table class="grid">
            <thead>
                <tr><th style="width: 40%;">Imagen</th><th>Observación</th></tr>
            </thead>
            <tbody>
                @foreach ($fotos as $foto)
                    <tr>
                        <td class="centro"><img class="foto" src="{{ $foto['data'] }}"></td>
                        <td>{{ $foto['descripcion'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>VI. Comentarios, Recomendaciones y Próximos Pasos</h2>
    <table class="info">
        <tr>
            <td class="etiqueta">Comentarios</td>
            <td class="comentarios">
                @forelse ($mantenimiento->comentarioMantenimientos->sortBy('created_at') as $comentario)
                    <p>• {{ $comentario->comentario }}</p>
                @empty
                    <span class="vacio">Sin comentarios</span>
                @endforelse
            </td>
        </tr>
        <tr>
            <td class="etiqueta">Evaluación General:</td>
            <td>{{ $condicion === null ? 'Sin registrar' : ($operativo ? 'Equipo Operativo' : 'Equipo Fuera de Servicio') }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Firma del Inspector:</td>
            <td>
                @if ($firma)
                    <img src="{{ $firma }}" style="max-height: 52px; max-width: 200px;">
                @endif
                <div class="firma-linea" @if ($firma) style="height: 4px;" @endif></div>
                Nombre: {{ $mantenimiento->tecnico->name }}
            </td>
        </tr>
    </table>

    <div class="pie">
        <table class="sin-borde">
            <tr>
                <td>{{ $empresa?->nombre }}</td>
                <td class="centro">La versión impresa no está controlada</td>
                <td style="text-align: right;">
                    Este PDF fue creado el {{ $fechaHora->locale('es')->isoFormat('DD/MMM/YYYY HH:mm') }}
                    · Página <span class="pagina"></span>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
