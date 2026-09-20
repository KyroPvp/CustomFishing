<?php

declare(strict_types=1);

namespace pup\fishing\items\loottable;

use pocketmine\item\Item;
use pup\fishing\items\fish\FishManager;
use pup\fishing\items\fish\FishRarity;
use pup\fishing\items\rods\CustomRod;
use pup\fishing\items\rods\RodManager;
use pup\fishing\utils\EnchantSpec;
use Random\RandomException;

final readonly class LootTableEntry
{
    /** @param list<EnchantSpec> $enchants */
    private function __construct(
        private float       $weight,
        private ?FishRarity $rarity = null,
        private ?string     $fishId = null,
        private ?string     $rodId = null,
        private ?Item       $item = null,
        private int         $minCount = 1,
        private int         $maxCount = 1,
        private array       $enchants = []
    ) {
    }

    public static function fishPool(float $weight, FishRarity $rarity): self
    {
        return new self($weight, rarity: $rarity);
    }

    public static function specificFish(float $weight, string $fishId): self
    {
        return new self($weight, fishId: $fishId);
    }

    public static function rod(float $weight, string $rodId): self
    {
        return new self($weight, rodId: $rodId);
    }

    /** @param list<EnchantSpec> $enchants */
    public static function plainItem(float $weight, Item $item, int $minCount, int $maxCount, array $enchants = []): self
    {
        return new self($weight, item: $item, minCount: $minCount, maxCount: $maxCount, enchants: $enchants);
    }

    public function getRarity(): ?FishRarity
    {
        return $this->rarity;
    }

    public function getRodId(): ?string
    {
        return $this->rodId;
    }

    public function getEffectiveWeight(CustomRod $rod): float
    {
        if ($this->rarity !== null) {
            return $this->weight * $rod->getRarityMultiplier($this->rarity);
        }
        return $this->weight;
    }

    /**
     * @throws RandomException
     */
    public function createItem(FishManager $fishes, ?RodManager $rods): ?Item
    {
        if ($this->fishId !== null) {
            return $fishes->get($this->fishId)?->createItem();
        }

        if ($this->rarity !== null) {
            return $fishes->getRandomByRarity($this->rarity)?->createItem();
        }

        if ($this->rodId !== null) {
            $rod = $rods?->get($this->rodId);
            return $rod === null ? null : $rods->createItem($rod);
        }

        if ($this->item !== null) {
            $item = clone $this->item;
            $item->setCount(random_int($this->minCount, $this->maxCount));
            EnchantSpec::applyAll($item, $this->enchants);
            return $item;
        }

        return null;
    }
}