<?php

namespace App\Filament\Pages;

use App\Models\Tier;
use App\Models\UsageLedger;
use App\Models\User;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UsageReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Analytics & Reports';

    protected static ?string $navigationLabel = 'Usage Report';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.usage-report';

    public function getTotalCardsConsumed(): int
    {
        return (int) UsageLedger::where('quantity', '>', 0)->sum('quantity');
    }

    public function getSmsCards(): int
    {
        return (int) UsageLedger::where('channel', 'sms')->sum('quantity');
    }

    public function getEmailCards(): int
    {
        return (int) UsageLedger::where('channel', 'email')->sum('quantity');
    }

    public function getAdjustmentCards(): int
    {
        return (int) UsageLedger::where('kind', 'adjustment')->sum('quantity');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(UsageLedger::query()->latest('created_at'))
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('user.activeSubscription.tier.name_en')
                    ->label('Tier')
                    ->badge()
                    ->default('Free'),
                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event')
                    ->default('General / Account')
                    ->searchable()
                    ->limit(25),
                Tables\Columns\TextColumn::make('kind')
                    ->badge()
                    ->uppercase(),
                Tables\Columns\TextColumn::make('channel')
                    ->badge()
                    ->uppercase(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cards')
                    ->weight('bold')
                    ->color(fn (int $state) => $state < 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn (int $state) => $state > 0 ? "-{$state}" : "+{$state}"),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Filter by User')
                    ->options(fn () => User::pluck('name', 'id')->toArray())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('kind')
                    ->options([
                        'invitation' => 'Invitation',
                        'reminder' => 'Reminder',
                        'thank_you' => 'Thank You',
                        'custom' => 'Custom Message',
                        'adjustment' => 'Admin Adjustment',
                        'refund' => 'Refund',
                    ]),
                Tables\Filters\SelectFilter::make('channel')
                    ->options([
                        'sms' => 'SMS',
                        'email' => 'Email',
                        'none' => 'None',
                    ]),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From Date'),
                        Forms\Components\DatePicker::make('until')->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_csv')
                    ->label('Export Usage (CSV)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (): StreamedResponse {
                        $records = UsageLedger::with(['user.activeSubscription.tier', 'event'])->latest('created_at')->get();
                        $filename = 'usage-report-' . now()->format('YmdHis') . '.csv';

                        return response()->stream(function () use ($records) {
                            $handle = fopen('php://output', 'w');
                            fputs($handle, "\xEF\xBB\xBF");
                            fputcsv($handle, [
                                'ID', 'User ID', 'User Name', 'User Email', 'Tier',
                                'Event Title', 'Kind', 'Channel', 'Quantity', 'Date',
                            ]);

                            foreach ($records as $r) {
                                fputcsv($handle, [
                                    $r->id,
                                    $r->user_id,
                                    $r->user?->name ?? 'Unknown',
                                    $r->user?->email ?? '',
                                    $r->user?->activeSubscription?->tier?->name_en ?? 'Free',
                                    $r->event?->title ?? 'General / Account',
                                    $r->kind,
                                    $r->channel,
                                    $r->quantity,
                                    $r->created_at?->toIso8601String() ?? '',
                                ]);
                            }
                            fclose($handle);
                        }, 200, [
                            'Content-Type' => 'text/csv; charset=UTF-8',
                            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                        ]);
                    }),
            ]);
    }
}
