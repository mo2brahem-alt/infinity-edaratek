<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('school_student_leave_attachments')) {
            return;
        }

        Schema::create('school_student_leave_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->unsignedBigInteger('school_student_leave_request_id');
            $table->foreign('school_student_leave_request_id', 'ssla_leave_req_fk')
                ->references('id')
                ->on('school_student_leave_requests')
                ->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path', 1024);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index(
                ['school_id', 'school_student_leave_request_id'],
                'school_student_leave_attachment_lookup_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_student_leave_attachments');
    }
};
