<?php

namespace Database\Seeders;

use App\Enums\FollowUpStatus;
use App\Models\Advice;
use App\Models\AiJob;
use App\Models\Company;
use App\Models\Document;
use App\Models\FollowUp;
use App\Models\InformationRequest;
use App\Models\Issue;
use App\Models\Meeting;
use App\Models\Membership;
use App\Models\PersonalNote;
use App\Models\RequiredDocument;
use App\Models\RequiredDocumentReceipt;
use App\Models\Resolution;
use App\Models\Space;
use App\Models\User;
use App\Services\Ai\AiGateway;
use App\Services\Ai\OpenAiClient;
use App\Services\DocumentService;
use App\Services\FollowUpService;
use App\Services\MeetingService;
use App\Support\CurrentCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Data demo fiktif untuk promosi dan uji coba.
 *
 * Dua perusahaan (BUMN induk dan anak usaha) agar pola multi-perusahaan
 * dan tampilan portofolio terlihat. Semua nama dan angka rekaan.
 * Kata sandi seluruh pengguna demo: AKSI_DEMO_PASSWORD (bawaan: Demo#2026aksi).
 */
class DemoSeeder extends Seeder
{
    protected string $password;

    /** @var array<string, User> */
    protected array $users = [];

    public function run(): void
    {
        $this->password = (string) env('AKSI_DEMO_PASSWORD', 'Demo#2026aksi');
        config(['queue.default' => 'sync']);

        if (Company::where('code', 'ENR')->exists()) {
            $this->command?->warn('Data demo sudah ada (kode ENR). Lewati.');

            return;
        }

        $this->users = [
            'utama' => $this->user('Dr. Hendra Wiratama', 'utama@demo.aksi.id'),
            'independen' => $this->user('Ratna Kusumawardani, CPA', 'independen@demo.aksi.id'),
            'komisaris' => $this->user('Bambang Sutrisno', 'komisaris@demo.aksi.id'),
            'komite' => $this->user('Agus Setiadi, Ak.', 'komite@demo.aksi.id'),
            'sekretariat' => $this->user('Maya Lestari', 'sekretariat@demo.aksi.id'),
            'direksi' => $this->user('Ir. Dimas Prasetyo (Direktur Keuangan)', 'direksi@demo.aksi.id'),
            'admin' => $this->user('Admin Perusahaan', 'admin@demo.aksi.id'),
        ];

        $parent = Company::create([
            'code' => 'ENR', 'name' => 'PT Energi Nusantara (Persero)', 'legal_form' => 'bumn',
            'sector' => 'Energi', 'ai_enabled' => true,
        ]);
        $subsidiary = Company::create([
            'code' => 'ENR-LOG', 'name' => 'PT Logistik Energi Nusantara', 'legal_form' => 'anak_bumn',
            'sector' => 'Logistik', 'ai_enabled' => false,
        ]);

        app(CurrentCompany::class)->runAs($parent, fn () => $this->seedParent($parent));
        app(CurrentCompany::class)->runAs($subsidiary, fn () => $this->seedSubsidiary($subsidiary));

        Artisan::call('aksi:required-documents');
        Artisan::call('aksi:followups');

        // Periode lama dianggap sudah diterima; laporan manajemen terakhir juga.
        app(CurrentCompany::class)->runAs($parent, function () {
            RequiredDocumentReceipt::whereDate('due_date', '<', now()->subDays(45))->update(['status' => 'diterima', 'received_at' => now()->subDays(50)]);

            $required = RequiredDocument::where('title', 'Laporan Manajemen Triwulanan')->first();
            $receipt = $required?->receipts()->orderByDesc('period_end')->first();
            $receipt?->update([
                'status' => 'diterima', 'received_at' => now()->subDays(20),
                'document_id' => Document::where('category', 'kinerja')->orderBy('id')->value('id'),
            ]);
        });

        $this->command?->info('Data demo siap. Masuk di /app dengan salah satu akun berikut, kata sandi: '.$this->password);
        $this->command?->table(['Peran', 'Email'], [
            ['Komisaris Utama', 'utama@demo.aksi.id'],
            ['Komisaris Independen', 'independen@demo.aksi.id'],
            ['Komisaris', 'komisaris@demo.aksi.id'],
            ['Anggota Komite Audit', 'komite@demo.aksi.id'],
            ['Sekretariat Dewan Komisaris', 'sekretariat@demo.aksi.id'],
            ['Direksi (penghubung)', 'direksi@demo.aksi.id'],
            ['Admin Perusahaan', 'admin@demo.aksi.id'],
        ]);
    }

    protected function user(string $name, string $email): User
    {
        return User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => $this->password, 'is_active' => true]);
    }

    protected function member(Company $company, string $key, string $role, string $title, bool $independent = false): void
    {
        Membership::create([
            'company_id' => $company->id,
            'user_id' => $this->users[$key]->id,
            'role' => $role,
            'position_title' => $title,
            'is_independent' => $independent,
            'valid_from' => now()->subYears(2)->startOfYear(),
            'valid_until' => now()->addYears(3)->endOfYear(),
        ]);
        $this->users[$key]->forgetMembershipCache();
    }

    protected function seedParent(Company $company): void
    {
        $u = $this->users;
        $this->member($company, 'utama', 'komisaris_utama', 'Komisaris Utama');
        $this->member($company, 'independen', 'komisaris_independen', 'Komisaris Independen / Ketua Komite Audit', true);
        $this->member($company, 'komisaris', 'komisaris', 'Komisaris');
        $this->member($company, 'komite', 'anggota_komite', 'Anggota Komite Audit (non-komisaris)');
        $this->member($company, 'sekretariat', 'sekretariat', 'Sekretaris Dewan Komisaris');
        $this->member($company, 'direksi', 'penghubung', 'Direktur Keuangan');
        $this->member($company, 'admin', 'admin_perusahaan', 'Admin TI');

        $board = $company->boardSpace();
        $committeeOrgan = $company->organs()->where('kind', 'komite')->first();
        $committee = Space::create([
            'company_id' => $company->id, 'kind' => 'komite', 'name' => 'Ruang Komite Audit', 'organ_id' => $committeeOrgan?->id,
        ]);
        $committee->members()->attach([$u['independen']->id => ['access' => 'kelola'], $u['komite']->id => ['access' => 'kontribusi'], $u['sekretariat']->id => ['access' => 'kelola']]);
        $private = Space::where('kind', 'pribadi')->where('owner_user_id', $u['independen']->id)->first();

        $year = now()->year;
        $q = intdiv(now()->month - 1, 3) ?: 4;
        $qYear = $q === 4 && now()->month <= 3 ? $year - 1 : $year;
        $periodEnd = now()->setDate($qYear, $q * 3, 1)->endOfMonth();

        // Dokumen wajib.
        foreach ([
            ['Laporan Keuangan Bulanan', 'keuangan', 'bulanan', 20, 'Direktorat Keuangan'],
            ['Laporan Manajemen Triwulanan', 'kinerja', 'triwulanan', 30, 'Direktorat Keuangan'],
            ['Profil Risiko Triwulanan', 'risiko', 'triwulanan', 30, 'Divisi Manajemen Risiko'],
            ['Laporan Hasil Audit Internal', 'audit', 'semesteran', 45, 'SPI'],
            ['RKAP', 'perencanaan', 'tahunan', 30, 'Divisi Perencanaan Korporat'],
        ] as [$title, $category, $frequency, $due, $responsible]) {
            RequiredDocument::create(compact('title', 'category', 'frequency', 'responsible') + ['due_days_after_period' => $due]);
        }

        // Dokumen dengan isi teks sehingga pencarian dan AI bisa diuji tanpa OCR.
        $docs = app(DocumentService::class);
        $lm = $this->document($docs, $board, $u['sekretariat'], 'Laporan Manajemen Triwulan '.$q.' '.$qYear, 'kinerja', 'internal', 'standar', $periodEnd, 'laporan-manajemen.txt', $this->managementReport($q, $qYear));
        $realisasi = $this->document($docs, $board, $u['sekretariat'], 'Realisasi RKAP Triwulan '.$q.' '.$qYear, 'keuangan', 'internal', 'standar', $periodEnd, 'realisasi-rkap.csv', $this->rkapCsv());
        $risk = $this->document($docs, $board, $u['sekretariat'], 'Profil Risiko Triwulan '.$q.' '.$qYear, 'risiko', 'rahasia', 'khusus', $periodEnd, 'profil-risiko.txt', $this->riskProfile());
        $spi = $this->document($docs, $committee, $u['sekretariat'], 'Laporan Hasil Audit SPI Pengadaan Suku Cadang', 'audit', 'rahasia', 'standar', $periodEnd, 'lha-spi.txt', $this->auditReport());
        $this->document($docs, $board, $u['sekretariat'], 'Rencana Akuisisi PLTU Mitra (Strictly Confidential)', 'korporasi', 'sangat_terbatas', 'dilarang', $periodEnd, 'akuisisi.txt', "Rancangan transaksi akuisisi. Dokumen ini tidak boleh diproses AI.\nNilai indikatif transaksi Rp 4,2 triliun.");
        $this->document($docs, $private, $u['independen'], 'Catatan kerja pribadi - telaah biaya pemeliharaan', 'pengawasan', 'rahasia', 'standar', $periodEnd, 'catatan.txt', "Catatan pribadi Komisaris Independen. Tidak terlihat oleh anggota lain.\nPerlu membandingkan biaya pemeliharaan per MW dengan BUMN sejenis.");

        // Rapat dewan dengan paket yang sudah dibekukan.
        $meeting = Meeting::create([
            'space_id' => $board->id, 'organ_id' => $board->organ_id,
            'title' => 'Rapat Dewan Komisaris Triwulan '.$q.' '.$qYear,
            'scheduled_at' => now()->subDays(10)->setTime(9, 0), 'location' => 'Ruang Rapat Lt. 12 / Zoom',
            'material_due_at' => now()->subDays(15),
        ]);
        $agenda1 = $meeting->agendaItems()->create(['company_id' => $company->id, 'sort' => 1, 'title' => 'Kinerja keuangan dan realisasi RKAP', 'presenter' => 'Direktur Keuangan']);
        $agenda2 = $meeting->agendaItems()->create(['company_id' => $company->id, 'sort' => 2, 'title' => 'Profil risiko dan tindak lanjut temuan SPI', 'presenter' => 'Kepala SPI']);
        $meetings = app(MeetingService::class);
        $meetings->attachMaterial($meeting, $agenda1->id, $lm->id);
        $meetings->attachMaterial($meeting, $agenda1->id, $realisasi->id);
        $meetings->attachMaterial($meeting, $agenda2->id, $risk->id);
        $meetings->freeze($meeting, $u['sekretariat']);
        foreach (['utama', 'independen', 'komisaris', 'sekretariat'] as $key) {
            $meeting->attendances()->create(['company_id' => $company->id, 'user_id' => $u[$key]->id, 'status' => 'hadir']);
        }
        $meeting->update(['status' => 'selesai']);
        $meeting->minutes()->create(['company_id' => $company->id, 'body' => "Risalah rapat (draf).\n\n1. Dewan mencatat EBITDA di bawah target RKAP sebesar 8,4%.\n2. Dewan meminta Direksi menyampaikan rencana pemulihan margin.\n3. Komisaris Independen menyampaikan catatan atas temuan SPI pengadaan suku cadang.", 'status' => 'draf']);
        $resolution = Resolution::create(['meeting_id' => $meeting->id, 'agenda_item_id' => $agenda1->id, 'text' => 'Direksi diminta menyampaikan rencana pemulihan margin EBITDA beserta target bulanan paling lambat 30 hari setelah rapat.', 'decision_type' => 'keputusan']);

        // Rapat mendatang yang masih menunggu bahan.
        $next = Meeting::create([
            'space_id' => $board->id, 'organ_id' => $board->organ_id,
            'title' => 'Rapat Gabungan Dewan Komisaris dan Direksi', 'scheduled_at' => now()->addDays(12)->setTime(14, 0),
            'location' => 'Kantor Pusat', 'material_due_at' => now()->addDays(7),
        ]);
        $nextAgenda = $next->agendaItems()->create(['company_id' => $company->id, 'sort' => 1, 'title' => 'Pembahasan RKAP tahun berikutnya', 'presenter' => 'Direktur Utama']);
        $meetings->attachMaterial($next, $nextAgenda->id, $realisasi->id);

        // Isu pengawasan, permintaan informasi, nasihat.
        $issue = Issue::create([
            'space_id' => $board->id, 'title' => 'Penurunan margin EBITDA akibat kenaikan biaya bahan bakar',
            'description' => 'Margin EBITDA turun dari 24,1% menjadi 19,8%. Perlu dipastikan apakah bersifat sementara.',
            'materiality' => 'tinggi', 'evidence_status' => 'penjelasan_manajemen', 'status' => 'menunggu_manajemen',
            'data_period_end' => $periodEnd, 'owner_user_id' => $u['utama']->id, 'created_by' => $u['utama']->id,
        ]);
        Issue::create([
            'space_id' => $committee->id, 'title' => 'Indikasi pemecahan paket pengadaan suku cadang',
            'description' => 'LHA SPI mencatat 14 paket pengadaan di bawah ambang lelang dengan vendor yang sama.',
            'materiality' => 'tinggi', 'evidence_status' => 'indikasi', 'status' => 'dipantau',
            'data_period_end' => $periodEnd, 'owner_user_id' => $u['independen']->id, 'created_by' => $u['independen']->id,
        ]);

        $answered = InformationRequest::create([
            'space_id' => $board->id, 'issue_id' => $issue->id, 'meeting_id' => $meeting->id,
            'question' => 'Mohon rincian kenaikan biaya bahan bakar per pembangkit dan asumsi harga yang dipakai dalam RKAP.',
            'basis' => 'Laporan Manajemen Triwulan '.$q.' halaman 3', 'purpose' => 'Menilai apakah penurunan margin bersifat sementara.',
            'addressee_user_id' => $u['direksi']->id, 'due_date' => now()->subDays(2), 'status' => 'dijawab',
            'created_by' => $u['independen']->id, 'approved_by' => $u['utama']->id, 'sent_at' => now()->subDays(9), 'answered_at' => now()->subDays(3),
        ]);
        $answered->responses()->create(['company_id' => $company->id, 'responder_id' => $u['direksi']->id, 'body' => 'Kenaikan biaya bahan bakar terutama di PLTGU Tambak Lorok (+Rp 112 miliar) karena harga gas naik 9%. Asumsi RKAP USD 6,2/MMBTU, realisasi USD 6,8/MMBTU.']);
        InformationRequest::create([
            'space_id' => $board->id, 'issue_id' => $issue->id,
            'question' => 'Mohon rencana lindung nilai harga gas untuk dua triwulan ke depan.',
            'purpose' => 'Menilai mitigasi risiko harga komoditas.', 'addressee_user_id' => $u['direksi']->id,
            'due_date' => now()->addDays(10), 'status' => 'diajukan', 'created_by' => $u['komisaris']->id,
        ]);

        $advice = Advice::create([
            'space_id' => $board->id, 'meeting_id' => $meeting->id, 'kind' => 'rekomendasi',
            'title' => 'Rekomendasi pemulihan margin EBITDA',
            'body' => "Dewan Komisaris merekomendasikan Direksi untuk:\n1. Menyusun rencana pemulihan margin dengan target bulanan.\n2. Mengkaji kontrak gas jangka panjang dan opsi lindung nilai.\n3. Melaporkan perkembangan dalam laporan manajemen bulanan.",
            'basis' => 'Laporan Manajemen Triwulan '.$q.'; Realisasi RKAP', 'status' => 'disampaikan',
            'created_by' => $u['independen']->id, 'approved_by' => $u['utama']->id, 'approved_at' => now()->subDays(8), 'delivered_at' => now()->subDays(7),
        ]);

        // Tindak lanjut dengan berbagai status, termasuk yang terlambat.
        $service = app(FollowUpService::class);
        $f1 = $this->followUp($resolution, 'Menyampaikan rencana pemulihan margin EBITDA beserta target bulanan.', $u, now()->addDays(20), 'Dokumen rencana pemulihan margin yang ditandatangani Direksi.');
        $this->advance($service, $f1, ['disahkan' => 'utama', 'disampaikan' => 'sekretariat', 'ditanggapi' => 'direksi']);

        $f2 = $this->followUp($advice, 'Mengkaji opsi lindung nilai harga gas.', $u, now()->subDays(5), 'Kajian opsi lindung nilai dan keputusan Direksi.');
        $this->advance($service, $f2, ['disahkan' => 'utama', 'disampaikan' => 'sekretariat', 'ditanggapi' => 'direksi', 'dilaksanakan' => 'direksi']);

        $f3 = $this->followUp($answered, 'Memperbarui asumsi harga gas dalam proyeksi RKAP.', $u, now()->subDays(25), 'Proyeksi RKAP terbaru dengan asumsi harga gas yang diperbarui.');
        $this->advance($service, $f3, ['disahkan' => 'utama', 'disampaikan' => 'sekretariat']);

        $f4 = $this->followUp($resolution, 'Menyampaikan laporan realisasi RKAP bulanan kepada Dewan.', $u, now()->subDays(3), 'Laporan realisasi bulanan.');
        $this->advance($service, $f4, ['disahkan' => 'utama', 'disampaikan' => 'sekretariat', 'ditanggapi' => 'direksi', 'dilaksanakan' => 'direksi']);
        $f4->evidences()->create(['company_id' => $company->id, 'uploaded_by' => $u['direksi']->id, 'description' => 'Laporan realisasi RKAP bulan lalu telah dikirim ke Sekretariat.']);
        $service->transition($f4->refresh(), FollowUpStatus::Diperiksa, $u['direksi']);
        $service->verifyEvidence($f4->refresh(), $f4->evidences()->first()->id, $u['independen']);
        $service->transition($f4->refresh(), FollowUpStatus::Ditutup, $u['independen'], 'Bukti sesuai.');

        $this->sampleAiBrief($meeting, $u['utama']);

        PersonalNote::create([
            'user_id' => $u['independen']->id, 'title' => 'Pertanyaan untuk rapat berikutnya', 'meeting_id' => $next->id,
            'body' => "- Mengapa biaya pemeliharaan naik 17% padahal jam operasi turun?\n- Minta pembanding dengan anak usaha logistik.",
        ]);
    }

    protected function seedSubsidiary(Company $company): void
    {
        $this->member($company, 'independen', 'komisaris_utama', 'Komisaris Utama (rangkap)');
        $this->member($company, 'sekretariat', 'sekretariat', 'Sekretaris Dewan Komisaris');
        $this->member($company, 'direksi', 'penghubung', 'Direktur Utama');

        $board = $company->boardSpace();
        $docs = app(DocumentService::class);
        $this->document($docs, $board, $this->users['sekretariat'], 'Laporan Kinerja Armada Semester I', 'kinerja', 'internal', 'standar', now()->startOfYear()->addMonths(5)->endOfMonth(), 'armada.txt', "Utilisasi armada kapal tanker 78% (target 85%).\nPendapatan Rp 1.240 miliar, laba bersih Rp 86 miliar.");
        RequiredDocument::create(['title' => 'Laporan Keuangan Bulanan', 'category' => 'keuangan', 'frequency' => 'bulanan', 'due_days_after_period' => 20]);

        $issue = Issue::create([
            'space_id' => $board->id, 'title' => 'Utilisasi armada di bawah target', 'materiality' => 'sedang',
            'evidence_status' => 'fakta_terverifikasi', 'status' => 'dibuka', 'created_by' => $this->users['independen']->id,
        ]);
        $f = $this->followUp($issue, 'Menyusun rencana peningkatan utilisasi armada.', ['direksi' => $this->users['direksi'], 'independen' => $this->users['independen'], 'utama' => $this->users['independen']], now()->subDays(30), 'Rencana kerja peningkatan utilisasi.');
        $service = app(FollowUpService::class);
        $this->advance($service, $f, ['disahkan' => 'independen', 'disampaikan' => 'sekretariat']);
    }

    protected function document(DocumentService $service, Space $space, User $user, string $title, string $category, string $classification, string $policy, $periodEnd, string $fileName, string $content): Document
    {
        $document = Document::create([
            'space_id' => $space->id, 'title' => $title, 'category' => $category, 'classification' => $classification,
            'ai_policy' => $policy, 'status' => 'final', 'period_end' => $periodEnd, 'created_by' => $user->id,
            'period_start' => (clone $periodEnd)->startOfQuarter(), 'entity_scope' => 'konsolidasi',
        ]);
        $temp = tempnam(sys_get_temp_dir(), 'aksi');
        file_put_contents($temp, $content);
        $service->addVersion($document, $temp, $fileName, $user, 'Versi awal (demo)');
        @unlink($temp);

        return $document->refresh();
    }

    protected function followUp($source, string $action, array $u, $target, string $evidence): FollowUp
    {
        return FollowUp::create([
            'source_type' => $source::class, 'source_id' => $source->id, 'action_text' => $action,
            'pic_user_id' => $u['direksi']->id, 'pic_label' => 'Direktorat Keuangan',
            'original_target_date' => $target, 'target_date' => $target,
            'required_evidence' => $evidence, 'reviewer_user_id' => $u['independen']->id,
            'created_by' => $u['utama']->id,
        ]);
    }

    /** @param  array<string, string>  $steps  status tujuan => kunci pengguna */
    protected function advance(FollowUpService $service, FollowUp $followUp, array $steps): void
    {
        foreach ($steps as $status => $key) {
            $service->transition($followUp->refresh(), FollowUpStatus::from($status), $this->users[$key]);
        }
    }

    /**
     * Contoh hasil asisten AI agar fitur bisa diperagakan tanpa kunci OpenAI.
     * Melewati gateway dan validator yang sama; hanya jawaban model yang direkam.
     */
    protected function sampleAiBrief(Meeting $meeting, User $user): void
    {
        $job = AiJob::create([
            'user_id' => $user->id, 'mode' => 'siapkan_rapat', 'scope' => ['meeting_id' => $meeting->id],
            'prompt' => 'Siapkan saya untuk rapat ini: isu material, angka penting, dan pertanyaan yang perlu diajukan. (Contoh hasil demo)',
        ]);

        $output = [
            'summary' => 'Kinerja triwulan di bawah RKAP terutama karena biaya bahan bakar. Margin EBITDA turun dan risiko harga energi primer berstatus tinggi dengan mitigasi yang belum selesai.',
            'items' => [
                ['kind' => 'fact', 'text' => 'EBITDA tercatat Rp 3.653 miliar, di bawah target RKAP Rp 3.988 miliar.', 'materiality' => 'tinggi', 'calc_ids' => [],
                    'citations' => [['source_id' => 'S1', 'quote' => 'EBITDA tercatat Rp 3.653 miliar, di bawah target RKAP Rp 3.988 miliar']]],
                ['kind' => 'management_explanation', 'text' => 'Manajemen menjelaskan kenaikan biaya bahan bakar karena harga gas naik dari USD 6,2 menjadi USD 6,8 per MMBTU.', 'materiality' => 'tinggi', 'calc_ids' => [],
                    'citations' => [['source_id' => 'S1', 'quote' => 'harga gas di PLTGU Tambak Lorok naik dari USD 6,2 menjadi USD 6,8 per MMBTU']]],
                ['kind' => 'fact', 'text' => 'Risiko harga energi primer berada pada tingkat Tinggi dengan tren meningkat.', 'materiality' => 'tinggi', 'calc_ids' => [],
                    'citations' => [['source_id' => 'S3', 'quote' => 'Tingkat risiko: Tinggi. Tren: meningkat.']]],
                ['kind' => 'analysis', 'text' => 'Penurunan margin kemungkinan berlanjut bila kontrak gas belum dinegosiasi ulang. Ini dugaan, perlu konfirmasi Direksi.', 'materiality' => 'sedang', 'calc_ids' => [],
                    'citations' => [['source_id' => 'S3', 'quote' => 'renegosiasi kontrak gas, kajian lindung nilai']]],
                ['kind' => 'missing_info', 'text' => 'Belum ada rincian rencana efisiensi Rp 250 miliar per pos biaya.', 'materiality' => 'sedang', 'calc_ids' => [], 'citations' => []],
                ['kind' => 'question', 'text' => 'Kapan renegosiasi kontrak gas ditargetkan selesai dan berapa dampaknya pada margin?', 'materiality' => 'tinggi', 'calc_ids' => [], 'citations' => []],
                ['kind' => 'question', 'text' => 'Mengapa overhaul unit 3 dimajukan dan apakah sudah dianggarkan?', 'materiality' => 'sedang', 'calc_ids' => [],
                    'citations' => [['source_id' => 'S1', 'quote' => 'overhaul unit 3 dimajukan']]],
            ],
            'conflicts' => [],
            'missing_information' => ['Laporan arus kas triwulan belum ada di paket rapat.'],
            'limitations' => ['Contoh hasil demo yang direkam, bukan panggilan langsung ke OpenAI.'],
        ];

        $fake = new class($output) extends OpenAiClient
        {
            public function __construct(protected array $output) {}

            public function respond(array $payload): array
            {
                return [
                    'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($this->output, JSON_UNESCAPED_UNICODE)]]]],
                    'usage' => ['input_tokens' => 4210, 'output_tokens' => 860],
                ];
            }
        };

        app()->instance(OpenAiClient::class, $fake);
        app()->forgetInstance(AiGateway::class);
        app(AiGateway::class)->run($job);
        app()->forgetInstance(OpenAiClient::class);
        app()->forgetInstance(AiGateway::class);
        $job->forceFill(['model' => 'contoh-demo'])->save();
    }

    protected function managementReport(int $q, int $year): string
    {
        return <<<TXT
LAPORAN MANAJEMEN TRIWULAN {$q} TAHUN {$year}
PT Energi Nusantara (Persero) - Konsolidasi

1. Ringkasan Kinerja
Pendapatan usaha mencapai Rp 18.450 miliar atau 96,2% dari target RKAP sebesar Rp 19.180 miliar.
EBITDA tercatat Rp 3.653 miliar, di bawah target RKAP Rp 3.988 miliar (pencapaian 91,6%).
Margin EBITDA turun menjadi 19,8% dibanding 24,1% pada periode yang sama tahun lalu.
Laba bersih Rp 1.214 miliar (target Rp 1.420 miliar).

2. Faktor Utama
Biaya bahan bakar naik 11,3% terutama karena harga gas di PLTGU Tambak Lorok naik dari USD 6,2 menjadi USD 6,8 per MMBTU.
Biaya pemeliharaan naik 17% karena overhaul unit 3 dimajukan.
Penjualan listrik 21.300 GWh, 98,1% dari target.

3. Investasi
Realisasi belanja modal Rp 2.150 miliar atau 61% dari anggaran tahunan Rp 3.520 miliar.
Proyek PLTS Terapung Saguling 60 MWp mencapai progres fisik 72%.

4. Rencana Tindak
Direksi menyiapkan program efisiensi biaya dengan target penghematan Rp 250 miliar sampai akhir tahun.
TXT;
    }

    protected function rkapCsv(): string
    {
        return "Pos,RKAP (Rp miliar),Realisasi (Rp miliar),Pencapaian (%)\n"
            ."Pendapatan usaha,19180,18450,96.2\n"
            ."Beban bahan bakar,9850,10963,111.3\n"
            ."Beban pemeliharaan,1420,1661,117.0\n"
            ."EBITDA,3988,3653,91.6\n"
            ."Laba bersih,1420,1214,85.5\n"
            ."Belanja modal,3520,2150,61.1\n";
    }

    protected function riskProfile(): string
    {
        return <<<'TXT'
PROFIL RISIKO KORPORAT

Risiko 1 - Harga energi primer (gas dan batubara). Tingkat risiko: Tinggi. Tren: meningkat.
Mitigasi: renegosiasi kontrak gas, kajian lindung nilai. Status mitigasi: dalam proses.

Risiko 2 - Keandalan pembangkit. Tingkat risiko: Sedang. Equivalent Forced Outage Rate 4,8% (batas toleransi 5%).

Risiko 3 - Kepatuhan pengadaan. Tingkat risiko: Sedang, naik dari Rendah.
Pemicu: temuan SPI atas pengadaan suku cadang.

Risiko 4 - Nilai tukar. Tingkat risiko: Sedang. 38% utang dalam USD.
TXT;
    }

    protected function auditReport(): string
    {
        return <<<'TXT'
LAPORAN HASIL AUDIT SATUAN PENGAWASAN INTERN
Audit atas Pengadaan Suku Cadang Pembangkit

Temuan 1: Terdapat 14 paket pengadaan langsung bernilai masing-masing di bawah Rp 200 juta dengan vendor yang sama dalam 3 bulan, dengan total Rp 2,46 miliar. Terindikasi pemecahan paket.
Rekomendasi: Direksi menertibkan perencanaan pengadaan dan melakukan konsolidasi kebutuhan.

Temuan 2: Dokumen penerimaan barang tidak lengkap untuk 6 dari 40 sampel transaksi.
Rekomendasi: Memperbaiki prosedur penerimaan barang di gudang unit.

Status tindak lanjut: belum ditanggapi manajemen.
TXT;
    }
}
