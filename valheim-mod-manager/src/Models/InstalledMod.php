<?php

namespace Chr0mX\ValheimModManager\Models;

use App\Models\Server;
use Chr0mX\ValheimModManager\DTO\InstalledModData;
use Chr0mX\ValheimModManager\Enums\ModSource;
use Chr0mX\ValheimModManager\Enums\ModStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Sushi\Sushi;

/**
 * A virtual (Sushi-backed, in-memory) Eloquent model wrapping the result of
 * a filesystem scan for a single request. This gives the Filament table
 * proper sorting/searching/bulk-selection for free, exactly like the core
 * panel's own App\Models\File does for the file manager, without needing a
 * real database table for something that only ever reflects live daemon
 * state.
 *
 * @property string $key
 * @property string $name
 * @property string|null $namespace
 * @property string|null $version
 * @property string|null $author
 * @property ModStatus $status
 * @property ModSource $source
 * @property string|null $latest_version
 * @property string|null $last_updated
 * @property bool $managed
 * @property bool $update_available
 * @property string|null $icon
 */
class InstalledMod extends Model
{
    use Sushi;

    /** @var InstalledModData[] */
    protected static array $data = [];

    protected static Server $server;

    /**
     * Named forServer() rather than hydrate() because Eloquent reserves the
     * static/instance method name "hydrate" for its own internal row-array
     * to model conversion (Illuminate\Database\Eloquent\Builder::hydrate());
     * defining our own hydrate() with an incompatible signature shadowed it
     * and threw a TypeError as soon as Filament's table tried to paginate.
     *
     * @param  InstalledModData[]  $mods
     */
    public static function forServer(Server $server, array $mods): Builder
    {
        static::$server = $server;
        static::$data = $mods;

        return static::query();
    }

    protected function casts(): array
    {
        return [
            'status' => ModStatus::class,
            'source' => ModSource::class,
            'managed' => 'boolean',
            'update_available' => 'boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getSchema(): array
    {
        return [
            'key' => 'string',
            'name' => 'string',
            'namespace' => 'string',
            'version' => 'string',
            'author' => 'string',
            'status' => 'string',
            'source' => 'string',
            'latest_version' => 'string',
            'last_updated' => 'string',
            'managed' => 'boolean',
            'update_available' => 'boolean',
            'icon' => 'string',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRows(): array
    {
        return array_map(static fn (InstalledModData $mod): array => [
            'key' => $mod->key,
            'name' => $mod->name,
            'namespace' => $mod->namespace,
            'version' => $mod->version,
            'author' => $mod->author,
            'status' => $mod->status->value,
            'source' => $mod->source->value,
            'latest_version' => $mod->latestVersion,
            'last_updated' => $mod->lastUpdated,
            'managed' => $mod->managed,
            'update_available' => $mod->updateAvailable(),
            'icon' => $mod->icon,
        ], static::$data);
    }

    public function toDto(): ?InstalledModData
    {
        foreach (static::$data as $mod) {
            if ($mod->key === $this->key) {
                return $mod;
            }
        }

        return null;
    }

    protected function sushiShouldCache(): bool
    {
        return false;
    }
}
