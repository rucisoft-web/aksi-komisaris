<?php

namespace App\Filament\App\Pages;

use App\Filament\Concerns\InteractsWithAksi;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class PengaturanPerusahaan extends Page
{
    use InteractsWithAksi;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Profil & Kebijakan AI';

    protected static ?string $title = 'Profil perusahaan dan kebijakan AI';

    protected string $view = 'filament.pages.pengaturan-perusahaan';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return static::aksiCan('pengaturan.kelola');
    }

    public function mount(): void
    {
        $this->form->fill(static::aksiCompany()->only(['name', 'code', 'legal_form', 'sector', 'fiscal_year_start', 'ai_enabled', 'ai_allow_restricted']));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Profil')->columns(2)->schema([
                    TextInput::make('name')->label('Nama perusahaan')->required(),
                    TextInput::make('code')->label('Kode')->disabled(),
                    Select::make('legal_form')->label('Status')->required()->options([
                        'bumn' => 'BUMN', 'anak_bumn' => 'Anak perusahaan BUMN', 'emiten' => 'Emiten / perusahaan publik', 'swasta' => 'Perseroan tertutup', 'afiliasi' => 'Afiliasi',
                    ]),
                    TextInput::make('sector')->label('Sektor'),
                    TextInput::make('fiscal_year_start')->label('Bulan awal tahun buku')->numeric()->minValue(1)->maxValue(12),
                ]),
                Section::make('Kebijakan pemrosesan AI')->schema([
                    Toggle::make('ai_enabled')->label('Izinkan dokumen publik dan internal diproses asisten AI (OpenAI API)'),
                    Toggle::make('ai_allow_restricted')->label('Izinkan dokumen "sangat terbatas" berizin khusus dikirim ke AI'),
                    Placeholder::make('retention')->label('Catatan retensi')->content(
                        'Permintaan dikirim dengan store: false dan API tidak dipakai untuk pelatihan secara bawaan. '.
                        'Log pemantauan penyalahgunaan di OpenAI umumnya dapat disimpan hingga 30 hari kecuali organisasi memperoleh Zero Data Retention. '.
                        'Dokumen rahasia hanya dikirim bila kebijakan AI dokumennya "Izin khusus".'
                    ),
                ]),
            ]);
    }

    public function save(): void
    {
        $company = static::aksiCompany();
        $company->update($this->form->getState());
        AuditLog::record('settings_updated', $company);
        Notification::make()->title('Pengaturan disimpan.')->success()->send();
    }
}
