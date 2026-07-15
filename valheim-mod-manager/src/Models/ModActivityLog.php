<?php

namespace Chr0mX\ValheimModManager\Models;

use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $server_id
 * @property string $action
 * @property string $mod_name
 * @property string|null $from_version
 * @property string|null $to_version
 * @property string|null $message
 */
class ModActivityLog extends Model
{
    protected $table = 'valheim_mod_activity_logs';

    protected $fillable = [
        'server_id',
        'action',
        'mod_name',
        'from_version',
        'to_version',
        'message',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function summary(): string
    {
        return match ($this->action) {
            'installed' => "Installed {$this->mod_name} {$this->to_version}",
            'updated' => "Updated {$this->mod_name} {$this->from_version} → {$this->to_version}",
            'removed' => "Removed {$this->mod_name}",
            'enabled' => "Enabled {$this->mod_name}",
            'disabled' => "Disabled {$this->mod_name}",
            default => "{$this->action} {$this->mod_name}",
        };
    }
}
