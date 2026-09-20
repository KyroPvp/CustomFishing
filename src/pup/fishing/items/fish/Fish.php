<?php

declare(strict_types=1);

namespace pup\fishing\items\fish;

use pocketmine\item\Item;
use pocketmine\utils\TextFormat;
use Random\RandomException;

final class Fish
{
    public const string TAG_FISH_ID = "fish_id";
    public const string TAG_WEIGHT = "fish_weight";

    public function __construct(
        private readonly string $id,
        private readonly string $displayName,
        private readonly Item $baseItem,
        private readonly FishRarity $rarity,
        private readonly float $minWeight,
        private readonly float $maxWeight
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

    public function getRarity(): FishRarity
    {
        return $this->rarity;
    }

    /** Builds a brand-new caught fish with its own random weight.
     * @throws RandomException
     */
    public function createItem(): Item
    {
        $grams = random_int((int) round($this->minWeight * 1000), (int) round($this->maxWeight * 1000));

        $item = clone $this->baseItem;
        $item->setCount(1);
        $item->setCustomName(TextFormat::RESET . $this->rarity->getColor() . $this->displayName);
        $item->setLore([
            TextFormat::RESET . $this->rarity->getColor() . $this->rarity->getLabel(),
            TextFormat::RESET . TextFormat::GRAY . "Weight: " . TextFormat::WHITE . number_format($grams / 1000, 2) . " kg",
        ]);

        $nbt = $item->getNamedTag();
        $nbt->setString(self::TAG_FISH_ID, $this->id);
        $nbt->setInt(self::TAG_WEIGHT, $grams);
        $item->setNamedTag($nbt);

        return $item;
    }

    public static function getFishIdFromItem(Item $item): ?string
    {
        $id = $item->getNamedTag()->getString(self::TAG_FISH_ID, "");
        return $id === "" ? null : $id;
    }
}