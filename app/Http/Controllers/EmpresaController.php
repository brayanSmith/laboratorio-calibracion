<?php

namespace App\Http\Controllers;

use App\Http\Requests\Empresa\StoreEmpresaRequest;
use App\Http\Requests\Empresa\UpdateEmpresaRequest;
use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class EmpresaController extends Controller
{
    /**
     * Display the empresa of the tenant, or the option to register it.
     */
    public function show(Request $request): Response
    {
        Gate::authorize('viewAny', Empresa::class);

        $empresa = $this->empresaDelTenant($request);

        return Inertia::render('empresa/show', [
            'empresa' => $empresa ? [
                'id' => $empresa->id,
                'nit' => $empresa->nit,
                'nombre' => $empresa->nombre,
                'direccion' => $empresa->direccion,
                'telefono' => $empresa->telefono,
                'logo_url' => $empresa->logoUrl(),
            ] : null,
        ]);
    }

    /**
     * Register the empresa of the tenant. A tenant can only have one.
     */
    public function store(StoreEmpresaRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $empresa = Empresa::withTrashed()->where('tenant_id', $tenantId)->first();

        if ($empresa && ! $empresa->trashed()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Tu laboratorio ya tiene una empresa registrada. Edítala en lugar de crear otra.'),
            ]);

            return to_route('empresa.show');
        }

        $data = [
            ...$request->safe()->only(['nit', 'nombre', 'direccion', 'telefono']),
            'logo' => $request->file('logo')?->store("logos/{$tenantId}", 'public') ?: null,
        ];

        if ($empresa) {
            $empresa->restore();
            $empresa->update($data);
        } else {
            Empresa::create([...$data, 'tenant_id' => $tenantId]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Empresa registrada.')]);

        return to_route('empresa.show');
    }

    /**
     * Update the empresa of the tenant.
     */
    public function update(UpdateEmpresaRequest $request): RedirectResponse
    {
        $empresa = Empresa::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->firstOrFail();

        $data = $request->safe()->only(['nit', 'nombre', 'direccion', 'telefono']);

        if ($request->hasFile('logo')) {
            $this->deleteLogo($empresa);
            $data['logo'] = $request->file('logo')->store("logos/{$empresa->tenant_id}", 'public');
        } elseif ($request->boolean('eliminar_logo')) {
            $this->deleteLogo($empresa);
            $data['logo'] = null;
        }

        $empresa->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Empresa actualizada.')]);

        return to_route('empresa.show');
    }

    /**
     * Remove the empresa of the tenant so a new one can be registered.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $empresa = Empresa::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->firstOrFail();

        Gate::authorize('delete', $empresa);

        $this->deleteLogo($empresa);
        $empresa->update(['logo' => null]);
        $empresa->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Empresa eliminada.')]);

        return to_route('empresa.show');
    }

    /**
     * Get the empresa registered by the tenant of the current user.
     */
    private function empresaDelTenant(Request $request): ?Empresa
    {
        return Empresa::query()->where('tenant_id', $request->user()->tenant_id)->first();
    }

    /**
     * Delete the logo file of the empresa from storage, if it has one.
     */
    private function deleteLogo(Empresa $empresa): void
    {
        if ($empresa->logo) {
            Storage::disk('public')->delete($empresa->logo);
        }
    }
}
