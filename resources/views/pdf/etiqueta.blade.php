@php
    // Mismo acento que la plataforma: el color de tema de la empresa (o el negro por defecto).
    $acento = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($empresa?->color_tema)) ? $empresa->color_tema : '#171717';
    [$r, $g, $b] = sscanf($acento, '#%02x%02x%02x');
    $sobreAcento = (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#171717' : '#ffffff';
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
        .marco { border: 1pt solid {{ $acento }}; border-radius: 3pt; height: 131pt; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 0; vertical-align: middle; }
        .logo { height: 26pt; text-align: center; padding: 3pt 4pt 2pt 4pt; }
        .titulo { background: {{ $acento }}; color: {{ $sobreAcento }}; text-align: center; font-weight: bold; font-size: 11pt; letter-spacing: 1.5pt; padding: 2pt 0; }
        .cuerpo { margin-top: 5pt; }
        .dato td { padding: 2pt 4pt; }
        .rotulo { color: #525252; white-space: nowrap; }
        .valor { font-weight: bold; text-align: right; }
        .qr { text-align: center; width: 78pt; padding-right: 4pt; }
    </style>
</head>
<body>
    <div class="marco">
        <div class="logo">
            @if ($logo)
                <img src="{{ $logo }}" style="max-height: 26pt; max-width: 160pt;">
            @else
                <strong>{{ $empresa?->nombre }}</strong>
            @endif
        </div>
        <div class="titulo">{{ $titulo }}</div>
        <table class="cuerpo">
            <tr>
                <td>
                    <table class="dato">
                        <tr><td class="rotulo">Fecha {{ $servicio }}:</td><td class="valor">{{ $fechaServicio }}</td></tr>
                        <tr><td class="rotulo">Próx. {{ $servicio }}:</td><td class="valor">{{ $fechaProximoServicio }}</td></tr>
                        <tr><td class="rotulo">ID:</td><td class="valor">{{ $codigoEquipo }}</td></tr>
                        <tr><td class="rotulo">Bahía:</td><td class="valor">{{ $bahia }}</td></tr>
                    </table>
                </td>
                <td class="qr"><img src="{{ $qr }}" style="width: 72pt; height: 72pt;"></td>
            </tr>
        </table>
    </div>
</body>
</html>
