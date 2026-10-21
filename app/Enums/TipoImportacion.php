<?php

namespace App\Enums;

use App\Models\User;
use App\Modules\Calibraciones\Models\Laboratorio;
use App\Modules\Calibraciones\Models\ProcedimientoCalibracion;
use App\Modules\Catalogos\Models\Area;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Catalogos\Models\Fabricante;
use App\Modules\Catalogos\Models\Novedad;
use App\Modules\Catalogos\Models\TipoEquipo;
use App\Modules\Catalogos\Models\TipoEquipoCheckList;
use App\Modules\Equipos\Models\Equipo;
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
use App\Modules\Inventario\Models\Item;
use App\Modules\ServiciosTerceros\Models\EmpresaTercero;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Lo que se puede importar desde un CSV. Quien puede crear registros de un tipo puede
 * importarlos.
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
     * Modelo que se crea.
     *
     * @return class-string<Model>
     */
    public function modelo(): string
    {
        return match ($this) {
            self::Equipos => Equipo::class,
            self::Items => Item::class,
            self::Clientes => Cliente::class,
            self::Fabricantes => Fabricante::class,
            self::ProcedimientosCalibracion => ProcedimientoCalibracion::class,
            self::Novedades => Novedad::class,
            self::Laboratorios => Laboratorio::class,
            self::Areas => Area::class,
            self::Bahias => Bahia::class,
            self::EmpresasTerceras => EmpresaTercero::class,
            self::TiposEquipo => TipoEquipo::class,
            self::ChecklistMantenimiento => TipoEquipoCheckList::class,
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
     * Si el usuario puede importar registros de este tipo: el permiso "create" de la policy
     * del modelo, salvo el checklist, que se gestiona al editar el tipo de equipo (ver
     * StoreTipoEquipoCheckListRequest).
     */
    public function permitidoPara(User $user): bool
    {
        if ($this === self::ChecklistMantenimiento) {
            return $user->can(TenantPermission::TiposEquipoEditar->value);
        }

        return Gate::forUser($user)->allows('create', $this->modelo());
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
