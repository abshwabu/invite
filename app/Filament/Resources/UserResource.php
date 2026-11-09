<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Subscription;
use App\Models\Tier;
use App\Models\UsageLedger;
use App\Models\User;
use App\Services\AuditLogService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Platform & Access';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(20),
                Forms\Components\Select::make('locale')
                    ->options([
                        'en' => 'English',
                        'am' => 'Amharic',
                    ])
                    ->default('en'),
                Forms\Components\TextInput::make('country')
                    ->maxLength(100)
                    ->default('ET'),
                Forms\Components\Toggle::make('is_suspended')
                    ->label('Account Suspended')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->description(fn (User $record): ?string => $record->phone),
                Tables\Columns\TextColumn::make('activeSubscription.tier.name_en')
                    ->label('Tier')
                    ->default('Free')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('quota_usage')
                    ->label('Cards Used / Total')
                    ->state(function (User $record) {
                        $sub = $record->activeSubscription;
                        if (! $sub) {
                            return '0 / 0';
                        }
                        $remaining = max(0, $sub->cards_total - $sub->cards_used);
                        return "{$sub->cards_used} / {$sub->cards_total} ({$remaining} left)";
                    })
                    ->badge()
                    ->color(function (User $record) {
                        $sub = $record->activeSubscription;
                        if (! $sub || $sub->cards_total <= 0) return 'gray';
                        $ratio = ($sub->cards_total - $sub->cards_used) / $sub->cards_total;
                        if ($ratio <= 0) return 'danger';
                        if ($ratio <= 0.10) return 'warning';
                        return 'success';
                    }),
                Tables\Columns\TextColumn::make('events_count')
                    ->counts('events')
                    ->label('Events')
                    ->sortable(),
                Tables\Columns\TextColumn::make('country')
                    ->badge()
                    ->default('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('M d, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->since()
                    ->default('Never')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_suspended')
                    ->boolean()
                    ->label('Suspended')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tier')
                    ->label('Subscription Tier')
                    ->relationship('subscriptions.tier', 'name_en'),

                Tables\Filters\TernaryFilter::make('is_suspended')
                    ->label('Suspension Status')
                    ->placeholder('All Users')
                    ->trueLabel('Suspended only')
                    ->falseLabel('Active only'),

                Tables\Filters\SelectFilter::make('country')
                    ->options(fn () => User::whereNotNull('country')->distinct()->pluck('country', 'country')->toArray()),

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

                Tables\Filters\Filter::make('last_login_at')
                    ->form([
                        Forms\Components\DatePicker::make('login_from')->label('Last Login From'),
                        Forms\Components\DatePicker::make('login_until')->label('Last Login Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['login_from'], fn ($q, $date) => $q->whereDate('last_login_at', '>=', $date))
                            ->when($data['login_until'], fn ($q, $date) => $q->whereDate('last_login_at', '<=', $date));
                    }),

                Tables\Filters\SelectFilter::make('quota_status')
                    ->options([
                        'exhausted' => 'Quota Exhausted (0 left)',
                        'low' => 'Low Quota (≤ 10% remaining)',
                        'active' => 'Active Balance (> 10%)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) return $query;
                        return match ($data['value']) {
                            'exhausted' => $query->whereHas('subscriptions', fn ($q) => $q->where('status', 'active')->whereRaw('cards_total - cards_used <= 0')),
                            'low' => $query->whereHas('subscriptions', fn ($q) => $q->where('status', 'active')->whereRaw('cards_total - cards_used > 0 and (cards_total - cards_used) * 10 <= cards_total')),
                            'active' => $query->whereHas('subscriptions', fn ($q) => $q->where('status', 'active')->whereRaw('(cards_total - cards_used) * 10 > cards_total')),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                // 1. Suspend / Unsuspend Action
                Tables\Actions\Action::make('toggle_suspend')
                    ->label(fn (User $record) => $record->is_suspended ? 'Unsuspend' : 'Suspend')
                    ->icon(fn (User $record) => $record->is_suspended ? 'heroicon-o-check-circle' : 'heroicon-o-no-symbol')
                    ->color(fn (User $record) => $record->is_suspended ? 'success' : 'danger')
                    ->visible(fn () => auth('superadmin')->user()?->isOwner() ?? true)
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        $newStatus = ! $record->is_suspended;
                        $record->update(['is_suspended' => $newStatus]);

                        AuditLogService::log(
                            $newStatus ? 'suspend_user' : 'unsuspend_user',
                            null,
                            $record,
                            ['user_id' => $record->id, 'name' => $record->name, 'email' => $record->email]
                        );

                        Notification::make()
                            ->title($newStatus ? "User {$record->name} suspended" : "User {$record->name} unsuspended")
                            ->color($newStatus ? 'danger' : 'success')
                            ->send();
                    }),

                // 2. Adjust Cards Action
                Tables\Actions\Action::make('adjust_cards')
                    ->label('Adjust Cards')
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->visible(fn () => auth('superadmin')->user()?->canManageMoneyOrTiers() ?? true)
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Card Adjustment Amount')
                            ->numeric()
                            ->required()
                            ->helperText('Positive number (e.g. 50) to add cards, negative (e.g. -20) to deduct cards'),
                        Forms\Components\Textarea::make('reason')
                            ->label('Mandatory Reason for Adjustment')
                            ->required()
                            ->rows(2)
                            ->helperText('Explain why cards are being added or deducted (logged in ledger and audit trail)'),
                    ])
                    ->action(function (User $record, array $data) {
                        $sub = $record->activeSubscription;
                        if (! $sub) {
                            $sub = $record->subscriptions()->latest('id')->first();
                        }

                        if (! $sub) {
                            Notification::make()->title('No subscription found for user')->danger()->send();
                            return;
                        }

                        $amount = (int) $data['amount'];
                        $oldTotal = $sub->cards_total;
                        $newTotal = max(0, $sub->cards_total + $amount);

                        $sub->update([
                            'cards_total' => $newTotal,
                        ]);

                        // Record in usage_ledger as adjustment
                        UsageLedger::create([
                            'user_id' => $record->id,
                            'subscription_id' => $sub->id,
                            'kind' => 'adjustment',
                            'channel' => 'none',
                            'quantity' => $amount,
                            'created_at' => now(),
                        ]);

                        // Record in audit_logs
                        AuditLogService::log(
                            'adjust_cards',
                            null,
                            $record,
                            [
                                'user_id' => $record->id,
                                'old_total' => $oldTotal,
                                'new_total' => $newTotal,
                                'adjustment_amount' => $amount,
                                'reason' => $data['reason'],
                            ]
                        );

                        Notification::make()
                            ->title("Adjusted {$amount} cards for {$record->name}")
                            ->body("New card total: {$newTotal}")
                            ->success()
                            ->send();
                    }),

                // 3. Extend Expiry Action
                Tables\Actions\Action::make('extend_expiry')
                    ->label('Extend Expiry')
                    ->icon('heroicon-o-clock')
                    ->visible(fn () => auth('superadmin')->user()?->canManageMoneyOrTiers() ?? true)
                    ->form([
                        Forms\Components\TextInput::make('days')
                            ->label('Days to Extend')
                            ->numeric()
                            ->default(30)
                            ->minValue(1)
                            ->required(),
                        Forms\Components\Textarea::make('reason')
                            ->label('Mandatory Reason')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (User $record, array $data) {
                        $sub = $record->activeSubscription ?? $record->subscriptions()->latest('id')->first();
                        if (! $sub) {
                            Notification::make()->title('No subscription found')->danger()->send();
                            return;
                        }

                        $days = (int) $data['days'];
                        $currentExpiry = $sub->expires_at && $sub->expires_at->isFuture() ? $sub->expires_at : now();
                        $newExpiry = $currentExpiry->copy()->addDays($days);

                        $sub->update(['expires_at' => $newExpiry, 'status' => 'active']);

                        AuditLogService::log(
                            'extend_subscription',
                            null,
                            $record,
                            [
                                'subscription_id' => $sub->id,
                                'days' => $days,
                                'new_expires_at' => $newExpiry->toIso8601String(),
                                'reason' => $data['reason'],
                            ]
                        );

                        Notification::make()
                            ->title("Subscription extended by {$days} days")
                            ->body("New expiration: {$newExpiry->format('M d, Y')}")
                            ->success()
                            ->send();
                    }),

                // 4. Grant Tier Action
                Tables\Actions\Action::make('grant_tier')
                    ->label('Grant Tier')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->visible(fn () => auth('superadmin')->user()?->canManageMoneyOrTiers() ?? true)
                    ->form([
                        Forms\Components\Select::make('tier_id')
                            ->label('Select Tier')
                            ->options(Tier::where('is_active', true)->pluck('name_en', 'id'))
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state && $tier = Tier::find($state)) {
                                    $set('cards_total', $tier->card_quota);
                                    $set('validity_days', $tier->validity_days);
                                }
                            }),
                        Forms\Components\TextInput::make('cards_total')
                            ->label('Card Quota')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('validity_days')
                            ->label('Validity (Days)')
                            ->numeric()
                            ->nullable(),
                        Forms\Components\Textarea::make('note')
                            ->label('Reason / Admin Note')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (User $record, array $data) {
                        $tier = Tier::findOrFail($data['tier_id']);
                        $cards = (int) $data['cards_total'];
                        $validityDays = ! empty($data['validity_days']) ? (int) $data['validity_days'] : null;
                        $expiresAt = $validityDays ? now()->addDays($validityDays) : null;

                        $subscription = $record->subscriptions()->create([
                            'tier_id' => $tier->id,
                            'cards_total' => $cards,
                            'cards_used' => 0,
                            'starts_at' => now(),
                            'expires_at' => $expiresAt,
                            'status' => 'active',
                            'source' => 'admin_grant',
                            'note' => $data['note'],
                        ]);

                        AuditLogService::log(
                            'grant_tier',
                            null,
                            $record,
                            [
                                'user_id' => $record->id,
                                'user_name' => $record->name,
                                'tier_id' => $tier->id,
                                'tier_name' => $tier->name_en,
                                'cards_total' => $cards,
                                'expires_at' => $expiresAt?->toIso8601String(),
                                'note' => $data['note'],
                                'subscription_id' => $subscription->id,
                            ]
                        );

                        Notification::make()
                            ->title("Tier '{$tier->name_en}' granted")
                            ->body("Allocated {$cards} cards to {$record->name}.")
                            ->success()
                            ->send();
                    }),

                // 5. Impersonate Action
                Tables\Actions\Action::make('impersonate')
                    ->label('Impersonate')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('mode')
                            ->label('Mode')
                            ->options([
                                'read_only' => 'Read-Only (Modifications disabled)',
                                'full' => 'Full Session (Can perform actions)',
                            ])
                            ->default('read_only')
                            ->required(),
                        Forms\Components\Select::make('duration')
                            ->label('Time Limit')
                            ->options([
                                15 => '15 minutes',
                                30 => '30 minutes',
                                60 => '1 hour',
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

                // 6. Reset Password Action
                Tables\Actions\Action::make('reset_password')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\TextInput::make('password')
                            ->label('New Password')
                            ->password()
                            ->minLength(8)
                            ->required(),
                    ])
                    ->action(function (User $record, array $data) {
                        $record->update(['password' => Hash::make($data['password'])]);

                        AuditLogService::log(
                            'reset_password',
                            null,
                            $record,
                            ['user_id' => $record->id, 'email' => $record->email]
                        );

                        Notification::make()
                            ->title("Password reset for {$record->name}")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->before(function (User $record) {
                        AuditLogService::log(
                            'delete_user',
                            null,
                            $record,
                            ['user_id' => $record->id, 'name' => $record->name, 'email' => $record->email]
                        );
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_csv')
                    ->label('Export Users (CSV)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (): StreamedResponse {
                        $users = User::with(['activeSubscription.tier'])->get();
                        $filename = 'users-export-' . now()->format('YmdHis') . '.csv';

                        return response()->stream(function () use ($users) {
                            $handle = fopen('php://output', 'w');
                            fputs($handle, "\xEF\xBB\xBF");
                            fputcsv($handle, [
                                'ID', 'Name', 'Email', 'Phone', 'Country', 'Source',
                                'Tier', 'Cards Total', 'Cards Used', 'Events Count',
                                'Status', 'Registered At', 'Last Login',
                            ]);

                            foreach ($users as $u) {
                                $sub = $u->activeSubscription;
                                fputcsv($handle, [
                                    $u->id,
                                    $u->name,
                                    $u->email ?: '',
                                    $u->phone ?: '',
                                    $u->country ?: '',
                                    $u->registration_source ?: 'direct',
                                    $sub?->tier?->name_en ?? 'Free',
                                    $sub?->cards_total ?? 0,
                                    $sub?->cards_used ?? 0,
                                    $u->events()->count(),
                                    $u->is_suspended ? 'Suspended' : 'Active',
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
