<?php

namespace App\Http\Requests;

use App\Enums\PropositionModality;
use App\Enums\PropositionOrigin;
use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A origem padrão é o Legislativo, preservando o comportamento anterior
     * para registros e integrações que não informam o campo.
     */
    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing(['origin' => PropositionOrigin::LEGISLATIVO->value]);
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        $isLegislative = fn () => $this->input('origin') === PropositionOrigin::LEGISLATIVO->value;

        return [
            'modality' => ['required', Rule::in(array_keys(PropositionModality::options()))],
            'origin' => ['required', Rule::in(array_keys(PropositionOrigin::options()))],
            'author_councilor_id' => [
                Rule::requiredIf($isLegislative),
                'nullable',
                Tenant::scopedExists('councilors'),
            ],
            'author_external_name' => [
                Rule::requiredIf(fn () => ! $isLegislative()),
                'nullable',
                'string',
                'max:255',
            ],
            'coauthor_ids' => ['nullable', 'array'],
            'coauthor_ids.*' => [Tenant::scopedExists('councilors')],
            'title' => ['required', 'string', 'max:255'],
            'ementa' => ['required', 'string'],
            'text' => ['nullable', 'string'],
            'justification' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }
}
