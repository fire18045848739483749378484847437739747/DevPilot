<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use App\Services\ProcessManager;
use Filament\Resources\Pages\CreateRecord;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;

    protected function afterCreate(): void
    {
        if ((bool) ($this->data['start_on_create'] ?? false)) {
            app(ProcessManager::class)->start($this->record);
        }
    }
}
