<?php

namespace Chr0mX\PfSenseAutoForward\Models;

use App\Models\Allocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manual override for one allocation, set from the admin status page's
 * "Currently mapped" table. Both fields are nullable and mean "no
 * override, use the automatic behaviour" when null - most allocations
 * never get a row here at all.
 *
 * @property int $id
 * @property int $allocation_id
 * @property string|null $protocol
 * @property bool|null $enabled_override
 */
class PortForwardOverride extends Model
{
    protected $table = 'pfsense_autoforward_overrides';

    protected $fillable = [
        'allocation_id',
        'protocol',
        'enabled_override',
    ];

    protected function casts(): array
    {
        return [
            'allocation_id' => 'integer',
            'enabled_override' => 'boolean',
        ];
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }
}
