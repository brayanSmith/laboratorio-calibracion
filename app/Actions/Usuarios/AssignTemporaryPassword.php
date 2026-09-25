<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssignTemporaryPassword
{
    /**
     * Replace the user's password with a temporary one, force a change on next login and close their sessions.
     */
    public function handle(User $user, string $temporaryPassword): void
    {
        $user->forceFill([
            'password' => $temporaryPassword,
            'must_change_password' => true,
            'remember_token' => Str::random(60),
        ])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
