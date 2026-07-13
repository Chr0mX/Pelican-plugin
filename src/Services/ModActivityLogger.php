<?php

namespace Chr0mX\ValheimModManager\Services;

use App\Models\Server;
use Chr0mX\ValheimModManager\Models\ModActivityLog;

class ModActivityLogger
{
    public function log(Server $server, string $action, string $modName, ?string $fromVersion, ?string $toVersion, ?string $message = null): ModActivityLog
    {
        return ModActivityLog::create([
            'server_id' => $server->id,
            'action' => $action,
            'mod_name' => $modName,
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'message' => $message,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, ModActivityLog>
     */
    public function recent(Server $server, int $limit = 100)
    {
        return ModActivityLog::query()
            ->where('server_id', $server->id)
            ->latest()
            ->limit($limit)
            ->get();
    }
}
