<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AgendarConsultaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'area_atuacao_id' => ['required', 'integer', 'exists:areas_atuacao,id,ativo,1'],
            'nome_cliente' => ['required', 'string', 'max:150'],
            'email_cliente' => ['required', 'email', 'max:190'],
            'telefone_cliente' => ['required', 'string', 'max:30'],
            'assunto' => ['required', 'string', 'max:150'],
            'observacao' => ['nullable', 'string', 'max:2000'],
            'data' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'consentimento' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'area_atuacao_id.required' => 'Escolha uma área de atuação.',
            'area_atuacao_id.exists' => 'Área de atuação inválida.',
            'nome_cliente.required' => 'Informe seu nome completo.',
            'email_cliente.required' => 'Informe um e-mail para contato.',
            'email_cliente.email' => 'Informe um e-mail válido.',
            'telefone_cliente.required' => 'Informe um telefone para contato.',
            'assunto.required' => 'Informe o assunto da consulta.',
            'data.required' => 'Escolha uma data.',
            'hora_inicio.required' => 'Escolha um horário.',
            'consentimento.accepted' => 'É necessário concordar com a Política de Privacidade para agendar.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'area_atuacao_id' => 'área de atuação',
            'nome_cliente' => 'nome',
            'email_cliente' => 'e-mail',
            'telefone_cliente' => 'telefone',
            'assunto' => 'assunto',
            'data' => 'data',
            'hora_inicio' => 'horário',
        ];
    }
}
