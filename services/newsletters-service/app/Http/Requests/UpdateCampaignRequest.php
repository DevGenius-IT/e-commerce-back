<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCampaignRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'subject' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'plain_text' => 'sometimes|nullable|string',
            'campaign_type' => 'sometimes|string|in:newsletter,promotional,transactional,announcement',
            'targeting_criteria' => 'sometimes|nullable|array',
            'notes' => 'sometimes|nullable|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.max' => 'Campaign name must not exceed 255 characters',
            'subject.max' => 'Email subject must not exceed 255 characters',
            'campaign_type.in' => 'Campaign type must be one of: newsletter, promotional, transactional, announcement',
            'targeting_criteria.array' => 'Targeting criteria must be an array',
        ];
    }
}