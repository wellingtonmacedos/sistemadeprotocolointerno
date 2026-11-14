<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use App\Enums\ProtocolPriority;
use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação do cadastro de protocolos.
 */
class ProtocolRequest extends FormRequest
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
            'document_type' => ['required', Rule::in(array_keys(DocumentType::options()))],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sender_name' => ['required', 'string', 'max:255'],
            'sender_document' => ['nullable', 'string', 'max:20'],
            'sender_email' => ['nullable', 'email'],
            'sender_phone' => ['nullable', 'string', 'max:30'],
            'destination_sector_id' => ['required', Tenant::scopedExists('sectors')],
            'priority' => ['required', Rule::in(array_keys(ProtocolPriority::options()))],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'document_type' => 'tipo de documento',
            'subject' => 'assunto',
            'description' => 'descrição',
            'sender_name' => 'interessado',
            'sender_document' => 'documento do interessado',
            'sender_email' => 'e-mail do interessado',
            'sender_phone' => 'telefone do interessado',
            'destination_sector_id' => 'setor de destino',
            'priority' => 'prioridade',
            'attachments' => 'anexos',
        ];
    }
}
