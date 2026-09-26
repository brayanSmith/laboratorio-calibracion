<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TipoEquipo;
use App\Models\TipoEquipoCheckList;
use Illuminate\Database\Seeder;

class TipoEquipoCheckListSeeder extends Seeder
{
    /**
     * Seed the default check list of every tipo de equipo for every tenant.
     *
     * The tipos de equipo are seeded first. Running it again does not duplicate check list items.
     */
    public function run(): void
    {
        $this->call(TipoEquipoSeeder::class);

        $checklists = $this->checklists();

        Tenant::query()->each(function (Tenant $tenant) use ($checklists): void {
            foreach ($checklists as $nombreTipoEquipo => $actividades) {
                $tipoEquipo = TipoEquipo::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('nombre', $nombreTipoEquipo)
                    ->firstOrFail();

                foreach ($actividades as $actividad) {
                    TipoEquipoCheckList::query()->updateOrCreate(
                        ['tipo_equipo_id' => $tipoEquipo->id, 'nombre' => $actividad],
                        ['tenant_id' => $tenant->id],
                    );
                }
            }
        });
    }

    /**
     * The activities of each tipo de equipo, keyed by its name, in the order they are performed.
     *
     * @return array<string, array<int, string>>
     */
    private function checklists(): array
    {
        return [
            'LIJADORA NEUMÁTICA' => [
                '1. Se realiza prueba de funcionamiento (en vacío) .',
                '2. Se tomar lectura de potencia (RPM) inicial. Resultado:',
                '3. Se inspecciona estado de partes externas (posibles desgastes)',
                '4. Se realiza limpieza con solvente.',
                '5. Se realiza lubricación interna (aceite neumático)',
                '6. Se realiza prueba de funcionamiento.',
                '7. Se toma lectura de potencia (RPM) final. Resultado:',
                '8. Se verifica si hay fugas.',
                '9. Se verifica el estado de disco PAD.',
                '10. Se coloca capucha (si se necesita).',
            ],
            'PISTOLA ELÉCTRICA' => [
                '1. Se inspecciona la carga de batería.',
                '2. Se realiza prueba de funcionamiento.',
                '3. Se desmonta las piezas (desarmado).',
                '4. Se realiza la limpieza de partes.',
                '5. Se inspecciona partes internas (posibles roturas/fisuras /desgaste)',
                '6. Se inspecciona el estado de partes externas (posibles desgastes)',
                '7. Se realiza la lubricación de piezas.',
                '8. Se verifica continuidad de motor y partes eléctricas.',
                '9. Se realiza montaje de piezas (realizar ajuste requerido).',
                '10. Se realiza prueba de funcionamiento (en vacío).',
            ],
            'Pistón Hidraúlico' => [
                '1. Se Inspecciona estado de partes externas (posibles desgastes)',
                '2. Se realiza la limpieza de partes externas',
                '3. Se realiza lubricación del componente.',
                '4. Se realiza ajuste requerido. .',
                '5. Se prueba de funcionamiento en el área.',
            ],
            'TURBINA NEUMÁTICA' => [
                '1. Se realiza prueba de funcionamiento (en vacío)',
                '2. Se toma lectura de potencia (RPM) inicial. Resultado:',
                '3. Se inspecciona estado de partes externas (posibles desgastes)',
                '4. Se realiza limpieza con solvente.',
                '5. Se realiza lubricación interna (aceite neumático)',
                '6. Se realiza prueba de funcionamiento.',
                '7. Se toma lectura de potencia (RPM) final. Resultado:',
                '8. Se verifica si hay fugas.',
                '9. Se coloca capucha (si se necesita)',
            ],
            'PISTOLA NEUMATICA' => [
                '1. Se realiza prueba en banco de torque (anotar valor) Resultado(s) de Medición 001:',
                '2. Se realiza desmontaje de piezas (desarmado)',
                '3. Se realiza limpieza de partes.',
                '4. Se inspecciona partes internas (posibles roturas/fisuras /desgaste)',
                '5. Se inspecciona estado de partes externas (posibles desgastes)',
                '6. Se realiza revisión de fisuras con KIT de inspección PPM.',
                '7. Se realiza lubricación de piezas',
                '8. Se realiza montaje de piezas (realizar ajuste requerido)',
                '9. Se realiza prueba de funcionamiento (en vacío)',
                '10. Se verifica si hay fugas.',
                '11. Se realiza prueba en banco de torque (anotar valor) Resultado(s) de Medición 002:',
                '12. Se coloca capucha (si se necesita)',
            ],
            'PISTOLA DE GIRO' => [
                '1. Se realiza prueba de funcionamiento.',
                '2. Se realiza desmontaje de piezas (desarmado).',
                '3. Se realiza limpieza de partes.',
                '4. Se inspecciona partes internas (posibles roturas/fisuras /desgaste)',
                '5. Se inspecciona estado de partes externas (posibles desgastes)',
                '6. Se realiza lubricación de piezas',
                '7. Se realiza montaje de piezas (realizar ajuste requerido)',
                '8. Se realiza prueba de funcionamiento (en vacío)',
            ],
            'Taladro neumático' => [
                '1. Se realiza prueba de funcionamiento (en vacío).',
                '2. Se verifica potencia (RPM).',
                '3. Se realiza desmontaje de piezas (desarmado)',
                '4. Se realiza limpieza de partes.',
                '5. Se inspecciona partes internas (posibles roturas/fisuras /desgaste)',
                '6. Se inspecciona partes externas (posibles desgastes)',
                '7. Se lubrica las piezas.',
                '8. Se realiza montaje de piezas (realizar ajuste requerido).',
                '9. Se realiza prueba de funcionamiento (en vacío)',
                '10. Se verifica si hay fugas.',
                '11. Se verifica potencia (RPM).',
                '12. Se Coloca capucha (si se necesita)',
            ],
            'Torque Hidráulico' => [
                '1. Se realiza prueba de funcionamiento (en vacío).',
                '2. Se inspecciona estado de partes externas (posibles desgastes)',
                '3. Se realiza el desmontaje de los componentes internos (desarmado).',
                '4. Se realiza la limpieza de partes internas y externas.',
                '5. Se Inspecciona partes internas (posibles roturas/fisuras /desgaste)',
                '6. Se revisa las fisuras con KIT de inspección PPM encastre (si es necesario)',
                '7. Se realiza lubricación de los componentes externos e internos.',
                '8. Se realiza montaje de piezas (realizar ajuste requerido)',
                '9. Se prueba de funcionamiento en el área.',
                '10. Se envia a calibración en proveedor. (mtto anual)',
            ],
            'Cadena de Izaje' => [
                '1. Se realiza lavado de cadena de izaje.',
                '2. Se realiza secado de cadena de izaje.',
                '3. Se inspecciona visualmente los tensores.',
                '4. Se realiza medición de eslabones.',
                '5. Se realiza revisión de ganchos.',
                '6. Se envia a magnaflux.',
                '7. Se realiza revisión de placa de identificación.',
            ],
            'Alexometro Analógico' => [
                '1. Se realiza la limpieza e inspección de sus mecanismos internos y externos.',
                '2. Se realiza la verificación del correcto funcionamiento del reloj.',
                '3. Se realiza la verificación de las puntas de medición y anillos.',
                '4. Se realiza la lubricación de las piezas móviles.',
                '5. Se realiza el armado de las partes.',
                '6. Se realiza las pruebas del funcionamiento en todo su alcance.',
                '7. Se realiza la verificación del valor cero.',
            ],
            'Calibrador Pie de Rey Digital' => [
                '1. Se realiza la limpieza e inspección de sus mecanismos externos.',
                '2. Se verifica las cuchillas de medición de exteriores, interiores y de profundidad.',
                '3. Se verifica el estado de los dispositivos electrónicos.',
                '4. Se verifica el estado de las pilas y se cambia las pilas en mal estado.',
                '5. Se realiza la lubricación de las piezas móviles.',
                '6. Se realiza las pruebas del correcto desplazamiento del nonio y ajuste del mismo.',
                '7. Se verifica el ajuste en cero.',
                '8. Se envía al LDC/Proveedor para su calibración.',
            ],
            'Kit de Termocuplas' => [
                '1. Se realiza la inspección visual de las termocuplas.',
                '2. Se realiza la limpieza e inspección de los accesorios.',
                '3. Se verifica los contactos eléctricos.',
                '4. Se envía al LDC/Proveedor para su calibración.',
            ],
            'Manómetro analógico' => [
                '1. Se realiza la limpieza e inspección de sus mecanismos externos.',
                '2. Se realiza la limpieza e inspección del dispositivo de entrada de fluido.',
                '3. Se verifica el nivel y estado de la glicerina.',
                '4. Se realiza la prueba de funcionamiento.',
                '5. Se realiza la limpieza del bourdon.',
                '6. Se verifica la posición de la aguja en el valor cero.',
                '7. Se envía al:',
            ],
            'Manómetro Digital' => [
                '1. Se realiza la limpieza e inspección de sus mecanismos externos.',
                '2. Se realiza la limpieza e inspección del dispositivo de entrada de fluido.',
                '3. Se verifica el estado de sus dispositivos electrónicos.',
                '4. Se verifica el estado de las pilas y se reemplaza las pilas en mal estado.',
                '5. Se realiza las pruebas de funcionamiento.',
                '6. Se realiza la limpieza del bourdon.',
                '7. Se verifica el ajuste en cero.',
                '8. Se envía al LDC/Proveedor para su calibración.',
            ],
            'Micrómetro Exterior Analógicos' => [
                '1. Se realiza el desarmado del nonio.',
                '2. Se realiza la limpieza e inspección de sus mecanismos.',
                '3. Se verifica el estado de la pila y reemplazo del mismo.',
                '4. Se verifica el estado de las caras de medición.',
                '5. Se realiza la lubricación de las piezas móviles.',
                '6. Se verifica la limpieza e inspección de accesorios.',
                '7. Se verifica el correcto desplazamiento del tornillo micrométrico.',
                '8. Se realiza el armado de las piezas.',
                '9. Se realiza las pruebas de funcionamiento del nonio y el ajuste de cero.',
                '10. Se envía al:',
            ],
            'Micrómetro Exterior Digital' => [
                '1. Se realiza el desarmado del nonio.',
                '2. Se realiza la limpieza e inspección de sus mecanismos.',
                '3. Se verifica los dispositivos electrónicos.',
                '4. Se verifica el estado de la pila y reemplazo del mismo.',
                '5. Se verifica el estado de las caras de medición.',
                '6. Se realiza la lubricación de las piezas móviles.',
                '7. Se verifica la limpieza e inspección de accesorios.',
                '8. Se verifica el correcto desplazamiento del tornillo micrométrico.',
                '9. Se realiza el armado de las piezas.',
                '10. Se realiza las pruebas de funcionamiento del nonio y el ajuste de cero.',
                '11. Se envía al:',
            ],
            'Micrómetro Interior Digital' => [
                '1. Se realiza el desarmado del nonio.',
                '2. Se realiza la limpieza e inspección de sus mecanismos.',
                '3. Se verifica los dispositivos electrónicos.',
                '4. Se verifica el estado de la pila y reemplazo del mismo.',
                '5. Se verifica el estado de las caras de medición.',
                '6. Se realiza la lubricación de las piezas móviles.',
                '7. Se verifica la limpieza e inspección de las barras patrones, topes fijos y las extensiones.',
                '8. Se verifica el correcto desplazamiento del tornillo micrométrico.',
                '9. Se realiza el armado de las piezas.',
                '10. Se realiza las pruebas de funcionamiento del nonio y el ajuste de cero.',
                '11. Se envía al:',
            ],
            'Pistola de Temperatura' => [
                '1. Se realiza una inspección visual del instrumento.',
                '2. Se realiza la limpieza e inspección del lente de medición',
                '3. Limpieza general del instrumento y accesorios.',
                '4. Se realiza la verificación de sus dispositivos electrónicos.',
                '5. Se realiza la verificación de las pilas y reemplazo del mismo.',
                '6. Se realiza las pruebas de funcionamiento.',
                '7. Se envía al LDC/Proveedor para su calibración.',
            ],
            'Reloj Comparador Analógico' => [
                '1. Se realiza el desarmado de las partes.',
                '2. Se realiza la limpieza e inspección de sus mecanismos internos y externos.',
                '3. Se verifica el estado del puntero.',
                '4. Se realiza la lubricación de las piezas móviles.',
                '5. Se realiza el armado de las piezas.',
                '6. Se verifica el correcto desplazamiento del husillo.',
                '7. Se realiza las pruebas de funcionamiento y pruebas de repetibilidad en el valor cero.',
                '8. Se realiza el suministro del protector del husillo.',
                '9. Se envía al:',
            ],
            'Reloj Comparador Digital' => [
                '1. Se realiza la limpieza e inspección de sus mecanismos internos y externos.',
                '2. Se realiza el desarmado de las partes.',
                '3. Se verifica los dispositivos electrónicos.',
                '4. Se verifica el estado del puntero.',
                '5. Se verifica el estado de la pila y el reemplazo del mismo.',
                '6. Se realiza la lubricación de las piezas móviles.',
                '7. Se realiza el armado de las piezas.',
                '8. Se verifica el correcto desplazamiento del husillo.',
                '9. Se realiza las pruebas de funcionamiento y pruebas de repetibilidad en el valor cero.',
                '10. Se realiza el suministro del protector del husillo.',
                '11. Se envía al:',
            ],
            'Reloj Comparador Palpador' => [
                '1. Se realiza inspección visual.',
                '2. Se realiza la limpieza e inspección de sus mecanismos externos.',
                '3. Se verifica el estado del puntero palpador.',
                '4. Se realiza la lubricación de las piezas móviles.',
                '5. Se verifica el correcto desplazamiento del palpador horario y antihorario.',
                '6. Se realiza las pruebas de funcionamiento y pruebas de repetibilidad en el valor cero.',
                '7. Se envía al:',
            ],
            'Rugosímetro' => [
                '1. Se realiza la limpieza e inspección visual de partes externas',
                '2. Se realiza la limpieza e inspección de accesorios.',
                '3. Se verifica el estado de los dispositivos electrónicos',
                '4. Se verifica el estado de las pilas/batería y se reemplaza las pilas/batería en mal estado.',
                '5. Se verifica/ajusta los valores de la indicación según patrón.',
                '6. Se realiza la prueba de funcionamiento.',
                '7. Se envía al LDC/Proveedor para su calibración.',
            ],
            'Sensor de Presión' => [
                '1. Se realiza la inspección visual del instrumento.',
                '2. Se realiza la limpieza e inspección de accesorios.',
                '3. Se realiza la inspección de conectores y cables eléctricos.',
                '4. Se envía al LDC/Proveedor para su calibración.',
            ],
            'Tacometro' => [
                '1. Se realiza una inspección visual del instrumento.',
                '2. Se realiza la limpieza e inspección de sus componentes externos.',
                '3. Se realiza la verificación de sus dispositivos electrónicos.',
                '4. Se realiza la verificación de las pilas y reemplazo del mismo.',
                '5. Se realiza las pruebas de funcionamiento.',
                '6. Se envía al:',
            ],
            'Torquimetro con reloj' => [
                '1. Se realiza limpieza e inspección visual de sus mecanismos externos.',
                '2. Se verifica las posibles fisuras en el encastre.',
                '3. Se realiza las pruebas de funcionamiento en el comprobador de toque al 20%, 60% y al 100% de su alcance.',
                '4. Se envía al:',
            ],
            'Torquimetro de flexión con cabezal' => [
                '1. Se realiza limpieza e inspección visual de sus mecanismos externos.',
                '2. Se realiza el desarmado del cabezal, limpieza e inspección del mismo.',
                '3. Se realiza el engrase del cabezal y armado de las partes.',
                '4. Se realiza el armado del mango regulador de torque.',
                '5. Se realiza las pruebas de funcionamiento en el comprobador de toque al 20%, 60% y al 100% de su alcance.',
                '6. Se envía al:',
            ],
            'Torquímetro Electrónico' => [
                '1. Se realiza limpieza e inspección visual de sus mecanismos externos.',
                '2. Se realiza el desarmado del cabezal, limpieza e inspección del mismo.',
                '3. Se realiza limpieza de tarjeta electrónica.',
                '4. Se verifica estado de batería.',
                '5. Se verifica las posibles fisuras en encastre.',
                '6. Se realiza las pruebas de funcionamiento en el comprobador de toque al 20%, 60% y al 100% de su alcance.',
                '7. Se envía al:',
            ],
            'Torquimetro Tipo Click' => [
                '1. Se realiza limpieza e inspección visual de sus mecanismos externos.',
                '2. Se realiza el desarmado del cabezal, limpieza e inspección del mismo.',
                '3. Se realiza el engrase del cabezal y armado de las partes.',
                '4. Se verifica las posibles fisuras de la base de las vigas con la prueba de ppm.',
                '5. Se realiza las pruebas de funcionamiento en el comprobador de torque.',
                '6. Se envía al:',
            ],
            'Torquimetro de flexión sin cabezal' => [
                '1. Se realiza limpieza e inspección visual de sus mecanismos externos.',
                '2. Se realiza las pruebas de funcionamiento en el comprobador de toque al 20%, 60% y al 100% de su alcance.',
                '3. Se envía al:',
            ],
            'HERRAMIENTA DE PRE-CARGA' => [
                '1. Se verificación de código de relojes comparadores instaladoscolocar códigos:',
                '2. Se desmonta y realiza limpieza general de la herramienta de pre-carga.',
                '3. Se verifica el funcionamiento de relojes comparadores.',
                '4. Se instala equipos de sensores.',
                '5. Se ajusta y realiza el torque de pernos equidistantes de acuerdo a manual.',
                '6. Se realiza el levantamiento de lecturas de transitorceldas de carga',
                '7. Se coloca en cero los relojes digitales en pulgadas.',
                '8. Se verifica suma de lecturas de 04 sensores :',
                '9. Se verifica el porcentaje de EMP de acuerdo al manual del fabricanteanotar valor:',
                '10. Se afloja los pernos de manera equidistante.',
                '11. Se toma lecturas de los reloj comparador N° 1 anotar valor Resultados de Medición :',
                '12. Se toma lecturas de los reloj comparador N° 2 anotar valor Resultados de Medición',
                '13. Se verifica que las lecturas del reloj comprador estén dentro de tolerancia 0,0015 inch:',
                '14. Se coloca etiqueta de identificación.',
            ],
        ];
    }
}
