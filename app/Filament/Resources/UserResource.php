<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Tier;
use App\Models\User;
use App\Services\AuditLogService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

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
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('locale')
                    ->badge()
                    ->uppercase(),
                Tables\Columns\IconColumn::make('is_suspended')
                    ->boolean()
                    ->label('Suspended')
                    ->trueColor('danger')
                    ->falseColor('success'),
                Tables\Columns\TextColumn::make('activeSubscription.tier.name_en')
                    ->label('Active Tier')
                    ->default('None')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_suspended'),
            ])
            ->actions([
                Tables\Actions\Action::make('grant_tier')
                    ->label('Grant Tier')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
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
                            ->required()
                            ->helperText('Number of cards to allocate to this user'),
                        Forms\Components\TextInput::make('validity_days')
                            ->label('Validity (Days)')
                            ->numeric()
                            ->nullable()
                            ->helperText('Leave empty for perpetual / never expires'),
                        Forms\Components\Textarea::make('note')
                            ->label('Reason / Admin Note')
                            ->required()
                            ->rows(3)
                            ->helperText('Explain why this quota is being granted (logged in audit trail)'),
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
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
