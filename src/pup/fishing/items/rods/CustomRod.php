<?php

declare(strict_types=1);

namespace pup\fishing\items\rods;

use pup\fishing\items\fish\FishRarity;

final readonly class CustomRod
{
    /**
     * @param list<string>          $lore
     * @param array<string, float>  $tableWeights       loot table id => weight
     * @param array<string, float>  $rarityMultipliers  rarity value => multiplier (missing = 1.0)
     */
    public function __construct(
        private string $id,
        private string $displayName,
        private array  $lore,
        private array  $tableWeights,
        private array  $rarityMultipliers
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    /** @return list<string> */
    public function getLore(): array
    {
        return $this->lore;
    }

    /** @return array<string, float> */
    public function getTableWeights(): array
    {
        return $this->tableWeights;
    }

    public function getRarityMultiplier(FishRarity $rarity): float
    {
        return $this->rarityMultipliers[$rarity->value] ?? 1.0;
    }
}