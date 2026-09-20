<?php

declare(strict_types=1);

namespace pup\fishing\items\fish;

use InvalidArgumentException;
use Logger;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\utils\Config;
use RuntimeException;

final class FishManager
{
    /** @var array<string, Fish> */
    private array $fish = [];

    /** @var array<string, list<Fish>> keyed by rarity value ("common", ...) */
    private array $byRarity = [];

    public function load(Config $config, Logger $logger): void
    {
        $this->fish = [];
        $this->byRarity = [];

        $section = $config->get("fishes", []);
        if (!is_array($section)) {
            throw new RuntimeException("fish.yml: 'fishes' must be a map of fish ids");
        }

        foreach ($section as $rawId => $data) {
            $id = (string) $rawId;

            if (!is_array($data)) {
                $logger->warning("fish.yml: '$id' has no data (unfinished entry?), skipping");
                continue;
            }

            $baseItem = self::resolveBaseItem((string) ($data["item"] ?? ""));
            if ($baseItem === null) {
                $logger->warning("fish.yml: '$id' has invalid item '" . ($data["item"] ?? "") . "' (use cod, salmon, pufferfish or tropicalfish), skipping");
                continue;
            }

            try {
                $rarity = FishRarity::fromConfig((string) ($data["rarity"] ?? ""));
            } catch (InvalidArgumentException $e) {
                $logger->warning("fish.yml: '$id': " . $e->getMessage() . ", skipping");
                continue;
            }

            $weight = is_array($data["weight"] ?? null) ? $data["weight"] : [];
            $min = (float) ($weight["min"] ?? 0.5);
            $max = (float) ($weight["max"] ?? 3.0);
            if ($min > $max) {
                [$min, $max] = [$max, $min];
            }
            $min = max(0.01, $min);
            $max = max($min, $max);

            $fish = new Fish($id, (string) ($data["display-name"] ?? $id), $baseItem, $rarity, $min, $max);
            $this->fish[$id] = $fish;
            $this->byRarity[$rarity->value][] = $fish;
        }

        $logger->info("Loaded " . count($this->fish) . " fish");
    }

    private static function resolveBaseItem(string $name): ?Item
    {
        return match (strtolower(trim($name))) {
            "cod", "raw_fish" => VanillaItems::RAW_FISH(),
            "salmon", "raw_salmon" => VanillaItems::RAW_SALMON(),
            "pufferfish" => VanillaItems::PUFFERFISH(),
            "tropicalfish", "tropical_fish", "clownfish" => VanillaItems::CLOWNFISH(),
            default => null,
        };
    }

    public function get(string $id): ?Fish
    {
        return $this->fish[$id] ?? null;
    }

    public function hasFish(string $id): bool
    {
        return isset($this->fish[$id]);
    }

    public function countByRarity(FishRarity $rarity): int
    {
        return count($this->byRarity[$rarity->value] ?? []);
    }

    public function getRandomByRarity(FishRarity $rarity): ?Fish
    {
        $list = $this->byRarity[$rarity->value] ?? [];
        return $list === [] ? null : $list[array_rand($list)];
    }
}