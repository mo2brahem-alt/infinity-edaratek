<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Services\School\SchoolDefaultDataProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DefaultDataImportController extends Controller
{
    public function __construct(
        private readonly SchoolDefaultDataProvisioningService $schoolDefaultDataProvisioningService,
    ) {
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if (!$user?->canImportSchoolDefaultData()) {
            abort(403, 'لا تملك صلاحية استيراد البيانات الافتراضية لهذه المدرسة.');
        }

        $schoolId = $this->resolveSchoolId($request);
        $result = $this->schoolDefaultDataProvisioningService->importForSchool(
            $schoolId,
            (int) $user->id,
            $request
        );
        $importedItemsCount = array_sum((array) ($result['counts'] ?? []));
        $wasPreviouslyImported = (bool) ($result['was_previously_imported'] ?? false);

        $message = match (true) {
            $importedItemsCount <= 0 => 'لا توجد عناصر جديدة مطابقة للاستيراد حاليًا، والبيانات الحالية داخل المدرسة بقيت كما هي.',
            $wasPreviouslyImported => 'تم استيراد العناصر الجديدة المطابقة إلى هذه المدرسة فقط، دون تعديل البيانات الحالية الموجودة فيها.',
            default => 'تم استيراد البيانات الافتراضية للمدرسة بنجاح، ويمكن الآن تخصيصها داخل هذه المدرسة فقط.',
        };

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'data' => $result,
            ]);
        }

        return back()->with('success', $message);
    }

    private function resolveSchoolId(Request $request): int
    {
        $schoolId = (int) $request->attributes->get('school_context_id', (int) ($request->user()?->school_id ?? 0));

        if ($schoolId <= 0) {
            throw ValidationException::withMessages([
                'school' => 'تعذر تحديد المدرسة الحالية لتنفيذ الاستيراد.',
            ]);
        }

        return $schoolId;
    }
}
