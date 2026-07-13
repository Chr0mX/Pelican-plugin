<?php

namespace App\Repositories\Daemon;

use App\Models\Server;
use Exception;

/**
 * In-memory fake of the real daemon-backed file repository. The actual
 * class talks HTTP to Wings; this fake simulates just enough of that
 * surface (a virtual filesystem plus "zip fixtures" for pull+decompress)
 * so this plugin's services can be exercised in a unit test without a
 * running panel + daemon. Never shipped with the plugin.
 */
class DaemonFileRepository
{
    protected ?Server $server = null;

    /** @var array<string, array{type: string, content: ?string}> */
    protected array $files = [];

    /** @var array<string, array{entries: array<string, string|null>, size: int}> */
    protected array $zipFixtures = [];

    /** @var array<string, string> staged zip path => fixture url */
    protected array $stagedZips = [];

    public function setServer(Server $server): static
    {
        $this->server = $server;

        return $this;
    }

    // --- Test helpers -------------------------------------------------

    public function seedDirectory(string $path): void
    {
        $this->files[$this->norm($path)] = ['type' => 'dir', 'content' => null];
    }

    public function seedFile(string $path, string $content = ''): void
    {
        $this->ensureParents($path);
        $this->files[$this->norm($path)] = ['type' => 'file', 'content' => $content];
    }

    /**
     * @param  array<string, string|null>  $entries  relative path (within the zip) => file content, or null for a directory
     */
    public function registerZipFixture(string $url, array $entries, int $size = 2048): void
    {
        $this->zipFixtures[$url] = ['entries' => $entries, 'size' => $size];
    }

    public function fileExists(string $path): bool
    {
        return isset($this->files[$this->norm($path)]);
    }

    public function readRaw(string $path): ?string
    {
        return $this->files[$this->norm($path)]['content'] ?? null;
    }

    // --- Real API surface ----------------------------------------------

    public function getContent(string $path): string
    {
        $entry = $this->files[$this->norm($path)] ?? null;

        if ($entry === null || $entry['type'] !== 'file') {
            throw new Exception("File not found: $path");
        }

        return $entry['content'] ?? '';
    }

    public function putContent(string $path, string $content): FakeDaemonResponse
    {
        $this->ensureParents($path);
        $this->files[$this->norm($path)] = ['type' => 'file', 'content' => $content];

        return new FakeDaemonResponse();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDirectory(string $path): array
    {
        $path = $this->norm($path);
        $prefix = $path === '' ? '' : "$path/";
        $seen = [];

        foreach ($this->files as $candidate => $entry) {
            if ($candidate === $path || !str_starts_with($candidate, $prefix)) {
                continue;
            }

            $relative = substr($candidate, strlen($prefix));
            $name = explode('/', $relative)[0];

            if (isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $fullChildPath = $prefix . $name;
            $isDirectChild = $this->files[$fullChildPath]['type'] ?? null;

            $seen[$name] = [
                'name' => $name,
                'file' => $isDirectChild === 'file',
                'directory' => $isDirectChild === 'dir' || $isDirectChild === null,
                'symlink' => false,
                'size' => $isDirectChild === 'file' ? strlen($this->files[$fullChildPath]['content'] ?? '') : 0,
                'mime' => $isDirectChild === 'file' ? 'application/octet-stream' : 'inode/directory',
                'modified' => now()->toIso8601String(),
                'created' => now()->toIso8601String(),
                'mode' => '0644',
                'mode_bits' => 644,
            ];
        }

        return array_values($seen);
    }

    public function createDirectory(string $name, string $path): FakeDaemonResponse
    {
        $this->files[$this->norm("$path/$name")] = ['type' => 'dir', 'content' => null];

        return new FakeDaemonResponse();
    }

    /**
     * @param  array<array{from: string, to: string}>  $files
     */
    public function renameFiles(?string $root, array $files): FakeDaemonResponse
    {
        foreach ($files as $move) {
            $from = $this->norm(($root ?? '/') . '/' . $move['from']);
            $to = $this->norm(($root ?? '/') . '/' . $move['to']);

            $this->moveTree($from, $to);
        }

        return new FakeDaemonResponse();
    }

    /**
     * @param  string[]  $files
     */
    public function deleteFiles(?string $root, array $files): FakeDaemonResponse
    {
        foreach ($files as $file) {
            $target = $this->norm(($root ?? '/') . '/' . $file);
            $this->deleteTree($target);
        }

        return new FakeDaemonResponse();
    }

    public function decompressFile(?string $root, string $file): FakeDaemonResponse
    {
        $zipPath = $this->norm(($root ?? '/') . '/' . $file);
        $url = $this->stagedZips[$zipPath] ?? null;
        $fixture = $url !== null ? ($this->zipFixtures[$url] ?? null) : null;

        if ($fixture === null) {
            return new FakeDaemonResponse();
        }

        foreach ($fixture['entries'] as $relative => $content) {
            $target = $this->norm(($root ?? '/') . '/' . $relative);

            if ($content === null) {
                $this->files[$target] = ['type' => 'dir', 'content' => null];
            } else {
                $this->ensureParents($target);
                $this->files[$target] = ['type' => 'file', 'content' => $content];
            }
        }

        return new FakeDaemonResponse();
    }

    /**
     * @param  array<mixed>  $params
     */
    public function pull(string $url, ?string $directory, array $params = []): FakeDaemonResponse
    {
        $filename = $params['filename'] ?? basename(parse_url($url, PHP_URL_PATH) ?: 'download');
        $target = $this->norm(($directory ?? '/') . '/' . $filename);

        $fixture = $this->zipFixtures[$url] ?? null;
        $size = $fixture['size'] ?? 0;

        $this->ensureParents($target);
        $this->files[$target] = ['type' => 'file', 'content' => str_repeat('x', $size)];
        $this->stagedZips[$target] = $url;

        return new FakeDaemonResponse();
    }

    protected function norm(string $path): string
    {
        return trim(str_replace('\\', '/', $path), '/');
    }

    protected function ensureParents(string $path): void
    {
        $parts = explode('/', $this->norm(dirname($this->norm($path))));
        $built = '';

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            $built = $built === '' ? $part : "$built/$part";

            if (!isset($this->files[$built])) {
                $this->files[$built] = ['type' => 'dir', 'content' => null];
            }
        }
    }

    protected function moveTree(string $from, string $to): void
    {
        if (!isset($this->files[$from])) {
            return;
        }

        $this->ensureParents($to);

        $prefix = "$from/";
        foreach ($this->files as $path => $entry) {
            if (str_starts_with($path, $prefix)) {
                $relative = substr($path, strlen($prefix));
                $this->files["$to/$relative"] = $entry;
                unset($this->files[$path]);
            }
        }

        $this->files[$to] = $this->files[$from];
        unset($this->files[$from]);
    }

    protected function deleteTree(string $path): void
    {
        unset($this->files[$path]);

        $prefix = "$path/";
        foreach (array_keys($this->files) as $candidate) {
            if (str_starts_with($candidate, $prefix)) {
                unset($this->files[$candidate]);
            }
        }
    }
}
