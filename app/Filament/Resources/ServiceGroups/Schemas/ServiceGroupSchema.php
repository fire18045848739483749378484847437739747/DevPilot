<?php

namespace App\Filament\Resources\ServiceGroups\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ServiceGroupSchema
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(static::components())->columns(2);
    }

    /**
     * Componentes soltos para reuso no `createOptionForm` do formulário de
     * serviço (que monta o próprio schema).
     *
     * @return list<\Filament\Schemas\Components\Component>
     */
    public static function components(): array
    {
        return [
            TextInput::make('name')
                ->label(__('groups.fields.name'))
                ->required()
                ->maxLength(120),

            Select::make('color')
                ->label(__('groups.fields.color'))
                ->options([
                    'primary' => __('groups.colors.primary'),
                    'info' => __('groups.colors.info'),
                    'warning' => __('groups.colors.warning'),
                    'danger' => __('groups.colors.danger'),
                    'gray' => __('groups.colors.gray'),
                ])
                ->default('primary')
                ->native(false),

            TextInput::make('start_delay_seconds')
                ->label(__('groups.fields.start_delay_seconds'))
                ->helperText(__('groups.hints.start_delay_seconds'))
                ->numeric()
                ->minValue(0)
                ->maxValue(30)
                ->default(2)
                ->suffix(__('services.units.seconds')),

            Textarea::make('description')
                ->label(__('groups.fields.description'))
                ->rows(2)
                ->columnSpanFull(),
        ];
    }
}
