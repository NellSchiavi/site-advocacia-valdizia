<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida apenas o formato do e-mail. A existência de conta associada não é
 * verificada nem exposta aqui, para evitar enumeração de usuários.
 */
class AdminForgotPasswordRequest extends FormRequest
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
            'email' => ['required', 'string', 'email'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido.',
        ];
    }
}
