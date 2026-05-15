<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 80);
            $table->string('orientation', 24)->default('landscape');
            $table->string('paper_size', 24)->default('A4');
            $table->string('frame_key', 80)->nullable();
            $table->string('background_disk', 80)->nullable();
            $table->string('background_path')->nullable();
            $table->json('layout_json')->nullable();
            $table->text('title_text')->nullable();
            $table->json('title_style_json')->nullable();
            $table->json('student_name_style_json')->nullable();
            $table->json('body_style_json')->nullable();
            $table->json('date_style_json')->nullable();
            $table->json('signature_style_json')->nullable();
            $table->longText('default_body')->nullable();
            $table->string('default_gender_mode', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'type', 'is_active']);
        });

        Schema::create('school_certificate_signatures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('signature_disk', 80)->nullable();
            $table->string('signature_path')->nullable();
            $table->string('stamp_disk', 80)->nullable();
            $table->string('stamp_path')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'is_active', 'is_default'], 'school_certificate_signatures_scope_idx');
        });

        Schema::create('student_certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_student_id')->nullable()->constrained('school_students')->nullOnDelete();
            $table->string('recipient_type', 40)->default('student');
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_label')->nullable();
            $table->json('recipient_context_json')->nullable();
            $table->foreignId('certificate_template_id')->nullable()->constrained('certificate_templates')->nullOnDelete();
            $table->foreignId('school_certificate_signature_id')->nullable()->constrained('school_certificate_signatures')->nullOnDelete();
            $table->string('certificate_number')->unique();
            $table->string('type', 80);
            $table->string('title');
            $table->longText('body');
            $table->json('rendered_data_json')->nullable();
            $table->string('pdf_disk', 80)->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('status', 40)->default('issued');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->string('verification_token', 120)->nullable()->unique();
            $table->string('verification_qr_disk', 80)->nullable();
            $table->string('verification_qr_path')->nullable();
            $table->foreignId('school_academic_year_id')->nullable()->constrained('school_academic_years')->nullOnDelete();
            $table->foreignId('school_term_id')->nullable()->constrained('school_terms')->nullOnDelete();
            $table->foreignId('school_stage_id')->nullable()->constrained('school_stages')->nullOnDelete();
            $table->foreignId('school_stage_grade_id')->nullable()->constrained('school_stage_grades')->nullOnDelete();
            $table->foreignId('school_classroom_id')->nullable()->constrained('school_classrooms')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'school_student_id', 'status']);
            $table->index(['school_id', 'recipient_type', 'recipient_id'], 'student_certificates_recipient_idx');
            $table->index(['school_id', 'type', 'issued_at']);
            $table->index(['school_id', 'school_classroom_id', 'issued_at'], 'student_certificates_classroom_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_certificates');
        Schema::dropIfExists('school_certificate_signatures');
        Schema::dropIfExists('certificate_templates');
    }
};
