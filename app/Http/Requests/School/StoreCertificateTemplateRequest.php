<?php

namespace App\Http\Requests\School;

use App\Support\CertificateOptionLibrary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCertificateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(CertificateOptionLibrary::typeValues())],
            'orientation' => ['nullable', 'string', Rule::in(['landscape', 'portrait'])],
            'paper_size' => ['nullable', 'string', Rule::in(['A4'])],
            'frame_key' => ['nullable', 'string', Rule::in(CertificateOptionLibrary::frameKeys())],
            'title_text' => ['nullable', 'string', 'max:255'],
            'default_body' => ['required', 'string', 'max:5000'],
            'layout_json' => ['nullable', 'array'],
            'title_style_json' => ['nullable', 'array'],
            'student_name_style_json' => ['nullable', 'array'],
            'body_style_json' => ['nullable', 'array'],
            'date_style_json' => ['nullable', 'array'],
            'signature_style_json' => ['nullable', 'array'],
            'default_gender_mode' => ['nullable', 'string', 'max:40'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم قالب الشهادة مطلوب.',
            'type.required' => 'نوع الشهادة مطلوب.',
            'type.in' => 'نوع الشهادة غير صالح.',
            'frame_key.in' => 'إطار الشهادة غير صالح.',
            'default_body.required' => 'نص الشهادة الافتراضي مطلوب.',
        ];
    }
}
