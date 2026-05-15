<?php

namespace App\Http\Requests\Manager;

use App\Models\School;
use App\Rules\SaudiMobile;
use App\Support\SaudiPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManagerOnboardingSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->hasSystemRole('school_manager') ?? false);
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        if ($this->exists('phone')) {
            $normalizedPhone = SaudiPhone::normalizeMobile($this->input('phone'));
            if ($normalizedPhone !== null) {
                $merged['phone'] = $normalizedPhone;
            }
        }

        foreach (['name', 'email', 'address', 'notes'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = $this->input($field);
            if (! is_string($value)) {
                $merged[$field] = $value;
                continue;
            }

            $trimmed = trim($value);

            if ($field === 'name') {
                $merged[$field] = $trimmed;
                continue;
            }

            $merged[$field] = $trimmed !== '' ? $trimmed : null;
        }

        if ($this->exists('school_type')) {
            $merged['school_type'] = trim((string) $this->input('school_type'));
        }

        foreach (['country_id', 'governorate_id', 'education_type_id', 'region_id'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $merged[$field] = (int) $this->input($field);
        }

        if ($this->exists('education_stage_ids')) {
            $merged['education_stage_ids'] = collect((array) $this->input('education_stage_ids'))
                ->map(fn ($value) => (int) $value)
                ->filter(fn (int $value): bool => $value > 0)
                ->unique()
                ->values()
                ->all();
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    public function rules(): array
    {
        $countryId = (int) ($this->input('country_id') ?: 0);

        return [
            'region_id' => ['bail', 'nullable', 'integer', 'exists:educational_directorates,id'],
            'country_id' => ['bail', 'required_without:region_id', 'integer', 'exists:countries,id'],
            'governorate_id' => [
                'bail',
                'required_without:region_id',
                'integer',
                Rule::exists('governorates', 'id')->when(
                    $countryId > 0,
                    fn ($rule) => $rule->where(fn ($query) => $query->where('country_id', $countryId))
                ),
            ],
            'education_type_id' => ['bail', 'required_without:region_id', 'integer', 'exists:education_types,id'],
            'template_key' => ['nullable', 'string', 'max:255'],
            'school_type' => ['bail', 'required', 'string', Rule::in(School::availableSchoolTypes())],
            'education_stage_ids' => ['bail', 'required', 'array', 'min:1'],
            'education_stage_ids.*' => ['integer', 'distinct', Rule::exists('education_stages', 'id')->where('is_active', true)],
            'name' => ['bail', 'required', 'string', 'max:255'],
            'phone' => ['bail', 'required', 'string', 'max:20', new SaudiMobile],
            'email' => ['nullable', 'email:rfc,dns', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'country_id.required_without' => 'الدولة مطلوبة.',
            'governorate_id.required_without' => 'المحافظة مطلوبة.',
            'governorate_id.exists' => 'المحافظة المحددة لا تنتمي إلى الدولة المختارة.',
            'education_type_id.required_without' => 'نوع التعليم مطلوب.',
            'school_type.required' => 'نوع المدرسة مطلوب.',
            'school_type.in' => 'نوع المدرسة المحدد غير صالح.',
            'education_stage_ids.required' => 'اختر مرحلة تعليمية واحدة على الأقل.',
            'education_stage_ids.array' => 'اختر المراحل التعليمية من القائمة المتاحة فقط.',
            'education_stage_ids.min' => 'اختر مرحلة تعليمية واحدة على الأقل.',
            'education_stage_ids.*.exists' => 'توجد مرحلة تعليمية غير متاحة أو غير مفعلة ضمن الاختيارات.',
            'name.required' => 'اسم المدرسة مطلوب.',
            'name.max' => 'اسم المدرسة يجب ألا يتجاوز 255 حرفًا.',
            'phone.required' => 'رقم الجوال مطلوب.',
            'phone.max' => 'رقم الجوال يجب ألا يتجاوز 20 رقمًا.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.max' => 'البريد الإلكتروني يجب ألا يتجاوز 255 حرفًا.',
            'address.max' => 'العنوان يجب ألا يتجاوز 500 حرف.',
            'notes.max' => 'الملاحظات يجب ألا تتجاوز 2000 حرف.',
            'logo.mimes' => 'شعار المدرسة يجب أن يكون بصيغة JPG أو PNG أو WebP.',
            'logo.max' => 'حجم شعار المدرسة يجب ألا يتجاوز 2 ميجابايت.',
        ];
    }

    public function attributes(): array
    {
        return [
            'region_id' => 'الإدارة التعليمية',
            'country_id' => 'الدولة',
            'governorate_id' => 'المحافظة',
            'education_type_id' => 'نوع التعليم',
            'template_key' => 'القالب الافتراضي',
            'school_type' => 'نوع المدرسة',
            'education_stage_ids' => 'المراحل التعليمية',
            'name' => 'اسم المدرسة',
            'phone' => 'رقم الجوال',
            'email' => 'البريد الإلكتروني',
            'address' => 'العنوان',
            'notes' => 'الملاحظات',
            'logo' => 'شعار المدرسة',
        ];
    }
}
