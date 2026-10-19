<?php

namespace App\Enums;

enum TipoDocumentoCliente: string
{
    case Nit = 'NIT';
    case Cc = 'CC';
    case Ce = 'CE';
    case Pasaporte = 'PASAPORTE';

    /**
     * Get the label shown in the selectors.
     */
    public function label(): string
    {
        return match ($this) {
            self::Nit => 'NIT',
            self::Cc => 'Cédula de ciudadanía',
            self::Ce => 'Cédula de extranjería',
            self::Pasaporte => 'Pasaporte',
        };
    }
}
