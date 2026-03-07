<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReforgerSettings extends Model
{
    /** @use HasFactory<\Database\Factories\ReforgerSettingsFactory> */
    use HasFactory;

    protected $fillable = [
        'server_id',
        'scenario_id',
        'third_person_view_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'third_person_view_enabled' => 'boolean',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
