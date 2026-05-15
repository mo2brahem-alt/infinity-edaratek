<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\SchoolExam;
use App\Models\SchoolStudent;
use App\Models\SchoolTeachingAssignment;
use App\Models\SchoolTimetableVersion;
use App\Models\User;
use App\Services\Support\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SchoolAttachmentController extends Controller
{
    public function __construct(private readonly AttachmentService $attachmentService)
    {
    }

    public function download(Request $request, Attachment $attachment)
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureInstitutionalAttachmentInSchool($attachment, $schoolId);
        $this->authorizeForModule($request->user(), $attachment, 'download');

        return $this->attachmentService->downloadInstitutionalAttachment($attachment);
    }

    public function destroy(Request $request, Attachment $attachment): RedirectResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->ensureInstitutionalAttachmentInSchool($attachment, $schoolId);
        $this->authorizeForModule($request->user(), $attachment, 'delete');

        $this->attachmentService->deleteInstitutionalAttachment(
            $attachment,
            $request,
            (int) ($request->user()?->id ?? 0) ?: null
        );

        return back()->with('success', 'تم حذف المرفق بنجاح.');
    }

    private function ensureInstitutionalAttachmentInSchool(Attachment $attachment, int $schoolId): void
    {
        if (!$attachment->isInstitutionalAttachment()) {
            abort(404, 'المرفق المطلوب غير متاح ضمن هذا السياق.');
        }

        if ((int) ($attachment->school_id ?? 0) !== $schoolId) {
            abort(404, 'لا يمكنك الوصول إلى مرفق خارج نطاق مدرستك.');
        }

        $attachable = $attachment->attachable;
        if (!$attachable) {
            abort(404, 'تعذر العثور على السجل المرتبط بهذا المرفق.');
        }

        if ((int) data_get($attachable, 'school_id', 0) !== $schoolId) {
            abort(404, 'لا يمكنك الوصول إلى مرفق خارج نطاق مدرستك.');
        }
    }

    private function authorizeForModule(?User $user, Attachment $attachment, string $ability): void
    {
        if (!$user) {
            abort(403, 'يجب تسجيل الدخول أولًا.');
        }

        $attachable = $attachment->attachable;
        $module = (string) ($attachment->module ?? '');

        if ($module === 'exams') {
            if (!$user->canManageSchoolExams()) {
                abort(403, $ability === 'delete'
                    ? 'ليست لديك صلاحية حذف مرفقات الاختبارات.'
                    : 'ليست لديك صلاحية تحميل مرفقات الاختبارات.');
            }

            if (
                !$user->hasSystemRole('super_admin')
                && !$user->hasSystemRole('school_manager')
                && !$user->canManageAcademicPlanning()
                && $attachable instanceof SchoolExam
                && (int) ($attachable->teacher_user_id ?? 0) !== (int) $user->id
            ) {
                abort(403, $ability === 'delete'
                    ? 'لا يمكنك حذف مرفقات اختبار لا يخص إسنادك الحالي.'
                    : 'لا يمكنك تحميل مرفقات اختبار لا يخص إسنادك الحالي.');
            }

            return;
        }

        if ($module === 'schedules') {
            if (!$user->canManageAcademicPlanning()) {
                abort(403, $ability === 'delete'
                    ? 'ليست لديك صلاحية حذف مرفقات الجداول.'
                    : 'ليست لديك صلاحية تحميل مرفقات الجداول.');
            }

            if (!$attachable instanceof SchoolTimetableVersion) {
                abort(404, 'تعذر العثور على نسخة الجدول المرتبطة بالمرفق.');
            }

            return;
        }

        if ($module === 'teacher_preparations') {
            if (!$user->canManageAcademicPlanning()) {
                abort(403, $ability === 'delete'
                    ? 'ليست لديك صلاحية حذف مرفقات تحضير المعلمين.'
                    : 'ليست لديك صلاحية تحميل مرفقات تحضير المعلمين.');
            }

            if (!$attachable instanceof SchoolTeachingAssignment) {
                abort(404, 'تعذر العثور على إسناد المعلم المرتبط بهذا المرفق.');
            }

            return;
        }

        if ($module === 'student_records') {
            if (!$user->canManageStudentStructure()) {
                abort(403, $ability === 'delete'
                    ? 'ليست لديك صلاحية حذف مرفقات ملفات الطلاب.'
                    : 'ليست لديك صلاحية تحميل مرفقات ملفات الطلاب.');
            }

            if (!$attachable instanceof SchoolStudent) {
                abort(404, 'تعذر العثور على الطالب المرتبط بهذا المرفق.');
            }

            return;
        }

        if ($module === 'staff_documents') {
            if (
                !$user->hasSystemRole('super_admin')
                && !$user->hasSystemRole('school_manager')
                && !$user->can('manage-school-users')
            ) {
                abort(403, $ability === 'delete'
                    ? 'ليست لديك صلاحية حذف مستندات مستخدمي المدرسة.'
                    : 'ليست لديك صلاحية تحميل مستندات مستخدمي المدرسة.');
            }

            if (!$attachable instanceof User || !$attachable->hasSystemRole('staff')) {
                abort(404, 'تعذر العثور على مستخدم المدرسة المرتبط بهذا المرفق.');
            }

            return;
        }

        abort(403, 'لا يمكن التعامل مع هذا النوع من المرفقات من هذا المسار.');
    }

    private function resolveSchoolId(Request $request): int
    {
        $user = $request->user();

        $schoolId = (int) ($user?->school_id ?? 0);
        if ($schoolId <= 0 && $user?->hasSystemRole('school_manager')) {
            $schoolId = (int) ($user->managedSchool?->id ?? 0);
        }

        if ($schoolId <= 0) {
            abort(403, 'لا يمكن تنفيذ العملية بدون تحديد المدرسة الحالية.');
        }

        return $schoolId;
    }
}
