<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -2;

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)->schema([
                    Select::make('period')
                        ->label('Time Range')
                        ->options([
                            'today' => 'Today',
                            '7d' => 'Past 7 Days',
                            '30d' => 'Past 30 Days',
                            'all' => 'All Time',
                        ])
                        ->default('30d'),
                    DatePicker::make('startDate')
                        ->label('Custom Start Date'),
                    DatePicker::make('endDate')
                        ->label('Custom End Date'),
                ]),
            ]);
    }
}
