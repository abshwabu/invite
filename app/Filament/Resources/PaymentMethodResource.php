<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentMethodResource\Pages;
use App\Models\AuditLog;
use App\Models\PaymentMethod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationGroup = 'Billing & Revenue';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Receiving Account Information')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('label')
                            ->label('Method Label')
                            ->required()
                            ->placeholder('e.g. Commercial Bank of Ethiopia (CBE)'),

                        Forms\Components\Select::make('type')
                            ->options([
                                'bank' => 'Bank Transfer',
                                'mobile_money' => 'Mobile Money (Telebirr)',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('bank_name')
                            ->label('Bank Name')
                            ->placeholder('e.g. Bank of Abyssinia')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'bank'),

                        Forms\Components\TextInput::make('account_name')
                            ->label('Account Holder Name')
                            ->required()
                            ->placeholder('e.g. Invite Technologies PLC'),

                        Forms\Components\TextInput::make('account_number')
                            ->label('Account / Merchant Number')
                            ->required()
                            ->placeholder('e.g. 1000123456789'),

                        Forms\Components\TextInput::make('phone_number')
                            ->label('Phone Number (Optional)')
                            ->tel()
                            ->placeholder('e.g. 0911234567'),

                        Forms\Components\FileUpload::make('qr_image_path')
                            ->label('QR Code Image (Optional)')
                            ->image()
                            ->disk('public')
                            ->directory('payment-methods')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('instructions_en')
                            ->label('Payment Instructions (English)')
                            ->rows(3)
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('instructions_am')
                            ->label('Payment Instructions (Amharic)')
                            ->rows(3)
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active for Customer Payments')
                            ->default(true),

                        Forms\Components\TextInput::make('sort')
                            ->label('Display Order')
                            ->numeric()
                            ->default(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('label')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('type')->badge()->uppercase(),
                Tables\Columns\TextColumn::make('account_name')->searchable(),
                Tables\Columns\TextColumn::make('account_number')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('phone_number')->placeholder('-'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->label('Last Updated'),
            ])
            ->defaultSort('sort', 'asc')
            ->reorderable('sort')
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function (PaymentMethod $record) {
                        if ($admin = Auth::guard('superadmin')->user()) {
                            AuditLog::create([
                                'super_admin_id' => $admin->id,
                                'action' => 'payment_method.updated',
                                'target_type' => PaymentMethod::class,
                                'target_id' => $record->id,
                                'ip_address' => request()->ip() ?? '127.0.0.1',
                                'user_agent' => request()->userAgent() ?? 'System',
                                'meta' => ['label' => $record->label, 'is_active' => $record->is_active],
                            ]);
                        }
                    }),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentMethods::route('/'),
            'create' => Pages\CreatePaymentMethod::route('/create'),
            'edit' => Pages\EditPaymentMethod::route('/{record}/edit'),
        ];
    }
}
