<?php

namespace App\Http\Requests\School\Leaves;

use Illuminate\Foundation\Http\FormRequest;

class UploadStudentLeaveAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-student-leaves');
    }

    public function rules(): array
    {
        return [
            'file' => $this->fileValidationRules(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function fileValidationRules(): array
    {
        $rules = ['required', 'file', 'max:10240'];

        if ($this->strictUploadValidationEnabled()) {
            $mimeTypes = $this->allowedAttachmentMimeTypes();
            if (count($mimeTypes) > 0) {
                $rules[] = 'mimetypes:' . implode(',', $mimeTypes);
            }
        }

        return $rules;
    }

    private function strictUploadValidationEnabled(): bool
    {
        return (bool) config('features.uploads.strict_student_leave_attachment_validation', true)
            || (bool) config('features.uploads.strict_validation_enabled', false);
    }

    /**
     * @return array<int, string>
     */
    private function allowedAttachmentMimeTypes(): array
    {
        return collect(config('features.uploads.student_leave_attachment_mime_types', []))
            ->map(fn ($mime) => trim((string) $mime))
            ->filter()
            ->values()
            ->all();
    }
}
