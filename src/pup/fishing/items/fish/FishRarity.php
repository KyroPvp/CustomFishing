<?php

declare(strict_types=1);

namespace pup\fishing\items\fish;

use InvalidArgumentException;
use pocketmine\utils\TextFormat;

enum FishRarity: string
{
    case Common = "common";
    case Uncommon = "uncommon";
    case Rare = "rare";
    case Epic = "epic";
    case Legendary = "legendary";
    case Mythic = "mythic";

    public function getColor(): string
    {
        return match ($this) {
            self::Common => TextFormat::WHITE,
            self::Uncommon => TextFormat::GREEN,
            self::Rare => TextFormat::AQUA,
            self::Epic => TextFormat::LIGHT_PURPLE,
            self::Legendary => TextFormat::GOLD,
            self::Mythic => TextFormat::RED,
        };
    }

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    /** @throws InvalidArgumentException when the text isn't a known rarity */
    public static function fromConfig(string $text): self
    {
        return self::tryFrom(strtolower(trim($text)))
            ?? throw new InvalidArgumentException("Unknown rarity '$text'");
    }
}