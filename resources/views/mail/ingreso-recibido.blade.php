@php
    $fecha = \Illuminate\Support\Carbon::parse($fechaRecepcion)->format('d/m/Y');
    $celda = 'padding: 7px 8px; border-bottom: 1px solid #f0f0f0; vertical-align: top; font-size: 13px; color: #171717; text-align: left;';
    $encabezado = 'padding: 7px 8px; background: #f5f5f5; border-bottom: 1px solid #e5e5e5; font-size: 12px; color: #404040; text-align: left;';
    $secundario = 'display: block; color: #737373; font-size: 11px;';
@endphp
<x-mail::layout>
{{-- Encabezado: logo de la empresa (o su nombre) y el tipo de documento --}}
<x-slot:header>
<tr>
<td class="header">
<table width="570" align="center" cellpadding="0" cellspacing="0" role="presentation" style="width: 570px; margin: 0 auto;">
<tr>
<td style="text-align: left; vertical-align: middle;">
@if ($logo)
<img src="{{ $message->embedData($logo['contenido'], $logo['nombre'], $logo['tipo']) }}" alt="{{ $empresa }}" style="max-height: 60px; max-width: 200px; width: auto; height: auto; border: none;">
@else
<span style="font-size: 20px; font-weight: bold; color: {{ $acento }};">{{ $empresa }}</span>
@endif
</td>
<td style="text-align: right; vertical-align: middle; font-size: 11px; font-weight: bold; letter-spacing: 1px; color: #737373;">RECEPCIÓN / INGRESO</td>
</tr>
</table>
</td>
</tr>
</x-slot:header>

<p>Estimados, {{ $cliente }}:</p>

<p>Les confirmamos la recepción de los equipos del ingreso N° <strong>{{ $codigo }}</strong> del {{ $fecha }}, en la bahía {{ $bahia }}@if ($tecnico), recibidos por {{ $tecnico }}@endif.</p>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; border-collapse: collapse; margin: 8px 0 20px;">
<tr>
<th style="{{ $encabezado }}">Equipo</th>
<th style="{{ $encabezado }}">Cliente</th>
<th style="{{ $encabezado }}">Área</th>
<th style="{{ $encabezado }}">Servicio</th>
</tr>
@foreach ($equipos as $equipo)
<tr>
<td style="{{ $celda }}">
<strong>{{ $equipo['codigo'] }}</strong> · {{ $equipo['tipo_equipo'] }}
<span style="{{ $secundario }}">Modelo: {{ $equipo['modelo'] ?: '—' }} · Serie: {{ $equipo['numero_serie'] ?: '—' }}</span>
</td>
<td style="{{ $celda }}">{{ $equipo['cliente'] ?? '—' }}</td>
<td style="{{ $celda }}">{{ $equipo['area'] }}</td>
<td style="{{ $celda }}">{{ $equipo['tipo_servicio'] }}</td>
</tr>
@endforeach
</table>

<p>Atentamente,<br>{{ $empresa }}</p>

{{-- Seguimiento: el código para consultar el estado de los equipos sin contraseña --}}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; margin: 24px 0 8px; border: 1px solid #e5e5e5; border-top: 2px solid {{ $acento }}; background: #f8fafc;">
<tr>
<td style="padding: 22px 20px; text-align: center;">
<p style="margin: 0 0 6px; font-size: 12px; font-weight: bold; letter-spacing: 1px; color: {{ $acento }};">SEGUIMIENTO DE EQUIPOS</p>
<p style="margin: 0 0 14px; font-size: 13px; color: #404040;">Consulte el estado técnico de sus equipos sin necesidad de contraseñas.</p>
<p style="margin: 0 0 16px; font-size: 13px; color: #404040;">
Código:
<span style="display: inline-block; margin-left: 6px; padding: 4px 12px; border: 1px solid #bbf7d0; border-radius: 4px; background: #f0fdf4; font-family: 'Courier New', monospace; font-size: 14px; font-weight: bold; letter-spacing: 1px; color: {{ $acento }};">{{ $codigo }}</span>
</p>
<a href="{{ route('seguimiento', ['codigo' => $codigo]) }}" target="_blank" rel="noopener" style="display: inline-block; padding: 10px 22px; border-radius: 20px; background: {{ $acento }}; color: #ffffff; font-size: 13px; font-weight: bold; text-decoration: none;">Consultar estado de equipos →</a>
</td>
</tr>
</table>

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $empresa }}.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
