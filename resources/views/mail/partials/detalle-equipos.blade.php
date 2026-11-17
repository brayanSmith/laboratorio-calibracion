@php
    $secciones = [
        'VENCIDO' => ['Vencidos', '#b91c1c'],
        'PROGRAMADO' => ['Programados', '#2563eb'],
    ];
    $fecha = fn (string $valor) => \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y');
    $celda = 'padding: 6px 8px; border-bottom: 1px solid #f0f0f0; vertical-align: top; font-size: 13px; color: #171717; text-align: left;';
    $encabezado = 'padding: 6px 8px; background: #f5f5f5; border-bottom: 1px solid #e5e5e5; font-size: 12px; color: #404040; text-align: left;';
    $secundario = 'display: block; color: #737373; font-size: 11px;';
@endphp
<div style="margin: 0 0 16px;">
    @foreach ($grupos as $grupo => $porEstado)
        @if ($agruparPor === 'bahia')
            <p style="margin: 16px 0 4px; font-size: 14px; font-weight: bold; color: #171717;">Bahía: {{ $grupo }}</p>
        @endif

        @foreach ($secciones as $estado => [$titulo, $color])
            @php($filas = $porEstado->get($estado))
            @continue(! $filas)

            <p style="margin: 12px 0 4px; font-size: 13px; font-weight: bold; color: {{ $color }};">{{ $titulo }} ({{ $filas->count() }})</p>
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; border-collapse: collapse; margin: 0 0 8px;">
                <tr>
                    <th style="{{ $encabezado }}">Equipo</th>
                    <th style="{{ $encabezado }}">{{ $agruparPor === 'bahia' ? 'Cliente / Área' : 'Área / Bahía' }}</th>
                    <th style="{{ $encabezado }}">Próximo servicio</th>
                    <th style="{{ $encabezado }} text-align: right;">Días</th>
                </tr>
                @foreach ($filas as $fila)
                    <tr>
                        <td style="{{ $celda }}">
                            <strong>{{ $fila['codigo'] }}</strong> · {{ $fila['tipo_equipo'] }}
                            <span style="{{ $secundario }}">Modelo: {{ $fila['modelo'] ?: '—' }} · Serie: {{ $fila['numero_serie'] ?: '—' }}</span>
                        </td>
                        <td style="{{ $celda }}">
                            @if ($agruparPor === 'bahia')
                                {{ $fila['cliente'] }}
                                <span style="{{ $secundario }}">{{ $fila['area'] }}</span>
                            @else
                                {{ $fila['area'] }}
                                <span style="{{ $secundario }}">Bahía: {{ $fila['bahia'] }}</span>
                            @endif
                        </td>
                        <td style="{{ $celda }}">
                            {{ $fecha($fila['fecha_proximo_servicio']) }}
                            <span style="{{ $secundario }}">{{ $fila['tipo_servicio'] }}</span>
                        </td>
                        <td style="{{ $celda }} text-align: right; white-space: nowrap; color: {{ $fila['dias'] < 0 ? '#b91c1c' : '#171717' }};">
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
            </table>
        @endforeach
    @endforeach

    @if ($faltantes > 0)
        <p style="margin: 8px 0 0; font-size: 12px; color: #737373;">Y {{ $faltantes }} {{ $faltantes === 1 ? 'equipo más' : 'equipos más' }} en el PDF adjunto.</p>
    @endif
</div>
