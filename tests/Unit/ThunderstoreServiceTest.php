<?php

namespace Chr0mX\ValheimModManager\Tests\Unit;

use Chr0mX\ValheimModManager\Contracts\GameProviderInterface;
use Chr0mX\ValheimModManager\Games\ValheimProvider;
use Chr0mX\ValheimModManager\Services\ThunderstoreService;
use Chr0mX\ValheimModManager\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class ThunderstoreServiceTest extends TestCase
{
    private function provider(): GameProviderInterface
    {
        return new ValheimProvider();
    }

    private function fakePackage(string $name, string $owner, int $downloads, bool $deprecated = false): array
    {
        return [
            'name' => $name,
            'full_name' => "$owner-$name",
            'owner' => $owner,
            'package_url' => "https://thunderstore.io/package/$owner/$name/",
            'is_deprecated' => $deprecated,
            'categories' => [],
            'versions' => [[
                'name' => $name,
                'full_name' => "$owner-$name-1.0.0",
                'version_number' => '1.0.0',
                'description' => "$name description",
                'icon' => null,
                'download_url' => "https://thunderstore.io/package/download/$owner/$name/1.0.0/",
                'downloads' => $downloads,
                'file_size' => 100,
                'dependencies' => [],
            ]],
        ];
    }

    public function test_fetches_and_caches_the_package_list(): void
    {
        Http::fake([
            'thunderstore.io/c/valheim/api/v1/package/' => Http::response([
                $this->fakePackage('Jotunn', 'ValheimModding', 500),
            ]),
        ]);

        $service = new ThunderstoreService();
        $packages = $service->getAllPackages($this->provider());

        $this->assertCount(1, $packages);
        $this->assertSame('Jotunn', $packages[0]->name);

        // Second call should be served from cache, i.e. exactly one HTTP request total.
        $service->getAllPackages($this->provider());
        Http::assertSentCount(1);
    }

    public function test_search_filters_out_deprecated_packages_and_matches_by_name(): void
    {
        Http::fake([
            'thunderstore.io/c/valheim/api/v1/package/' => Http::response([
                $this->fakePackage('Jotunn', 'ValheimModding', 500),
                $this->fakePackage('PlantEverything', 'Advize', 300),
                $this->fakePackage('OldMod', 'Someone', 999, deprecated: true),
            ]),
        ]);

        $service = new ThunderstoreService();

        $results = $service->search($this->provider(), 'plant');
        $this->assertSame(1, $results->total());
        $this->assertSame('PlantEverything', $results->items()[0]->name);

        $all = $service->search($this->provider(), null);
        $this->assertSame(2, $all->total());
    }

    public function test_search_paginates_and_sorts_by_downloads_descending(): void
    {
        Http::fake([
            'thunderstore.io/c/valheim/api/v1/package/' => Http::response([
                $this->fakePackage('Low', 'A', 10),
                $this->fakePackage('High', 'B', 900),
                $this->fakePackage('Mid', 'C', 500),
            ]),
        ]);

        $service = new ThunderstoreService();

        $page1 = $service->search($this->provider(), null, page: 1, perPage: 2);
        $this->assertCount(2, $page1->items());
        $this->assertSame('High', $page1->items()[0]->name);
        $this->assertSame('Mid', $page1->items()[1]->name);

        $page2 = $service->search($this->provider(), null, page: 2, perPage: 2);
        $this->assertCount(1, $page2->items());
        $this->assertSame('Low', $page2->items()[0]->name);
    }

    public function test_find_package_matches_owner_and_name_case_insensitively(): void
    {
        Http::fake([
            'thunderstore.io/c/valheim/api/v1/package/' => Http::response([
                $this->fakePackage('Jotunn', 'ValheimModding', 500),
            ]),
        ]);

        $service = new ThunderstoreService();

        $this->assertNotNull($service->findPackage($this->provider(), 'valheimmodding', 'JOTUNN'));
        $this->assertNull($service->findPackage($this->provider(), 'nope', 'nope'));
    }

    public function test_gracefully_returns_empty_list_on_http_failure(): void
    {
        Http::fake([
            'thunderstore.io/*' => Http::response('server error', 500),
        ]);

        $service = new ThunderstoreService();

        $this->assertSame([], $service->getAllPackages($this->provider()));
    }
}
