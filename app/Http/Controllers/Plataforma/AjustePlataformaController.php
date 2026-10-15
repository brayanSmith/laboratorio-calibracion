<?php

namespace App\Http\Controllers\Plataforma;

use App\Http\Controllers\Controller;
use App\Http\Requests\Plataforma\UpdateAjustePlataformaRequest;
use App\Models\AjustePlataforma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AjustePlataformaController extends Controller
{
    /**
     * Display the public identity (name and logo) of the platform.
     */
    public function edit(): Response
    {
        $ajuste = AjustePlataforma::actual();

        return Inertia::render('plataforma/ajustes', [
            'ajuste' => [
                'nombre' => $ajuste->nombre,
                'logo_url' => $ajuste->logoUrl(),
            ],
        ]);
    }

    /**
     * Update the public identity of the platform.
     */
    public function update(UpdateAjustePlataformaRequest $request): RedirectResponse
    {
        $ajuste = AjustePlataforma::actual();

        $data = ['nombre' => $request->validated('nombre') ?: null];

        if ($request->hasFile('logo')) {
            $this->deleteLogo($ajuste);
            $data['logo'] = $request->file('logo')->store('plataforma', 'public');
        } elseif ($request->boolean('eliminar_logo')) {
            $this->deleteLogo($ajuste);
            $data['logo'] = null;
        }

        $ajuste->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ajustes de la plataforma actualizados.')]);

        return to_route('plataforma.ajustes.edit');
    }

    /**
     * Delete the logo file from storage, if there is one.
     */
    private function deleteLogo(AjustePlataforma $ajuste): void
    {
        if ($ajuste->logo) {
            Storage::disk('public')->delete($ajuste->logo);
        }
    }
}
