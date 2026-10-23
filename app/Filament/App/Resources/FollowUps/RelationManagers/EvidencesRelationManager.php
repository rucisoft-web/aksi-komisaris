<?php

namespace App\Filament\App\Resources\FollowUps\RelationManagers;

use App\Models\FollowUpEvidence;
use App\Services\FollowUpService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EvidencesRelationManager extends RelationManager
{
    protected static string $relationship = 'evidences';

    protected static ?string $modelLabel = 'bukti';

    protected static ?string $pluralModelLabel = 'bukti';

    protected static ?string $title = 'Bukti penyelesaian';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        $followUp = $this->getOwnerRecord();

        return $schema->components([
            Textarea::make('description')->label('Uraian bukti')->required()->columnSpanFull(),
            FileUpload::make('disk_path')->label('Berkas bukti')
                ->disk(config('aksi.uploads.disk'))
                ->directory('company/'.$followUp->company_id.'/evidence/'.$followUp->id)
                ->visibility('private')
                ->storeFileNamesIn('original_name')
                ->maxSize(config('aksi.uploads.max_kb'))
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        $followUp = $this->getOwnerRecord();
        $user = auth()->user();
        $service = app(FollowUpService::class);

        return $table
            ->columns([
                TextColumn::make('description')->label('Uraian')->wrap(),
                TextColumn::make('original_name')->label('Berkas')->placeholder('—')
                    ->url(fn (FollowUpEvidence $record) => $record->disk_path ? route('bukti.lihat', $record) : null, shouldOpenInNewTab: true),
                TextColumn::make('uploader.name')->label('Diunggah oleh'),
                IconColumn::make('is_verified')->label('Terverifikasi')->boolean(),
                TextColumn::make('created_at')->label('Tanggal')->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                CreateAction::make()->label('Unggah bukti')
                    ->visible(fn () => $followUp->pic_user_id === $user->id || $user->canIn('tindaklanjut.kelola', $followUp->company_id))
                    ->mutateDataUsing(fn (array $data) => $data + ['uploaded_by' => $user->id, 'company_id' => $followUp->company_id]),
            ])
            ->recordActions([
                Action::make('verify')
                    ->label('Verifikasi')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (FollowUpEvidence $record) => ! $record->is_verified && $service->isReviewer($followUp, $user))
                    ->action(function (FollowUpEvidence $record) use ($service, $followUp, $user) {
                        $service->verifyEvidence($followUp, $record->id, $user);
                        Notification::make()->title('Bukti diverifikasi.')->success()->send();
                    }),
            ]);
    }
}
