@php
    // Mismo acento que la plataforma: el color de tema de la empresa (o el negro por defecto).
    $acento = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($empresa?->color_tema)) ? $empresa->color_tema : '#171717';
    [$r, $g, $b] = sscanf($acento, '#%02x%02x%02x');
    $sobreAcento = (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#171717' : '#ffffff';
    $tipoLabel = $mantenimiento->tipo_mantenimiento === 'CORRECTIVO' ? 'Correctivo (CM)' : 'Preventivo (PMI)';
    $operativo = $mantenimiento->estado_final_equipo === 'OPERATIVO';
    $condicion = $mantenimiento->estado_final_equipo === null
        ? null
        : ($operativo ? 'Operativo' : 'Inoperativo');
    $fecha = fn ($valor) => $valor ? $valor->locale('es')->isoFormat('DD/MMM/YYYY') : '—';
    $moneda = fn ($valor) => '$ '.number_format((float) $valor, 2, ',', '.');
    $riesgo = [
        'ALTO' => ['Alto', '#ffffff', '#b91c1c'],
        'MEDIO' => ['Medio', '#171717', '#eab308'],
        'BAJO' => ['Bajo', '#ffffff', '#15803d'],
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe de Inspección de Mantenimiento {{ $mantenimiento->ordenTrabajo->codigo }}</title>
    <style>
        @page { margin: 36px 38px 62px 38px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #171717; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 10px; text-align: left; vertical-align: top; }
        .sin-borde td { border: none; padding: 0; vertical-align: middle; }
        .banda { margin-top: 10px; background: {{ $acento }}; color: {{ $sobreAcento }}; border-radius: 6px; padding: 12px 16px; }
        .banda td { padding: 0; vertical-align: middle; }
        .banda h1 { font-size: 17px; margin: 0 0 2px; color: {{ $sobreAcento }}; }
        .banda .tipo { font-size: 11px; margin: 0; font-weight: bold; }
        .banda .derecha { text-align: right; color: {{ $sobreAcento }}; font-size: 8.5px; }
        .banda .derecha strong { font-size: 10.5px; }
        .derecha { text-align: right; }
        h2 { font-size: 11.5px; margin: 18px 0 7px; color: #171717; }
        .caja { border: 1px solid #e5e5e5; border-radius: 6px; }
        .info td { border-bottom: 1px solid #f0f0f0; }
        .info tr.ultima td { border-bottom: 0; }
        .info td.etiqueta { width: 38%; color: #737373; font-weight: bold; background: #fafafa; }
        .grid { border-collapse: separate; border-spacing: 0; }
        .grid th { background: {{ $acento }}; color: {{ $sobreAcento }}; font-size: 9px; }
        .grid th.inicio { border-radius: 5px 0 0 0; }
        .grid th.fin { border-radius: 0 5px 0 0; }
        .grid td { border-bottom: 1px solid #f0f0f0; }
        .grid tr.par td { background: #fafafa; }
        .grid tr.ultima td { border-bottom: 0; }
        .centro { text-align: center; }
        .chip { padding: 2px 8px; font-weight: bold; font-size: 9px; border-radius: 5px; color: #ffffff; }
        .vacio { color: #737373; }
        .foto { max-width: 150px; max-height: 110px; border-radius: 4px; }
        .comentarios p { margin: 0 0 5px; padding-left: 10px; }
        .firma-linea { border-bottom: 1px solid #a3a3a3; height: 38px; width: 210px; }
        .pie { position: fixed; bottom: -40px; left: 0; right: 0; font-size: 8.5px; color: #737373; border-top: 1px solid #e5e5e5; padding-top: 6px; }
        .pie td { padding: 0; }
        .pie .pagina:before { content: counter(page); }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <table class="sin-borde">
        <tr>
            <td>@if ($logo)<img src="{{ $logo }}" style="max-height: 90px; max-width: 240px;">@endif</td>
            <td class="derecha" style="color: #737373; font-size: 8.5px;">
                <strong style="color: #171717; font-size: 11px;">{{ $empresa?->nombre }}</strong><br>
                {{ $empresa?->direccion }}
            </td>
        </tr>
    </table>

    <div class="banda">
        <table>
            <tr>
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
    </div>
    <h2>I. Información General</h2>
    <div class="caja"><table class="info">
        <tr><td class="etiqueta">Fecha y Hora:</td><td>{{ $fechaHora->locale('es')->isoFormat('dddd DD [de] MMM [del] YYYY [a las] HH:mm') }}</td></tr>
        <tr><td class="etiqueta">Ubicación:</td><td>{{ $equipo->bahia?->nombre ?? '—' }}</td></tr>
        <tr><td class="etiqueta">ID de Equipo/Activo:</td><td>{{ data_get($equipo->ficha_tecnica, 'numero_activo') ?: $equipo->codigo }}</td></tr>
        <tr><td class="etiqueta">Fabricante:</td><td>{{ $equipo->fabricante->nombre }}</td></tr>
        <tr><td class="etiqueta">Número de Modelo:</td><td>{{ $equipo->modelo }}</td></tr>
        <tr><td class="etiqueta">Número de Serie:</td><td>{{ $equipo->numero_serie }}</td></tr>
        <tr><td class="etiqueta">Tipo de Equipo:</td><td>{{ $equipo->tipoEquipo->tipo_mantenimiento }}</td></tr>
        <tr><td class="etiqueta">Duración del Mantenimiento:</td><td>{{ $duracion ?? '—' }}</td></tr>
        <tr><td class="etiqueta">Fecha del Último Mantenimiento:</td><td>{{ $fecha($programacion->fecha_ultimo_servicio) }}</td></tr>
        <tr><td class="etiqueta">Próximo Mantenimiento Programado:</td><td>{{ $fecha($programacion->fecha_proximo_servicio) }}</td></tr>
        <tr>
            <td class="etiqueta">Condición Operativa:</td>
            <td>
                @if ($condicion)
                    <span class="chip" style="background: {{ $operativo ? '#15803d' : '#b91c1c' }};">{{ $condicion }}</span>
                @else
                    <span class="vacio">Sin registrar</span>
                @endif
            </td>
        </tr>
        <tr class="ultima"><td class="etiqueta">Comentarios Adicionales:</td><td>{{ $mantenimiento->descripcion ?: '—' }}</td></tr>
    </table></div>

    @if ($mantenimiento->mantenimientoCheckLists->isNotEmpty())
        <h2>II. Lista de Verificación de Inspección</h2>
        <div class="caja"><table class="grid">
            <thead><tr><th class="inicio" style="width: 52%;">Ítem</th><th class="centro" style="width: 14%;">Resultado</th><th class="fin">Comentarios</th></tr>
            </thead>
            <tbody>
                @foreach ($mantenimiento->mantenimientoCheckLists as $item)
                    <tr class="{{ $loop->even ? 'par' : '' }} {{ $loop->last ? 'ultima' : '' }}">
                        <td>{{ $item->tipoEquipoCheckList->nombre }}</td>
                        <td class="centro">
                            <span class="chip" style="background: {{ $item->cumple ? '#15803d' : '#b91c1c' }};">{{ $item->cumple ? '✓ Cumple' : '✗ No cumple' }}</span>
                        </td>
                        <td>{{ $item->observacion }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    @endif

    @if ($mantenimiento->defectoIdentificados->isNotEmpty())
        <h2>III. Defectos/Problemas Identificados</h2>
        <div class="caja"><table class="grid">
            <thead><tr><th class="inicio" style="width: 36%;">Problema</th><th class="centro" style="width: 16%;">Nivel de Urgencia</th><th class="fin">Acción a Tomar</th></tr>
            </thead>
            <tbody>
                @foreach ($mantenimiento->defectoIdentificados as $defecto)
                    @php [$etiqueta, $texto, $fondo] = $riesgo[$defecto->nivel_riesgo] ?? [$defecto->nivel_riesgo, '#ffffff', '#525252']; @endphp
                    <tr class="{{ $loop->even ? 'par' : '' }} {{ $loop->last ? 'ultima' : '' }}">
                        <td>{{ $defecto->defecto_identificado }}</td>
                        <td class="centro"><span class="chip" style="color: {{ $texto }}; background: {{ $fondo }};">{{ $etiqueta }}</span></td>
                        <td>{{ $defecto->accion_correctiva }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    @endif

    @if ($mantenimiento->itemMantenimientos->isNotEmpty())
        <h2>IV. Items Utilizados</h2>
        <div class="caja"><table class="grid">
            <thead><tr><th class="inicio">Item</th><th class="centro" style="width: 9%;">Cantidad</th><th class="derecha" style="width: 17%;">Precio unitario</th><th class="derecha" style="width: 17%;">Subtotal</th><th class="fin centro" style="width: 14%;">Fecha</th></tr>
            </thead>
            <tbody>
                @foreach ($mantenimiento->itemMantenimientos as $itemUsado)
                    <tr class="{{ $loop->even ? 'par' : '' }} {{ $loop->last ? 'ultima' : '' }}">
                        <td>{{ $itemUsado->item->codigo }} - {{ $itemUsado->item->nombre }}</td>
                        <td class="centro">{{ rtrim(rtrim(number_format((float) $itemUsado->cantidad, 2, '.', ''), '0'), '.') }}</td>
                        <td class="derecha">{{ $moneda($itemUsado->precio_unitario) }}</td>
                        <td class="derecha"><strong>{{ $moneda($itemUsado->subtotal) }}</strong></td>
                        <td class="centro">{{ $fecha($itemUsado->created_at) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    @endif

    @if (count($fotos) > 0)
        <h2>V. Galería de Imágenes</h2>
        <div class="caja"><table class="grid">
            <thead><tr><th class="inicio" style="width: 40%;">Imagen</th><th class="fin">Observación</th></tr>
            </thead>
            <tbody>
                @foreach ($fotos as $foto)
                    <tr class="{{ $loop->even ? 'par' : '' }} {{ $loop->last ? 'ultima' : '' }}">
                        <td class="centro"><img class="foto" src="{{ $foto['data'] }}"></td>
                        <td>{{ $foto['descripcion'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    @endif

    <h2>VI. Comentarios, Recomendaciones y Próximos Pasos</h2>
    <div class="caja"><table class="info">
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
        <tr class="ultima">
            <td class="etiqueta">Firma del Inspector:</td>
            <td>
                @if ($firma)
                    <img src="{{ $firma }}" style="max-height: 52px; max-width: 200px;">
                @endif
                <div class="firma-linea" @if ($firma) style="height: 4px;" @endif></div>
                Nombre: {{ $mantenimiento->tecnico->name }}
            </td>
        </tr>
    </table></div>

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
