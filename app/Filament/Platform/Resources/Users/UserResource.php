<?php

namespace App\Filament\Platform\Resources\Users;

use App\Enums\Role;
use App\Filament\Platform\Resources\Users\Pages\ManageUsers;
use App\Models\Company;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'Akun';

    protected static ?string $pluralModelLabel = 'Akun pengguna';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required(),
            TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('password')->label('Kata sandi')->password()->revealable()->minLength(10)
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn ($state) => filled($state)),
            Toggle::make('is_active')->label('Aktif')->default(true),
            Toggle::make('is_platform_admin')->label('Admin platform'),
            Repeater::make('memberships')
                ->label('Keanggotaan perusahaan')
                ->relationship('memberships')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('company_id')->label('Perusahaan')->options(fn () => Company::query()->pluck('name', 'id'))->required(),
                    Select::make('role')->label('Peran')->options(Role::class)->required(),
                    TextInput::make('position_title')->label('Jabatan'),
                    DatePicker::make('valid_from')->label('Mulai')->default(now())->required(),
                    DatePicker::make('valid_until')->label('Berakhir'),
                    Toggle::make('is_active')->label('Aktif')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('memberships_count')->label('Perusahaan')->counts('memberships'),
                IconColumn::make('mfa')->label('MFA')->boolean()->state(fn (User $record) => filled($record->app_authentication_secret)),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                IconColumn::make('is_platform_admin')->label('Admin platform')->boolean(),
            ])
            ->recordActions([
                Action::make('reset_mfa')
                    ->label('Reset MFA')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Pengguna wajib memasang ulang aplikasi autentikator saat masuk berikutnya.')
                    ->action(function (User $record) {
                        $record->saveAppAuthenticationSecret(null);
                        $record->saveAppAuthenticationRecoveryCodes(null);
                        Notification::make()->title('MFA direset.')->success()->send();
                    }),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
