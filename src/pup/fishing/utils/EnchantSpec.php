<?php

declare(strict_types=1);

namespace pup\fishing\utils;

use Logger;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\Item;
use Random\RandomException;

final readonly class EnchantSpec
{
    private function __construct(
        private Enchantment $enchantment,
        private int         $minLevel,
        private int         $maxLevel
    ) {
    }

    /** @return list<self> */
    public static function parseAll(mixed $raw, string $where, Logger $logger): array
    {
        if ($raw === null) {
            return [];
        }
        if (!is_array($raw)) {
            $logger->warning("$where: 'enchantments' must be a map (name: level), ignoring");
            return [];
        }

        $specs = [];
        foreach ($raw as $name => $level) {
            $enchantment = StringToEnchantmentParser::getInstance()->parse((string) $name);
            if ($enchantment === null) {
                $logger->warning("$where: unknown enchantment '$name', ignoring");
                continue;
            }

            if (is_array($level)) {
                $min = (int) ($level["min"] ?? 1);
                $max = (int) ($level["max"] ?? $min);
            } else {
                $min = $max = (int) $level;
            }
            $min = max(1, $min);
            $max = max($min, $max);

            $specs[] = new self($enchantment, $min, $max);
        }

        return $specs;
    }

    /** @param list<self> $specs
     * @throws RandomException
     */
    public static function applyAll(Item $item, array $specs): void
    {
        foreach ($specs as $spec) {
            $item->addEnchantment(new EnchantmentInstance($spec->enchantment, random_int($spec->minLevel, $spec->maxLevel)));
        }
    }
}