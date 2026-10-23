<?php

namespace App\Filament\App\Resources\RequiredDocuments\Pages;

use App\Filament\App\Resources\RequiredDocuments\RequiredDocumentResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Artisan;

class ManageRequiredDocuments extends ManageRecords
{
    protected static string $resource = RequiredDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')->label('Perbarui status penerimaan')->color('gray')->icon('heroicon-o-arrow-path')
                ->action(function () {
                    Artisan::call('aksi:required-documents');
                    Notification::make()->title('Status dokumen wajib diperbarui.')->success()->send();
                }),
            CreateAction::make(),
        ];
    }
}
