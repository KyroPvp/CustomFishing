<?php

declare(strict_types=1);

namespace pup\fishing\items\loottable;

use pup\fishing\items\rods\CustomRod;
use pup\fishing\utils\WeightedRandom;

final readonly class LootTable
{
    /** @param list<LootTableEntry> $entries */
    public function __construct(
        private string $id,
        private array  $entries
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    /** @return list<LootTableEntry> */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    public function roll(CustomRod $rod): ?LootTableEntry
    {
        $weights = array_map(function ($entry) use ($rod) { return $entry->getEffectiveWeight($rod); }, $this->entries);

        $picked = WeightedRandom::pick($weights);
        return $picked === null ? null : $this->entries[$picked];
    }
}