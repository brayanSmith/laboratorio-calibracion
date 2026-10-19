<?php

namespace App\Modules\Importaciones\Support;

use OpenSpout\Reader\CSV\Options as CSVOptions;
use Spatie\SimpleExcel\SimpleExcelReader;

/**
 * SimpleExcelReader que también conserva las filas vacías en los CSV: la librería solo lo
 * permite en XLSX, y sin ellas los números de fila de los errores no coinciden con Excel.
 */
class LectorSimpleExcel extends SimpleExcelReader
{
    public function conservarFilasVaciasCsv(): self
    {
        $this->csvOptions = new CSVOptions(
            SHOULD_PRESERVE_EMPTY_ROWS: true,
            FIELD_DELIMITER: $this->csvOptions->FIELD_DELIMITER,
            FIELD_ENCLOSURE: $this->csvOptions->FIELD_ENCLOSURE,
            ENCODING: $this->csvOptions->ENCODING,
        );

        return $this;
    }
}
