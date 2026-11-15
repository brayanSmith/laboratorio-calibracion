<?php

namespace App\Modules\Recordatorios\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recordatorios\Http\Requests\StoreRecordatorioPlantillaRequest;
use App\Modules\Recordatorios\Http\Requests\UpdatePlantillaPredeterminadaRequest;
use App\Modules\Recordatorios\Http\Requests\UpdateRecordatorioPlantillaRequest;
use App\Modules\Recordatorios\Models\Recordatorio;
use App\Modules\Recordatorios\Models\RecordatorioPlantilla;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RecordatorioPlantillaController extends Controller
{
    /**
     * Create a plantilla for a correo of the tenant (ej. "Navidad"), starting from the
     * texts of its predeterminada, so it only has to be adjusted.
     */
    public function store(StoreRecordatorioPlantillaRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $tipo = $request->validated('tipo');

        RecordatorioPlantilla::query()->create([
            ...$this->recordatorioDelTenant($tenantId)->plantillaPredeterminada($tipo),
            'tipo' => $tipo,
            'nombre' => $request->validated('nombre'),
            'tenant_id' => $tenantId,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plantilla creada. Ajusta sus textos y guárdala.')]);

        return back();
    }

    /**
     * Save the name and texts of a plantilla.
     */
    public function update(UpdateRecordatorioPlantillaRequest $request, RecordatorioPlantilla $plantilla): RedirectResponse
    {
        $plantilla->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plantilla guardada.')]);

        return back();
    }

    /**
     * Delete a plantilla. If it was in use, the correo goes back to the predeterminada.
     */
    public function destroy(RecordatorioPlantilla $plantilla): RedirectResponse
    {
        Gate::authorize('delete', $plantilla);

        $plantilla->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plantilla eliminada.')]);

        return back();
    }

    /**
     * Save the texts of the predeterminada plantilla of a correo.
     *
     * @param  'cliente'|'bahia'  $tipo
     */
    public function actualizarPredeterminada(UpdatePlantillaPredeterminadaRequest $request, string $tipo): RedirectResponse
    {
        Recordatorio::query()->updateOrCreate(
            ['tenant_id' => $request->user()->tenant_id],
            $request->datosPlantilla($tipo),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plantilla guardada.')]);

        return back();
    }

    /**
     * Choose the plantilla the correo is sent with; without plantilla_id, the predeterminada.
     *
     * @param  'cliente'|'bahia'  $tipo
     */
    public function usar(Request $request, string $tipo): RedirectResponse
    {
        Gate::authorize('update', Recordatorio::class);

        $tenantId = $request->user()->tenant_id;

        $datos = $request->validate([
            'plantilla_id' => [
                'nullable', 'integer',
                Rule::exists('recordatorio_plantillas', 'id')->where('tenant_id', $tenantId)->where('tipo', $tipo),
            ],
        ], attributes: ['plantilla_id' => 'plantilla']);

        Recordatorio::query()->updateOrCreate(
            ['tenant_id' => $tenantId],
            ["plantilla_{$tipo}_id" => $datos['plantilla_id'] ?? null],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Desde ahora el correo se envía con esta plantilla.')]);

        return back();
    }

    /**
     * The saved configuration of the tenant, or a new one with the defaults.
     */
    private function recordatorioDelTenant(int $tenantId): Recordatorio
    {
        return Recordatorio::query()->firstOrNew(['tenant_id' => $tenantId]);
    }
}
