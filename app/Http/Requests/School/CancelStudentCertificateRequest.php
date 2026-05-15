<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class CancelStudentCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cancel_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'cancel_reason.required' => 'سبب إلغاء الشهادة مطلوب.',
        ];
    }
}
