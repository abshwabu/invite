<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryResource\Pages;
use App\Jobs\SendDeliveryJob;
use App\Models\Delivery;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DeliveryResource extends Resource
{
    protected static ?string $model = Delivery::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Delivery & Campaigns';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('campaign.event.title')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('guest.name')
                    ->label('Recipient')
                    ->searchable()
                    ->description(fn (Delivery $record) => $record->guest?->phone ?: $record->guest?->email),
                Tables\Columns\TextColumn::make('channel')
                    ->badge()
                    ->uppercase(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'delivered' => 'success',
                        'sent' => 'info',
                        'queued', 'sending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('error')
                    ->label('Provider Error / Failure')
                    ->limit(40)
                    ->color('danger')
                    ->default('—'),
                Tables\Columns\TextColumn::make('provider_message_id')
                    ->label('Provider ID')
                    ->fontFamily(\Filament\Support\Enums\FontFamily::Mono)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->dateTime('M d, H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'queued' => 'Queued',
                        'sending' => 'Sending',
                        'sent' => 'Sent',
                        'delivered' => 'Delivered',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('channel')
                    ->options([
                        'sms' => 'SMS',
                        'email' => 'Email',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('retry')
                    ->label('Retry Send')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (Delivery $record) => $record->status === 'failed')
                    ->requiresConfirmation()
                    ->action(function (Delivery $record) {
                        $record->update(['status' => 'queued', 'error' => null]);
                        SendDeliveryJob::dispatch($record->id);
                        Notification::make()->title('Delivery re-queued')->success()->send();
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
            'index' => Pages\ListDeliveries::route('/'),
        ];
    }
}
