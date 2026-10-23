<?php

namespace App\Filament\App\Resources\Meetings\RelationManagers;

use App\Models\Minute;
use App\Services\MeetingService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MinutesRelationManager extends RelationManager
{
    protected static string $relationship = 'minutes';

    protected static ?string $modelLabel = 'risalah';

    protected static ?string $pluralModelLabel = 'risalah';

    protected static ?string $title = 'Risalah';

    public function isReadOnly(): bool
    {
        return false;
    }

    protected function canManage(): bool
    {
        return auth()->user()->canIn('rapat.kelola', $this->getOwnerRecord()->company_id);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('status')->label('Status')->options(['draf' => 'Draf', 'diedarkan' => 'Diedarkan ke anggota'])->default('draf')->required(),
            RichEditor::make('body')->label('Isi risalah')->required()->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('version_no')->label('Versi')->prefix('v'),
                TextColumn::make('status')->label('Status')->badge()->color(fn ($state) => $state === 'disahkan' ? 'success' : 'gray'),
                TextColumn::make('approver.name')->label('Disahkan oleh')->placeholder('—'),
                TextColumn::make('approved_at')->label('Tanggal sah')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextColumn::make('body')->label('Cuplikan')->html()->limit(160)->wrap(),
            ])
            ->headerActions([
                CreateAction::make()->label('Tulis risalah')
                    ->visible(fn () => $this->canManage())
                    ->mutateDataUsing(fn (array $data) => $data + ['version_no' => $this->getOwnerRecord()->minutes()->max('version_no') + 1]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Sahkan')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Minute $record) => $record->status !== 'disahkan' && auth()->user()->canIn('risalah.sahkan', $record->company_id))
                    ->action(function (Minute $record) {
                        app(MeetingService::class)->approveMinutes($record, auth()->user());
                        Notification::make()->title('Risalah disahkan.')->success()->send();
                    }),
                EditAction::make()->visible(fn (Minute $record) => $record->status !== 'disahkan' && $this->canManage()),
            ]);
    }
}
