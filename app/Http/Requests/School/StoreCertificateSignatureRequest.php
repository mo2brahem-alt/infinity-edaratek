<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class StoreCertificateSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'signature' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'stamp' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم صاحب التوقيع مطلوب.',
            'signature.mimes' => 'ملف التوقيع يجب أن يكون صورة من نوع jpg أو png أو webp.',
            'stamp.mimes' => 'ملف الختم يجب أن يكون صورة من نوع jpg أو png أو webp.',
            'signature.max' => 'حجم ملف التوقيع يجب ألا يتجاوز 2 ميجابايت.',
            'stamp.max' => 'حجم ملف الختم يجب ألا يتجاوز 2 ميجابايت.',
        ];
    }
}
