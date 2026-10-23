<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel inti AKSI Komisaris (MVP).
 *
 * Setiap tabel bisnis membawa company_id. Konsistensi company_id antara
 * baris induk dan anak dijaga oleh trait BelongsToCompany (lihat
 * App\Models\Concerns\BelongsToCompany) dan diuji di tests/Feature/IsolationTest.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // Organisasi dan akses
        // ---------------------------------------------------------------
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('legal_form', 30)->default('swasta'); // emiten, bumn, anak_bumn, swasta, afiliasi
            $table->string('sector')->nullable();
            $table->unsignedTinyInteger('fiscal_year_start')->default(1);
            $table->boolean('ai_enabled')->default(false);
            $table->boolean('ai_allow_restricted')->default(false);
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('organs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30); // dewan_komisaris, direksi, komite, rups, sekretariat
            $table->string('name');
            $table->text('mandate')->nullable();
            $table->unsignedSmallInteger('quorum_min')->nullable();
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 40);
            $table->string('position_title')->nullable();
            $table->boolean('is_independent')->default(false);
            $table->string('field_of_supervision')->nullable();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });

        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20); // pribadi, dewan, komite, terbatas
            $table->string('name');
            $table->foreignId('organ_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('space_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('access', 20)->default('kontribusi'); // baca, kontribusi, kelola
            $table->date('valid_until')->nullable();
            $table->timestamps();
            $table->unique(['space_id', 'user_id']);
        });

        // ---------------------------------------------------------------
        // Dokumen
        // ---------------------------------------------------------------
        Schema::create('required_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category', 30);
            $table->string('frequency', 20); // bulanan, triwulanan, semesteran, tahunan
            $table->unsignedSmallInteger('due_days_after_period')->default(30);
            $table->string('responsible')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category', 30);
            $table->string('doc_type')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('entity_scope', 20)->nullable(); // konsolidasi, individual
            $table->string('classification', 30)->default('internal');
            $table->string('ai_policy', 20)->default('standar');
            $table->string('status', 20)->default('draf');
            $table->string('owner_unit')->nullable();
            $table->foreignId('required_document_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->boolean('legal_hold')->default(false);
            $table->date('retain_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'space_id', 'category']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('disk_path');
            $table->string('original_name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->char('sha256', 64);
            $table->string('processing_status', 20)->default('antre'); // antre, diproses, siap, perlu_tinjauan, gagal
            $table->string('text_quality', 20)->nullable(); // baik, rendah, tidak_ada_teks
            $table->unsignedInteger('page_count')->nullable();
            $table->text('processing_note')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('change_note')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['document_id', 'version_no']);
        });

        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->string('classification', 30);
            $table->unsignedInteger('page_no')->nullable();
            $table->string('sheet')->nullable();
            $table->string('cell_range')->nullable();
            $table->unsignedInteger('seq')->default(0);
            $table->mediumText('content');
            $table->timestamps();
            $table->index(['company_id', 'space_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE document_chunks ADD FULLTEXT INDEX document_chunks_content_fulltext (content)');
        }

        Schema::create('document_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('expires_at')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['document_id', 'user_id']);
        });

        Schema::create('annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page_no')->nullable();
            $table->text('body');
            $table->string('visibility', 20)->default('pribadi'); // pribadi, bersama
            $table->timestamps();
        });

        Schema::create('required_document_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('required_document_id')->constrained()->cascadeOnDelete();
            $table->string('period_label', 30);
            $table->date('period_end');
            $table->date('due_date');
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('diharapkan'); // diharapkan, diterima, terlambat, belum_diterima
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->unique(['required_document_id', 'period_label'], 'rdr_required_period_unique');
        });

        // ---------------------------------------------------------------
        // Rapat
        // ---------------------------------------------------------------
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organ_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->dateTime('scheduled_at');
            $table->string('location')->nullable();
            $table->dateTime('material_due_at')->nullable();
            $table->string('status', 30)->default('dijadwalkan');
            $table->timestamp('frozen_at')->nullable();
            $table->foreignId('frozen_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('manifest_sha256', 64)->nullable();
            $table->unsignedSmallInteger('quorum_min')->nullable();
            $table->timestamps();
        });

        Schema::create('agenda_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(1);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('presenter')->nullable();
            $table->timestamps();
        });

        Schema::create('agenda_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agenda_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_version_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_addendum')->default(false);
            $table->timestamps();
            $table->unique(['agenda_item_id', 'document_id']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('hadir'); // hadir, tidak_hadir, izin
            $table->timestamps();
            $table->unique(['meeting_id', 'user_id']);
        });

        Schema::create('conflict_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agenda_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->text('nature');
            $table->boolean('recusal')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('minutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version_no')->default(1);
            $table->longText('body');
            $table->string('status', 20)->default('draf'); // draf, diedarkan, disahkan
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agenda_item_id')->nullable()->constrained()->nullOnDelete();
            $table->text('text');
            $table->string('decision_type', 30)->default('keputusan'); // keputusan, rekomendasi, catatan
            $table->timestamps();
        });

        Schema::create('dissents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resolution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('statement');
            $table->timestamps();
        });

        // ---------------------------------------------------------------
        // Pengawasan: isu, catatan pribadi, permintaan informasi, nasihat
        // ---------------------------------------------------------------
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('materiality', 10)->default('sedang');
            $table->string('evidence_status', 30)->default('indikasi');
            $table->string('status', 30)->default('dibuka');
            $table->date('data_period_end')->nullable();
            $table->string('source_note')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('personal_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->string('promoted_to_type')->nullable();
            $table->unsignedBigInteger('promoted_to_id')->nullable();
            $table->timestamps();
        });

        Schema::create('information_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('information_requests')->nullOnDelete();
            $table->foreignId('issue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->text('question');
            $table->text('basis')->nullable();
            $table->text('purpose')->nullable();
            $table->foreignId('addressee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('draf');
            $table->string('assessment', 30)->nullable();
            $table->text('assessment_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('request_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('information_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('responder_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('advices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 30)->default('nasihat'); // catatan_pengawasan, nasihat, rekomendasi, persetujuan
            $table->string('title');
            $table->text('body');
            $table->text('basis')->nullable();
            $table->string('status', 20)->default('draf'); // draf, disahkan, disampaikan, ditanggapi
            $table->text('management_response')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        // ---------------------------------------------------------------
        // Tindak lanjut
        // ---------------------------------------------------------------
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('source');
            $table->text('action_text');
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pic_label')->nullable();
            $table->date('original_target_date');
            $table->date('target_date');
            $table->text('expected_output')->nullable();
            $table->text('required_evidence');
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draf');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('timeliness', 20)->nullable(); // tepat_waktu, terlambat
            $table->unsignedTinyInteger('escalation_level')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('follow_up_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('follow_up_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description');
            $table->string('disk_path')->nullable();
            $table->string('original_name')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('follow_up_deadline_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('follow_up_id')->constrained()->cascadeOnDelete();
            $table->date('old_date');
            $table->date('new_date');
            $table->text('reason');
            $table->string('status', 20)->default('diajukan'); // diajukan, disetujui, ditolak
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('follow_up_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('follow_up_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // ---------------------------------------------------------------
        // AI dan audit
        // ---------------------------------------------------------------
        Schema::create('ai_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 40);
            $table->text('prompt');
            $table->json('scope')->nullable(); // document_ids, meeting_id, information_request_id
            $table->string('status', 20)->default('antre'); // antre, berjalan, selesai, gagal
            $table->string('model')->nullable();
            $table->string('template_version', 20)->nullable();
            $table->char('request_sha256', 64)->nullable();
            $table->json('sources')->nullable();
            $table->json('excluded_sources')->nullable();
            $table->json('calculations')->nullable();
            $table->json('tool_calls')->nullable();
            $table->json('output')->nullable();
            $table->json('validation')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach ([
            'audit_logs', 'ai_jobs', 'follow_up_events', 'follow_up_deadline_changes', 'follow_up_evidences',
            'follow_ups', 'advices', 'request_responses', 'information_requests', 'personal_notes', 'issues',
            'dissents', 'resolutions', 'minutes', 'conflict_declarations', 'attendances', 'agenda_materials',
            'agenda_items', 'meetings', 'required_document_receipts', 'annotations', 'document_grants',
            'document_chunks', 'document_versions', 'documents', 'required_documents', 'space_members',
            'spaces', 'memberships', 'organs', 'companies',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
