<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TierResource\Pages;
use App\Models\Tier;
use App\Services\AuditLogService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TierResource extends Resource
{
    protected static ?string $model = Tier::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Platform & Access';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('General Information')
                    ->schema([
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('name_en')
                            ->label('Name (English)')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('name_am')
                            ->label('Name (Amharic)')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\Textarea::make('description_en')
                            ->label('Description (English)')
                            ->rows(2)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description_am')
                            ->label('Description (Amharic)')
                            ->rows(2)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('sort')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower number appears first'),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                        Forms\Components\Toggle::make('is_public')
                            ->default(true),
                        Forms\Components\Toggle::make('is_default')
                            ->label('Default Registration Tier')
                            ->helperText('Exactly one tier can be default. Setting this will untoggle the previous default.')
                            ->default(false),
                    ])->columns(3),

                Forms\Components\Section::make('Pricing & Card Quota')
                    ->schema([
                        Forms\Components\TextInput::make('price_amount')
                            ->label('Price')
                            ->numeric()
                            ->prefix('ETB')
                            ->default(0.00)
                            ->required(),
                        Forms\Components\TextInput::make('currency')
                            ->default('ETB')
                            ->required()
                            ->maxLength(10),
                        Forms\Components\TextInput::make('card_quota')
                            ->label('Card Quota')
                            ->numeric()
                            ->required()
                            ->helperText('Total invitations/messages granted per subscription'),
                        Forms\Components\TextInput::make('validity_days')
                            ->label('Validity (Days)')
                            ->numeric()
                            ->nullable()
                            ->helperText('Leave blank for perpetual / never expires'),
                    ])->columns(4),

                Forms\Components\Section::make('Usage Limits & Channel Weights')
                    ->schema([
                        Forms\Components\TextInput::make('max_events')
                            ->numeric()
                            ->nullable()
                            ->helperText('Leave empty for unlimited events'),
                        Forms\Components\TextInput::make('max_guests_per_event')
                            ->numeric()
                            ->default(500)
                            ->required(),
                        Forms\Components\TextInput::make('max_scanners_per_event')
                            ->numeric()
                            ->default(3)
                            ->required(),
                        Forms\Components\TextInput::make('sms_weight')
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->helperText('Cards deducted per SMS'),
                        Forms\Components\TextInput::make('email_weight')
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->helperText('Cards deducted per Email'),
                        Forms\Components\Toggle::make('thank_you_consumes_quota')
                            ->label('Thank-you Cards Consume Quota')
                            ->default(false)
                            ->helperText('When disabled, post-event thank-you cards are free of charge'),
                    ])->columns(3),

                Forms\Components\Section::make('Feature Toggles')
                    ->description('Enable or restrict specific platform features for this tier')
                    ->schema([
                        Forms\Components\Toggle::make('features.sms_channel')
                            ->label('SMS Channel')
                            ->default(true),
                        Forms\Components\Toggle::make('features.email_channel')
                            ->label('Email Channel')
                            ->default(true),
                        Forms\Components\Toggle::make('features.qr_checkin')
                            ->label('QR Code Guest Check-in')
                            ->default(false),
                        Forms\Components\Toggle::make('features.thank_you')
                            ->label('Thank You Cards')
                            ->default(false),
                        Forms\Components\Toggle::make('features.custom_messages')
                            ->label('Custom Messages')
                            ->default(false),
                        Forms\Components\Toggle::make('features.custom_links')
                            ->label('Custom Links')
                            ->default(false),
                        Forms\Components\Toggle::make('features.reminders')
                            ->label('Event Reminders')
                            ->default(false),
                        Forms\Components\Toggle::make('features.upload_templates')
                            ->label('Upload Custom Templates')
                            ->default(false),
                        Forms\Components\Toggle::make('features.co_hosts')
                            ->label('Co-hosts / Team Members')
                            ->default(false),
                        Forms\Components\Toggle::make('features.remove_branding')
                            ->label('Remove Invite Watermark')
                            ->default(false),
                        Forms\Components\Toggle::make('features.csv_import')
                            ->label('Guest CSV Bulk Import')
                            ->default(false),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name_en')
                    ->label('Tier')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Tier $record): string => $record->name_am),
                Tables\Columns\TextColumn::make('price_amount')
                    ->label('Price')
                    ->money('ETB')
                    ->sortable(),
                Tables\Columns\TextColumn::make('card_quota')
                    ->label('Quota')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('validity_days')
                    ->label('Validity')
                    ->formatStateUsing(fn ($state) => $state ? "{$state} days" : 'Perpetual')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean(),
                Tables\Columns\TextColumn::make('subscriptions_count')
                    ->counts('subscriptions')
                    ->label('Subscribers'),
            ])
            ->defaultSort('sort', 'asc')
            ->reorderable('sort')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\TernaryFilter::make('is_public'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('apply_to_subscribers')
                    ->label('Apply to Subscribers')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Tier $record) => "Apply '{$record->name_en}' Quota to Existing Subscribers")
                    ->modalDescription(fn (Tier $record) => "This will update all active subscriptions under this tier to have cards_total = {$record->card_quota}. Subscribers who were exhausted may become active if their cards_used < {$record->card_quota}. Are you sure you wish to proceed?")
                    ->action(function (Tier $record) {
                        $count = 0;
                        $activeSubs = $record->subscriptions()->where('status', 'active')->get();

                        foreach ($activeSubs as $sub) {
                            $sub->update([
                                'cards_total' => $record->card_quota,
                                'status' => $sub->cards_used >= $record->card_quota ? 'exhausted' : 'active',
                            ]);
                            $count++;
                        }

                        AuditLogService::log(
                            'tier_applied_to_existing_subscribers',
                            null,
                            $record,
                            [
                                'tier_id' => $record->id,
                                'tier_name' => $record->name_en,
                                'updated_subscriptions_count' => $count,
                                'new_card_quota' => $record->card_quota,
                            ]
                        );

                        Notification::make()
                            ->title("Updated {$count} subscriber(s)")
                            ->body("Active subscriptions have been updated to {$record->card_quota} cards.")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, Tier $record) {
                        if ($record->subscriptions()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot Delete Tier')
                                ->body('This tier is associated with active or past subscriptions and cannot be deleted.')
                                ->send();

                            $action->halt();
                        }
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTiers::route('/'),
            'create' => Pages\CreateTier::route('/create'),
            'edit' => Pages\EditTier::route('/{record}/edit'),
        ];
    }
}
