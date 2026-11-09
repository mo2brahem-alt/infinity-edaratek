<?php

namespace App\Services\Support;

use App\Models\{Attachment, SchoolStudentLeaveAttachment, SchoolAttendanceAttachment};

final class PrivateFileClassification
{
    public static function hasSensitiveReference(string $path): bool
    {
        try {
            return Attachment::withTrashed()->where('file_path', $path)->exists()
                || SchoolStudentLeaveAttachment::where('file_path', $path)->exists()
                || SchoolAttendanceAttachment::where('file_path', $path)->exists();
        } catch (\Throwable) { return true; } // An unavailable classification cannot authorize public bytes.
    }
}
