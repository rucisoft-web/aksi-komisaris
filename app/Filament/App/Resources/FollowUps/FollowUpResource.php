<?php

namespace App\Filament\App\Resources\FollowUps;

use App\Enums\FollowUpStatus;
use App\Filament\App\Resources\FollowUps\Pages\CreateFollowUp;
use App\Filament\App\Resources\FollowUps\Pages\EditFollowUp;
use App\Filament\App\Resources\FollowUps\Pages\ListFollowUps;
use App\Filament\App\Resources\FollowUps\Pages\ViewFollowUp;
use App\Filament\App\Resources\FollowUps\RelationManagers\DeadlineChangesRelationManager;
use App\Filament\App\Resources\FollowUps\RelationManagers\EventsRelationManager;
use App\Filament\App\Resources\FollowUps\RelationManagers\EvidencesRelationManager;
use App\Filament\Concerns\InteractsWithAksi;
use App\Models\FollowUp;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FollowUpResource extends Resource
{
    use InteractsWithAksi;

    protected static ?string $model = FollowUp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Pengawasan';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Tindak lanjut';

    protected static ?string $pluralModelLabel = 'Nasihat & Tindak Lanjut';

    protected static ?string $navigationLabel = 'Tindak Lanjut';

    public static function canViewAny(): bool
    {
        return static::aksiCan('tindaklanjut.lihat') || static::aksiCan('tindaklanjut.tanggapi');
    }

    public static function canCreate(): bool
    {
        return static::aksiCan('tindaklanjut.kelola');
    }

    public static function canEdit(Model $record): bool
    {
        return static::aksiCan('tindaklanjut.kelola') && in_array($record->status, [FollowUpStatus::Draf, FollowUpStatus::Disahkan], true);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** Penghubung hanya melihat tindak lanjut yang ditugaskan kepadanya. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['pic', 'reviewer']);

        return static::aksiCan('tindaklanjut.lihat') ? $query : $query->where('pic_user_id', auth()->id());
    }

    /** Kolom yang dipakai saat membuat tindak lanjut, juga dari keputusan rapat dan nasihat. */
    public static function creationFields(): array
    {
        return [
            Textarea::make('action_text')->label('Tindakan yang diminta')->required()->columnSpanFull(),
            Select::make('pic_user_id')->label('PIC (akun manajemen)')->options(fn () => static::memberOptions(['penghubung']))->searchable(),
            TextInput::make('pic_label')->label('Jabatan PIC')->placeholder('mis. Direktur Keuangan'),
            DatePicker::make('target_date')->label('Tenggat')->required()->minDate(now()->startOfDay()),
            Select::make('reviewer_user_id')->label('Reviewer (pemeriksa bukti)')->required()
                ->options(fn () => static::memberOptions(['komisaris_utama', 'komisaris_independen', 'komisaris', 'anggota_komite'])),
            Textarea::make('expected_output')->label('Keluaran yang diharapkan'),
            Textarea::make('required_evidence')->label('Bukti penyelesaian yang dipersyaratkan')->required(),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema(static::creationFields()),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('action_text')->label('Tindakan yang diminta')->columnSpanFull()->weight('bold'),
                TextEntry::make('status')->label('Status pelaksanaan')->badge(),
                TextEntry::make('timeliness_label')->label('Ketepatan waktu')->badge()
                    ->state(fn (FollowUp $record) => $record->timelinessLabel())
                    ->color(fn ($state) => match ($state) {
                        'Lewat tenggat', 'Selesai terlambat' => 'danger', 'Mendekati tenggat' => 'warning', default => 'success'
                    }),
                TextEntry::make('target_date')->label('Tenggat berlaku')->date('d/m/Y'),
                TextEntry::make('original_target_date')->label('Tenggat awal')->date('d/m/Y'),
                TextEntry::make('pic')->label('PIC')->state(fn (FollowUp $record) => trim(($record->pic?->name ?? '').' '.($record->pic_label ? '('.$record->pic_label.')' : '')) ?: '—'),
                TextEntry::make('reviewer.name')->label('Reviewer'),
                TextEntry::make('source')->label('Sumber')->state(fn (FollowUp $record) => $record->sourceLabel().($record->source_id ? ' #'.$record->source_id : '')),
                TextEntry::make('escalation_level')->label('Tingkat eskalasi'),
                TextEntry::make('expected_output')->label('Keluaran yang diharapkan')->columnSpan(2)->placeholder('—'),
                TextEntry::make('required_evidence')->label('Bukti yang dipersyaratkan')->columnSpan(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('target_date')
            ->columns([
                TextColumn::make('action_text')->label('Tindakan')->wrap()->limit(120)->searchable(),
                TextColumn::make('pic_label')->label('PIC')->state(fn (FollowUp $record) => $record->pic_label ?: $record->pic?->name),
                TextColumn::make('target_date')->label('Tenggat')->date('d/m/Y')->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('timeliness_label')->label('Ketepatan waktu')->badge()
                    ->state(fn (FollowUp $record) => $record->timelinessLabel())
                    ->color(fn ($state) => match ($state) {
                        'Lewat tenggat', 'Selesai terlambat' => 'danger', 'Mendekati tenggat' => 'warning', default => 'success'
                    }),
                TextColumn::make('reviewer.name')->label('Reviewer')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(FollowUpStatus::class),
                Filter::make('overdue')->label('Lewat tenggat')
                    ->query(fn (Builder $query) => $query->where('status', '!=', FollowUpStatus::Ditutup->value)->whereDate('target_date', '<', now())),
                Filter::make('open')->label('Masih terbuka')->default()
                    ->query(fn (Builder $query) => $query->where('status', '!=', FollowUpStatus::Ditutup->value)),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            EvidencesRelationManager::class,
            DeadlineChangesRelationManager::class,
            EventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFollowUps::route('/'),
            'create' => CreateFollowUp::route('/create'),
            'view' => ViewFollowUp::route('/{record}'),
            'edit' => EditFollowUp::route('/{record}/edit'),
        ];
    }
}
