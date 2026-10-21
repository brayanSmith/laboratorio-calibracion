<?php

namespace App\Enums;

enum TipoDocumentoCliente: string
{
    case Dni = 'DNI';
    case CodigoSap = 'SAP';
    case CarnetExtranjeria = 'CE';

    /**
     * Get the label shown in the selectors.
     */
    public function label(): string
    {
        return match ($this) {
            self::Dni => 'DNI',
            self::CodigoSap => 'Código SAP',
            self::CarnetExtranjeria => 'Carnet de extranjería',
        };
    }
}
