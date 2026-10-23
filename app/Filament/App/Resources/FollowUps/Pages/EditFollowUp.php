<?php

namespace App\Filament\App\Resources\FollowUps\Pages;

use App\Filament\App\Resources\FollowUps\FollowUpResource;
use Filament\Resources\Pages\EditRecord;

class EditFollowUp extends EditRecord
{
    protected static string $resource = FollowUpResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Sebelum disampaikan, tenggat awal ikut disesuaikan. Setelahnya wajib lewat pengajuan perubahan tenggat.
        return $data + ['original_target_date' => $data['target_date']];
    }
}
