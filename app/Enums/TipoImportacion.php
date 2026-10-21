<?php

namespace App\Enums;

use App\Models\User;
use App\Modules\Importaciones\Actions\ImportadorFila;
use App\Modules\Importaciones\Actions\ImportarFilaArea;
use App\Modules\Importaciones\Actions\ImportarFilaBahia;
use App\Modules\Importaciones\Actions\ImportarFilaChecklistMantenimiento;
use App\Modules\Importaciones\Actions\ImportarFilaCliente;
use App\Modules\Importaciones\Actions\ImportarFilaEmpresaTercero;
use App\Modules\Importaciones\Actions\ImportarFilaEquipo;
use App\Modules\Importaciones\Actions\ImportarFilaFabricante;
use App\Modules\Importaciones\Actions\ImportarFilaItem;
use App\Modules\Importaciones\Actions\ImportarFilaLaboratorio;
use App\Modules\Importaciones\Actions\ImportarFilaNovedad;
use App\Modules\Importaciones\Actions\ImportarFilaProcedimientoCalibracion;
use App\Modules\Importaciones\Actions\ImportarFilaTipoEquipo;

/**
 * Lo que se puede importar desde un CSV. Cada tipo tiene su permiso de importación (ver
 * permiso()), que se asigna por rol.
 */
enum TipoImportacion: string
{
    case Equipos = 'equipos';
    case Items = 'items';
    case Clientes = 'clientes';
    case Fabricantes = 'fabricantes';
    case ProcedimientosCalibracion = 'procedimientos-calibracion';
    case Novedades = 'novedades';
    case Laboratorios = 'laboratorios';
    case Areas = 'areas';
    case Bahias = 'bahias';
    case EmpresasTerceras = 'empresas-terceras';
    case TiposEquipo = 'tipos-equipo';
    case ChecklistMantenimiento = 'checklist-mantenimiento';

    /**
     * Nombre en plural, para los títulos y los avisos.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Equipos => 'Equipos',
            self::Items => 'Ítems',
            self::Clientes => 'Clientes',
            self::Fabricantes => 'Fabricantes',
            self::ProcedimientosCalibracion => 'Procedimientos de calibración',
            self::Novedades => 'Novedades',
            self::Laboratorios => 'Laboratorios',
            self::Areas => 'Áreas',
            self::Bahias => 'Bahías',
            self::EmpresasTerceras => 'Empresas terceras',
            self::TiposEquipo => 'Tipos de equipo',
            self::ChecklistMantenimiento => 'Checklist de mantenimiento',
        };
    }

    /**
     * @return class-string<ImportadorFila>
     */
    public function importador(): string
    {
        return match ($this) {
            self::Equipos => ImportarFilaEquipo::class,
            self::Items => ImportarFilaItem::class,
            self::Clientes => ImportarFilaCliente::class,
            self::Fabricantes => ImportarFilaFabricante::class,
            self::ProcedimientosCalibracion => ImportarFilaProcedimientoCalibracion::class,
            self::Novedades => ImportarFilaNovedad::class,
            self::Laboratorios => ImportarFilaLaboratorio::class,
            self::Areas => ImportarFilaArea::class,
            self::Bahias => ImportarFilaBahia::class,
            self::EmpresasTerceras => ImportarFilaEmpresaTercero::class,
            self::TiposEquipo => ImportarFilaTipoEquipo::class,
            self::ChecklistMantenimiento => ImportarFilaChecklistMantenimiento::class,
        };
    }

    /**
     * Permiso del grupo "Importaciones" que habilita importar este tipo.
     */
    public function permiso(): TenantPermission
    {
        return TenantPermission::from("importaciones.{$this->value}");
    }

    /**
     * Si el usuario puede importar registros de este tipo: cada tipo tiene su propio permiso
     * en el grupo "Importaciones", independiente del de crear en su módulo.
     */
    public function permitidoPara(User $user): bool
    {
        return $user->can($this->permiso()->value);
    }

    /**
     * Si el usuario puede importar algún tipo (para mostrarle el botón Importar).
     */
    public static function algunoPermitidoPara(User $user): bool
    {
        foreach (self::cases() as $tipo) {
            if ($tipo->permitidoPara($user)) {
                return true;
            }
        }

        return false;
    }
}
