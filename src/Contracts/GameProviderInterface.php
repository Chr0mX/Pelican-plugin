<?php

namespace Chr0mX\ValheimModManager\Contracts;

use App\Models\Server;

/**
 * A GameProviderInterface describes everything the mod manager needs to know
 * about a specific moddable game so that Thunderstore browsing, filesystem
 * scanning and install/update/remove logic stay game-agnostic.
 *
 * Supporting an additional Thunderstore-backed game (e.g. Lethal Company,
 * Risk of Rain 2) only requires implementing this interface and registering
 * it with the GameProviderRegistry - none of the services, jobs or Filament
 * pages need to change.
 */
interface GameProviderInterface
{
    /**
     * Unique, stable slug for this provider (e.g. "valheim").
     */
    public function getSlug(): string;

    /**
     * Human readable name shown in the UI.
     */
    public function getDisplayName(): string;

    /**
     * Whether this provider is responsible for the given server. Implementations
     * typically inspect the server's egg tags.
     */
    public function matchesServer(Server $server): bool;

    /**
     * The Thunderstore community slug used to scope package/search requests,
     * e.g. "https://thunderstore.io/c/{community}/api/v1/package/".
     */
    public function getThunderstoreCommunity(): string;

    /**
     * Directories (relative to the server root) that are scanned for
     * installed plugins.
     *
     * @return string[]
     */
    public function getPluginsDirectories(): array;

    /**
     * Directories (relative to the server root) that are scanned for
     * installed patchers.
     *
     * @return string[]
     */
    public function getPatchersDirectories(): array;

    /**
     * The directory (relative to the server root) that must never be
     * overwritten by an install/update unless explicitly requested.
     */
    public function getConfigDirectory(): string;

    /**
     * The primary directory new packages are installed into.
     */
    public function getDefaultInstallDirectory(): string;
}
