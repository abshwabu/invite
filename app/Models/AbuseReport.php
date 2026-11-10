<?php

namespace App\Models;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbuseReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'guest_id',
        'reporter_ip',
        'reporter_email',
        'reason',
        'description',
        'status',
        'action_taken',
        'reviewed_by',
        'reviewed_at',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'reviewed_by');
    }

    /**
     * Dismiss the report with optional admin note.
     */
    public function dismiss(?SuperAdmin $reviewer = null, ?string $notes = null): void
    {
        $this->update([
            'status' => 'dismissed',
            'action_taken' => 'dismissed',
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => now(),
            'admin_notes' => $notes ?? $this->admin_notes,
        ]);

        if ($reviewer) {
            AuditLogService::log(
                action: 'abuse_report.dismissed',
                actor: $reviewer,
                subject: $this,
                meta: [
                    'event_id' => $this->event_id,
                    'reason' => $this->reason,
                    'notes' => $notes,
                ]
            );
        }
    }

    /**
     * Suspend the reported event.
     */
    public function suspendEvent(?SuperAdmin $reviewer = null, ?string $notes = null): void
    {
        if ($this->event) {
            $this->event->update(['status' => 'suspended']);

            if ($reviewer) {
                AuditLogService::log(
                    action: 'event.suspended_from_report',
                    actor: $reviewer,
                    subject: $this->event,
                    meta: [
                        'report_id' => $this->id,
                        'reason' => $this->reason,
                    ]
                );
            }
        }

        $this->update([
            'status' => 'action_taken',
            'action_taken' => 'event_suspended',
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => now(),
            'admin_notes' => $notes ?? $this->admin_notes,
        ]);
    }

    /**
     * Suspend the host user and all their events.
     */
    public function suspendUser(?SuperAdmin $reviewer = null, ?string $notes = null): void
    {
        $user = $this->event?->user;

        if ($user) {
            $user->update(['is_suspended' => true]);
            $user->events()->update(['status' => 'suspended']);

            if ($reviewer) {
                AuditLogService::log(
                    action: 'user.suspended_from_report',
                    actor: $reviewer,
                    subject: $user,
                    meta: [
                        'report_id' => $this->id,
                        'reason' => $this->reason,
                    ]
                );
            }
        }

        $this->update([
            'status' => 'action_taken',
            'action_taken' => 'user_suspended',
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => now(),
            'admin_notes' => $notes ?? $this->admin_notes,
        ]);
    }
}
