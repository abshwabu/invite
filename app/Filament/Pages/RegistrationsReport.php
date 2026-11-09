<?php

namespace App\Filament\Pages;

use App\Models\Tier;
use App\Models\User;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationsReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = 'Analytics & Reports';

    protected static ?string $navigationLabel = 'Registrations Report';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.registrations-report';

    public function getTotalRegistrations(): int
    {
        return User::count();
    }

    public function getTodayRegistrations(): int
    {
        return User::whereDate('created_at', today())->count();
    }

    public function getThisWeekRegistrations(): int
    {
        return User::where('created_at', '>=', now()->subDays(7))->count();
    }

    public function getThisMonthRegistrations(): int
    {
        return User::where('created_at', '>=', now()->subDays(30))->count();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->with(['activeSubscription.tier'])->latest('created_at'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->default('-'),
                Tables\Columns\TextColumn::make('country')
                    ->badge()
                    ->color('info')
                    ->default('ET')
                    ->sortable(),
                Tables\Columns\TextColumn::make('registration_source')
                    ->label('Source')
                    ->badge()
                    ->default('direct')
                    ->sortable(),
                Tables\Columns\TextColumn::make('registered_ip')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('activeSubscription.tier.name_en')
                    ->label('Tier')
                    ->badge()
                    ->color('success')
                    ->default('Free'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime('M d, Y H:i')
                    ->default('Never')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tier')
                    ->label('Subscription Tier')
                    ->relationship('subscriptions.tier', 'name_en'),
                Tables\Filters\SelectFilter::make('country')
                    ->options(fn () => User::whereNotNull('country')->distinct()->pluck('country', 'country')->toArray()),
                Tables\Filters\SelectFilter::make('registration_source')
                    ->label('Source')
                    ->options(fn () => User::whereNotNull('registration_source')->distinct()->pluck('registration_source', 'registration_source')->toArray()),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('registered_from')->label('Registered From'),
                        Forms\Components\DatePicker::make('registered_until')->label('Registered Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['registered_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['registered_until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_csv')
                    ->label('Export Registrations (CSV)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (): StreamedResponse {
                        $records = User::with(['activeSubscription.tier'])->latest('created_at')->get();
                        $filename = 'registrations-report-' . now()->format('YmdHis') . '.csv';

                        return response()->stream(function () use ($records) {
                            $handle = fopen('php://output', 'w');
                            fputs($handle, "\xEF\xBB\xBF");
                            fputcsv($handle, [
                                'User ID', 'Name', 'Email', 'Phone', 'Country',
                                'Registration Source', 'Registered IP', 'Tier',
                                'Registered At', 'Last Login At',
                            ]);

                            foreach ($records as $u) {
                                fputcsv($handle, [
                                    $u->id,
                                    $u->name,
                                    $u->email,
                                    $u->phone ?? '',
                                    $u->country ?? 'ET',
                                    $u->registration_source ?? 'direct',
                                    $u->registered_ip ?? '',
                                    $u->activeSubscription?->tier?->name_en ?? 'Free',
                                    $u->created_at?->toIso8601String() ?? '',
                                    $u->last_login_at?->toIso8601String() ?? '',
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
