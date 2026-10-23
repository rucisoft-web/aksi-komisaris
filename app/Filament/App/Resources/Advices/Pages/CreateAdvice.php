<?php

namespace App\Filament\App\Resources\Advices\Pages;

use App\Enums\AdviceStatus;
use App\Filament\App\Resources\Advices\AdviceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdvice extends CreateRecord
{
    protected static string $resource = AdviceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['status' => AdviceStatus::Draf, 'created_by' => auth()->id()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
