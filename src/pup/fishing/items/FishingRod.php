<?php

namespace pup\fishing\items;

final class FishingRod extends \pocketmine\item\FishingRod
{
    public function getMaxDurability(): int
    {
        return 64;
    }
}