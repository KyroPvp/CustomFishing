<?php

declare(strict_types=1);

namespace pup\fishing\items\rods;

use InvalidArgumentException;
use Logger;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pup\fishing\items\fish\FishRarity;
use pup\fishing\items\loottable\LootTableManager;
use pup\fishing\utils\EnchantSpec;
use Random\RandomException;
use RuntimeException;

final class RodManager
{
    public const string TAG_ROD_ID = "rod_id";
    public const string DEFAULT_ROD_ID = "default";

    /** @var array<string, CustomRod> */
    private array $rods = [];

    /** @var array<string, list<EnchantSpec>> rod id => enchantments applied to the rod item */
    private array $enchants = [];

    public function __construct(private readonly LootTableManager $lootTables)
    {
    }

    public function load(Config $config, Logger $logger): void
    {
        $this->rods = [];
        $this->enchants = [];

        $section = $config->get("rods", []);
        if (!is_array($section)) {
            throw new RuntimeException("rods.yml: 'rods' must be a map of rod ids");
        }

        foreach ($section as $rawId => $data) {
            $id = (string) $rawId;

            if (!is_array($data)) {
                $logger->warning("rods.yml: '$id' has no data, skipping");
                continue;
            }

            $tableWeights = [];
            $tablesRaw = is_array($data["table-weights"] ?? null) ? $data["table-weights"] : [];
            foreach ($tablesRaw as $tableId => $weight) {
                $tableId = (string) $tableId;
                if (!$this->lootTables->hasTable($tableId)) {
                    $logger->warning("rods.yml: rod '$id' references unknown loot table '$tableId', ignoring that entry");
                    continue;
                }
                $tableWeights[$tableId] = (float) $weight;
            }
            if ($tableWeights === []) {
                $logger->warning("rods.yml: rod '$id' has no usable table-weights, skipping");
                continue;
            }

            $multipliers = [];
            $multRaw = is_array($data["rarity-multipliers"] ?? null) ? $data["rarity-multipliers"] : [];
            foreach ($multRaw as $rarityName => $mult) {
                try {
                    $multipliers[FishRarity::fromConfig((string) $rarityName)->value] = (float) $mult;
                } catch (InvalidArgumentException $e) {
                    $logger->warning("rods.yml: rod '$id': " . $e->getMessage() . ", ignoring");
                }
            }

            $lore = [];
            foreach ((array) ($data["lore"] ?? []) as $line) {
                $lore[] = TextFormat::RESET . TextFormat::colorize((string) $line);
            }

            $this->enchants[$id] = EnchantSpec::parseAll($data["enchantments"] ?? null, "rods.yml: rod '$id'", $logger);

            $this->rods[$id] = new CustomRod(
                $id,
                TextFormat::colorize((string) ($data["display-name"] ?? $id)),
                $lore,
                $tableWeights,
                $multipliers
            );
        }

        if (!isset($this->rods[self::DEFAULT_ROD_ID])) {
            throw new RuntimeException("rods.yml: a rod called '" . self::DEFAULT_ROD_ID . "' is required (used for plain vanilla rods)");
        }

        $logger->info("Loaded " . count($this->rods) . " rods");
    }

    public function get(string $id): ?CustomRod
    {
        return $this->rods[$id] ?? null;
    }

    /** @return list<string> */
    public function getIds(): array
    {
        return array_map('strval', array_keys($this->rods));
    }

    /**
     * @throws RandomException
     */
    public function createItem(CustomRod $rod): Item
    {
        $item = VanillaItems::FISHING_ROD();
        $item->setCustomName(TextFormat::RESET . $rod->getDisplayName());
        $item->setLore($rod->getLore());
        EnchantSpec::applyAll($item, $this->enchants[$rod->getId()] ?? []);

        $nbt = $item->getNamedTag();
        $nbt->setString(self::TAG_ROD_ID, $rod->getId());
        $item->setNamedTag($nbt);

        return $item;
    }

    public function getRodFromItem(Item $item): CustomRod
    {
        $id = $item->getNamedTag()->getString(self::TAG_ROD_ID, self::DEFAULT_ROD_ID);
        return $this->rods[$id] ?? $this->rods[self::DEFAULT_ROD_ID];
    }
}