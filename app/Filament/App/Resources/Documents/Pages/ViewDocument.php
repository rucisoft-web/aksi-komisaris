<?php

namespace App\Filament\App\Resources\Documents\Pages;

use App\Enums\ProcessingStatus;
use App\Filament\App\Resources\AiJobs\AiJobResource;
use App\Filament\App\Resources\Documents\DocumentResource;
use App\Jobs\ProcessDocumentVersion;
use App\Models\Annotation;
use App\Models\PersonalNote;
use App\Models\User;
use App\Services\DocumentService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;

class ViewDocument extends ViewRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $document = $this->getRecord();

        return [
            Action::make('open')
                ->label('Buka berkas')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn () => $document->current_version_id ? route('berkas.lihat', $document->current_version_id) : null, shouldOpenInNewTab: true)
                ->visible(fn () => $document->current_version_id !== null),
            Action::make('ask_ai')
                ->label('Tanya asisten')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->url(fn () => AiJobResource::getUrl('create', ['mode' => 'jelaskan_dokumen', 'documents' => $document->id]))
                ->visible(fn () => $user->canIn('ai.gunakan', $document->company_id)),
            ActionGroup::make([
                Action::make('new_version')
                    ->label('Unggah versi baru')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->visible(fn () => $user->canIn('dokumen.kelola', $document->company_id) || $document->created_by === $user->id)
                    ->schema([
                        FileUpload::make('upload')->label('Berkas')->disk('local')->directory('incoming')->storeFileNamesIn('upload_name')->required()
                            ->maxSize(config('aksi.uploads.max_kb')),
                        Textarea::make('change_note')->label('Apa yang berubah?')->required(),
                    ])
                    ->action(function (array $data) use ($document, $user) {
                        app(DocumentService::class)->addVersion($document, Storage::disk('local')->path($data['upload']), $data['upload_name'] ?? basename($data['upload']), $user, $data['change_note']);
                        Storage::disk('local')->delete($data['upload']);
                        Notification::make()->title('Versi baru disimpan dan sedang diproses.')->success()->send();
                    }),
                Action::make('confirm_extraction')
                    ->label('Konfirmasi hasil ekstraksi')
                    ->icon('heroicon-o-check-badge')
                    ->requiresConfirmation()
                    ->modalDescription('Teks berkualitas rendah akan dipakai sebagai dasar analisis. Pastikan Anda sudah memeriksa halaman yang ditandai.')
                    ->visible(fn () => $document->currentVersion?->processing_status === ProcessingStatus::PerluTinjauan && $user->canIn('dokumen.kelola', $document->company_id))
                    ->action(function () use ($document, $user) {
                        app(DocumentService::class)->confirm($document->currentVersion, $user);
                        Notification::make()->title('Hasil ekstraksi dikonfirmasi.')->success()->send();
                    }),
                Action::make('reprocess')
                    ->label('Proses ulang teks')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn () => $user->canIn('dokumen.kelola', $document->company_id) && $document->current_version_id)
                    ->action(function () use ($document) {
                        ProcessDocumentVersion::dispatch($document->current_version_id);
                        Notification::make()->title('Pemrosesan ulang dijadwalkan.')->success()->send();
                    }),
                Action::make('annotate')
                    ->label('Tambah anotasi')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->visible(fn () => $document->current_version_id !== null)
                    ->schema([
                        TextInput::make('page_no')->label('Halaman')->numeric(),
                        Select::make('visibility')->label('Sifat')->options(['pribadi' => 'Pribadi (hanya saya)', 'bersama' => 'Bersama (pembaca dokumen ini)'])->default('pribadi')->required(),
                        Textarea::make('body')->label('Anotasi')->required(),
                    ])
                    ->action(function (array $data) use ($document, $user) {
                        Annotation::query()->create($data + ['document_version_id' => $document->current_version_id, 'user_id' => $user->id]);
                        Notification::make()->title('Anotasi disimpan.')->success()->send();
                    }),
                Action::make('private_note')
                    ->label('Catatan pribadi')
                    ->icon('heroicon-o-pencil-square')
                    ->schema([
                        TextInput::make('title')->label('Judul')->required(),
                        Textarea::make('body')->label('Catatan / pertanyaan')->rows(5)->required(),
                    ])
                    ->action(function (array $data) use ($document, $user) {
                        PersonalNote::query()->create($data + ['user_id' => $user->id, 'document_id' => $document->id]);
                        Notification::make()->title('Catatan pribadi disimpan.')->success()->send();
                    }),
            ])->label('Lainnya')->button()->color('gray'),
            EditAction::make(),
        ];
    }
}
