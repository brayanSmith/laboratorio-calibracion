<x-mail::layout>
{{-- Encabezado con el logo de la empresa (incrustado) o, si no tiene, su nombre --}}
<x-slot:header>
<tr>
<td class="header">
@if ($logo)
<img src="{{ $message->embed($logo) }}" alt="{{ $empresa }}" style="max-height: 75px; max-width: 220px; width: auto; height: auto; border: none;">
@else
{{ $empresa }}
@endif
</td>
</tr>
</x-slot:header>

@foreach ($bloques as $bloque)
@if ($bloque === null)
<x-mail::table>
| Estado | Equipos |
| :--- | ---: |
| Vencidos | {{ $vencidos }} |
| Próximos a vencer | {{ $proximos }} |
| Agendados | {{ $agendados }} |
</x-mail::table>
@else
{!! $bloque !!}
@endif

@endforeach

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $empresa }}.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
