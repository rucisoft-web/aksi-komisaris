<?php

namespace App\Filament\App\Resources\Meetings\Pages;

use App\Filament\App\Resources\Meetings\MeetingResource;
use Filament\Resources\Pages\EditRecord;

class EditMeeting extends EditRecord
{
    protected static string $resource = MeetingResource::class;
}
