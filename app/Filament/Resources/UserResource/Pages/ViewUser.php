<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\UsageLedger;
use App\Models\User;
use Filament\Actions;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('impersonate')
                ->label('Impersonate User')
                ->icon('heroicon-o-eye')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\Select::make('mode')
                        ->label('Impersonation Mode')
                        ->options([
                            'read_only' => 'Read-Only (Safest: cannot modify data)',
                            'full' => 'Full Session (Can perform actions as user)',
                        ])
                        ->default('read_only')
                        ->required(),
                    \Filament\Forms\Components\Select::make('duration')
                        ->label('Time Limit')
                        ->options([
                            15 => '15 minutes',
                            30 => '30 minutes',
                            60 => '1 hour',
                            120 => '2 hours',
                        ])
                        ->default(30)
                        ->required(),
                ])
                ->action(function (User $record, array $data) {
                    return redirect()->route('superadmin.impersonate', [
                        'user' => $record->id,
                        'mode' => $data['mode'],
                        'duration' => $data['duration'],
                    ]);
                }),
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Header Profile & Registration Grid
                Section::make('Profile & Registration Dossier')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('name')
                                ->weight('bold'),
                            TextEntry::make('email')
                                ->copyable(),
                            TextEntry::make('phone')
                                ->default('—'),
                            TextEntry::make('locale')
                                ->badge()
                                ->uppercase(),
                        ]),
                        Grid::make(4)->schema([
                            TextEntry::make('country')
                                ->badge()
                                ->default('Unknown'),
                            TextEntry::make('registered_ip')
                                ->label('Registration IP')
                                ->fontFamily(\Filament\Support\Enums\FontFamily::Mono)
                                ->default('127.0.0.1'),
                            TextEntry::make('registration_source')
                                ->label('Source / Channel')
                                ->badge()
                                ->default('direct'),
                            TextEntry::make('is_suspended')
                                ->label('Status')
                                ->badge()
                                ->formatStateUsing(fn ($state) => $state ? 'Suspended' : 'Active')
                                ->color(fn ($state) => $state ? 'danger' : 'success'),
                        ]),
                        Grid::make(4)->schema([
                            TextEntry::make('created_at')
                                ->label('Registered On')
                                ->dateTime(),
                            TextEntry::make('last_login_at')
                                ->label('Last Active / Login')
                                ->since()
                                ->default('Never'),
                            TextEntry::make('activeSubscription.tier.name_en')
                                ->label('Current Tier')
                                ->badge()
                                ->color('primary')
                                ->default('Free'),
                            TextEntry::make('activeSubscription.cards_total')
                                ->label('Quota Remaining')
                                ->formatStateUsing(fn ($record) => ($record->activeSubscription?->cards_total ?? 0) - ($record->activeSubscription?->cards_used ?? 0) . ' / ' . ($record->activeSubscription?->cards_total ?? 0) . ' cards')
                                ->weight('bold'),
                        ]),
                    ]),

                // Detailed Tabs: Subscriptions, Events, Usage, Payments, Campaigns, Audit
                Tabs::make('User Activity & Records')
                    ->tabs([
                        Tabs\Tab::make('Subscription History')
                            ->icon('heroicon-o-sparkles')
                            ->schema([
                                RepeatableEntry::make('subscriptions')
                                    ->schema([
                                        Grid::make(6)->schema([
                                            TextEntry::make('tier.name_en')->label('Tier')->weight('bold'),
                                            TextEntry::make('cards_total')->label('Total Cards'),
                                            TextEntry::make('cards_used')->label('Used'),
                                            TextEntry::make('status')->badge()->color(fn ($state) => match ($state) {
                                                'active' => 'success',
                                                'expired' => 'danger',
                                                default => 'gray',
                                            }),
                                            TextEntry::make('source')->badge(),
                                            TextEntry::make('expires_at')->label('Expires')->dateTime()->default('Perpetual'),
                                        ]),
                                        TextEntry::make('note')->label('Admin Note')->default('None')->italic(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Events Created')
                            ->icon('heroicon-o-calendar')
                            ->schema([
                                RepeatableEntry::make('events')
                                    ->schema([
                                        Grid::make(5)->schema([
                                            TextEntry::make('title')->weight('bold'),
                                            TextEntry::make('category.name_en')->label('Category'),
                                            TextEntry::make('starts_at')->label('Event Date')->dateTime(),
                                            TextEntry::make('status')->badge(),
                                            TextEntry::make('guests_count')->label('Guests')
                                                ->state(fn ($record) => $record->guests()->count()),
                                        ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Monthly Usage')
                            ->icon('heroicon-o-chart-bar')
                            ->schema([
                                TextEntry::make('monthly_usage_summary')
                                    ->label('Cards Used by Month')
                                    ->state(function (User $record) {
                                        $monthly = UsageLedger::where('user_id', $record->id)
                                            ->select(DB::raw("strftime('%Y-%m', created_at) as m"), DB::raw('sum(quantity) as total'))
                                            ->groupBy('m')
                                            ->orderBy('m', 'desc')
                                            ->pluck('total', 'm');

                                        if ($monthly->isEmpty()) {
                                            return 'No usage recorded yet.';
                                        }

                                        $lines = [];
                                        foreach ($monthly as $month => $total) {
                                            $lines[] = "{$month}: {$total} card(s)";
                                        }
                                        return implode(' • ', $lines);
                                    }),
                                RepeatableEntry::make('usageLedgers')
                                    ->label('Recent Usage Ledger Entries')
                                    ->schema([
                                        Grid::make(5)->schema([
                                            TextEntry::make('kind')->badge()->uppercase(),
                                            TextEntry::make('channel')->badge(),
                                            TextEntry::make('quantity')->weight('bold'),
                                            TextEntry::make('event.title')->label('Event')->default('General'),
                                            TextEntry::make('created_at')->dateTime(),
                                        ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Payments')
                            ->icon('heroicon-o-credit-card')
                            ->schema([
                                RepeatableEntry::make('payments')
                                    ->schema([
                                        Grid::make(5)->schema([
                                            TextEntry::make('amount')->label('Amount (ETB)')->weight('bold'),
                                            TextEntry::make('payment_method')->label('Method')->badge(),
                                            TextEntry::make('status')->badge(),
                                            TextEntry::make('reference')->fontFamily(\Filament\Support\Enums\FontFamily::Mono),
                                            TextEntry::make('created_at')->dateTime(),
                                        ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Campaigns')
                            ->icon('heroicon-o-paper-airplane')
                            ->schema([
                                RepeatableEntry::make('campaigns')
                                    ->schema([
                                        Grid::make(5)->schema([
                                            TextEntry::make('type')->badge()->uppercase(),
                                            TextEntry::make('channel')->badge(),
                                            TextEntry::make('status')->badge(),
                                            TextEntry::make('audience_count')->label('Recipients'),
                                            TextEntry::make('created_at')->dateTime(),
                                        ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Audit Trail')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                TextEntry::make('audit_trail')
                                    ->label('Audit Trail')
                                    ->state(function (User $record) {
                                        $logs = AuditLog::where(function ($q) use ($record) {
                                            $q->where('subject_type', User::class)->where('subject_id', $record->id)
                                                ->orWhere('actor_type', User::class)->where('actor_id', $record->id);
                                        })->latest('created_at')->take(15)->get();

                                        if ($logs->isEmpty()) {
                                            return 'No audit trail recorded.';
                                        }

                                        $items = [];
                                        foreach ($logs as $l) {
                                            $actor = class_basename($l->actor_type ?? 'System') . ($l->actor_id ? " #{$l->actor_id}" : '');
                                            $items[] = "{$l->created_at->format('Y-m-d H:i')} • {$l->action} by {$actor} (IP: {$l->ip})";
                                        }
                                        return implode("\n", $items);
                                    })
                                    ->formatStateUsing(fn ($state) => nl2br(e($state)))
                                    ->html(),
                            ]),
                    ]),
            ]);
    }
}
