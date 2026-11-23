@php
    $etiqueta = 'padding: 7px 10px; border-bottom: 1px solid #f0f0f0; width: 38%; font-size: 12px; color: #737373; text-align: left; vertical-align: top;';
    $valor = 'padding: 7px 10px; border-bottom: 1px solid #f0f0f0; font-size: 13px; color: #171717; text-align: left; vertical-align: top;';
@endphp
<x-mail::layout>
{{-- Encabezado: logo de la empresa (o su nombre) y el tipo de aviso --}}
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
<td style="text-align: right; vertical-align: middle; font-size: 11px; font-weight: bold; letter-spacing: 1px; color: #b91c1c;">EQUIPO INHABILITADO</td>
</tr>
</table>
</td>
</tr>
</x-slot:header>

<p>Estimados, {{ $cliente }}:</p>

<p>Les informamos que el equipo <strong>{{ $equipo['codigo'] }} · {{ $equipo['tipo_equipo'] }}</strong> quedó <strong style="color: #b91c1c;">inhabilitado (fuera de servicio)</strong> el {{ $fecha }}.</p>

{{-- Motivo destacado --}}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; margin: 8px 0 18px; border: 1px solid #fecaca; border-left: 4px solid #b91c1c; background: #fef2f2;">
<tr>
<td style="padding: 14px 16px;">
<p style="margin: 0 0 4px; font-size: 11px; font-weight: bold; letter-spacing: 1px; color: #b91c1c;">MOTIVO</p>
<p style="margin: 0; font-size: 14px; color: #171717;">{{ $motivo }}</p>
@if ($detalle)
<p style="margin: 10px 0 0; font-size: 13px; color: #404040; white-space: pre-line;">{{ $detalle }}</p>
@endif
</td>
</tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; border-collapse: collapse; margin: 0 0 20px; border: 1px solid #e5e5e5;">
<tr><td style="{{ $etiqueta }}">Código</td><td style="{{ $valor }}"><strong>{{ $equipo['codigo'] }}</strong></td></tr>
<tr><td style="{{ $etiqueta }}">Tipo de equipo</td><td style="{{ $valor }}">{{ $equipo['tipo_equipo'] }}</td></tr>
<tr><td style="{{ $etiqueta }}">Modelo</td><td style="{{ $valor }}">{{ $equipo['modelo'] ?: '—' }}</td></tr>
<tr><td style="{{ $etiqueta }}">Número de serie</td><td style="{{ $valor }}">{{ $equipo['numero_serie'] ?: '—' }}</td></tr>
<tr><td style="{{ $etiqueta }}">Área</td><td style="{{ $valor }}">{{ $equipo['area'] ?: '—' }}</td></tr>
<tr><td style="{{ $etiqueta }}">Bahía</td><td style="{{ $valor }}">{{ $equipo['bahia'] ?: '—' }}</td></tr>
@if ($registradoPor)
<tr><td style="{{ $etiqueta }} border-bottom: 0;">Registrado por</td><td style="{{ $valor }} border-bottom: 0;">{{ $registradoPor }}</td></tr>
@endif
</table>

<p>Mientras esté inhabilitado, el equipo no debe usarse. Si tienen alguna duda, por favor comuníquense con nosotros.</p>

<p>Atentamente,<br>{{ $empresa }}</p>

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $empresa }}.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
