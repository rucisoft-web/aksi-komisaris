<?php

namespace App\Filament\App\Resources\Issues\Pages;

use App\Filament\App\Resources\Issues\IssueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIssues extends ListRecords
{
    protected static string $resource = IssueResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
