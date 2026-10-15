<?php

namespace App\Concerns;

use App\Models\Area;
use App\Models\Bahia;
use App\Models\Cliente;
use App\Models\DocumentoEquipo;
use App\Models\Equipo;
use App\Models\Fabricante;
use App\Models\TipoEquipo;
use App\Models\TipoMagnitud;
use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

trait ListaEquipos
{
    /**
     * Todos los equipos del tenant y las opciones de sus formularios. La tabla los filtra,
     * ordena y pagina en el navegador, así que el servidor no vuelve a intervenir al
     * usarla. La comparten el listado de equipos y el dashboard.
     *
     * @return array<string, mixed>
     */
    protected function datosListadoEquipos(Request $request, bool $incluirEquipos = true): array
    {
        $tenantId = $request->user()->tenant_id;

        return [
            ...($incluirEquipos ? ['equipos' => Equipo::query()
                ->where('tenant_id', $tenantId)
                ->with($this->relacionesListadoEquipos())
                ->latest()
                ->get()
                ->map(fn (Equipo $equipo) => $this->conDocumentos($equipo))
                ->all()] : []),
            'options' => $this->formOptions($tenantId),
        ];
    }

    /**
     * Relaciones que necesita la tabla de equipos y el detalle de un equipo.
     *
     * @return array<int|string, mixed>
     */
    protected function relacionesListadoEquipos(): array
    {
        return [
            'tipoEquipo:id,nombre',
            'fabricante:id,nombre',
            'area:id,nombre',
            'bahia:id,nombre',
            'cliente:id,nombre',
            'equipoEspecificacionTecnica',
            'equipoDocumentos' => fn ($query) => $query->latest(),
            'equipoProgramaciones' => fn ($query) => $query->latest()->with([
                'seguimientos' => fn ($seguimientos) => $seguimientos->with('usuario:id,name')->latest('id'),
            ]),
        ];
    }

    /**
     * Add the equipo's documents (with their public URL) to its array representation.
     *
     * @return array<string, mixed>
     */
    protected function conDocumentos(Equipo $equipo): array
    {
        $documentos = $equipo
            ->loadMissing(['equipoDocumentos' => fn ($query) => $query->latest()])
            ->equipoDocumentos
            ->map(fn (DocumentoEquipo $documento) => [
                'id' => $documento->id,
                'nombre' => $documento->nombre,
                'archivo_url' => $documento->archivoUrl(),
            ]);

        return [
            ...$equipo->toArray(),
            'equipo_documentos' => $documentos,
        ];
    }

    /**
     * Get the select options for the equipo form, scoped to the given tenant.
     *
     * @return array{
     *     tipoEquipos: Collection<int, TipoEquipo>,
     *     fabricantes: Collection<int, Fabricante>,
     *     areas: Collection<int, Area>,
     *     bahias: Collection<int, Bahia>,
     *     clientes: Collection<int, Cliente>,
     *     tiposMagnitud: Collection<int, TipoMagnitud>,
     *     unidadesMedida: Collection<int, UnidadMedida>,
     * }
     */
    private function formOptions(int $tenantId): array
    {
        return [
            'tipoEquipos' => TipoEquipo::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre', 'tipo_mantenimiento']),
            'fabricantes' => Fabricante::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'areas' => Area::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'bahias' => Bahia::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre', 'area_id']),
            'clientes' => Cliente::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'tiposMagnitud' => TipoMagnitud::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'unidadesMedida' => UnidadMedida::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre', 'simbolo']),
        ];
    }
}
