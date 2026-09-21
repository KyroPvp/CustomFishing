<?php
declare(strict_types=1);

namespace pup\fishing\session;

use pocketmine\player\Player;
use pup\fishing\entities\FishingHook;
use pup\fishing\items\rods\CustomRod;

final class SessionManager
{
    /** @var array<string, FishingSession> keyed by player name */
    private array $sessions = [];

    public int $waitChanceTicks = 120;

    public function isFishing(Player $player): bool
    {
        return isset($this->sessions[$player->getXuid()]);
    }

    public function startFishing(Player $player, FishingHook $hook, CustomRod $rod, int $slot): FishingSession
    {
        $session = new FishingSession(
            $hook,
            $rod,
            $slot,
            $this->waitChanceTicks
        );
        $this->sessions[$player->getXuid()] = $session;
        return $session;
    }

    public function stopFishing(Player $player): void
    {
        unset($this->sessions[$player->getXuid()]);
    }

    public function getSession(Player $player): ?FishingSession
    {
        return $this->sessions[$player->getXuid()] ?? null;
    }

    public function getFishingHook(Player $player): ?FishingHook
    {
        return $this->getSession($player)?->getHook();
    }

    public function closeSessions(): void
    {
        unset($this->sessions);
    }
}