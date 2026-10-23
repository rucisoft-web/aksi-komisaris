<?php

namespace App\Filament\App\Resources\Documents;

use App\Enums\AiPolicy;
use App\Enums\Classification;
use App\Enums\DocumentCategory;
use App\Enums\DocumentStatus;
use App\Enums\ProcessingStatus;
use App\Filament\App\Resources\Documents\Pages\CreateDocument;
use App\Filament\App\Resources\Documents\Pages\EditDocument;
use App\Filament\App\Resources\Documents\Pages\ListDocuments;
use App\Filament\App\Resources\Documents\Pages\ViewDocument;
use App\Filament\Concerns\InteractsWithAksi;
use App\Models\Annotation;
use App\Models\Document;
use App\Models\RequiredDocument;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DocumentResource extends Resource
{
    use InteractsWithAksi;

    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Dokumen';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Dokumen';

    protected static ?string $pluralModelLabel = 'Pusat Dokumen';

    protected static ?string $recordTitleAttribute = 'title';

    public static function canViewAny(): bool
    {
        return static::aksiCan('dokumen.lihat_dewan') || static::aksiCan('dokumen.unggah');
    }

    public static function canCreate(): bool
    {
        return static::aksiCan('dokumen.unggah');
    }

    public static function canEdit(Model $record): bool
    {
        return static::aksiCan('dokumen.kelola') || $record->created_by === auth()->id();
    }

    public static function canDelete(Model $record): bool
    {
        return false; // dokumen tidak dihapus; tandai "digantikan" atau atur retensi
    }

    public static function getEloquentQuery(): Builder
    {
        return static::gate()->scopeDocuments(parent::getEloquentQuery(), static::aksiUser(), static::aksiCompany())
            ->with(['currentVersion', 'space']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Berkas')
                ->visibleOn('create')
                ->columnSpanFull()
                ->schema([
                    FileUpload::make('upload')
                        ->label('Berkas dokumen')
                        ->helperText('PDF, DOCX, XLSX, CSV, PPTX, TXT, atau gambar hasil pindai. Maks. '.round(config('aksi.uploads.max_kb') / 1024).' MB.')
                        ->disk('local')
                        ->directory('incoming')
                        ->visibility('private')
                        ->storeFileNamesIn('upload_name')
                        ->maxSize(config('aksi.uploads.max_kb'))
                        ->required(),
                ]),
            Section::make('Metadata')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('title')->label('Judul')->required()->columnSpanFull(),
                    Select::make('category')->label('Kelompok')->options(DocumentCategory::class)->required(),
                    TextInput::make('doc_type')->label('Jenis dokumen')->placeholder('mis. Laporan Triwulan III, RKAP 2027'),
                    Select::make('space_id')->label('Ruang penyimpanan')->options(fn () => static::spaceOptions())->required()
                        ->helperText('Ruang pribadi hanya bisa dibuka oleh Anda.'),
                    TextInput::make('owner_unit')->label('Unit pemilik'),
                    DatePicker::make('period_start')->label('Awal periode'),
                    DatePicker::make('period_end')->label('Akhir periode'),
                    Select::make('entity_scope')->label('Lingkup entitas')->options(['konsolidasi' => 'Konsolidasi', 'individual' => 'Individual']),
                    Select::make('required_document_id')->label('Memenuhi dokumen wajib')
                        ->options(fn () => RequiredDocument::query()->where('is_active', true)->pluck('title', 'id')),
                ]),
            Section::make('Kerahasiaan dan status')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('classification')->label('Klasifikasi')->options(Classification::class)->default(Classification::Internal)->required(),
                    Select::make('ai_policy')->label('Kebijakan AI')->options(AiPolicy::class)->default(AiPolicy::Standar)->required()
                        ->helperText('Rahasia butuh "Izin khusus". Sangat terbatas tidak dikirim ke AI kecuali diizinkan perusahaan.'),
                    Select::make('status')->label('Status')->options(DocumentStatus::class)->default(DocumentStatus::Final)->required(),
                    Toggle::make('legal_hold')->label('Penahanan hukum (jangan hapus)')->visibleOn('edit'),
                    DatePicker::make('retain_until')->label('Simpan sampai')->visibleOn('edit'),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ringkasan dokumen')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('title')->label('Judul')->columnSpanFull()->weight('bold'),
                    TextEntry::make('category')->label('Kelompok')->badge(),
                    TextEntry::make('doc_type')->label('Jenis')->placeholder('—'),
                    TextEntry::make('period')->label('Periode')->state(fn (Document $record) => $record->periodLabel()),
                    TextEntry::make('classification')->label('Klasifikasi')->badge(),
                    TextEntry::make('ai_policy')->label('Kebijakan AI')->badge(),
                    TextEntry::make('status')->label('Status')->badge(),
                    TextEntry::make('space.name')->label('Ruang'),
                    TextEntry::make('entity_scope')->label('Lingkup')->placeholder('—'),
                    TextEntry::make('owner_unit')->label('Unit pemilik')->placeholder('—'),
                ]),
            Section::make('Versi terkini dan kualitas teks')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('currentVersion.version_no')->label('Versi')->prefix('v'),
                    TextEntry::make('currentVersion.processing_status')->label('Status teks')->badge(),
                    TextEntry::make('currentVersion.page_count')->label('Jumlah halaman')->placeholder('—'),
                    TextEntry::make('currentVersion.processing_note')->label('Catatan pemrosesan')->placeholder('Tidak ada catatan.')->columnSpanFull()
                        ->color('warning'),
                ]),
            Section::make('Riwayat versi')
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    RepeatableEntry::make('versions')
                        ->hiddenLabel()
                        ->columns(4)
                        ->schema([
                            TextEntry::make('version_no')->label('Versi')->prefix('v')
                                ->url(fn ($record) => route('berkas.lihat', $record), shouldOpenInNewTab: true),
                            TextEntry::make('original_name')->label('Berkas'),
                            TextEntry::make('created_at')->label('Diunggah')->dateTime('d/m/Y H:i'),
                            TextEntry::make('change_note')->label('Catatan perubahan')->placeholder('—'),
                        ]),
                ]),
            Section::make('Anotasi')
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    RepeatableEntry::make('visible_annotations')
                        ->hiddenLabel()
                        ->state(fn (Document $record) => Annotation::query()
                            ->whereIn('document_version_id', $record->versions()->pluck('id'))
                            ->where(fn ($q) => $q->where('visibility', 'bersama')->orWhere('user_id', auth()->id()))
                            ->with(['user', 'version'])
                            ->latest()
                            ->get())
                        ->placeholder('Belum ada anotasi.')
                        ->columns(4)
                        ->schema([
                            TextEntry::make('user.name')->label('Oleh'),
                            TextEntry::make('location')->label('Lokasi')->state(fn ($record) => 'v'.$record->version->version_no.($record->page_no ? ', hal. '.$record->page_no : '')),
                            TextEntry::make('visibility')->label('Sifat')->badge()->formatStateUsing(fn ($state) => $state === 'bersama' ? 'Bersama' : 'Pribadi'),
                            TextEntry::make('body')->label('Isi')->columnSpanFull(),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable()->wrap()
                    ->description(fn (Document $record) => $record->doc_type),
                TextColumn::make('category')->label('Kelompok')->badge()->color('gray'),
                TextColumn::make('period_end')->label('Periode s.d.')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('classification')->label('Klasifikasi')->badge(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('currentVersion.processing_status')->label('Teks')->badge(),
                TextColumn::make('currentVersion.version_no')->label('Versi')->prefix('v'),
                TextColumn::make('space.name')->label('Ruang')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label('Diperbarui')->since(),
            ])
            ->filters([
                SelectFilter::make('category')->label('Kelompok')->options(DocumentCategory::class),
                SelectFilter::make('classification')->label('Klasifikasi')->options(Classification::class),
                SelectFilter::make('status')->label('Status')->options(DocumentStatus::class),
                SelectFilter::make('space_id')->label('Ruang')->options(fn () => static::spaceOptions()),
                SelectFilter::make('processing')->label('Status teks')->options(ProcessingStatus::class)
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn ($q, $v) => $q->whereHas('currentVersion', fn ($v2) => $v2->where('processing_status', $v)))),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
            'create' => CreateDocument::route('/create'),
            'view' => ViewDocument::route('/{record}'),
            'edit' => EditDocument::route('/{record}/edit'),
        ];
    }
}
