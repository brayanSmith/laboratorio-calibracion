<x-mail::layout>
{{-- Encabezado: logo de la empresa (o su nombre) y el tipo de mensaje --}}
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
<td style="text-align: right; vertical-align: middle; font-size: 11px; font-weight: bold; letter-spacing: 1px; color: #737373;">PORTAL DE CLIENTES</td>
</tr>
</table>
</td>
</tr>
</x-slot:header>

<p>Estimados, {{ $cliente->nombre }}:</p>

<p>Recibimos su solicitud para crear una cuenta en el portal de clientes, donde puede consultar sus equipos, el historial de sus servicios y descargar sus certificados e informes.</p>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; margin: 24px 0 8px; border: 1px solid #e5e5e5; border-top: 2px solid {{ $acento }}; background: #f8fafc;">
<tr>
<td style="padding: 22px 20px; text-align: center;">
<p style="margin: 0 0 16px; font-size: 13px; color: #404040;">Para elegir su usuario y su contraseña, use este enlace:</p>
<a href="{{ $enlace }}" target="_blank" rel="noopener" style="display: inline-block; padding: 10px 22px; border-radius: 20px; background: {{ $acento }}; color: #ffffff; font-size: 13px; font-weight: bold; text-decoration: none;">Crear mi cuenta →</a>
<p style="margin: 16px 0 0; font-size: 11px; color: #737373;">El enlace vence en {{ $minutos }} minutos. Si no solicitó este correo, puede ignorarlo.</p>
</td>
</tr>
</table>

<p>Atentamente,<br>{{ $empresa }}</p>

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $empresa }}.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
