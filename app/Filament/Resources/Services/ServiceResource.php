<?php

namespace App\Filament\Resources\Services;

use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Filament\Resources\Services\Pages\ViewService;
use App\Filament\Resources\Services\Schemas\ServiceInfolist;
use App\Filament\Resources\Services\Schemas\ServiceSchema;
use App\Filament\Resources\Services\Tables\ServicesTable;
use App\Models\Service;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $slug = 'services';

    protected static ?string $recordTitleAttribute = 'name';

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedServer;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('services.resource.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('services.resource.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('services.resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('services.resource.model_plural_label');
    }

    /**
     * Mostra no menu quantos serviços estão rodando.
     */
    public static function getNavigationBadge(): ?string
    {
        $running = Service::query()
            ->where('status', \App\Enums\ServiceStatus::Running)
            ->count();

        return $running > 0 ? (string) $running : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function form(Schema $schema): Schema
    {
        return ServiceSchema::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return \App\Filament\Resources\Services\Schemas\ServiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServices::route('/'),
            'create' => CreateService::route('/create'),
            'view' => ViewService::route('/{record}'),
            'edit' => EditService::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return true;
    }
}
