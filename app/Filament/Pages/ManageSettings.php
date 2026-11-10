<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\Tier;
use App\Services\AuditLogService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'System Settings';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.manage-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth('superadmin')->user()?->isOwner() ?? false;
    }

    public function mount(): void
    {
        $tiers = Tier::pluck('slug', 'slug')->toArray();
        $defaultTier = Setting::get('default_tier', 'free');
        if (! isset($tiers[$defaultTier]) && ! empty($tiers)) {
            $defaultTier = array_key_first($tiers);
        }

        $this->form->fill([
            'default_tier' => $defaultTier,
            'free_credits_behavior' => Setting::get('free_credits_behavior', 'once_on_signup'),
            'sms_provider' => Setting::get('sms_provider', config('invite.sms.default', 'log')),
            'cost_per_sms' => Setting::get('cost_per_sms', 0.45),
            'sender_id' => Setting::get('sender_id', config('invite.sms.afromessage.sender_id', 'Invite')),
            'new_account_daily_send_cap' => Setting::get('new_account_daily_send_cap', 50),
            'custom_link_blocklist' => Setting::get('custom_link_blocklist', ['bit.ly', 'tinyurl.com', 't.co']),
            'prohibited_keywords' => Setting::get('prohibited_keywords', ['lottery', 'winner', 'claim now', 'free money']),
            'custom_link_review_age_days' => Setting::get('custom_link_review_age_days', 7),
            'guest_data_retention_days' => Setting::get('guest_data_retention_days', 365),
            'maintenance_mode' => (bool) Setting::get('maintenance_mode', false),
            'payment_instructions_en' => Setting::get('payment_instructions_en', 'Transfer to our bank or Telebirr account and include your payment code in the note.'),
            'payment_instructions_am' => Setting::get('payment_instructions_am', 'በባንክ ወይም በቴሌብር ይክፈሉ እና በምክንያት ቦታ ላይ የክፍያ ኮድዎን ያስገቡ።'),
            'admin_notification_emails' => Setting::get('admin_notification_emails', config('invite.payments.admin_notification_emails', 'admin@invite.et')),
            'payment_notify_user_email' => (bool) Setting::get('payment_notify_user_email', true),
            'payment_notify_user_sms' => (bool) Setting::get('payment_notify_user_sms', false),
            'payment_receipt_retention_days' => Setting::get('payment_receipt_retention_days', 90),
            'payment_abandoned_after_days' => Setting::get('payment_abandoned_after_days', 7),
        ]);
    }

    public function form(Form $form): Form
    {
        $tierOptions = Tier::pluck('name_en', 'slug')->toArray();
        if (empty($tierOptions)) {
            $tierOptions = ['free' => 'Free'];
        }

        return $form
            ->schema([
                Forms\Components\Section::make('General & Membership Defaults')
                    ->schema([
                        Forms\Components\Select::make('default_tier')
                            ->label('Default User Tier on Signup')
                            ->options($tierOptions)
                            ->required(),
                        Forms\Components\Select::make('free_credits_behavior')
                            ->label('Free Quota Credit Policy')
                            ->options([
                                'once_on_signup' => 'Grant free cards once upon initial registration',
                                'monthly_refresh' => 'Refresh free cards quota monthly',
                                'none' => 'No free credits without payment/upgrade',
                            ])
                            ->required(),
                        Forms\Components\Toggle::make('maintenance_mode')
                            ->label('System Maintenance Mode')
                            ->helperText('Put the public application in maintenance mode (super admin panel remains accessible).'),
                    ])->columns(2),

                Forms\Components\Section::make('SMS & Delivery Gateway')
                    ->schema([
                        Forms\Components\Select::make('sms_provider')
                            ->label('Active SMS Gateway Provider')
                            ->options([
                                'log' => 'Local Log (Development / Test)',
                                'afromessage' => 'Afro Message API',
                                'geezsms' => 'Geez SMS API',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('sender_id')
                            ->label('SMS Sender ID / Header')
                            ->default('Invite')
                            ->required(),
                        Forms\Components\TextInput::make('cost_per_sms')
                            ->label('Estimated Provider Cost per SMS (ETB)')
                            ->numeric()
                            ->step(0.01)
                            ->required(),
                        Forms\Components\TextInput::make('new_account_daily_send_cap')
                            ->label('New Account Daily Send Cap (Cards)')
                            ->numeric()
                            ->default(50)
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Content Moderation & Security')
                    ->schema([
                        Forms\Components\TagsInput::make('custom_link_blocklist')
                            ->label('Custom Link Domain Blocklist')
                            ->placeholder('Add domain and press Enter')
                            ->helperText('Domains prohibited in custom message links'),
                        Forms\Components\TagsInput::make('prohibited_keywords')
                            ->label('Prohibited Content Keywords')
                            ->placeholder('Add keyword and press Enter')
                            ->helperText('Keywords that trigger automated campaign review'),
                        Forms\Components\TextInput::make('custom_link_review_age_days')
                            ->label('Account Age Threshold for Review (Days)')
                            ->numeric()
                            ->helperText('Campaigns with custom URLs by accounts newer than this require super admin approval')
                            ->required(),
                        Forms\Components\TextInput::make('guest_data_retention_days')
                            ->label('Guest Data Retention (Days)')
                            ->numeric()
                            ->helperText('Number of days to retain guest contact details after event ends')
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Manual Payments & Verification')
                    ->schema([
                        Forms\Components\Textarea::make('payment_instructions_en')
                            ->label('Payment Instructions (English)')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('payment_instructions_am')
                            ->label('Payment Instructions (Amharic)')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('admin_notification_emails')
                            ->label('Admin Notification Email(s)')
                            ->helperText('Comma-separated email addresses alerted on new payment submissions')
                            ->required(),

                        Forms\Components\TextInput::make('payment_receipt_retention_days')
                            ->label('Proof Retention Days')
                            ->numeric()
                            ->default(90)
                            ->helperText('Days after approval to keep receipt images before automated purge')
                            ->required(),

                        Forms\Components\TextInput::make('payment_abandoned_after_days')
                            ->label('Abandoned Expiry Days')
                            ->numeric()
                            ->default(7)
                            ->helperText('Days before unsubmitted payments are marked abandoned')
                            ->required(),

                        Forms\Components\Toggle::make('payment_notify_user_email')
                            ->label('Email User on Approval / Rejection')
                            ->default(true),

                        Forms\Components\Toggle::make('payment_notify_user_sms')
                            ->label('SMS User on Approval')
                            ->default(false),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach ($state as $key => $val) {
            Setting::set($key, $val);
        }

        AuditLogService::log(
            'settings_updated',
            null,
            null,
            $state
        );

        Notification::make()
            ->title('Settings updated successfully')
            ->success()
            ->send();
    }
}
