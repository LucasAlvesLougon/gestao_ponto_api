<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'registered_at',
        'is_edited',
        'edit_reason',
        'original_registered_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'original_registered_at' => 'datetime',
            'is_edited' => 'boolean',
        ];
    }

    /**
     * Get the user that owns the time entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter entries for a specific day in user's timezone.
     */
    public function scopeForDate($query, string $date, string $timezone = 'America/Sao_Paulo')
    {
        $startOfDay = Carbon::parse($date, $timezone)->startOfDay()->setTimezone('UTC');
        $endOfDay = Carbon::parse($date, $timezone)->endOfDay()->setTimezone('UTC');

        return $query->whereBetween('registered_at', [$startOfDay, $endOfDay]);
    }
}
