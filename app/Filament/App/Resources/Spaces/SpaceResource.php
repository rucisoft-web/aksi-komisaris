<?php

namespace App\Filament\App\Resources\Spaces;

use App\Enums\SpaceKind;
use App\Filament\App\Resources\Spaces\Pages\CreateSpace;
use App\Filament\App\Resources\Spaces\Pages\EditSpace;
use App\Filament\App\Resources\Spaces\Pages\ListSpaces;
use App\Filament\Concerns\InteractsWithAksi;
use App\Models\Organ;
use App\Models\Space;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SpaceResource extends Resource
{
    use InteractsWithAksi;

    protected static ?string $model = Space::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $modelLabel = 'Ruang';

    protected static ?string $pluralModelLabel = 'Ruang & Anggota';

    public static function canViewAny(): bool
    {
        return static::aksiCan('pengaturan.kelola');
    }

    /** Admin mengatur keanggotaan ruang resmi, tetapi tidak pernah melihat ruang pribadi. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('kind', '!=', SpaceKind::Pribadi->value)->withCount('members');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama ruang')->required(),
            Select::make('kind')->label('Jenis')->required()->options(collect(SpaceKind::options())->except('pribadi')->all()),
            Select::make('organ_id')->label('Organ')->options(fn () => Organ::query()->pluck('name', 'id')),
            Repeater::make('spaceMembers')
                ->label('Anggota tambahan')
                ->helperText('Ruang dewan otomatis terbuka bagi komisaris dan sekretariat. Ruang komite dan terbatas hanya bagi anggota di sini.')
                ->relationship('spaceMembers')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('user_id')->label('Pengguna')->options(fn () => static::memberOptions())->required(),
                    Select::make('access')->label('Akses')->options(['baca' => 'Baca', 'kontribusi' => 'Kontribusi', 'kelola' => 'Kelola'])->default('kontribusi')->required(),
                    DatePicker::make('valid_until')->label('Berlaku sampai'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Ruang'),
                TextColumn::make('kind')->label('Jenis')->badge(),
                TextColumn::make('organ.name')->label('Organ')->placeholder('—'),
                TextColumn::make('members_count')->label('Anggota tambahan'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpaces::route('/'),
            'create' => CreateSpace::route('/create'),
            'edit' => EditSpace::route('/{record}/edit'),
        ];
    }
}
