<?php

namespace Tests\Feature;

use App\Enums\FollowUpStatus;
use App\Models\AgendaMaterial;
use App\Models\AiJob;
use App\Models\Company;
use App\Models\Document;
use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\Membership;
use App\Models\PersonalNote;
use App\Models\User;
use App\Services\Ai\AiGateway;
use App\Services\Ai\OutputValidator;
use App\Services\DocumentService;
use App\Services\FollowUpService;
use App\Services\MeetingService;
use App\Support\AccessGate;
use App\Support\CurrentCompany;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Aturan inti dari spesifikasi: isolasi, kebijakan AI, paket beku, bukti, masa jabatan, validasi rujukan. */
class AksiRulesTest extends TestCase
{
    use RefreshDatabase;

    protected Company $parent;

    protected Company $subsidiary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->parent = Company::where('code', 'ENR')->firstOrFail();
        $this->subsidiary = Company::where('code', 'ENR-LOG')->firstOrFail();
    }

    protected function user(string $key): User
    {
        return User::where('email', $key.'@demo.aksi.id')->firstOrFail();
    }

    public function test_company_scope_isolates_data(): void
    {
        $inParent = app(CurrentCompany::class)->runAs($this->parent, fn () => Document::pluck('company_id')->unique()->all());
        $inSub = app(CurrentCompany::class)->runAs($this->subsidiary, fn () => Document::pluck('company_id')->unique()->all());

        $this->assertSame([$this->parent->id], $inParent);
        $this->assertSame([$this->subsidiary->id], $inSub);
    }

    public function test_user_without_membership_cannot_open_other_company(): void
    {
        $this->actingAs($this->user('komisaris'))->get('/app/'.$this->subsidiary->slug)->assertNotFound();
        $this->actingAs($this->user('komisaris'))->get('/app/'.$this->parent->slug.'/documents')->assertOk();
    }

    public function test_private_space_and_notes_stay_private(): void
    {
        $gate = app(AccessGate::class);
        $privateDoc = Document::withoutGlobalScopes()->where('title', 'like', 'Catatan kerja pribadi%')->firstOrFail();

        $this->assertTrue($gate->canViewDocument($this->user('independen'), $privateDoc));
        $this->assertFalse($gate->canViewDocument($this->user('utama'), $privateDoc));
        $this->assertFalse($gate->canViewDocument($this->user('sekretariat'), $privateDoc));

        $note = PersonalNote::withoutGlobalScopes()->firstOrFail();
        $this->actingAs($this->user('utama'))->get('/app/'.$this->parent->slug.'/personal-notes/'.$note->id.'/edit')->assertNotFound();
    }

    public function test_committee_space_hidden_from_non_members(): void
    {
        $gate = app(AccessGate::class);
        $spi = Document::withoutGlobalScopes()->where('title', 'like', 'Laporan Hasil Audit SPI%')->firstOrFail();

        $this->assertTrue($gate->canViewDocument($this->user('komite'), $spi));
        $this->assertFalse($gate->canViewDocument($this->user('komisaris'), $spi));
    }

    public function test_ai_never_receives_documents_blocked_by_policy(): void
    {
        $captured = [];
        Http::fake(function (Request $request) use (&$captured) {
            $captured[] = $request->data();

            return Http::response([
                'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
                    'summary' => 'Ringkasan', 'items' => [], 'conflicts' => [], 'missing_information' => [], 'limitations' => [],
                ])]]]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]);
        });

        $ids = Document::withoutGlobalScopes()->where('company_id', $this->parent->id)->pluck('id')->all();
        $user = $this->user('utama');
        $job = app(CurrentCompany::class)->runAs($this->parent, fn () => AiJob::create([
            'user_id' => $user->id, 'mode' => 'tanya_dokumen', 'prompt' => 'Berapa EBITDA dan nilai akuisisi?', 'scope' => ['document_ids' => $ids],
        ]));

        $job = app(CurrentCompany::class)->runAs($this->parent, fn () => app(AiGateway::class)->run($job));

        $this->assertSame('selesai', $job->status, (string) $job->error);
        $sent = json_encode($captured, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('EBITDA', $sent);
        $this->assertStringNotContainsString('4,2 triliun', $sent, 'Dokumen berkebijakan AI "dilarang" terkirim ke model.');
        $this->assertStringNotContainsString('Catatan pribadi Komisaris Independen', $sent, 'Isi ruang pribadi orang lain terkirim ke model.');
        $this->assertFalse($captured[0]['store']);
        $this->assertNotEmpty($job->excluded_sources);
    }

    public function test_frozen_pack_keeps_the_version_discussed(): void
    {
        $meeting = Meeting::withoutGlobalScopes()->whereNotNull('frozen_at')->firstOrFail();
        $material = AgendaMaterial::withoutGlobalScopes()->where('agenda_item_id', $meeting->agendaItems()->withoutGlobalScopes()->first()->id)->firstOrFail();
        $frozenVersion = $material->document_version_id;
        $document = Document::withoutGlobalScopes()->findOrFail($material->document_id);

        $temp = tempnam(sys_get_temp_dir(), 'aksi');
        file_put_contents($temp, 'Versi revisi setelah rapat.');
        app(CurrentCompany::class)->runAs($this->parent, fn () => app(DocumentService::class)->addVersion($document, $temp, 'revisi.txt', $this->user('sekretariat')));

        $this->assertNotSame($frozenVersion, $document->refresh()->current_version_id);
        $this->assertSame($frozenVersion, $material->refresh()->document_version_id);
        $this->assertSame($meeting->manifest_sha256, app(MeetingService::class)->manifestHash($meeting));
    }

    public function test_follow_up_cannot_close_without_verified_evidence(): void
    {
        $service = app(FollowUpService::class);
        $followUp = app(CurrentCompany::class)->runAs($this->parent, fn () => FollowUp::where('status', 'dilaksanakan')->firstOrFail());

        app(CurrentCompany::class)->runAs($this->parent, function () use ($service, $followUp) {
            $followUp->evidences()->create(['uploaded_by' => $this->user('direksi')->id, 'description' => 'Sudah selesai.']);
            $service->transition($followUp, FollowUpStatus::Diperiksa, $this->user('direksi'));

            try {
                $service->transition($followUp->refresh(), FollowUpStatus::Ditutup, $this->user('independen'));
                $this->fail('Tindak lanjut tertutup tanpa bukti terverifikasi.');
            } catch (ValidationException) {
                $this->assertSame(FollowUpStatus::Diperiksa, $followUp->refresh()->status);
            }

            // Penghubung (PIC) tidak boleh menutup sendiri.
            $this->assertArrayNotHasKey('ditutup', $service->availableTransitions($followUp, $this->user('direksi')));
        });
    }

    public function test_expired_membership_loses_access(): void
    {
        $user = $this->user('komisaris');
        Membership::withoutGlobalScopes()->where('user_id', $user->id)->update(['valid_until' => now()->subDay()]);
        $user->forgetMembershipCache();

        $this->assertNull($user->activeMembership($this->parent));
        $this->actingAs($user->refresh())->get('/app/'.$this->parent->slug.'/documents')->assertNotFound();
    }

    public function test_validator_flags_fabricated_citations_and_numbers(): void
    {
        $sources = ['S1' => ['text' => 'EBITDA tercatat Rp 3.653 miliar, di bawah target Rp 3.988 miliar.']];
        $output = ['summary' => 'x', 'conflicts' => [], 'missing_information' => [], 'limitations' => [], 'items' => [
            ['kind' => 'fact', 'text' => 'EBITDA Rp 3.653 miliar.', 'citations' => [['source_id' => 'S1', 'quote' => 'EBITDA tercatat Rp 3.653 miliar']], 'calc_ids' => []],
            ['kind' => 'fact', 'text' => 'Laba naik 40%.', 'citations' => [['source_id' => 'S9', 'quote' => 'Laba naik 40%']], 'calc_ids' => []],
            ['kind' => 'analysis', 'text' => 'Selisih sekitar Rp 335 miliar.', 'citations' => [['source_id' => 'S1', 'quote' => 'di bawah target']], 'calc_ids' => []],
        ]];

        $result = app(OutputValidator::class)->validate($output, $sources, []);

        $this->assertSame(1, $result['report']['fabricated_citations']);
        $this->assertGreaterThanOrEqual(1, $result['report']['unverified_numbers']);
        $this->assertTrue($result['output']['items'][0]['citations'][0]['verified']);
        $this->assertSame([], $result['output']['items'][1]['citations']);
    }
}
