<?php

namespace App\Modules\Importaciones\Actions;

use App\Models\User;
use App\Modules\Catalogos\Models\Area;
use App\Modules\Catalogos\Models\Bahia;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Asigna un usuario a una bahía a partir de una fila del CSV (una fila por asignación).
 * Solo agrega: las asignaciones que ya tenía la bahía se conservan. La bahía se busca por
 * su área, porque el mismo nombre de bahía puede repetirse en otra área.
 */
class ImportarFilaBahiaUsuario extends ImportadorFila
{
    /** @var array<string, int>|null "clave del área|clave de la bahía" => id */
    private ?array $bahias = null;

    /** @var array<string, int>|null correo en minúsculas => id */
    private ?array $usuarios = null;

    /** @var array<string, true>|null "bahía|usuario" ya asignados o importados en este archivo */
    private ?array $asignados = null;

    public static function campos(): array
    {
        return [
            self::campo('area', 'Área', true, 'Nombre del área de la bahía.'),
            self::campo('bahia', 'Bahía', true, 'Nombre de una bahía ya registrada en esa área.'),
            self::campo('usuario', 'Correo del usuario', true, 'Correo de un usuario ya registrado.'),
        ];
    }

    public static function ejemplo(): array
    {
        return [
            'area' => 'Planta principal',
            'bahia' => 'Bahía 1',
            'usuario' => 'tecnico@laboratorio.com',
        ];
    }

    public static function nota(): ?string
    {
        return 'Una fila por asignación: para darle varias bahías a un usuario (o varios usuarios a una bahía), repite la fila. Las asignaciones existentes se conservan.';
    }

    public function importar(array $valores): Bahia
    {
        $datos = $this->validar($this->limpiar($valores), [
            'area' => ['required', 'string', 'max:255'],
            'bahia' => ['required', 'string', 'max:255'],
            'usuario' => ['required', 'string', 'email', 'max:255'],
        ]);

        $bahia = Bahia::query()->findOrFail($this->bahiaId($datos['area'], $datos['bahia']));
        $usuarioId = $this->usuarioId($datos['usuario']);

        $this->reservarAsignacion($bahia->id, $usuarioId, "El usuario {$datos['usuario']} ya está asignado a la bahía {$datos['bahia']}.");

        $bahia->usuarios()->attach($usuarioId);

        return $bahia;
    }

    /**
     * Id de la bahía del tenant con ese nombre en esa área, sin importar mayúsculas, tildes
     * ni espacios.
     *
     * @throws ValidationException si el área o la bahía no existen
     */
    private function bahiaId(string $area, string $bahia): int
    {
        $this->idExistente(Area::class, $area, 'area', "No existe el área {$area}; créala o impórtala primero.");

        $this->bahias ??= Bahia::query()
            ->where('tenant_id', $this->tenantId)
            ->with('area:id,nombre')
            ->orderBy('id')
            ->get(['id', 'nombre', 'area_id'])
            ->reduce(function (array $porClave, Bahia $registro): array {
                $porClave[self::clave($registro->area->nombre).'|'.self::clave($registro->nombre)] ??= $registro->id;

                return $porClave;
            }, []);

        return $this->bahias[self::clave($area).'|'.self::clave($bahia)]
            ?? throw ValidationException::withMessages(['bahia' => "El área {$area} no tiene una bahía llamada {$bahia}."]);
    }

    /**
     * Id del usuario del tenant con ese correo, sin importar mayúsculas.
     *
     * @throws ValidationException si no existe
     */
    private function usuarioId(string $correo): int
    {
        $this->usuarios ??= User::query()
            ->where('tenant_id', $this->tenantId)
            ->pluck('id', 'email')
            ->mapWithKeys(fn (int $id, string $email): array => [Str::lower($email) => $id])
            ->all();

        return $this->usuarios[Str::lower($correo)]
            ?? throw ValidationException::withMessages(['usuario' => "No existe un usuario con el correo {$correo}."]);
    }

    /**
     * Falla si el usuario ya está asignado a la bahía (desde antes o en otra fila del
     * archivo): así volver a importar el mismo CSV no duplica nada.
     *
     * @throws ValidationException
     */
    private function reservarAsignacion(int $bahiaId, int $usuarioId, string $mensaje): void
    {
        $this->asignados ??= Bahia::query()
            ->where('tenant_id', $this->tenantId)
            ->with('usuarios:id')
            ->get(['id'])
            ->flatMap(fn (Bahia $registro) => $registro->usuarios->map(fn (User $usuario): string => "{$registro->id}|{$usuario->id}"))
            ->mapWithKeys(fn (string $llave): array => [$llave => true])
            ->all();

        $llave = "{$bahiaId}|{$usuarioId}";

        if (isset($this->asignados[$llave])) {
            throw ValidationException::withMessages(['usuario' => $mensaje]);
        }

        $this->asignados[$llave] = true;
    }
}
