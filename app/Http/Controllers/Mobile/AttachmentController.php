<?php

namespace App\Http\Controllers\Mobile;

use App\Models\{Attachment, SchoolStudentLeaveRequest, SchoolStudentLeaveAttachment};
use App\Services\Support\{AttachmentAccess, AttachmentService, PrivateFileReader};
use App\Services\Ticketing\SubtaskAccessQuery;
use Illuminate\Http\Request;

final class AttachmentController
{
    public function download(Request $request, string $attachment)
    {
        app(SubtaskAccessQuery::class)->rejectAuthority($request);
        if (! $request->user()->school_id && ! $request->user()->hasSystemRole('super_admin') && ! $request->user()->hasSystemRole('supervisor')) {
            app(\App\Services\School\SchoolContextResolver::class)->current($request->user());
        }
        $file = Attachment::query()->findOrFail($this->id($attachment));
        app(AttachmentAccess::class)->authorize($request->user(), $file);
        return app(AttachmentService::class)->downloadInstitutionalAttachment($file, $request->user());
    }

    public function leave(Request $request, string $leave, string $attachment)
    {
        app(SubtaskAccessQuery::class)->rejectAuthority($request);
        app(\App\Services\School\SchoolContextResolver::class)->current($request->user());
        abort_unless($request->user()->canManageStudentLeaves(), 403);
        $parent = SchoolStudentLeaveRequest::query()->findOrFail($this->id($leave));
        $file = SchoolStudentLeaveAttachment::query()->findOrFail($this->id($attachment));
        app(AttachmentAccess::class)->leave($request->user(), $parent, $file);
        return app(PrivateFileReader::class)->download('local', $file->file_path, $file->file_name);
    }

    private function id(string $id): int
    {
        $value = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($value === false, 404);
        return $value;
    }
}
