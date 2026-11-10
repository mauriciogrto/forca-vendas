<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrcamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;
        $descontoMaximo = (float) ($this->user()->empresa->desconto_maximo ?? 0);

        return [
            'cliente_id' => ['nullable', 'integer', Rule::exists('clientes', 'id')->where('empresa_id', $empresaId)],
            'cliente_nome' => ['required', 'string', 'max:255'],
            'condicao_pagamento' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'itens' => ['required', 'array', 'min:1', 'max:200'],
            'itens.*.produto_id' => [
                'required', 'integer',
                Rule::exists('produtos', 'id')->where('empresa_id', $empresaId)->where('ativo', true),
            ],
            'itens.*.quantidade' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'itens.*.desconto_percentual' => ['nullable', 'numeric', 'min:0', 'max:'.$descontoMaximo],
        ];
    }

    public function messages(): array
    {
        return [
            'itens.required' => 'Adicione pelo menos um produto.',
            'itens.min' => 'Adicione pelo menos um produto.',
            'itens.*.desconto_percentual.max' => 'O desconto máximo permitido é :max%.',
            'itens.*.quantidade.gt' => 'A quantidade deve ser maior que zero.',
        ];
    }
}
