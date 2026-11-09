<?php

namespace App\Services\Support;

use App\Models\{Attachment, SchoolStudentLeaveAttachment, SchoolAttendanceAttachment, PrivateFileMigration, Media, School, User, Setting};

final class PrivateFileClassification
{
    public static function hasSensitiveReference(string $path): bool
    {
        try {
            return PrivateFileMigration::where('source_disk', 'public')->where('source_path', $path)->exists()
                || Attachment::withTrashed()->where('file_path', $path)->exists()
                || SchoolStudentLeaveAttachment::where('file_path', $path)->exists()
                || SchoolAttendanceAttachment::where('file_path', $path)->exists();
        } catch (\Throwable) { return true; } // An unavailable classification cannot authorize public bytes.
    }

    public static function isIntentionalPublic(string $path): bool
    {
        if (! \App\Support\PublicAssetPath::allows($path)) { return false; }
        try {
            return Media::where('file_path', $path)->whereIn('file_type', ['image', 'video'])->exists()
                || School::where('logo_path', $path)->exists()
                || User::where('profile_photo_path', $path)->exists()
                || Setting::where('type', 'file')->where('value', $path)->exists();
        } catch (\Throwable) { return false; }
    }
}
