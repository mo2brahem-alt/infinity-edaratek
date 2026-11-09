<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Services\Support\{AttachmentAccess, AttachmentService};
use App\Services\Ticketing\SubtaskAccessQuery;
use Illuminate\Http\Request;

class SchoolAttachmentController extends Controller
{
    public function __construct(private readonly AttachmentService $attachmentService) {}

    public function download(Request $request, Attachment $attachment)
    {
        app(SubtaskAccessQuery::class)->rejectAuthority($request);
        return $this->attachmentService->downloadInstitutionalAttachment($attachment, $request->user());
    }

    public function destroy(Request $request, Attachment $attachment)
    {
        app(SubtaskAccessQuery::class)->rejectAuthority($request);
        app(AttachmentAccess::class)->authorize($request->user(), $attachment, 'delete');
        $this->attachmentService->deleteInstitutionalAttachment($attachment, $request, (int) $request->user()->id);
        return back()->with('success', 'تم حذف المرفق بنجاح.');
    }
}
