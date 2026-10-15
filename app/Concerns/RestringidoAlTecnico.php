<?php

namespace App\Concerns;

use App\Enums\TenantPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Para los servicios que tienen un técnico responsable (mantenimientos, calibraciones y
 * despachos): quien no tiene el permiso "ver todos" solo ve y gestiona los suyos.
 */
trait RestringidoAlTecnico
{
    /**
     * Permiso que permite ver los servicios de todos los técnicos.
     */
    abstract public static function permisoVerTodos(): TenantPermission;

    /**
     * Columna con el técnico responsable del servicio.
     */
    abstract public static function columnaTecnico(): string;

    /**
     * Limit the query to the services the user is allowed to see.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisiblesPara(Builder $query, User $user): Builder
    {
        if ($user->can(static::permisoVerTodos()->value)) {
            return $query;
        }

        return $query->where($query->getModel()->qualifyColumn(static::columnaTecnico()), $user->id);
    }

    /**
     * Determine whether the user is allowed to see this service.
     */
    public function esVisiblePara(User $user): bool
    {
        return $user->can(static::permisoVerTodos()->value)
            || $this->{static::columnaTecnico()} === $user->id;
    }
}
