<?php

namespace App\Filament\Resources;

use App\Contracts\PaymentProvider;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Billing & Revenue';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereIn('status', ['submitted', 'needs_info'])->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $hasOverdue = static::getModel()::where('status', 'submitted')
            ->where('submitted_at', '<=', now()->subHours(24))
            ->exists();

        if ($hasOverdue) {
            return 'danger';
        }

        $hasWarning = static::getModel()::where('status', 'submitted')
            ->where('submitted_at', '<=', now()->subHours(12))
            ->exists();

        return $hasWarning ? 'warning' : 'primary';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payment_code')
                    ->label('Code')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Payment $record) => $record->user?->email ?: $record->user?->phone),

                Tables\Columns\TextColumn::make('tier.name_en')
                    ->label('Tier')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('amount_expected')
                    ->label('Expected')
                    ->money('ETB')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount_declared')
                    ->label('Declared')
                    ->money('ETB')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'submitted' => 'warning',
                        'needs_info' => 'info',
                        'rejected', 'refunded' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('waiting_time')
                    ->label('Waiting Time')
                    ->state(function (Payment $record): string {
                        if (!$record->submitted_at || !in_array($record->status, ['submitted', 'needs_info'])) {
                            return '-';
                        }
                        return $record->submitted_at->diffForHumans(null, true);
                    })
                    ->badge()
                    ->color(function (Payment $record): string {
                        if (!$record->submitted_at || $record->status !== 'submitted') {
                            return 'gray';
                        }
                        $hours = $record->submitted_at->diffInHours();
                        if ($hours >= 24) return 'danger';
                        if ($hours >= 12) return 'warning';
                        return 'gray';
                    }),

                Tables\Columns\TextColumn::make('reference_number')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('method.label')
                    ->label('Method')
                    ->placeholder('Bank / Telebirr'),

                Tables\Columns\TextColumn::make('flags')
                    ->label('Flags')
                    ->state(function (Payment $record): string {
                        if (empty($record->flags)) {
                            return '-';
                        }
                        return implode(', ', array_keys(array_filter($record->flags)));
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state !== '-' ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tier_id')
                    ->relationship('tier', 'name_en')
                    ->label('Tier'),

                Tables\Filters\SelectFilter::make('method_id')
                    ->relationship('method', 'label')
                    ->label('Payment Method'),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'awaiting_proof' => 'Not submitted',
                        'submitted' => 'Under review',
                        'needs_info' => 'Needs info',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'refunded' => 'Refunded',
                        'abandoned' => 'Abandoned',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                // Action: Approve
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Payment $record): bool =>
                        in_array($record->status, ['submitted', 'needs_info']) &&
                        Auth::guard('superadmin')->user()?->isOwner()
                    )
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\TextInput::make('amount_verified')
                            ->label('Verified Amount (ETB)')
                            ->numeric()
                            ->default(fn (Payment $record) => $record->amount_declared ?: $record->amount_expected)
                            ->required()
                            ->helperText(fn (Payment $record) => "Expected price: {$record->amount_expected} ETB"),

                        Forms\Components\Textarea::make('admin_note')
                            ->label('Approval Note (Optional)'),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        $provider = app(PaymentProvider::class);
                        $admin = Auth::guard('superadmin')->user();
                        $provider->approve($record, (float) $data['amount_verified'], $admin, $data['admin_note'] ?? null);

                        Notification::make()
                            ->title("Payment {$record->payment_code} Approved")
                            ->body("Tier {$record->tier->name_en} activated for {$record->user->name}.")
                            ->success()
                            ->send();
                    }),

                // Action: Request More Info
                Tables\Actions\Action::make('requestInfo')
                    ->label('Request Info')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('info')
                    ->visible(fn (Payment $record): bool =>
                        in_array($record->status, ['submitted', 'needs_info']) &&
                        Auth::guard('superadmin')->user()?->isOwner()
                    )
                    ->form([
                        Forms\Components\Textarea::make('message')
                            ->label('Information Needed from User')
                            ->required()
                            ->placeholder('e.g. Please upload a clear photo showing the transaction time and reference.'),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        $provider = app(PaymentProvider::class);
                        $admin = Auth::guard('superadmin')->user();
                        $provider->requestInfo($record, $data['message'], $admin);

                        Notification::make()
                            ->title('Information Requested')
                            ->body('User has been notified to provide additional payment details.')
                            ->info()
                            ->send();
                    }),

                // Action: Reject
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Payment $record): bool =>
                        in_array($record->status, ['submitted', 'needs_info']) &&
                        Auth::guard('superadmin')->user()?->isOwner()
                    )
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Select::make('preset_reason')
                            ->label('Rejection Reason')
                            ->options([
                                'Reference number not found in bank statement' => 'Reference number not found in bank statement',
                                'Receipt image illegible or unverified' => 'Receipt image illegible or unverified',
                                'Amount transferred is less than required price' => 'Amount transferred is less than required price',
                                'Duplicate receipt previously submitted' => 'Duplicate receipt previously submitted',
                                'Other' => 'Other (specified below)',
                            ])
                            ->required(),

                        Forms\Components\Textarea::make('custom_reason')
                            ->label('Additional Details / Reason')
                            ->placeholder('Explanation sent to the user...'),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        $reason = $data['preset_reason'] === 'Other' || !empty($data['custom_reason'])
                            ? ($data['preset_reason'] . ($data['custom_reason'] ? ': ' . $data['custom_reason'] : ''))
                            : $data['preset_reason'];

                        $provider = app(PaymentProvider::class);
                        $admin = Auth::guard('superadmin')->user();
                        $provider->reject($record, $reason, $admin);

                        Notification::make()
                            ->title("Payment {$record->payment_code} Rejected")
                            ->body('User has been notified.')
                            ->danger()
                            ->send();
                    }),

                // Action: Refund / Cancel Subscription
                Tables\Actions\Action::make('refund')
                    ->label('Refund / Revoke')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('danger')
                    ->visible(fn (Payment $record): bool =>
                        $record->status === 'approved' &&
                        Auth::guard('superadmin')->user()?->isOwner()
                    )
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Refund Reason')
                            ->required()
                            ->placeholder('e.g. User requested cancellation before card dispatch.'),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        $provider = app(PaymentProvider::class);
                        $admin = Auth::guard('superadmin')->user();
                        $provider->refund($record, $admin, $data['reason']);

                        Notification::make()
                            ->title("Payment {$record->payment_code} Refunded")
                            ->body('Subscription revoked and unused quota deducted.')
                            ->warning()
                            ->send();
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_csv')
                    ->label('Export Approved (CSV)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        $payments = Payment::query()
                            ->where('status', 'approved')
                            ->with(['user', 'tier', 'method'])
                            ->latest('reviewed_at')
                            ->get();

                        $filename = 'approved-payments-' . now()->format('Y-m-d-His') . '.csv';

                        $headers = [
                            'Content-Type' => 'text/csv',
                            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                        ];

                        $callback = function () use ($payments) {
                            $handle = fopen('php://output', 'w');
                            fputcsv($handle, [
                                'Payment Code',
                                'User Name',
                                'User Email',
                                'Tier',
                                'Amount Verified (ETB)',
                                'Reference Number',
                                'Payment Method',
                                'Approved Date',
                            ]);

                            foreach ($payments as $p) {
                                fputcsv($handle, [
                                    $p->payment_code,
                                    $p->user?->name,
                                    $p->user?->email,
                                    $p->tier?->name_en,
                                    $p->amount_verified ?: $p->amount_expected,
                                    $p->reference_number ?: 'N/A',
                                    $p->method?->label ?: 'Manual',
                                    $p->reviewed_at?->toDateTimeString(),
                                ]);
                            }

                            fclose($handle);
                        };

                        return response()->stream($callback, 200, $headers);
                    }),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Payment Overview')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('payment_code')->weight('bold'),
                        Infolists\Components\TextEntry::make('status')->badge(),
                        Infolists\Components\TextEntry::make('tier.name_en')->label('Purchased Tier'),
                        Infolists\Components\TextEntry::make('amount_expected')->money('ETB')->label('Expected Amount'),
                        Infolists\Components\TextEntry::make('amount_declared')->money('ETB')->label('Declared Amount'),
                        Infolists\Components\TextEntry::make('amount_verified')->money('ETB')->label('Verified Amount'),
                    ]),

                Infolists\Components\Section::make('Customer & Transfer Details')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('user.name')->label('Customer Name'),
                        Infolists\Components\TextEntry::make('user.email')->label('Email'),
                        Infolists\Components\TextEntry::make('user.phone')->label('Phone'),
                        Infolists\Components\TextEntry::make('reference_number')->label('Transaction Reference'),
                        Infolists\Components\TextEntry::make('method.label')->label('Receiving Method'),
                        Infolists\Components\TextEntry::make('paid_from')->label('Paid From (Account / Name)'),
                        Infolists\Components\TextEntry::make('paid_at_declared')->label('Transfer Date'),
                        Infolists\Components\TextEntry::make('user_note')->label('Customer Note')->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Receipt Screenshot')
                    ->schema([
                        Infolists\Components\ViewEntry::make('receipt_path')
                            ->label('Receipt Image')
                            ->view('filament.infolists.receipt-viewer'),
                    ]),

                Infolists\Components\Section::make('Security Flags & Audit')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('flags')->label('Security Flags'),
                        Infolists\Components\TextEntry::make('rejection_reason')->label('Rejection Reason')->placeholder('None'),
                        Infolists\Components\TextEntry::make('admin_note')->label('Admin Note')->placeholder('None'),
                        Infolists\Components\TextEntry::make('reviewer.name')->label('Reviewed By')->placeholder('Pending Review'),
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
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }
}
