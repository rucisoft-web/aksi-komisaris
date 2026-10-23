<?php

namespace App\Filament\App\Resources\Organs;

use App\Filament\App\Resources\Organs\Pages\ManageOrgans;
use App\Filament\Concerns\InteractsWithAksi;
use App\Models\Organ;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Organ dan komite. Nama serta mandat mengikuti struktur perusahaan, tidak ditanam di kode. */
class OrganResource extends Resource
{
    use InteractsWithAksi;

    protected static ?string $model = Organ::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $modelLabel = 'Organ / komite';

    protected static ?string $pluralModelLabel = 'Organ & Komite';

    public static function canViewAny(): bool
    {
        return static::aksiCan('pengaturan.kelola');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required(),
            Select::make('kind')->label('Jenis')->required()->options([
                'dewan_komisaris' => 'Dewan Komisaris / Dewan Pengawas', 'direksi' => 'Direksi', 'komite' => 'Komite', 'rups' => 'RUPS / Pemegang saham', 'sekretariat' => 'Sekretariat',
            ]),
            TextInput::make('quorum_min')->label('Kuorum minimal')->numeric(),
            Textarea::make('mandate')->label('Mandat / piagam (ringkas)')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama'),
                TextColumn::make('kind')->label('Jenis')->badge(),
                TextColumn::make('quorum_min')->label('Kuorum')->placeholder('—'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageOrgans::route('/')];
    }
}
