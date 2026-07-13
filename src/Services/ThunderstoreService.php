<?php

namespace Chr0mX\ValheimModManager\Services;

use Chr0mX\ValheimModManager\Contracts\GameProviderInterface;
use Chr0mX\ValheimModManager\DTO\ThunderstorePackageData;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;

/**
 * Thin, game-agnostic client for the Thunderstore v1 package API. Every
 * method takes a GameProviderInterface so the same service works for any
 * Thunderstore community, not just Valheim.
 */
class ThunderstoreService
{
    /**
     * @return ThunderstorePackageData[]
     */
    public function getAllPackages(GameProviderInterface $provider): array
    {
        $cacheKey = "valheim-mod-manager:packages:{$provider->getThunderstoreCommunity()}";

        $packages = cache()->remember($cacheKey, now()->addMinutes(30), function () use ($provider) {
            try {
                $baseUrl = rtrim(config('valheim-mod-manager.thunderstore_api_url'), '/');
                $community = $provider->getThunderstoreCommunity();

                $response = Http::asJson()
                    ->timeout((int) config('valheim-mod-manager.download_timeout', 60))
                    ->connectTimeout(5)
                    ->throw()
                    ->get("$baseUrl/c/$community/api/v1/package/")
                    ->json();

                return is_array($response) ? $response : [];
            } catch (Exception $exception) {
                report($exception);

                return [];
            }
        });

        return array_map(static fn (array $package): ThunderstorePackageData => ThunderstorePackageData::fromArray($package), $packages);
    }

    /**
     * Thunderstore's v1 API does not support server-side search, so results
     * are filtered and paginated locally against the (cached) full package
     * list for the community.
     */
    public function search(GameProviderInterface $provider, ?string $search = null, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $packages = $this->getAllPackages($provider);

        $packages = array_values(array_filter($packages, static fn (ThunderstorePackageData $package): bool => !$package->isDeprecated));

        if ($search !== null && $search !== '') {
            $needle = strtolower($search);
            $packages = array_values(array_filter(
                $packages,
                static fn (ThunderstorePackageData $package): bool => str_contains(strtolower($package->name), $needle)
                    || str_contains(strtolower($package->owner), $needle)
                    || str_contains(strtolower($package->description()), $needle)
            ));
        }

        usort($packages, static fn (ThunderstorePackageData $a, ThunderstorePackageData $b): int => $b->latestVersion()?->downloads <=> $a->latestVersion()?->downloads);

        $total = count($packages);
        $items = array_slice($packages, ($page - 1) * $perPage, $perPage);

        return new LengthAwarePaginator($items, $total, $perPage, $page);
    }

    public function findPackage(GameProviderInterface $provider, string $namespace, string $name): ?ThunderstorePackageData
    {
        foreach ($this->getAllPackages($provider) as $package) {
            if (strcasecmp($package->owner, $namespace) === 0 && strcasecmp($package->name, $name) === 0) {
                return $package;
            }
        }

        return null;
    }

    public function findPackageByFullName(GameProviderInterface $provider, string $fullName): ?ThunderstorePackageData
    {
        foreach ($this->getAllPackages($provider) as $package) {
            if (strcasecmp($package->fullName, $fullName) === 0) {
                return $package;
            }
        }

        return null;
    }
}
