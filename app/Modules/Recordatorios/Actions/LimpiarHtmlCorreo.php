<?php

namespace App\Modules\Recordatorios\Actions;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class LimpiarHtmlCorreo
{
    /**
     * Deja solo el HTML que produce el editor del mensaje (párrafos, títulos, listas,
     * negrita, enlaces, imágenes...) y quita scripts, eventos, iframes y cualquier
     * estilo que no sea la alineación del texto.
     */
    public function handle(string $html): string
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowMediaSchemes(['https', 'http'])
            ->allowAttribute('style', ['p', 'h1', 'h2', 'h3'])
            ->forceAttribute('a', 'target', '_blank')
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(100_000);

        $limpio = (new HtmlSanitizer($config))->sanitize($html);

        return preg_replace_callback(
            '/\sstyle="([^"]*)"/i',
            function (array $coincidencia): string {
                return preg_match('/text-align:\s*(left|center|right|justify)/i', html_entity_decode($coincidencia[1]), $alineacion)
                    ? ' style="text-align: '.strtolower($alineacion[1]).'"'
                    : '';
            },
            $limpio,
        );
    }

    /**
     * Si el HTML no tiene contenido visible (ni texto ni imágenes), como el "<p></p>"
     * que deja el editor vacío.
     */
    public function estaVacio(string $html): bool
    {
        return trim(html_entity_decode(strip_tags($html)), " \t\n\r\0\x0B\u{A0}") === ''
            && ! str_contains(strtolower($html), '<img');
    }
}
