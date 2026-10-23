<?php

namespace App\Filament\App\Resources\Meetings;

use App\Enums\MeetingStatus;
use App\Filament\App\Resources\Meetings\Pages\CreateMeeting;
use App\Filament\App\Resources\Meetings\Pages\EditMeeting;
use App\Filament\App\Resources\Meetings\Pages\ListMeetings;
use App\Filament\App\Resources\Meetings\Pages\ViewMeeting;
use App\Filament\App\Resources\Meetings\RelationManagers\AgendaItemsRelationManager;
use App\Filament\App\Resources\Meetings\RelationManagers\AttendancesRelationManager;
use App\Filament\App\Resources\Meetings\RelationManagers\MinutesRelationManager;
use App\Filament\App\Resources\Meetings\RelationManagers\ResolutionsRelationManager;
use App\Filament\Concerns\InteractsWithAksi;
use App\Models\ConflictDeclaration;
use App\Models\Meeting;
use App\Models\Organ;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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

class MeetingResource extends Resource
{
    use InteractsWithAksi;

    protected static ?string $model = Meeting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Rapat';

    protected static ?string $modelLabel = 'Rapat';

    protected static ?string $pluralModelLabel = 'Rapat';

    protected static ?string $recordTitleAttribute = 'title';

    public static function canViewAny(): bool
    {
        return static::aksiCan('rapat.lihat');
    }

    public static function canCreate(): bool
    {
        return static::aksiCan('rapat.kelola');
    }

    public static function canEdit(Model $record): bool
    {
        return static::aksiCan('rapat.kelola');
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('space_id', static::visibleSpaceIds());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->label('Judul rapat')->required()->columnSpanFull(),
                Select::make('organ_id')->label('Organ')->options(fn () => Organ::query()->pluck('name', 'id'))->required(),
                Select::make('space_id')->label('Ruang')->options(fn () => static::spaceOptions(includePrivate: false))->required(),
                DateTimePicker::make('scheduled_at')->label('Waktu rapat')->seconds(false)->required(),
                TextInput::make('location')->label('Tempat / tautan'),
                DateTimePicker::make('material_due_at')->label('Batas penyerahan bahan')->seconds(false),
                TextInput::make('quorum_min')->label('Kuorum minimal (orang)')->numeric()
                    ->helperText('Ikuti anggaran dasar / board manual perusahaan.'),
                Select::make('status')->label('Status')->options(MeetingStatus::class)->default(MeetingStatus::Dijadwalkan)->visibleOn('edit'),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('title')->label('Rapat')->columnSpan(2)->weight('bold'),
                TextEntry::make('organ.name')->label('Organ'),
                TextEntry::make('status')->label('Status')->badge(),
                TextEntry::make('scheduled_at')->label('Waktu')->dateTime('l, d F Y H:i'),
                TextEntry::make('location')->label('Tempat')->placeholder('—'),
                TextEntry::make('material_due_at')->label('Batas bahan')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextEntry::make('quorum')->label('Kuorum')->state(function (Meeting $record) {
                    $present = $record->attendances()->where('status', 'hadir')->count();
                    $recused = ConflictDeclaration::query()->whereIn('agenda_item_id', $record->agendaItems()->pluck('id'))->where('recusal', true)->distinct('user_id')->count('user_id');

                    return $record->quorum_min
                        ? "{$present} hadir dari minimal {$record->quorum_min}".($present >= $record->quorum_min ? ' (terpenuhi)' : ' (belum terpenuhi)').($recused ? "; {$recused} anggota mengundurkan diri pada agenda tertentu" : '')
                        : "{$present} hadir; aturan kuorum belum diisi";
                }),
                TextEntry::make('frozen_at')->label('Paket dibekukan')->dateTime('d/m/Y H:i')->placeholder('Belum dibekukan'),
                TextEntry::make('manifest_sha256')->label('Sidik manifes')->placeholder('—')->limit(16)->columnSpan(3),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('scheduled_at', 'desc')
            ->columns([
                TextColumn::make('scheduled_at')->label('Waktu')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('title')->label('Rapat')->searchable()->wrap(),
                TextColumn::make('organ.name')->label('Organ'),
                TextColumn::make('agenda_items_count')->label('Agenda')->counts('agendaItems'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('frozen_at')->label('Paket')->formatStateUsing(fn ($state) => $state ? 'Dibekukan' : '')->placeholder('Terbuka')->badge()->color('info'),
            ])
            ->filters([SelectFilter::make('status')->options(MeetingStatus::class)])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            AgendaItemsRelationManager::class,
            AttendancesRelationManager::class,
            ResolutionsRelationManager::class,
            MinutesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMeetings::route('/'),
            'create' => CreateMeeting::route('/create'),
            'view' => ViewMeeting::route('/{record}'),
            'edit' => EditMeeting::route('/{record}/edit'),
        ];
    }
}
