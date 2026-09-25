<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('platform:make-admin {email : Correo del usuario que será administrador de plataforma}')]
#[Description('Convierte a un usuario existente en administrador de plataforma y lo desvincula de cualquier tenant')]
class MakePlatformAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No existe un usuario con ese correo.');

            return self::FAILURE;
        }

        $user->forceFill([
            'is_platform_admin' => true,
            'tenant_id' => null,
        ])->save();

        $this->info("{$user->email} ahora es administrador de plataforma y ya no pertenece a ningún tenant.");

        return self::SUCCESS;
    }
}
