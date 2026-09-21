<?php

namespace pup\fishing\session;

use pocketmine\player\Player;
use pup\fishing\entities\FishingHook;

final class SessionManager
{
    /** @var array<string, FishingSession> keyed by player name */
    private array $sessions = [];

    public int $waitChanceTicks = 120;

    public function isFishing(Player $player): bool
    {
        return isset($this->sessions[$player->getName()]);
    }

    public function startFishing(Player $player, FishingHook $hook): FishingSession
    {
        $session = new FishingSession($hook, $this->waitChanceTicks);
        $this->sessions[$player->getName()] = $session;
        return $session;
    }

    public function stopFishing(Player $player): void
    {
        unset($this->sessions[$player->getName()]);
    }

    public function getSession(Player $player): ?FishingSession
    {
        return $this->sessions[$player->getName()] ?? null;
    }

    public function getFishingHook(Player $player): ?FishingHook
    {
        return $this->getSession($player)?->getHook();
    }
}