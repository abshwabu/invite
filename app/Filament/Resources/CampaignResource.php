<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignResource\Pages;
use App\Jobs\SendDeliveryJob;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Notifications\HostAlertNotification;
use App\Services\AuditLogService;
use App\Services\QuotaService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationGroup = 'Delivery & Campaigns';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Host')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->uppercase(),
                Tables\Columns\TextColumn::make('channel')
                    ->badge()
                    ->uppercase(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'sending' => 'info',
                        'scheduled' => 'gray',
                        'pending_review' => 'warning',
                        'rejected', 'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('audience_count')
                    ->label('Recipients')
                    ->sortable(),
                Tables\Columns\TextColumn::make('custom_url')
                    ->label('Link URL')
                    ->limit(30)
                    ->copyable()
                    ->default('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending_review' => 'Pending Review',
                        'sending' => 'Sending',
                        'sent' => 'Sent',
                        'scheduled' => 'Scheduled',
                        'rejected' => 'Rejected',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('channel')
                    ->options([
                        'sms' => 'SMS',
                        'email' => 'Email',
                        'both' => 'Both',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'invitation' => 'Invitation',
                        'reminder' => 'Reminder',
                        'thank_you' => 'Thank You',
                        'custom' => 'Custom Message',
                    ]),
            ])
            ->actions([
                // 1. Approve Custom URL Campaign Action
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Campaign $record) => $record->status === 'pending_review' && (auth('superadmin')->user()?->canManageMoneyOrTiers() ?? true))
                    ->requiresConfirmation()
                    ->modalHeading('Approve Campaign for Delivery')
                    ->modalDescription('This will reserve the required quota, create deliveries, and immediately dispatch them to recipients.')
                    ->action(function (Campaign $record) {
                        $user = $record->user;
                        $quotaService = app(QuotaService::class);
                        $cardsNeeded = $record->estimated_cards ?: $record->audience_count;

                        if (! $quotaService->hasQuota($user, $cardsNeeded)) {
                            Notification::make()
                                ->title('User has insufficient card quota')
                                ->body("User needs {$cardsNeeded} cards, but has {$quotaService->available($user)} available.")
                                ->danger()
                                ->send();
                            return;
                        }

                        // Reserve quota
                        $res = $quotaService->reserve($user, $cardsNeeded, [
                            'event_id' => $record->event_id,
                            'campaign_id' => $record->id,
                            'kind' => $record->type,
                            'channel' => $record->channel,
                        ]);

                        if (! $res->success) {
                            Notification::make()->title('Quota reservation failed')->body($res->message)->danger()->send();
                            return;
                        }

                        $record->update(['status' => 'sending']);

                        // Resolve recipients from event guests
                        $event = $record->event;
                        $guests = $event->guests()->where('opted_out', false)->get();
                        $unitCost = $guests->count() > 0 ? (int) ceil($cardsNeeded / $guests->count()) : 0;

                        foreach ($guests as $guest) {
                            $channels = match ($record->channel) {
                                'sms' => ['sms'],
                                'email' => ['email'],
                                'both' => array_filter([$guest->phone ? 'sms' : null, $guest->email ? 'email' : null]),
                                default => ['sms'],
                            };

                            foreach ($channels as $ch) {
                                $delivery = Delivery::create([
                                    'campaign_id' => $record->id,
                                    'guest_id' => $guest->id,
                                    'channel' => $ch,
                                    'status' => 'queued',
                                    'quota_charged' => $unitCost,
                                ]);

                                SendDeliveryJob::dispatch($delivery->id);
                            }
                        }

                        // Notify host
                        $user->notify(new HostAlertNotification(
                            type: 'campaign_approved',
                            title: __('Campaign Approved'),
                            message: __("Your message for event ':title' has been approved and dispatched to :count recipients.", [
                                'title' => $event->title,
                                'count' => $guests->count(),
                            ]),
                            meta: ['campaign_id' => $record->id, 'event_id' => $event->id],
                            actionUrl: route('events.deliveries', $event->id)
                        ));

                        AuditLogService::log(
                            'campaign_approved',
                            null,
                            $record,
                            ['campaign_id' => $record->id, 'recipients' => $guests->count()]
                        );

                        Notification::make()->title('Campaign approved and dispatched')->success()->send();
                    }),

                // 2. Reject Custom URL Campaign Action
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Campaign $record) => $record->status === 'pending_review' && (auth('superadmin')->user()?->canManageMoneyOrTiers() ?? true))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3)
                            ->helperText('Explain why this campaign link is being rejected. This will be sent to the host.'),
                    ])
                    ->action(function (Campaign $record, array $data) {
                        $reason = $data['reason'];
                        $record->update([
                            'status' => 'rejected',
                            'error' => $reason,
                        ]);

                        $user = $record->user;
                        $event = $record->event;

                        // Refund quota if any was charged
                        $charged = Delivery::where('campaign_id', $record->id)->sum('quota_charged');
                        if ($charged > 0) {
                            app(QuotaService::class)->refund([
                                'user_id' => $user->id,
                                'event_id' => $event?->id,
                                'campaign_id' => $record->id,
                            ], $charged);
                        }

                        // Notify host
                        if ($user) {
                            $user->notify(new HostAlertNotification(
                                type: 'campaign_rejected',
                                title: __('Campaign Message Rejected'),
                                message: __("Your custom campaign for ':title' was rejected: :reason. Any reserved cards have been refunded.", [
                                    'title' => $event?->title ?? 'Event',
                                    'reason' => $reason,
                                ]),
                                meta: ['campaign_id' => $record->id, 'event_id' => $event?->id, 'reason' => $reason],
                                actionUrl: $event ? route('events.campaigns.index', $event->id) : null
                            ));
                        }

                        AuditLogService::log(
                            'campaign_rejected',
                            null,
                            $record,
                            ['campaign_id' => $record->id, 'reason' => $reason]
                        );

                        Notification::make()->title('Campaign rejected and host notified')->danger()->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaigns::route('/'),
        ];
    }
}
