<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Security & Logs';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('action')
                    ->disabled(),
                Forms\Components\TextInput::make('actor_type')
                    ->disabled(),
                Forms\Components\TextInput::make('actor_id')
                    ->disabled(),
                Forms\Components\TextInput::make('ip')
                    ->disabled(),
                Forms\Components\TextInput::make('user_agent')
                    ->disabled()
                    ->columnSpanFull(),
                Forms\Components\KeyValue::make('meta')
                    ->disabled()
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('created_at')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'login_success', 'two_factor_setup_confirmed' => 'success',
                        'login_failed', 'two_factor_challenge_failed', 'login_locked_out' => 'danger',
                        'grant_tier', 'tier_applied_to_existing_subscribers' => 'warning',
                        default => 'gray',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('actor_type')
                    ->formatStateUsing(fn ($state, AuditLog $record) => class_basename($state) . " #{$record->actor_id}")
                    ->label('Actor'),
                Tables\Columns\TextColumn::make('subject_type')
                    ->formatStateUsing(fn ($state, AuditLog $record) => $state ? class_basename($state) . " #{$record->subject_id}" : '-')
                    ->label('Subject'),
                Tables\Columns\TextColumn::make('ip')
                    ->label('IP Address')
                    ->searchable(),
                Tables\Columns\TextColumn::make('meta')
                    ->formatStateUsing(fn ($state) => json_encode($state))
                    ->limit(40)
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options([
                        'login_success' => 'Login Success',
                        'login_failed' => 'Login Failed',
                        'login_locked_out' => 'Login Locked Out',
                        'logout' => 'Logout',
                        'two_factor_setup_confirmed' => '2FA Setup Confirmed',
                        'grant_tier' => 'Grant Tier',
                        'adjust_cards' => 'Adjust Cards',
                        'extend_subscription' => 'Extend Subscription',
                        'suspend_user' => 'Suspend User',
                        'unsuspend_user' => 'Unsuspend User',
                        'suspend_event' => 'Suspend Event',
                        'unsuspend_event' => 'Unsuspend Event',
                        'guest_data_revealed' => 'Guest Data Revealed',
                        'impersonate_user' => 'Impersonate User',
                        'impersonation_ended' => 'Impersonation Ended',
                        'campaign_approved' => 'Campaign Approved',
                        'campaign_rejected' => 'Campaign Rejected',
                        'settings_updated' => 'Settings Updated',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From Date'),
                        Forms\Components\DatePicker::make('until')->label('Until Date'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
