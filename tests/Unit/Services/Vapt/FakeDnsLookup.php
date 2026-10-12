<?php

namespace Tests\Unit\Services\Vapt;

use App\Services\Vapt\Email\DnsLookup;

class FakeDnsLookup extends DnsLookup
{
    /**
     * @param  array<string, array<int, string>>  $txt
     * @param  array<string, array<int, string>>  $a
     * @param  array<string, array<int, array{host:string, priority:int}>>  $mx
     * @param  array<string, array<int, string>>  $ptr
     */
    public function __construct(
        private readonly array $txt = [],
        private readonly array $a = [],
        private readonly array $mx = [],
        private readonly array $ptr = [],
    ) {}

    public function txt(string $name): array
    {
        return $this->txt[$name] ?? $this->txt['*.'.substr($name, strpos($name, '.') + 1)] ?? [];
    }

    public function a(string $name): array
    {
        return $this->a[$name] ?? [];
    }

    public function mx(string $name): array
    {
        return $this->mx[$name] ?? [];
    }

    public function aaaa(string $name): array
    {
        return [];
    }

    public function ptr(string $ip): array
    {
        return $this->ptr[$ip] ?? [];
    }
}
