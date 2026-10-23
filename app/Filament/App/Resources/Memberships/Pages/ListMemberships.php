<?php

namespace App\Filament\App\Resources\Memberships\Pages;

use App\Filament\App\Resources\Memberships\MembershipResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMemberships extends ListRecords
{
    protected static string $resource = MembershipResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
