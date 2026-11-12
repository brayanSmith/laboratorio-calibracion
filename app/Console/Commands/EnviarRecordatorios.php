<?php

namespace App\Console\Commands;

use App\Modules\Recordatorios\Actions\EnviarRecordatorio;
use App\Modules\Recordatorios\Models\Recordatorio;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('recordatorios:enviar')]
#[Description('Envía por correo el recordatorio de equipos vencidos y próximos a vencer de los tenants a los que les toca ahora')]
class EnviarRecordatorios extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(EnviarRecordatorio $enviarRecordatorio): int
    {
        $ahora = now();

        Recordatorio::query()
            ->where('activo', true)
            ->whereHas('tenant', fn ($query) => $query->activo())
            ->each(function (Recordatorio $recordatorio) use ($ahora, $enviarRecordatorio): void {
                if (! $recordatorio->debeEnviarse($ahora)) {
                    return;
                }

                $enviados = $enviarRecordatorio->handle($recordatorio);
                $this->line("Tenant {$recordatorio->tenant_id}: {$enviados} correo(s) encolados.");
            });

        return self::SUCCESS;
    }
}
