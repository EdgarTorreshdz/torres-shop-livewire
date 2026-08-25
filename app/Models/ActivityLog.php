<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false; // only created_at is meaningful; entries are immutable

    // created_at is listed explicitly: with $timestamps = false, Eloquent
    // won't auto-populate it, and record() below passes it manually — if
    // it's left out of $fillable, mass assignment silently drops it and
    // every entry gets stored with a NULL created_at.
    protected $fillable = [
        'user_id', 'action', 'description', 'subject_type', 'subject_id',
        'old_values', 'new_values', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'subject_id' => 'integer',
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record one admin action. Called directly from controllers/Livewire
     * components (not a model observer) so each entry gets a specific,
     * readable description instead of a generic "Product updated".
     *
     * $oldValues/$newValues are plain arrays — build them with
     * self::snapshot() for "the whole record before/after", or by hand for
     * a narrower change (e.g. just ['role' => '...']). Left null on
     * creation (no "before") or deletion (no "after").
     */
    public static function record(
        User $user,
        string $action,
        string $description,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): self {
        return static::create([
            'user_id' => $user->id,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'created_at' => now(),
        ]);
    }

    /**
     * A plain-array snapshot of a model's own columns — no relations, and
     * with timestamps stripped since they always differ and aren't a
     * meaningful "change" on their own. Eloquent's attributesToArray()
     * already respects each model's $hidden (e.g. User::password never
     * ends up here, hashed or not).
     */
    public static function snapshot(Model $model): array
    {
        return collect($model->attributesToArray())
            ->except(['created_at', 'updated_at'])
            ->all();
    }
}
