<?php

namespace App\Filament\Resources\ServiceGroups;

use App\Filament\Resources\ServiceGroups\Pages\CreateServiceGroup;
use App\Filament\Resources\ServiceGroups\Pages\EditServiceGroup;
use App\Filament\Resources\ServiceGroups\Pages\ListServiceGroups;
use App\Filament\Resources\ServiceGroups\Schemas\ServiceGroupSchema;
use App\Filament\Resources\ServiceGroups\Tables\ServiceGroupsTable;
use App\Models\ServiceGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ServiceGroupResource extends Resource
{
    protected static ?string $model = ServiceGroup::class;

    protected static ?string $slug = 'service-groups';

    protected static ?string $recordTitleAttribute = 'name';

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('groups.resource.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('services.resource.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('groups.resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('groups.resource.model_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return ServiceGroupSchema::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServiceGroupsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceGroups::route('/'),
            'create' => CreateServiceGroup::route('/create'),
            'edit' => EditServiceGroup::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return true;
    }
}
