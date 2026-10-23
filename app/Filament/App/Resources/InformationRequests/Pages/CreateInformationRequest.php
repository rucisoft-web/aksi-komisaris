<?php

namespace App\Filament\App\Resources\InformationRequests\Pages;

use App\Enums\RequestStatus;
use App\Filament\App\Resources\InformationRequests\InformationRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInformationRequest extends CreateRecord
{
    protected static string $resource = InformationRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['status' => RequestStatus::Draf, 'created_by' => auth()->id()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
