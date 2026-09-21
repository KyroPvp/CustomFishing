<?php

declare(strict_types=1);

namespace pup\fishing\utils;

use Random\RandomException;

final class WeightedRandom
{
    /**
     * @param array<string|int, float|int> $weights
     * @throws RandomException
     */
    public static function pick(array $weights): string|int|null
    {
        $total = 0.0;
        foreach ($weights as $weight) {
            if ($weight > 0) {
                $total += $weight;
            }
        }

        if ($total <= 0) {
            return null;
        }

        $roll = random_int(0, $total); // random float between 0 and $total
        $lastValid = null;

        foreach ($weights as $key => $weight) {
            if ($weight <= 0) {
                continue;
            }
            $lastValid = $key;
            $roll -= $weight;
            if ($roll < 0) {
                return $key;
            }
        }

        return $lastValid; // only reached through floating-point rounding
    }
}