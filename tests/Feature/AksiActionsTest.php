<?php

namespace Tests\Feature;

use App\Filament\App\Resources\Documents\Pages\CreateDocument;
use App\Filament\App\Resources\FollowUps\Pages\ViewFollowUp;
use App\Filament\App\Resources\InformationRequests\Pages\ViewInformationRequest;
use App\Models\Company;
use App\Models\Document;
use App\Models\FollowUp;
use App\Models\InformationRequest;
use App\Models\User;
use App\Support\AccessGate;
use App\Support\CurrentCompany;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/** Alur kerja lewat antarmuka (komponen Livewire Filament). */
class AksiActionsTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->company = Company::where('code', 'ENR')->firstOrFail();
        Filament::setCurrentPanel(Filament::getPanel('app'));
        app(CurrentCompany::class)->set($this->company);
    }

    protected function as(string $key): User
    {
        $user = User::where('email', $key.'@demo.aksi.id')->firstOrFail();
        $this->actingAs($user);
        Filament::setTenant($this->company);
        app(AccessGate::class)->flush();

        return $user;
    }

    public function test_secretariat_uploads_document_and_text_is_extracted(): void
    {
        $this->as('sekretariat');
        $file = UploadedFile::fake()->createWithContent('lap-bulanan.txt', "Laporan bulan September.\nPendapatan Rp 6.120 miliar.");

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'upload' => $file,
                'title' => 'Laporan Keuangan September',
                'category' => 'keuangan',
                'space_id' => $this->company->boardSpace()->id,
                'classification' => 'internal',
                'ai_policy' => 'standar',
                'status' => 'final',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $document = Document::where('title', 'Laporan Keuangan September')->firstOrFail();
        $version = $document->currentVersion;
        $this->assertSame('lap-bulanan.txt', $version->original_name);
        $this->assertSame('siap', $version->processing_status->value);
        $this->assertStringContainsString('6.120', (string) $version->chunks()->value('content'));
    }

    public function test_information_request_flows_from_board_to_management_and_back(): void
    {
        $request = InformationRequest::where('status', 'diajukan')->firstOrFail();

        $this->as('utama');
        Livewire::test(ViewInformationRequest::class, ['record' => $request->getRouteKey()])
            ->callAction('approve_send', ['addressee_user_id' => User::where('email', 'direksi@demo.aksi.id')->value('id'), 'due_date' => now()->addWeek()->toDateString()])
            ->assertHasNoActionErrors();
        $this->assertSame('dikirim', $request->refresh()->status->value);

        $this->as('direksi');
        Livewire::test(ViewInformationRequest::class, ['record' => $request->getRouteKey()])
            ->callAction('answer', ['body' => 'Lindung nilai 40% volume gas melalui kontrak swap.'])
            ->assertHasNoActionErrors();
        $this->assertSame('dijawab', $request->refresh()->status->value);
        $this->assertSame(1, $request->responses()->count());
    }

    public function test_pic_responds_and_reviewer_returns_follow_up_with_reason(): void
    {
        $followUp = FollowUp::where('status', 'dilaksanakan')->firstOrFail();
        $followUp->evidences()->create(['uploaded_by' => $followUp->pic_user_id, 'description' => 'Kajian lindung nilai.']);

        $this->as('direksi');
        Livewire::test(ViewFollowUp::class, ['record' => $followUp->getRouteKey()])
            ->callAction('to_diperiksa', ['note' => 'Mohon diperiksa.'])
            ->assertHasNoActionErrors();
        $this->assertSame('diperiksa', $followUp->refresh()->status->value);

        $this->as('independen');
        Livewire::test(ViewFollowUp::class, ['record' => $followUp->getRouteKey()])
            ->assertActionVisible('to_ditutup')
            ->callAction('to_dilaksanakan', ['note' => ''])
            ->assertHasActionErrors(['note' => 'required']);

        Livewire::test(ViewFollowUp::class, ['record' => $followUp->getRouteKey()])
            ->callAction('to_dilaksanakan', ['note' => 'Bukti belum memuat keputusan Direksi.'])
            ->assertHasNoActionErrors();
        $this->assertSame('dilaksanakan', $followUp->refresh()->status->value);
    }
}
