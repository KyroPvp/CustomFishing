<?php

declare(strict_types=1);

namespace pup\fishing\items\loottable;

use InvalidArgumentException;
use Logger;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pup\fishing\items\fish\FishManager;
use pup\fishing\items\fish\FishRarity;
use pup\fishing\items\rods\CustomRod;
use pup\fishing\items\rods\RodManager;
use pup\fishing\utils\EnchantSpec;
use pup\fishing\utils\WeightedRandom;
use Random\RandomException;
use RuntimeException;

final class LootTableManager
{
    /** @var array<string, LootTable> */
    private array $tables = [];

    private ?RodManager $rods = null;

    public function __construct(private readonly FishManager $fishes)
    {
    }

    public function load(Config $config, Logger $logger): void
    {
        $this->tables = [];

        $section = $config->get("loot-tables", []);
        if (!is_array($section)) {
            throw new RuntimeException("loot-table.yml: 'loot-tables' must be a map (fish / junk / treasure)");
        }

        foreach ($section as $rawTableId => $rawEntries) {
            $tableId = (string) $rawTableId;
            $entries = [];

            foreach ((array) $rawEntries as $index => $data) {
                $where = "loot-table.yml: $tableId #" . ($index + 1);

                if (!is_array($data)) {
                    $logger->warning("$where is not a map, skipping");
                    continue;
                }

                $weight = (float) ($data["weight"] ?? 0);
                if ($weight <= 0) {
                    $logger->warning("$where needs a weight above 0, skipping");
                    continue;
                }

                $entry = $this->parseEntry($data, $weight, $where, $logger);
                if ($entry !== null) {
                    $entries[] = $entry;
                }
            }

            $this->tables[$tableId] = new LootTable($tableId, $entries);
            if ($entries === []) {
                $logger->warning("loot-table.yml: table '$tableId' has no valid entries");
            }
        }

        $logger->info("Loaded " . count($this->tables) . " loot tables");
    }

    /** @param array<string, mixed> $data */
    private function parseEntry(array $data, float $weight, string $where, Logger $logger): ?LootTableEntry
    {
        if (isset($data["fish"])) {
            $fishId = (string) $data["fish"];
            if (!$this->fishes->hasFish($fishId)) {
                $logger->warning("$where: unknown fish '$fishId', skipping");
                return null;
            }
            return LootTableEntry::specificFish($weight, $fishId);
        }

        if (isset($data["rarity"])) {
            try {
                $rarity = FishRarity::fromConfig((string) $data["rarity"]);
            } catch (InvalidArgumentException $e) {
                $logger->warning("$where: " . $e->getMessage() . ", skipping");
                return null;
            }
            if ($this->fishes->countByRarity($rarity) === 0) {
                $logger->warning("$where: no fish of rarity '{$rarity->value}' exist yet (entry will roll nothing)");
            }
            return LootTableEntry::fishPool($weight, $rarity);
        }

        if (isset($data["rod"])) {
            return LootTableEntry::rod($weight, (string) $data["rod"]);
        }

        if (isset($data["item"])) {
            $item = StringToItemParser::getInstance()->parse((string) $data["item"]);
            if ($item === null) {
                $logger->warning("$where: unknown item '{$data["item"]}', skipping");
                return null;
            }
            if (isset($data["name"])) {
                $item->setCustomName(TextFormat::colorize((string) $data["name"]));
            }
            $count = is_array($data["count"] ?? null) ? $data["count"] : [];
            $min = max(1, (int) ($count["min"] ?? 1));
            $max = max($min, (int) ($count["max"] ?? $min));
            $enchants = EnchantSpec::parseAll($data["enchantments"] ?? null, $where, $logger);
            return LootTableEntry::plainItem($weight, $item, $min, $max, $enchants);
        }

        $logger->warning("$where needs one of: fish, rarity, rod, item. Skipping");
        return null;
    }

    public function linkRods(RodManager $rods, Logger $logger): void
    {
        $this->rods = $rods;

        foreach ($this->tables as $table) {
            foreach ($table->getEntries() as $entry) {
                $rodId = $entry->getRodId();
                if ($rodId !== null && $rods->get($rodId) === null) {
                    $logger->warning("loot-table.yml: table '{$table->getId()}' references unknown rod '$rodId' (it will roll nothing)");
                }
            }
        }
    }

    public function hasTable(string $id): bool
    {
        return isset($this->tables[$id]);
    }

    /**
     * @throws RandomException
     */
    public function rollCatch(CustomRod $rod): Item
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $tableId = WeightedRandom::pick($rod->getTableWeights());
            if ($tableId === null) {
                break;
            }

            $item = ($this->tables[(string) $tableId] ?? null)
                ?->roll($rod)
                ?->createItem($this->fishes, $this->rods);

            if ($item !== null) {
                return $item;
            }
        }

        return VanillaItems::RAW_FISH();
    }
}