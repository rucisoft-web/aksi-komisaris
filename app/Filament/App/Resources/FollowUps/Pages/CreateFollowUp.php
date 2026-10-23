<?php

namespace App\Filament\App\Resources\FollowUps\Pages;

use App\Enums\FollowUpStatus;
use App\Filament\App\Resources\FollowUps\FollowUpResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFollowUp extends CreateRecord
{
    protected static string $resource = FollowUpResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['original_target_date' => $data['target_date'], 'status' => FollowUpStatus::Draf, 'created_by' => auth()->id()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
