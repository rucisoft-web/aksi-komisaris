<?php

namespace App\Filament\App\Resources\Memberships;

use App\Enums\Role;
use App\Filament\App\Resources\Memberships\Pages\CreateMembership;
use App\Filament\App\Resources\Memberships\Pages\EditMembership;
use App\Filament\App\Resources\Memberships\Pages\ListMemberships;
use App\Filament\Concerns\InteractsWithAksi;
use App\Models\Membership;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class MembershipResource extends Resource
{
    use InteractsWithAksi;

    protected static ?string $model = Membership::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna & Peran';

    public static function canViewAny(): bool
    {
        return static::aksiCan('pengaturan.kelola');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Akun')->columns(2)->columnSpanFull()->visibleOn('create')->schema([
                TextInput::make('email')->label('Email')->email()->required(),
                TextInput::make('name')->label('Nama lengkap')->required(),
                TextInput::make('password')->label('Kata sandi awal')->password()->revealable()->minLength(10)
                    ->helperText('Diisi bila akun belum ada. Pengguna wajib memasang MFA saat pertama masuk.'),
            ]),
            Section::make('Peran dan masa jabatan')->columns(2)->columnSpanFull()->schema([
                Select::make('role')->label('Peran')->options(Role::class)->required(),
                TextInput::make('position_title')->label('Jabatan')->placeholder('mis. Komisaris Independen, Direktur Keuangan'),
                Toggle::make('is_independent')->label('Independen'),
                TextInput::make('field_of_supervision')->label('Bidang pengawasan'),
                DatePicker::make('valid_from')->label('Mulai berlaku')->default(now())->required(),
                DatePicker::make('valid_until')->label('Berakhir')->helperText('Akses dicabut otomatis setelah tanggal ini.'),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Nama')->searchable()->description(fn (Membership $record) => $record->user->email),
                TextColumn::make('role')->label('Peran')->badge(),
                TextColumn::make('position_title')->label('Jabatan')->placeholder('—'),
                TextColumn::make('valid_from')->label('Mulai')->date('d/m/Y'),
                TextColumn::make('valid_until')->label('Berakhir')->date('d/m/Y')->placeholder('—'),
                IconColumn::make('valid')->label('Berlaku')->boolean()->state(fn (Membership $record) => $record->isCurrentlyValid()),
                IconColumn::make('mfa')->label('MFA')->boolean()->state(fn (Membership $record) => filled($record->user->app_authentication_secret)),
            ])
            ->filters([SelectFilter::make('role')->options(Role::class)])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMemberships::route('/'),
            'create' => CreateMembership::route('/create'),
            'edit' => EditMembership::route('/{record}/edit'),
        ];
    }
}
