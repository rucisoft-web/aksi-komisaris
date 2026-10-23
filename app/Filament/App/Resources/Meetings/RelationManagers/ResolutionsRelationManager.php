<?php

namespace App\Filament\App\Resources\Meetings\RelationManagers;

use App\Enums\FollowUpStatus;
use App\Filament\App\Resources\FollowUps\FollowUpResource;
use App\Models\Dissent;
use App\Models\FollowUp;
use App\Models\Resolution;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ResolutionsRelationManager extends RelationManager
{
    protected static string $relationship = 'resolutions';

    protected static ?string $modelLabel = 'keputusan';

    protected static ?string $pluralModelLabel = 'keputusan';

    protected static ?string $title = 'Keputusan dan perbedaan pendapat';

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
            Select::make('agenda_item_id')->label('Agenda')->options(fn () => $this->getOwnerRecord()->agendaItems()->pluck('title', 'id')),
            Select::make('decision_type')->label('Jenis')->options(['keputusan' => 'Keputusan', 'rekomendasi' => 'Rekomendasi', 'catatan' => 'Catatan pembahasan'])->default('keputusan')->required(),
            Textarea::make('text')->label('Rumusan')->required()->columnSpanFull()
                ->helperText('Tulis hanya yang benar-benar diputuskan dalam rapat.'),
        ]);
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        return $table
            ->columns([
                TextColumn::make('decision_type')->label('Jenis')->badge(),
                TextColumn::make('text')->label('Rumusan')->wrap(),
                TextColumn::make('dissents_list')->label('Perbedaan pendapat')->wrap()
                    ->state(fn (Resolution $record) => $record->dissents()->with('user')->get()->map(fn ($d) => $d->user->name.': '.$d->statement)->implode(' | ') ?: '—'),
            ])
            ->headerActions([CreateAction::make()->label('Catat keputusan')->visible(fn () => $this->canManage())])
            ->recordActions([
                Action::make('dissent')
                    ->label('Catat perbedaan pendapat saya')
                    ->icon('heroicon-o-scale')
                    ->visible(fn () => $user->roleIn($this->getOwnerRecord()->company_id)?->isCommissioner())
                    ->schema([Textarea::make('statement')->label('Pendapat berbeda')->required()])
                    ->action(function (Resolution $record, array $data) use ($user) {
                        Dissent::query()->create(['resolution_id' => $record->id, 'user_id' => $user->id, 'statement' => $data['statement']]);
                        Notification::make()->title('Perbedaan pendapat dicatat.')->success()->send();
                    }),
                Action::make('followup')
                    ->label('Buat tindak lanjut')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->visible(fn () => $user->canIn('tindaklanjut.kelola', $this->getOwnerRecord()->company_id))
                    ->schema(FollowUpResource::creationFields())
                    ->action(function (Resolution $record, array $data) use ($user) {
                        FollowUp::query()->create($data + [
                            'source_type' => Resolution::class,
                            'source_id' => $record->id,
                            'original_target_date' => $data['target_date'],
                            'status' => FollowUpStatus::Draf,
                            'created_by' => $user->id,
                        ]);
                        Notification::make()->title('Tindak lanjut dibuat sebagai draf.')->success()->send();
                    }),
                EditAction::make()->visible(fn () => $this->canManage() && $this->getOwnerRecord()->minutes()->where('status', 'disahkan')->doesntExist()),
            ]);
    }
}
