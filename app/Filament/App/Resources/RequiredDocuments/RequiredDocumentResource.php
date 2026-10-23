<?php

namespace App\Filament\App\Resources\RequiredDocuments;

use App\Enums\DocumentCategory;
use App\Filament\App\Resources\RequiredDocuments\Pages\ManageRequiredDocuments;
use App\Filament\Concerns\InteractsWithAksi;
use App\Models\RequiredDocument;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class RequiredDocumentResource extends Resource
{
    use InteractsWithAksi;

    protected static ?string $model = RequiredDocument::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Dokumen';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Dokumen wajib';

    protected static ?string $pluralModelLabel = 'Dokumen Wajib';

    public static function canViewAny(): bool
    {
        return static::aksiCan('dokumen.lihat_dewan') || static::aksiCan('pengaturan.kelola');
    }

    public static function canCreate(): bool
    {
        return static::aksiCan('pengaturan.kelola') || static::aksiCan('dokumen.kelola');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Nama dokumen')->required()->columnSpanFull(),
            Select::make('category')->label('Kelompok')->options(DocumentCategory::class)->required(),
            Select::make('frequency')->label('Frekuensi')->options(['bulanan' => 'Bulanan', 'triwulanan' => 'Triwulanan', 'semesteran' => 'Semesteran', 'tahunan' => 'Tahunan'])->required(),
            TextInput::make('due_days_after_period')->label('Batas (hari setelah akhir periode)')->numeric()->default(30)->required(),
            TextInput::make('responsible')->label('Penanggung jawab'),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Dokumen')->wrap(),
                TextColumn::make('frequency')->label('Frekuensi')->badge()->color('gray'),
                TextColumn::make('responsible')->label('Penanggung jawab')->placeholder('—'),
                TextColumn::make('missing')->label('Belum diterima')->badge()->color('danger')
                    ->state(fn (RequiredDocument $record) => $record->receipts()->whereIn('status', ['belum_diterima', 'terlambat'])->whereNull('document_id')->count() ?: null)->placeholder('0'),
                TextColumn::make('last')->label('Periode terakhir')
                    ->state(fn (RequiredDocument $record) => optional($record->receipts()->latest('period_end')->first(), fn ($r) => $r->period_label.' · '.str_replace('_', ' ', $r->status))),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([EditAction::make()->visible(fn () => static::canCreate())]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRequiredDocuments::route('/')];
    }
}
