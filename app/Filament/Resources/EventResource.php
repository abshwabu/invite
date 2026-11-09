<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Models\Event;
use App\Services\AuditLogService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationGroup = 'Events & Operations';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')->required()->maxLength(255),
                Forms\Components\Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'closed' => 'Closed',
                        'suspended' => 'Suspended',
                    ])
                    ->required(),
                Forms\Components\DateTimePicker::make('starts_at')->required(),
                Forms\Components\DateTimePicker::make('ends_at'),
                Forms\Components\TextInput::make('venue_name')->maxLength(255),
                Forms\Components\Toggle::make('is_invite_only')->label('Invite Only (QR Check-in)'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Host / Owner')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name_en')
                    ->label('Category')
                    ->badge(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Event Date')
                    ->dateTime('M d, Y g:i A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'gray',
                        'closed' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_invite_only')
                    ->label('QR Only')
                    ->boolean(),
                Tables\Columns\TextColumn::make('guests_count')
                    ->counts('guests')
                    ->label('Guests')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'published' => 'Published',
                        'draft' => 'Draft',
                        'closed' => 'Closed',
                        'suspended' => 'Suspended',
                    ]),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name_en'),
                Tables\Filters\TernaryFilter::make('is_invite_only')
                    ->label('Invite Only'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                // 1. Suspend / Unsuspend Action
                Tables\Actions\Action::make('toggle_suspend')
                    ->label(fn (Event $record) => $record->status === 'suspended' ? 'Unsuspend Event' : 'Suspend Event')
                    ->icon(fn (Event $record) => $record->status === 'suspended' ? 'heroicon-o-play' : 'heroicon-o-pause')
                    ->color(fn (Event $record) => $record->status === 'suspended' ? 'success' : 'danger')
                    ->visible(fn () => auth('superadmin')->user()?->isOwner() ?? true)
                    ->requiresConfirmation()
                    ->action(function (Event $record) {
                        $newStatus = $record->status === 'suspended' ? 'published' : 'suspended';
                        $record->update(['status' => $newStatus]);

                        AuditLogService::log(
                            $newStatus === 'suspended' ? 'suspend_event' : 'unsuspend_event',
                            null,
                            $record,
                            ['event_id' => $record->id, 'title' => $record->title, 'new_status' => $newStatus]
                        );

                        Notification::make()
                            ->title("Event status updated to {$newStatus}")
                            ->color($newStatus === 'suspended' ? 'danger' : 'success')
                            ->send();
                    }),

                // 2. Reveal Guest Records (Audited)
                Tables\Actions\Action::make('reveal_guests')
                    ->label('Reveal Guests')
                    ->icon('heroicon-o-eye')
                    ->color('warning')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Mandatory Reason for Accessing Guest Personal Data')
                            ->required()
                            ->rows(3)
                            ->helperText('Accessing guest personal data is strictly audited for compliance and privacy.'),
                    ])
                    ->action(function (Event $record, array $data) {
                        $count = $record->guests()->count();

                        // Log audit trail
                        AuditLogService::log(
                            'guest_data_revealed',
                            null,
                            $record,
                            [
                                'event_id' => $record->id,
                                'event_title' => $record->title,
                                'reason' => $data['reason'],
                                'guest_count' => $count,
                            ]
                        );

                        Notification::make()
                            ->title("Guest records revealed ({$count} guests)")
                            ->body("Access reason logged to audit trail: '{$data['reason']}'.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Event Overview')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('title')->weight('bold'),
                            TextEntry::make('user.name')->label('Organizer / Host'),
                            TextEntry::make('category.name_en')->label('Category')->badge(),
                        ]),
                        Grid::make(3)->schema([
                            TextEntry::make('starts_at')->dateTime(),
                            TextEntry::make('ends_at')->dateTime()->default('Not specified'),
                            TextEntry::make('status')->badge(),
                        ]),
                        Grid::make(3)->schema([
                            TextEntry::make('venue_name')->default('—'),
                            TextEntry::make('is_invite_only')->label('Invite Only')->badge(),
                            TextEntry::make('guests_count')->label('Total Guests')
                                ->state(fn ($record) => $record->guests()->count()),
                        ]),
                    ]),

                Section::make('Campaigns Sent for this Event')
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
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
        ];
    }
}
