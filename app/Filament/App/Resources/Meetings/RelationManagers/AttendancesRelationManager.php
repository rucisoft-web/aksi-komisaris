<?php

namespace App\Filament\App\Resources\Meetings\RelationManagers;

use App\Models\Membership;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    protected static ?string $modelLabel = 'kehadiran';

    protected static ?string $pluralModelLabel = 'kehadiran';

    protected static ?string $title = 'Kehadiran';

    public function isReadOnly(): bool
    {
        return ! auth()->user()->canIn('rapat.kelola', $this->getOwnerRecord()->company_id);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label('Peserta')->required()
                ->options(fn () => Membership::query()->with('user')->get()->filter->isCurrentlyValid()->mapWithKeys(fn ($m) => [$m->user_id => $m->user->name])),
            Select::make('status')->label('Kehadiran')->options(['hadir' => 'Hadir', 'tidak_hadir' => 'Tidak hadir', 'izin' => 'Izin'])->default('hadir')->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Peserta'),
                TextColumn::make('status')->label('Kehadiran')->badge(),
            ])
            ->headerActions([CreateAction::make()->label('Catat kehadiran')])
            ->recordActions([EditAction::make()]);
    }
}
