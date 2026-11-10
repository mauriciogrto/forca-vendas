<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cadastro de cliente feito em campo (fica pendente até o escritório conferir). */
class ClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // a autorização por perfil fica no controller
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'documento' => preg_replace('/\D+/', '', (string) $this->input('documento')),
            'uf' => strtoupper(trim((string) $this->input('uf'))),
        ]);
    }

    public function confirmando(): bool
    {
        return $this->input('acao') === 'confirmar';
    }

    public function rules(): array
    {
        /** @var Cliente|null $atual */
        $atual = $this->route('cliente');
        $empresaId = $this->user()->empresa_id;

        $regras = [
            'nome' => ['required', 'string', 'max:255'],
            'documento' => [
                'bail', 'required', 'digits_between:11,14',
                function (string $atributo, mixed $valor, Closure $falha) use ($atual) {
                    if (! in_array(strlen((string) $valor), [11, 14], true)) {
                        $falha('Informe um CPF (11 dígitos) ou CNPJ (14 dígitos).');

                        return;
                    }
                    $existente = Cliente::query()
                        ->when($atual, fn ($q) => $q->whereKeyNot($atual->id))
                        ->whereRaw("REPLACE(REPLACE(REPLACE(documento, '.', ''), '-', ''), '/', '') = ?", [$valor])
                        ->first();
                    if ($existente) {
                        $falha("Já existe um cliente com este CPF/CNPJ: {$existente->nome} (cód. {$existente->codigo}).");
                    }
                },
            ],
            'telefone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'cidade' => ['required', 'string', 'max:120'],
            'uf' => ['required', 'string', 'size:2', 'alpha'],
            'acao' => ['nullable', Rule::in(['salvar', 'confirmar'])],
        ];

        if ($this->confirmando()) {
            $regras['codigo'] = [
                'required', 'string', 'max:60',
                Rule::unique('clientes', 'codigo')->where('empresa_id', $empresaId)->ignore($atual?->id),
                function (string $atributo, mixed $valor, Closure $falha) {
                    if (str_starts_with(strtoupper(trim((string) $valor)), 'NOVO-')) {
                        $falha('Informe o código que o cliente tem no ERP.');
                    }
                },
            ];
        }

        return $regras;
    }

    public function messages(): array
    {
        return [
            'documento.required' => 'Informe o CPF ou CNPJ.',
            'documento.digits_between' => 'Informe um CPF (11 dígitos) ou CNPJ (14 dígitos).',
            'codigo.required' => 'Informe o código do cliente no ERP para confirmar o cadastro.',
            'codigo.unique' => 'Já existe outro cliente com este código.',
            'uf.size' => 'Use a sigla do estado com 2 letras.',
            'uf.alpha' => 'Use a sigla do estado com 2 letras.',
        ];
    }
}
