<x-mail::message>
# Hola, {{ $destinatario }}

Te enviamos el resumen de {{ $porBahia ? 'los equipos de tus bahías' : 'tus equipos' }} que requieren atención: los vencidos y los que vencen en los próximos {{ $diasAnticipacion }} días.

<x-mail::table>
| Estado | Equipos |
| :--- | ---: |
| Vencidos | {{ $vencidos }} |
| Próximos a vencer | {{ $proximos }} |
| Agendados | {{ $agendados }} |
</x-mail::table>

Encontrarás el detalle de cada equipo en el PDF adjunto.

Saludos,<br>
{{ config('app.name') }}
</x-mail::message>
