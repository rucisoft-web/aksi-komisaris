<?php

namespace App\Filament\App\Resources\PersonalNotes\Pages;

use App\Filament\App\Resources\PersonalNotes\PersonalNoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPersonalNotes extends ListRecords
{
    protected static string $resource = PersonalNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
