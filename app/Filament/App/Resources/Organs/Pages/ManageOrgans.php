<?php

namespace App\Filament\App\Resources\Organs\Pages;

use App\Filament\App\Resources\Organs\OrganResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOrgans extends ManageRecords
{
    protected static string $resource = OrganResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
