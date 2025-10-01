<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'plain_text' => 'nullable|string',
            'campaign_type' => 'sometimes|string|in:newsletter,promotional,transactional,announcement',
            'targeting_criteria' => 'sometimes|nullable|array',
            'notes' => 'sometimes|nullable|string',
            'created_by' => 'sometimes|integer',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Campaign name is required',
            'name.max' => 'Campaign name must not exceed 255 characters',
            'subject.required' => 'Email subject is required',
            'subject.max' => 'Email subject must not exceed 255 characters',
            'content.required' => 'Email content is required',
            'campaign_type.in' => 'Campaign type must be one of: newsletter, promotional, transactional, announcement',
            'targeting_criteria.array' => 'Targeting criteria must be an array',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'campaign_type' => $this->campaign_type ?? 'newsletter',
            'created_by' => auth()->id() ?? null,
        ]);
    }
}