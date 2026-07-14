<?php

namespace Chr0mX\ValheimModManager\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Chr0mX\ValheimModManager\Contracts\GameProviderInterface;
use Chr0mX\ValheimModManager\Support\SafeCache;
use Chr0mX\ValheimModManager\Support\SafePath;
use Exception;

/**
 * Persists the list of packages this plugin installed for a server as a
 * single JSON file inside the primary plugins directory, mirroring the
 * approach used by other Pelican mod-manager plugins. This is the source
 * of truth for anything ModScanner cannot infer purely from the filesystem
 * (namespace, tracked dependencies, which files belong to which package).
 *
 * @phpstan-type ManagedModEntry array{namespace: string, name: string, version: string, directory: string, files: string[], dependencies: string[], disabled: bool, installed_at: string, updated_at: string}
 */
class ModMetadataStore
{
    public function __construct(
        protected DaemonFileRepository $fileRepository,
    ) {}

    protected function metadataPath(GameProviderInterface $provider): string
    {
        return SafePath::join($provider->getPluginsDirectories()[0], config('valheim-mod-manager.metadata_file', '.valheim-mod-manager.json'));
    }

    /**
     * @return array<string, array{namespace: string, name: string, version: string, directory: string, files: string[], dependencies: string[], disabled: bool, installed_at: string, updated_at: string}>
     */
    public function all(Server $server, GameProviderInterface $provider): array
    {
        try {
            $content = $this->fileRepository->setServer($server)->getContent($this->metadataPath($provider));
            $decoded = json_decode($content, true);

            if (!is_array($decoded) || !isset($decoded['mods']) || !is_array($decoded['mods'])) {
                return [];
            }

            $mods = [];
            foreach ($decoded['mods'] as $key => $entry) {
                if (is_array($entry) && isset($entry['namespace'], $entry['name'], $entry['version'])) {
                    $mods[(string) $key] = $entry;
                }
            }

            return $mods;
        } catch (Exception) {
            return [];
        }
    }

    public function find(Server $server, GameProviderInterface $provider, string $key): ?array
    {
        return $this->all($server, $provider)[$key] ?? null;
    }

    public static function key(string $namespace, string $name): string
    {
        return strtolower("$namespace-$name");
    }

    /**
     * @param  string[]  $files
     * @param  string[]  $dependencies
     */
    public function put(
        Server $server,
        GameProviderInterface $provider,
        string $namespace,
        string $name,
        string $version,
        string $directory,
        array $files,
        array $dependencies,
        bool $disabled = false,
    ): bool {
        return $this->mutate($server, $provider, function (array $mods) use ($namespace, $name, $version, $directory, $files, $dependencies, $disabled) {
            $key = self::key($namespace, $name);
            $now = now()->toIso8601String();

            $mods[$key] = [
                'namespace' => $namespace,
                'name' => $name,
                'version' => $version,
                'directory' => $directory,
                'files' => $files,
                'dependencies' => $dependencies,
                'disabled' => $disabled,
                'installed_at' => $mods[$key]['installed_at'] ?? $now,
                'updated_at' => $now,
            ];

            return $mods;
        });
    }

    public function setDisabled(Server $server, GameProviderInterface $provider, string $key, bool $disabled): bool
    {
        return $this->mutate($server, $provider, function (array $mods) use ($key, $disabled) {
            if (isset($mods[$key])) {
                $mods[$key]['disabled'] = $disabled;
                $mods[$key]['updated_at'] = now()->toIso8601String();
            }

            return $mods;
        });
    }

    public function forget(Server $server, GameProviderInterface $provider, string $key): bool
    {
        return $this->mutate($server, $provider, function (array $mods) use ($key) {
            unset($mods[$key]);

            return $mods;
        });
    }

    /**
     * @param  callable(array<string, array<string, mixed>>): array<string, array<string, mixed>>  $callback
     */
    protected function mutate(Server $server, GameProviderInterface $provider, callable $callback): bool
    {
        try {
            return SafeCache::lock("valheim-mod-manager:metadata:{$server->id}", 10, 5, function () use ($server, $provider, $callback) {
                $mods = $callback($this->all($server, $provider));

                $response = $this->fileRepository->setServer($server)->putContent(
                    $this->metadataPath($provider),
                    json_encode(['mods' => $mods], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );

                return !$response->failed();
            }) === true;
        } catch (Exception $exception) {
            report($exception);

            return false;
        }
    }
}
