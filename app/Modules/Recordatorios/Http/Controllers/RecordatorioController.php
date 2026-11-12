<?php

namespace App\Modules\Recordatorios\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Recordatorios\Actions\GenerarPdfRecordatorio;
use App\Modules\Recordatorios\Actions\ObtenerEquiposRecordatorio;
use App\Modules\Recordatorios\Http\Requests\UpdateRecordatorioRequest;
use App\Modules\Recordatorios\Mail\RecordatorioEquipos;
use App\Modules\Recordatorios\Models\Recordatorio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class RecordatorioController extends Controller
{
    /**
     * Display the recordatorio configuration of the tenant (with the defaults if it never
     * saved one), along with how many equipos and recipients the next send would reach.
     */
    public function show(Request $request, ObtenerEquiposRecordatorio $obtenerEquipos): Response
    {
        Gate::authorize('viewAny', Recordatorio::class);

        $tenantId = $request->user()->tenant_id;
        $recordatorio = $this->recordatorioDelTenant($tenantId);
        $equipos = $obtenerEquipos->handle($tenantId, $recordatorio->dias_anticipacion);
        $conteo = $equipos->countBy('estado');

        return Inertia::render('recordatorio/show', [
            'recordatorio' => [
                'activo' => $recordatorio->activo,
                'frecuencia' => $recordatorio->frecuencia,
                'dia_semana' => $recordatorio->dia_semana,
                'dia_mes' => $recordatorio->dia_mes,
                'hora_envio' => $recordatorio->hora_envio,
                'dias_anticipacion' => $recordatorio->dias_anticipacion,
                'enviar_a_clientes' => $recordatorio->enviar_a_clientes,
                'enviar_resumen_bahia' => $recordatorio->enviar_resumen_bahia,
                'correos_copia' => $recordatorio->correos_copia ?? [],
                'ultimo_envio_at' => $recordatorio->ultimo_envio_at?->toIso8601String(),
            ],
            'resumen' => [
                'vencidos' => $conteo->get('VENCIDO', 0),
                'proximos_a_vencer' => $conteo->get('PROXIMO_A_VENCER', 0),
                'agendados' => $conteo->get('AGENDADO', 0),
                'clientes' => $equipos->pluck('cliente_id')->unique()->count(),
                'usuarios_bahia' => User::query()
                    ->where('tenant_id', $tenantId)
                    ->whereHas('bahias', fn ($query) => $query->whereIn('bahias.id', $equipos->pluck('bahia_id')->unique()))
                    ->count(),
            ],
            // Para asignar bahías a los usuarios del resumen por bahía sin ir a Bahías.
            'asignacion_bahias' => Gate::allows('asignarUsuarios', Bahia::class) ? [
                'bahias' => Bahia::listadoConUsuarios($tenantId),
                'usuarios' => User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'email']),
            ] : null,
        ]);
    }

    /**
     * Save the recordatorio configuration of the tenant.
     */
    public function update(UpdateRecordatorioRequest $request): RedirectResponse
    {
        Recordatorio::query()->updateOrCreate(
            ['tenant_id' => $request->user()->tenant_id],
            $request->datosRecordatorio(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recordatorio guardado.')]);

        return to_route('recordatorio.show');
    }

    /**
     * Show the PDF the recordatorio would send right now, with every equipo of the tenant
     * grouped by bahía.
     */
    public function vistaPrevia(Request $request, ObtenerEquiposRecordatorio $obtenerEquipos, GenerarPdfRecordatorio $generarPdf): HttpResponse
    {
        Gate::authorize('viewAny', Recordatorio::class);

        $tenantId = $request->user()->tenant_id;
        $recordatorio = $this->recordatorioDelTenant($tenantId);

        $pdf = $generarPdf->handle(
            $tenantId,
            $request->user()->name,
            $obtenerEquipos->handle($tenantId, $recordatorio->dias_anticipacion)->all(),
            'bahia',
            $recordatorio->dias_anticipacion,
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="vista-previa-recordatorio.pdf"',
        ]);
    }

    /**
     * Send the authenticated user, right away, the recordatorio with every equipo of the
     * tenant, to check that the mail arrives and how it looks.
     */
    public function enviarPrueba(Request $request, ObtenerEquiposRecordatorio $obtenerEquipos): RedirectResponse
    {
        Gate::authorize('update', Recordatorio::class);

        $usuario = $request->user();
        $recordatorio = $this->recordatorioDelTenant($usuario->tenant_id);

        try {
            Mail::to($usuario->email)->sendNow(new RecordatorioEquipos(
                $usuario->tenant_id,
                $usuario->name,
                $obtenerEquipos->handle($usuario->tenant_id, $recordatorio->dias_anticipacion)->all(),
                'bahia',
                $recordatorio->dias_anticipacion,
            ));
        } catch (Throwable $exception) {
            Log::error('No se pudo enviar el correo de prueba del recordatorio.', ['exception' => $exception]);

            Inertia::flash('toast', ['type' => 'error', 'message' => __('No se pudo enviar el correo. Revisa la configuración de correo.')]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Correo de prueba enviado a :email.', ['email' => $usuario->email])]);

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
