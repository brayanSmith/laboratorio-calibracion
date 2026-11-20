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
<td style="text-align: right; vertical-align: middle; font-size: 11px; font-weight: bold; letter-spacing: 1px; color: #737373;">SEGUIMIENTO / BAHÍAS</td>
</tr>
</table>
</td>
</tr>
</x-slot:header>

<p>Estimados, {{ $cliente }}:</p>

<p>Recibimos su solicitud para consultar el estado de sus equipos. Con el siguiente enlace puede ver, sin códigos ni contraseñas, los ingresos y equipos de las bahías que tiene asignadas:</p>

<ul style="margin: 0 0 16px; padding-left: 20px; font-size: 13px; color: #171717;">
@foreach ($bahias as $bahia)
<li>{{ $bahia }}</li>
@endforeach
</ul>

<p>Atentamente,<br>{{ $empresa }}</p>

{{-- El enlace firmado al seguimiento por bahía --}}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; margin: 24px 0 8px; border: 1px solid #e5e5e5; border-top: 2px solid {{ $acento }}; background: #f8fafc;">
<tr>
<td style="padding: 22px 20px; text-align: center;">
<p style="margin: 0 0 6px; font-size: 12px; font-weight: bold; letter-spacing: 1px; color: {{ $acento }};">SEGUIMIENTO POR BAHÍA</p>
<p style="margin: 0 0 16px; font-size: 13px; color: #404040;">Consulte el estado técnico de los equipos de todas sus bahías en un solo lugar.</p>
<a href="{{ $enlace }}" target="_blank" rel="noopener" style="display: inline-block; padding: 10px 22px; border-radius: 20px; background: {{ $acento }}; color: #ffffff; font-size: 13px; font-weight: bold; text-decoration: none;">Ver ingresos por bahía →</a>
<p style="margin: 16px 0 0; font-size: 11px; color: #737373;">El enlace es personal y vence en {{ $diasVigencia }} días. Si no lo solicitó, puede ignorar este correo.</p>
</td>
</tr>
</table>

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $empresa }}.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
