<?php

namespace App\Filament\App\Resources\Documents\Pages;

use App\Filament\App\Resources\Documents\DocumentResource;
use App\Services\DocumentService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $upload = $data['upload'];
        $name = $data['upload_name'] ?? basename($upload);
        unset($data['upload'], $data['upload_name']);

        $data['created_by'] = auth()->id();
        $document = parent::handleRecordCreation($data);

        app(DocumentService::class)->addVersion($document, Storage::disk('local')->path($upload), $name, auth()->user(), 'Unggahan awal');
        Storage::disk('local')->delete($upload);

        return $document;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
