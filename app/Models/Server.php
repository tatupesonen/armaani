<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Server extends Model
{
    /** @use HasFactory<\Database\Factories\ServerFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'port',
        'query_port',
        'max_players',
        'password',
        'admin_password',
        'description',
        'active_preset_id',
        'headless_client_count',
        'additional_params',
    ];

    public function activePreset(): BelongsTo
    {
        return $this->belongsTo(ModPreset::class, 'active_preset_id');
    }

    /**
     * Get the server installation directory path.
     */
    public function getInstallationPath(): string
    {
        return config('arma.servers_base_path').'/'.$this->id;
    }
}
