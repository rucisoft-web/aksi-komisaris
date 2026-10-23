<?php

namespace App\Filament\Platform\Resources\Companies;

use App\Filament\Platform\Resources\Companies\Pages\ManageCompanies;
use App\Models\Company;
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

/** Admin platform mengelola daftar perusahaan, tanpa membuka isi datanya. */
class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'Perusahaan';

    protected static ?string $pluralModelLabel = 'Perusahaan';

    protected static bool $isScopedToTenant = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Kode')->required()->unique(ignoreRecord: true)->maxLength(20),
            TextInput::make('name')->label('Nama')->required(),
            TextInput::make('slug')->label('Slug URL')->unique(ignoreRecord: true)->helperText('Kosongkan untuk dibuat dari kode.'),
            Select::make('legal_form')->label('Status')->required()->default('bumn')->options([
                'bumn' => 'BUMN', 'anak_bumn' => 'Anak perusahaan BUMN', 'emiten' => 'Emiten', 'swasta' => 'Perseroan tertutup', 'afiliasi' => 'Afiliasi',
            ]),
            TextInput::make('sector')->label('Sektor'),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label('Kode')->searchable(),
            TextColumn::make('name')->label('Nama')->searchable(),
            TextColumn::make('legal_form')->label('Status')->badge(),
            TextColumn::make('memberships_count')->label('Pengguna')->counts('memberships'),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCompanies::route('/')];
    }
}
