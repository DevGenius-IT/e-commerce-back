<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubscribeRequest extends FormRequest
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
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'source' => 'nullable|string|max:100',
            'preferences' => 'nullable|array',
            'preferences.*' => 'string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required',
            'email.email' => 'Please provide a valid email address',
            'email.max' => 'Email address must not exceed 255 characters',
            'name.max' => 'Name must not exceed 255 characters',
            'phone.max' => 'Phone number must not exceed 20 characters',
            'source.max' => 'Source must not exceed 100 characters',
            'preferences.array' => 'Preferences must be an array',
        ];
    }
}