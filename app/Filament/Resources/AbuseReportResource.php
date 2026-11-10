<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AbuseReportResource\Pages;
use App\Models\AbuseReport;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AbuseReportResource extends Resource
{
    protected static ?string $model = AbuseReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationGroup = 'Safety & Moderation';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('event.user.name')
                    ->label('Host')
                    ->searchable()
                    ->sortable()
                    ->description(fn (AbuseReport $record) => $record->event?->user?->email ?: $record->event?->user?->phone),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'fraud', 'harassment' => 'danger',
                        'inappropriate', 'spam' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('description')
                    ->label('Details')
                    ->limit(40)
                    ->tooltip(fn (AbuseReport $record): ?string => $record->description),

                Tables\Columns\TextColumn::make('reporter_email')
                    ->label('Reporter')
                    ->placeholder('Anonymous')
                    ->searchable(),

                Tables\Columns\TextColumn::make('reporter_ip')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'action_taken' => 'danger',
                        'dismissed' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('action_taken')
                    ->label('Action')
                    ->badge()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Reported')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending review',
                        'action_taken' => 'Action taken',
                        'dismissed' => 'Dismissed',
                    ]),

                Tables\Filters\SelectFilter::make('reason')
                    ->options([
                        'spam' => 'Spam / Commercial advertising',
                        'inappropriate' => 'Inappropriate / Offensive content',
                        'fraud' => 'Fraud / Scam / Phishing',
                        'harassment' => 'Harassment / Defamation',
                        'other' => 'Other',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                // Action: Dismiss
                Tables\Actions\Action::make('dismiss')
                    ->label('Dismiss')
                    ->icon('heroicon-o-check')
                    ->color('gray')
                    ->visible(fn (AbuseReport $record): bool =>
                        $record->status === 'pending' &&
                        Auth::guard('superadmin')->check()
                    )
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes (Optional)')
                            ->placeholder('Reason for dismissal, e.g. legitimate event, false alarm.'),
                    ])
                    ->action(function (AbuseReport $record, array $data): void {
                        $admin = Auth::guard('superadmin')->user();
                        $record->dismiss($admin, $data['admin_notes'] ?? null);

                        Notification::make()
                            ->title('Report Dismissed')
                            ->body('Abuse report was dismissed without action against the event.')
                            ->success()
                            ->send();
                    }),

                // Action: Suspend Event
                Tables\Actions\Action::make('suspendEvent')
                    ->label('Suspend Event')
                    ->icon('heroicon-o-no-symbol')
                    ->color('warning')
                    ->visible(fn (AbuseReport $record): bool =>
                        $record->status === 'pending' &&
                        Auth::guard('superadmin')->user()?->isOwner()
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Suspend Reported Event')
                    ->modalDescription('Are you sure you want to suspend this event? Public card links will immediately display a suspended notice.')
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Reason for suspension')
                            ->required(),
                    ])
                    ->action(function (AbuseReport $record, array $data): void {
                        $admin = Auth::guard('superadmin')->user();
                        $record->suspendEvent($admin, $data['admin_notes'] ?? null);

                        Notification::make()
                            ->title('Event Suspended')
                            ->body("Event '{$record->event?->title}' has been suspended.")
                            ->warning()
                            ->send();
                    }),

                // Action: Suspend User
                Tables\Actions\Action::make('suspendUser')
                    ->label('Suspend User & Events')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->visible(fn (AbuseReport $record): bool =>
                        $record->status === 'pending' &&
                        Auth::guard('superadmin')->user()?->isOwner()
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Suspend Host and All Associated Events')
                    ->modalDescription('This will disable the user account, prevent further logins, and suspend all events owned by this user.')
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Reason for suspension')
                            ->required(),
                    ])
                    ->action(function (AbuseReport $record, array $data): void {
                        $admin = Auth::guard('superadmin')->user();
                        $record->suspendUser($admin, $data['admin_notes'] ?? null);

                        Notification::make()
                            ->title('User Suspended')
                            ->body("User '{$record->event?->user?->name}' and all associated events have been suspended.")
                            ->danger()
                            ->send();
                    }),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Report Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)->schema([
                            Infolists\Components\TextEntry::make('reason')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'fraud', 'harassment' => 'danger',
                                    'inappropriate', 'spam' => 'warning',
                                    default => 'gray',
                                }),
                            Infolists\Components\TextEntry::make('status')
                                ->badge(),
                            Infolists\Components\TextEntry::make('action_taken')
                                ->badge()
                                ->placeholder('-'),
                        ]),
                        Infolists\Components\Grid::make(3)->schema([
                            Infolists\Components\TextEntry::make('reporter_email')
                                ->placeholder('Anonymous'),
                            Infolists\Components\TextEntry::make('reporter_ip'),
                            Infolists\Components\TextEntry::make('created_at')
                                ->dateTime(),
                        ]),
                        Infolists\Components\TextEntry::make('description')
                            ->label('Report Description / Details')
                            ->placeholder('No additional description provided by reporter.'),
                    ]),

                Infolists\Components\Section::make('Reported Target & Host')
                    ->schema([
                        Infolists\Components\Grid::make(2)->schema([
                            Infolists\Components\TextEntry::make('event.title')
                                ->label('Event Title'),
                            Infolists\Components\TextEntry::make('event.status')
                                ->label('Event Status')
                                ->badge(),
                            Infolists\Components\TextEntry::make('event.user.name')
                                ->label('Host Name'),
                            Infolists\Components\TextEntry::make('event.user.email')
                                ->label('Host Email')
                                ->placeholder('-'),
                            Infolists\Components\TextEntry::make('event.user.phone')
                                ->label('Host Phone')
                                ->placeholder('-'),
                            Infolists\Components\IconEntry::make('event.user.is_suspended')
                                ->label('Host Suspended')
                                ->boolean(),
                        ]),
                    ]),

                Infolists\Components\Section::make('Moderation Resolution')
                    ->schema([
                        Infolists\Components\Grid::make(2)->schema([
                            Infolists\Components\TextEntry::make('reviewer.name')
                                ->label('Reviewed By')
                                ->placeholder('Pending review'),
                            Infolists\Components\TextEntry::make('reviewed_at')
                                ->label('Reviewed At')
                                ->dateTime()
                                ->placeholder('-'),
                        ]),
                        Infolists\Components\TextEntry::make('admin_notes')
                            ->label('Admin Notes')
                            ->placeholder('No notes entered.'),
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
            'index' => Pages\ListAbuseReports::route('/'),
            'view' => Pages\ViewAbuseReport::route('/{record}'),
        ];
    }
}
