<?php

namespace App\Filament\App\Resources\InformationRequests;

use App\Enums\Assessment;
use App\Enums\RequestStatus;
use App\Filament\App\Resources\InformationRequests\Pages\CreateInformationRequest;
use App\Filament\App\Resources\InformationRequests\Pages\EditInformationRequest;
use App\Filament\App\Resources\InformationRequests\Pages\ListInformationRequests;
use App\Filament\App\Resources\InformationRequests\Pages\ViewInformationRequest;
use App\Filament\Concerns\InteractsWithAksi;
use App\Models\InformationRequest;
use App\Models\Issue;
use App\Models\Meeting;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

class InformationRequestResource extends Resource
{
    use InteractsWithAksi;

    protected static ?string $model = InformationRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Pengawasan';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Permintaan informasi';

    protected static ?string $pluralModelLabel = 'Pertanyaan & Jawaban';

    public static function canViewAny(): bool
    {
        return static::aksiCan('permintaan.buat') || static::aksiCan('permintaan.jawab');
    }

    public static function canCreate(): bool
    {
        return static::aksiCan('permintaan.buat');
    }

    public static function canEdit(Model $record): bool
    {
        return $record->status === RequestStatus::Draf && ($record->created_by === auth()->id() || static::aksiCan('permintaan.kirim'));
    }

    public static function canDelete(Model $record): bool
    {
        return $record->status === RequestStatus::Draf && $record->created_by === auth()->id();
    }

    /**
     * Anggota dewan melihat permintaan di ruang yang bisa diakses. Penghubung
     * hanya melihat permintaan yang sudah dikirim kepadanya.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['addressee', 'creator']);

        if (static::aksiCan('permintaan.buat')) {
            return $query->whereIn('space_id', static::visibleSpaceIds());
        }

        return $query->where('addressee_user_id', auth()->id())
            ->whereNotIn('status', [RequestStatus::Draf->value, RequestStatus::Diajukan->value]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                Textarea::make('question')->label('Pertanyaan')->rows(4)->required()->columnSpanFull(),
                Textarea::make('basis')->label('Dasar (dokumen, angka, pembahasan)')->rows(3)->columnSpanFull(),
                Textarea::make('purpose')->label('Penjelasan yang dibutuhkan')->rows(2)->columnSpanFull(),
                Select::make('space_id')->label('Ruang')->options(fn () => static::spaceOptions())->required()
                    ->helperText('Draf di ruang pribadi tidak terlihat anggota lain sampai Anda memindahkannya.'),
                Select::make('addressee_user_id')->label('Penerima (pejabat/penghubung resmi)')->options(fn () => static::memberOptions(['penghubung']))->searchable(),
                DatePicker::make('due_date')->label('Tenggat jawaban'),
                Select::make('issue_id')->label('Isu terkait')->options(fn () => Issue::query()->whereIn('space_id', static::visibleSpaceIds())->pluck('title', 'id')),
                Select::make('meeting_id')->label('Rapat terkait')->options(fn () => Meeting::query()->whereIn('space_id', static::visibleSpaceIds())->latest('scheduled_at')->pluck('title', 'id')),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pertanyaan')->columns(4)->columnSpanFull()->schema([
                TextEntry::make('question')->label('Pertanyaan')->columnSpanFull()->weight('bold'),
                TextEntry::make('basis')->label('Dasar')->columnSpan(2)->placeholder('—')
                    ->visible(fn () => static::aksiCan('permintaan.buat')),
                TextEntry::make('purpose')->label('Penjelasan yang dibutuhkan')->columnSpan(2)->placeholder('—'),
                TextEntry::make('status')->label('Status')->badge(),
                TextEntry::make('addressee.name')->label('Penerima')->placeholder('—'),
                TextEntry::make('due_date')->label('Tenggat')->date('d/m/Y')->placeholder('—'),
                TextEntry::make('space.name')->label('Ruang'),
                TextEntry::make('assessment')->label('Penilaian')->badge()->placeholder('Belum dinilai')
                    ->visible(fn () => static::aksiCan('permintaan.buat')),
                TextEntry::make('assessment_note')->label('Catatan penilaian')->columnSpan(3)->placeholder('—')
                    ->visible(fn () => static::aksiCan('permintaan.buat')),
            ]),
            Section::make('Jawaban manajemen')->columnSpanFull()->schema([
                RepeatableEntry::make('responses')->hiddenLabel()->placeholder('Belum ada jawaban.')->columns(3)->schema([
                    TextEntry::make('responder.name')->label('Oleh'),
                    TextEntry::make('created_at')->label('Tanggal')->dateTime('d/m/Y H:i'),
                    TextEntry::make('document.title')->label('Lampiran')->placeholder('—'),
                    TextEntry::make('body')->label('Jawaban')->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('question')->label('Pertanyaan')->wrap()->limit(140)->searchable(),
                TextColumn::make('addressee.name')->label('Penerima')->placeholder('—'),
                TextColumn::make('due_date')->label('Tenggat')->date('d/m/Y')->placeholder('—')
                    ->color(fn (InformationRequest $record) => $record->isOverdue() ? 'danger' : null),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('assessment')->label('Penilaian')->badge()->placeholder('—'),
                TextColumn::make('space.name')->label('Ruang')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(RequestStatus::class),
                SelectFilter::make('assessment')->label('Penilaian')->options(Assessment::class),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInformationRequests::route('/'),
            'create' => CreateInformationRequest::route('/create'),
            'view' => ViewInformationRequest::route('/{record}'),
            'edit' => EditInformationRequest::route('/{record}/edit'),
        ];
    }
}
