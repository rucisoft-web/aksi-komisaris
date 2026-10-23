<?php

namespace App\Filament\App\Resources\Advices\Pages;

use App\Filament\App\Resources\Advices\AdviceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdvices extends ListRecords
{
    protected static string $resource = AdviceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
