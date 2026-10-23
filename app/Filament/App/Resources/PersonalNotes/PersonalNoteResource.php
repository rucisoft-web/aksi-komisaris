<?php

namespace App\Filament\App\Resources\PersonalNotes;

use App\Filament\App\Resources\PersonalNotes\Pages\CreatePersonalNote;
use App\Filament\App\Resources\PersonalNotes\Pages\EditPersonalNote;
use App\Filament\App\Resources\PersonalNotes\Pages\ListPersonalNotes;
use App\Filament\Concerns\InteractsWithAksi;
use App\Models\PersonalNote;
use App\Services\NoteService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PersonalNoteResource extends Resource
{
    use InteractsWithAksi;

    protected static ?string $model = PersonalNote::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string|UnitEnum|null $navigationGroup = 'Pengawasan';

    protected static ?int $navigationSort = 9;

    protected static ?string $modelLabel = 'Catatan pribadi';

    protected static ?string $pluralModelLabel = 'Catatan Pribadi';

    public static function canViewAny(): bool
    {
        return static::aksiUser()->roleIn(static::aksiCompany())?->isCommissioner()
            || static::aksiUser()->roleIn(static::aksiCompany())?->value === 'anggota_komite';
    }

    /** Ruang pribadi: hanya pemilik. Tidak ada pengecualian untuk peran apa pun. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Judul')->required()->columnSpanFull(),
            Textarea::make('body')->label('Catatan, pertanyaan, atau analisis pribadi')->rows(8)->required()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable()->wrap()->description(fn (PersonalNote $record) => str($record->body)->limit(120)),
                TextColumn::make('document.title')->label('Dokumen')->placeholder('—'),
                TextColumn::make('promoted')->label('Status')->badge()
                    ->state(fn (PersonalNote $record) => $record->isPromoted() ? 'Sudah diajukan' : 'Pribadi')
                    ->color(fn ($state) => $state === 'Pribadi' ? 'gray' : 'success'),
                TextColumn::make('updated_at')->label('Diperbarui')->since(),
            ])
            ->recordActions([
                Action::make('promote')
                    ->label('Ajukan ke ruang resmi')
                    ->icon('heroicon-o-arrow-up-on-square')
                    ->visible(fn (PersonalNote $record) => ! $record->isPromoted())
                    ->modalDescription('Salinan catatan dibuat sebagai draf di ruang resmi. Catatan pribadi Anda tidak berubah.')
                    ->schema([
                        Select::make('as')->label('Jadikan')->options(['isu' => 'Isu pengawasan', 'pertanyaan' => 'Draf permintaan informasi'])->required(),
                        Select::make('space_id')->label('Ruang tujuan')->required()
                            ->options(fn () => static::gate()->writableOfficialSpaces(static::aksiUser(), static::aksiCompany())),
                    ])
                    ->action(function (PersonalNote $record, array $data) {
                        app(NoteService::class)->promote($record, auth()->user(), $data['as'], (int) $data['space_id']);
                        Notification::make()->title('Catatan diajukan ke ruang resmi sebagai draf.')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPersonalNotes::route('/'),
            'create' => CreatePersonalNote::route('/create'),
            'edit' => EditPersonalNote::route('/{record}/edit'),
        ];
    }
}
