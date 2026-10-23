<?php

namespace App\Filament\App\Resources\FollowUps\Pages;

use App\Filament\App\Resources\FollowUps\FollowUpResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFollowUps extends ListRecords
{
    protected static string $resource = FollowUpResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
