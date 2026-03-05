<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModPreset extends Model
{
    /** @use HasFactory<\Database\Factories\ModPresetFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function mods(): BelongsToMany
    {
        return $this->belongsToMany(WorkshopMod::class, 'mod_preset_workshop_mod');
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class, 'active_preset_id');
    }
}
