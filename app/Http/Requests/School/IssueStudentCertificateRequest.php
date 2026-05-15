<?php

namespace App\Http\Requests\School;

use App\Support\CertificateOptionLibrary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueStudentCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'certificate_template_id' => ['nullable', 'integer'],
            'school_certificate_signature_id' => ['nullable', 'integer'],
            'recipient_type' => ['nullable', 'string', Rule::in(['student', 'user'])],
            'recipient_ids' => ['required_without:student_ids', 'array', 'min:1', 'max:100'],
            'recipient_ids.*' => ['integer'],
            'student_ids' => ['nullable', 'array', 'min:1', 'max:100'],
            'student_ids.*' => ['integer'],
            'type' => ['required', 'string', Rule::in(CertificateOptionLibrary::typeValues())],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'certificate_date' => ['nullable', 'date'],
            'hijri_date' => ['nullable', 'string', 'max:80'],
            'teacher_name' => ['nullable', 'string', 'max:255'],
            'activity_name' => ['nullable', 'string', 'max:255'],
            'achievement_detail' => ['nullable', 'string', 'max:255'],
            'school_academic_year_id' => ['nullable', 'integer'],
            'school_term_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_type.in' => 'نوع المستفيد غير صالح.',
            'recipient_ids.required_without' => 'اختر مستفيدًا واحدًا على الأقل.',
            'recipient_ids.min' => 'اختر مستفيدًا واحدًا على الأقل.',
            'recipient_ids.max' => 'لا يمكن إصدار أكثر من 100 شهادة في العملية الواحدة.',
            'student_ids.min' => 'اختر طالبًا واحدًا على الأقل.',
            'student_ids.max' => 'لا يمكن إصدار أكثر من 100 شهادة في العملية الواحدة.',
            'type.required' => 'نوع الشهادة مطلوب.',
            'type.in' => 'نوع الشهادة غير صالح.',
        ];
    }
}
