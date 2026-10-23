<?php

namespace App\Filament\App\Resources\InformationRequests\Pages;

use App\Enums\Assessment;
use App\Enums\DocumentCategory;
use App\Enums\FollowUpStatus;
use App\Enums\RequestStatus;
use App\Enums\SpaceKind;
use App\Filament\App\Resources\AiJobs\AiJobResource;
use App\Filament\App\Resources\FollowUps\FollowUpResource;
use App\Filament\App\Resources\InformationRequests\InformationRequestResource;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\FollowUp;
use App\Models\InformationRequest;
use App\Models\Membership;
use App\Models\RequestResponse;
use App\Models\User;
use App\Notifications\AksiNotification;
use App\Services\DocumentService;
use App\Support\AccessGate;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ViewInformationRequest extends ViewRecord
{
    protected static string $resource = InformationRequestResource::class;

    protected function getHeaderActions(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $request = $this->getRecord();
        $company = $request->company;
        $isPrivate = $request->space->kind === SpaceKind::Pribadi;

        return [
            Action::make('submit')
                ->label('Ajukan ke dewan')
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn () => $request->status === RequestStatus::Draf && $request->created_by === $user->id)
                ->schema($isPrivate ? [
                    Select::make('space_id')->label('Pindahkan ke ruang resmi')->required()
                        ->options(fn () => app(AccessGate::class)->writableOfficialSpaces($user, $company)),
                ] : [])
                ->requiresConfirmation()
                ->modalDescription('Pertanyaan akan terlihat anggota ruang resmi dan menunggu persetujuan sebelum dikirim ke manajemen.')
                ->action(function (array $data) use ($request) {
                    $request->update(array_filter(['status' => RequestStatus::Diajukan, 'space_id' => $data['space_id'] ?? null]));
                    Notification::make()->title('Pertanyaan diajukan.')->success()->send();
                }),
            Action::make('approve_send')
                ->label('Setujui dan kirim')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $request->status === RequestStatus::Diajukan && ($user->canIn('permintaan.setujui', $company) || $user->canIn('permintaan.kirim', $company)))
                ->schema([
                    Select::make('addressee_user_id')->label('Penerima')->required()->default($request->addressee_user_id)
                        ->options(fn () => Membership::query()->with('user')->where('role', 'penghubung')->get()->mapWithKeys(fn ($m) => [$m->user_id => $m->user->name.' ('.$m->position_title.')'])),
                    DatePicker::make('due_date')->label('Tenggat jawaban')->required()->default($request->due_date),
                ])
                ->action(function (array $data) use ($request, $user, $company) {
                    if (! $user->canIn('permintaan.setujui', $company) && ! $request->approved_by) {
                        throw ValidationException::withMessages(['status' => 'Permintaan perlu disetujui Komisaris Utama sebelum dikirim.']);
                    }

                    $request->update($data + ['status' => RequestStatus::Dikirim, 'approved_by' => $request->approved_by ?? $user->id, 'sent_at' => now()]);
                    $request->addressee?->notify(new AksiNotification('Permintaan informasi baru', str($request->question)->limit(120), static::getResource()::getUrl('view', ['record' => $request])));
                    AuditLog::record('sent', $request);
                    Notification::make()->title('Permintaan dikirim ke penerima.')->success()->send();
                }),
            Action::make('approve_only')
                ->label('Setujui (dikirim sekretariat)')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn () => $request->status === RequestStatus::Diajukan && ! $request->approved_by && $user->canIn('permintaan.setujui', $company))
                ->action(function () use ($request, $user) {
                    $request->update(['approved_by' => $user->id]);
                    Notification::make()->title('Disetujui. Sekretariat dapat mengirimkannya.')->success()->send();
                }),
            Action::make('answer')
                ->label('Jawab')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->visible(fn () => in_array($request->status, [RequestStatus::Dikirim, RequestStatus::Dijawab], true) && $request->addressee_user_id === $user->id)
                ->schema([
                    Textarea::make('body')->label('Jawaban')->rows(6)->required(),
                    FileUpload::make('upload')->label('Dokumen pendukung')->disk('local')->directory('incoming')->storeFileNamesIn('upload_name')
                        ->maxSize(config('aksi.uploads.max_kb')),
                ])
                ->action(function (array $data) use ($request, $user) {
                    $documentId = null;

                    if (! empty($data['upload'])) {
                        $document = Document::query()->create([
                            'space_id' => $request->space_id,
                            'title' => 'Lampiran jawaban: '.str($request->question)->limit(80),
                            'category' => DocumentCategory::Pengawasan,
                            'classification' => 'internal',
                            'status' => 'final',
                            'created_by' => $user->id,
                        ]);
                        app(DocumentService::class)->addVersion($document, Storage::disk('local')->path($data['upload']), $data['upload_name'] ?? 'lampiran', $user, 'Lampiran jawaban');
                        Storage::disk('local')->delete($data['upload']);
                        $documentId = $document->id;
                    }

                    RequestResponse::query()->create(['information_request_id' => $request->id, 'responder_id' => $user->id, 'body' => $data['body'], 'document_id' => $documentId]);
                    $request->update(['status' => RequestStatus::Dijawab, 'answered_at' => now()]);
                    $request->creator?->notify(new AksiNotification('Jawaban manajemen diterima', str($request->question)->limit(120), static::getResource()::getUrl('view', ['record' => $request])));
                    Notification::make()->title('Jawaban terkirim.')->success()->send();
                }),
            Action::make('check_ai')
                ->label('Periksa jawaban dengan AI')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->url(fn () => AiJobResource::getUrl('create', ['mode' => 'periksa_jawaban', 'request' => $request->id]))
                ->visible(fn () => $request->status === RequestStatus::Dijawab && $user->canIn('ai.gunakan', $company)),
            Action::make('assess')
                ->label('Nilai jawaban')
                ->icon('heroicon-o-clipboard-document-check')
                ->visible(fn () => $request->status === RequestStatus::Dijawab && $user->canIn('permintaan.nilai', $company))
                ->schema([
                    Select::make('assessment')->label('Penilaian')->options(Assessment::class)->required(),
                    Textarea::make('assessment_note')->label('Catatan')->required(),
                ])
                ->action(function (array $data) use ($request) {
                    $request->update($data + ['status' => RequestStatus::Dinilai]);
                    Notification::make()->title('Penilaian disimpan.')->success()->send();
                }),
            Action::make('clarify')
                ->label('Minta klarifikasi')
                ->icon('heroicon-o-arrow-uturn-right')
                ->color('warning')
                ->visible(fn () => $request->status === RequestStatus::Dinilai && $request->assessment !== Assessment::Memadai && $user->canIn('permintaan.buat', $company))
                ->schema([Textarea::make('question')->label('Pertanyaan klarifikasi')->required()])
                ->action(function (array $data) use ($request, $user) {
                    $child = InformationRequest::query()->create([
                        'space_id' => $request->space_id,
                        'parent_id' => $request->id,
                        'question' => $data['question'],
                        'basis' => 'Klarifikasi atas permintaan #'.$request->id,
                        'addressee_user_id' => $request->addressee_user_id,
                        'status' => RequestStatus::Diajukan,
                        'created_by' => $user->id,
                    ]);
                    $this->redirect(static::getResource()::getUrl('view', ['record' => $child]));
                }),
            Action::make('followup')
                ->label('Jadikan tindak lanjut')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('gray')
                ->visible(fn () => in_array($request->status, [RequestStatus::Dinilai, RequestStatus::Dijawab], true) && $user->canIn('tindaklanjut.kelola', $company))
                ->schema(FollowUpResource::creationFields())
                ->action(function (array $data) use ($request, $user) {
                    FollowUp::query()->create($data + [
                        'source_type' => InformationRequest::class,
                        'source_id' => $request->id,
                        'original_target_date' => $data['target_date'],
                        'status' => FollowUpStatus::Draf,
                        'created_by' => $user->id,
                    ]);
                    Notification::make()->title('Tindak lanjut dibuat sebagai draf.')->success()->send();
                }),
            Action::make('close')
                ->label('Tutup')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $request->status === RequestStatus::Dinilai && $user->canIn('permintaan.nilai', $company))
                ->action(fn () => $request->update(['status' => RequestStatus::Ditutup, 'closed_at' => now()])),
            EditAction::make(),
        ];
    }
}
