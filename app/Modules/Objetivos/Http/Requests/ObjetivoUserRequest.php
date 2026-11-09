<?php

namespace App\Modules\Objetivos\Http\Requests;

use App\Models\ObjetivoUser;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Guardar los objetivos de un usuario para los 12 meses de un año, de mantenimientos y de
 * calibraciones a la vez. Un mes vacío no tiene objetivo (si lo tenía, se quita).
 */
class ObjetivoUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', ObjetivoUser::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reglas = [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'meses' => ['present', 'array'],
        ];

        foreach (ObjetivoUser::TIPOS as $tipo) {
            $reglas["meses.{$tipo}"] = ['nullable', 'array'];

            foreach (range(1, 12) as $mes) {
                $reglas["meses.{$tipo}.{$mes}"] = ['nullable', 'numeric', 'min:0', 'max:999999.99'];
            }
        }

        return $reglas;
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $conValores = array_filter(ObjetivoUser::TIPOS, fn (string $tipo): bool => $this->valoresPorMes($tipo) !== []);

                if ($conValores === []) {
                    $validator->errors()->add('meses', 'Ingresa al menos un objetivo de mantenimientos o de calibraciones.');
                }
            },
        ];
    }

    /**
     * Los meses con objetivo de un tipo, como [mes => valor].
     *
     * @return array<int, float>
     */
    public function valoresPorMes(string $tipo): array
    {
        $valores = [];

        foreach (range(1, 12) as $mes) {
            $valor = $this->input("meses.{$tipo}.{$mes}");

            if ($valor !== null && $valor !== '') {
                $valores[$mes] = (float) $valor;
            }
        }

        return $valores;
    }

    /**
     * Get custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $atributos = [
            'user_id' => 'usuario',
            'anio' => 'año',
        ];

        foreach (ObjetivoUser::TIPOS as $tipo) {
            $nombre = $tipo === 'MANTENIMIENTO' ? 'mantenimientos' : 'calibraciones';

            foreach (range(1, 12) as $mes) {
                $atributos["meses.{$tipo}.{$mes}"] = "objetivo de {$nombre} del mes {$mes}";
            }
        }

        return $atributos;
    }
}
