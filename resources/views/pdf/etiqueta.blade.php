@php
    // Color del servicio (verde calibración, azul mantenimiento) en el borde y la franja del
    // título, con letra blanca; el resto de la etiqueta va sobre blanco.
    $acento = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($color ?? '')) ? $color : '#171717';
    $sobreAcento = '#ffffff';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Etiqueta {{ $codigoEquipo }}</title>
    <style>
        /* Todo en pt: la hoja mide 226.77 x 141.73 pt (80 x 50 mm). */
        @page { margin: 4pt; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 6.5pt; color: #171717; margin: 0; }
        .marco { border: 1pt solid {{ $acento }}; border-radius: 3pt; height: 131pt; overflow: hidden; position: relative; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 0; vertical-align: middle; }
        .logo { height: 20pt; text-align: center; padding: 2pt 4pt 1pt 4pt; }
        .titulo { background: {{ $acento }}; color: {{ $sobreAcento }}; text-align: center; font-weight: bold; font-size: 10pt; letter-spacing: 1.2pt; padding: 1.5pt 0; }
        .cuerpo { margin-top: 3pt; }
        .dato td { padding: 0.9pt 4pt 0.9pt 3pt; }
        .rotulo { color: #525252; white-space: nowrap; }
        .valor { font-weight: bold; }
        .qr { text-align: center; width: 62pt; padding-left: 4pt; }
        .pie { position: absolute; bottom: 2pt; left: 0; right: 0; text-align: center; font-size: 5.5pt; font-weight: bold; color: #404040; }
    </style>
</head>
<body>
    <div class="marco">
        <div class="logo">
            @if ($logo)
                <img src="{{ $logo }}" style="max-height: 20pt; max-width: 160pt;">
            @else
                <strong>{{ $empresa?->nombre }}</strong>
            @endif
        </div>
        <div class="titulo">{{ $titulo }}</div>
        <table class="cuerpo">
            <tr>
                {{-- QR a la izquierda y los datos a la derecha, como la etiqueta de control impresa --}}
                <td class="qr"><img src="{{ $qr }}" style="width: 58pt; height: 58pt;"></td>
                <td>
                    <table class="dato">
                        @foreach ($datos as $rotulo => $valor)
                            <tr><td class="rotulo">{{ $rotulo }}</td><td class="valor">: {{ $valor }}</td></tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        </table>
        @if ($contacto !== [])
            <div class="pie">{{ implode(' · ', $contacto) }}</div>
        @endif
    </div>
</body>
</html>
