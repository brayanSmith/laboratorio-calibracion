<?php

namespace App\Modules\Recordatorios\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Recordatorios\Actions\GenerarPdfRecordatorio;
use App\Modules\Recordatorios\Actions\ObtenerEquiposRecordatorio;
use App\Modules\Recordatorios\Actions\RegistrarEnvioRecordatorio;
use App\Modules\Recordatorios\Http\Requests\UpdateRecordatorioRequest;
use App\Modules\Recordatorios\Mail\RecordatorioEquipos;
use App\Modules\Recordatorios\Models\Recordatorio;
use App\Modules\Recordatorios\Models\RecordatorioEnvio;
use App\Modules\Recordatorios\Models\RecordatorioPlantilla;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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
        $equipos = $obtenerEquipos->handle($tenantId, $recordatorio->diasAviso(), $recordatorio->diasHastaInicioAviso());
        $conteo = $equipos->countBy('estado');

        return Inertia::render('recordatorio/show', [
            'recordatorio' => [
                'activo' => $recordatorio->activo,
                'frecuencia' => $recordatorio->frecuencia,
                'dia_semana' => $recordatorio->dia_semana,
                'dia_mes' => $recordatorio->dia_mes,
                'hora_envio' => $recordatorio->hora_envio,
                'mes_aviso' => $recordatorio->mes_aviso,
                'dias_anticipacion' => $recordatorio->dias_anticipacion,
                'enviar_a_clientes' => $recordatorio->enviar_a_clientes,
                'enviar_resumen_bahia' => $recordatorio->enviar_resumen_bahia,
                'correos_copia' => $recordatorio->correos_copia ?? [],
                'ultimo_envio_at' => $recordatorio->ultimo_envio_at?->toIso8601String(),
            ],
            'resumen' => [
                'avisa_desde' => $recordatorio->fechaInicioAviso()->toDateString(),
                'avisa_hasta' => $recordatorio->fechaLimiteAviso()->toDateString(),
                'vencidos' => $conteo->get('VENCIDO', 0),
                'programados' => $conteo->get('PROGRAMADO', 0),
                'clientes' => $equipos->pluck('cliente_id')->unique()->count(),
                'usuarios_bahia' => User::query()
                    ->where('tenant_id', $tenantId)
                    ->whereHas('bahias', fn ($query) => $query->whereIn('bahias.id', $equipos->pluck('bahia_id')->unique()))
                    ->count(),
            ],
            // Los clientes que recibirían correo, para ver la vista previa del PDF de cada uno.
            'clientes_recordatorio' => Cliente::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $equipos->pluck('cliente_id')->unique())
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'email'])
                ->map(fn (Cliente $cliente) => [
                    'id' => $cliente->id,
                    'nombre' => $cliente->nombre,
                    'tiene_correo' => filled($cliente->email),
                    'equipos' => $equipos->where('cliente_id', $cliente->id)->count(),
                ]),
            'plantillas' => $this->plantillasDelTenant($recordatorio, $tenantId),
            // Historial de correos enviados, del más reciente al más antiguo.
            'envios' => RecordatorioEnvio::query()
                ->where('tenant_id', $tenantId)
                ->latest('id')
                ->paginate(20, pageName: 'pagina_envios')
                ->withQueryString()
                ->through(fn (RecordatorioEnvio $envio) => [
                    'id' => $envio->id,
                    'tipo' => $envio->tipo,
                    'es_prueba' => $envio->es_prueba,
                    'destinatario' => $envio->destinatario,
                    'correo' => $envio->correo,
                    'asunto' => $envio->asunto,
                    'equipos' => $envio->equipos,
                    'estado' => $envio->estado,
                    'error' => $envio->error,
                    'creado_at' => $envio->created_at?->toIso8601String(),
                    'enviado_at' => $envio->enviado_at?->toIso8601String(),
                ]),
            'plantillas_por_defecto' => Recordatorio::PLANTILLAS_POR_DEFECTO,
            'variables_plantilla' => Recordatorio::VARIABLES,
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
     * Show the PDF the recordatorio would send right now of that tipo (cliente: grouped by
     * cliente; bahia: grouped by bahía), with every equipo of the tenant. With ?cliente=ID
     * (only for cliente), exactly the PDF that cliente would get: only its equipos.
     *
     * @param  'cliente'|'bahia'  $tipo
     */
    public function vistaPrevia(Request $request, string $tipo, ObtenerEquiposRecordatorio $obtenerEquipos, GenerarPdfRecordatorio $generarPdf): HttpResponse
    {
        Gate::authorize('viewAny', Recordatorio::class);

        $request->validate(['cliente' => ['nullable', 'integer']]);

        $tenantId = $request->user()->tenant_id;
        $recordatorio = $this->recordatorioDelTenant($tenantId);
        $equipos = $obtenerEquipos->handle($tenantId, $recordatorio->diasAviso(), $recordatorio->diasHastaInicioAviso());
        $destinatario = $request->user()->name;

        if ($tipo === 'cliente' && $request->filled('cliente')) {
            $cliente = Cliente::query()->where('tenant_id', $tenantId)->findOrFail($request->integer('cliente'));
            $equipos = $equipos->where('cliente_id', $cliente->id)->values();
            $destinatario = $cliente->nombre;
        }

        $pdf = $generarPdf->handle(
            $tenantId,
            $destinatario,
            $equipos->all(),
            $tipo,
            $recordatorio->diasAviso(),
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"vista-previa-recordatorio-{$tipo}.pdf\"",
        ]);
    }

    /**
     * Send the authenticated user, right away, the recordatorio mail of that tipo (with its
     * texts and PDF) with every equipo of the tenant, to check that it arrives and how it
     * looks.
     *
     * @param  'cliente'|'bahia'  $tipo
     */
    public function enviarPrueba(Request $request, string $tipo, ObtenerEquiposRecordatorio $obtenerEquipos, RegistrarEnvioRecordatorio $registrarEnvio): RedirectResponse
    {
        Gate::authorize('update', Recordatorio::class);

        $usuario = $request->user();
        $recordatorio = $this->recordatorioDelTenant($usuario->tenant_id);
        $equipos = $obtenerEquipos->handle($usuario->tenant_id, $recordatorio->diasAviso(), $recordatorio->diasHastaInicioAviso())->all();
        $plantilla = $recordatorio->plantilla($tipo);
        $correo = new RecordatorioEquipos(
            $usuario->tenant_id,
            $usuario->name,
            $equipos,
            $tipo,
            $recordatorio->diasAviso(),
            $plantilla['asunto'],
            $plantilla['mensaje'],
        );
        $envio = $registrarEnvio->handle($correo, $usuario->email, $tipo, esPrueba: true);

        try {
            Mail::to($usuario->email)->sendNow($correo);
        } catch (Throwable $exception) {
            $envio->marcarFallido($exception->getMessage());
            Log::error('No se pudo enviar el correo de prueba del recordatorio.', ['exception' => $exception]);

            Inertia::flash('toast', ['type' => 'error', 'message' => __('No se pudo enviar el correo. Revisa la configuración de correo.')]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __($tipo === 'cliente'
            ? 'Se envió a :email la prueba del correo a clientes.'
            : 'Se envió a :email la prueba del resumen por bahía.', ['email' => $usuario->email])]);

        return back();
    }

    /**
     * Store an image inserted in the mensaje of the correo and return its public URL, which
     * the correo links to.
     */
    public function subirImagen(Request $request): JsonResponse
    {
        Gate::authorize('update', Recordatorio::class);

        $request->validate(
            ['imagen' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048']],
            attributes: ['imagen' => 'imagen'],
        );

        $ruta = $request->file('imagen')->store("recordatorios/{$request->user()->tenant_id}", 'public');

        return response()->json(['url' => Storage::disk('public')->url($ruta)], 201);
    }

    /**
     * Las plantillas de cada correo: la predeterminada, las creadas por el tenant y cuál
     * está en uso (null: la predeterminada).
     *
     * @return array<'cliente'|'bahia', array{en_uso: int|null, predeterminada: array{asunto: string, mensaje: string}, personalizadas: list<array{id: int, nombre: string, asunto: string, mensaje: string}>}>
     */
    private function plantillasDelTenant(Recordatorio $recordatorio, int $tenantId): array
    {
        $personalizadas = RecordatorioPlantilla::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('nombre')
            ->get(['id', 'tipo', 'nombre', 'asunto', 'mensaje'])
            ->groupBy('tipo');

        return collect(RecordatorioPlantilla::TIPOS)
            ->mapWithKeys(fn (string $tipo) => [$tipo => [
                'en_uso' => $recordatorio->getAttribute("plantilla_{$tipo}_id"),
                'predeterminada' => $recordatorio->plantillaPredeterminada($tipo),
                'personalizadas' => $personalizadas->get($tipo, collect())
                    ->map(fn (RecordatorioPlantilla $plantilla) => $plantilla->only(['id', 'nombre', 'asunto', 'mensaje']))
                    ->values()
                    ->all(),
            ]])
            ->all();
    }

    /**
     * The saved configuration of the tenant, or a new one with the defaults.
     */
    private function recordatorioDelTenant(int $tenantId): Recordatorio
    {
        return Recordatorio::query()->firstOrNew(['tenant_id' => $tenantId]);
    }
}
