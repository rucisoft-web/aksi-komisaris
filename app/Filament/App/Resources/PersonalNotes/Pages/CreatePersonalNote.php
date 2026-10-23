<?php

namespace App\Filament\App\Resources\PersonalNotes\Pages;

use App\Filament\App\Resources\PersonalNotes\PersonalNoteResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePersonalNote extends CreateRecord
{
    protected static string $resource = PersonalNoteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['user_id' => auth()->id()];
    }
}
