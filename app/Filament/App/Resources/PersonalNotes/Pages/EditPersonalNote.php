<?php

namespace App\Filament\App\Resources\PersonalNotes\Pages;

use App\Filament\App\Resources\PersonalNotes\PersonalNoteResource;
use Filament\Resources\Pages\EditRecord;

class EditPersonalNote extends EditRecord
{
    protected static string $resource = PersonalNoteResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless($this->getRecord()->user_id === auth()->id(), 404);
    }
}
