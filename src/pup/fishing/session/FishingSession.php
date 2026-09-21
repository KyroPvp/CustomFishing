<?php

declare(strict_types=1);

namespace pup\fishing\session;

use pocketmine\math\Vector3;
use pocketmine\world\particle\BubbleParticle;
use pup\fishing\entities\FishingHook;
use Random\RandomException;

final class FishingSession
{
    private const float ATTRACT_SPEED = 0.1;
    private const float BITE_DISTANCE = 0.15;

    private int $waitTimer;
    private bool $attracted = false;
    private bool $caught = false;
    private int $caughtTimer = 0;

    private float $fishX = 0.0;
    private float $fishZ = 0.0;

    private int $popupTicks = 0;
    private int $elapsedSeconds = 0;

    public function __construct(
        private readonly FishingHook $hook,
        private readonly int $waitChance = 120
    ) {
        $this->waitTimer = $this->waitChance * 2;
    }

    public function getHook(): FishingHook
    {
        return $this->hook;
    }

    public function isCaught(): bool
    {
        return $this->caught;
    }

    /**
     * @throws RandomException
     */
    public function tick(int $tickDiff): bool
    {
        if (!$this->attracted) {
            $this->waitTimer -= $tickDiff;
            if ($this->waitTimer > 0) {
                return false;
            }
            if (random_int(1, 100) <= 90) {
                $this->startAttracting();
            } else {
                $this->waitTimer = $this->waitChance;
            }
            return false;
        }

        if (!$this->caught) {
            return $this->attractFish($tickDiff);
        }

        $this->caughtTimer -= $tickDiff;
        if ($this->caughtTimer <= 0) {
            $this->resetToWaiting();
        }
        return false;
    }

    private function startAttracting(): void
    {
        $hookPos = $this->hook->getPosition();
        $this->fishX = $hookPos->x + (mt_rand(0, 1200) / 1000 + 1) * (mt_rand(0, 1) === 1 ? 1 : -1);
        $this->fishZ = $hookPos->z + (mt_rand(0, 1200) / 1000 + 1) * (mt_rand(0, 1) === 1 ? 1 : -1);
        $this->attracted = true;
    }

    private function attractFish(int $tickDiff): bool
    {
        $hookPos = $this->hook->getPosition();

        for ($i = 0; $i < $tickDiff; $i++) {
            $this->fishX += ($hookPos->x - $this->fishX) * self::ATTRACT_SPEED;
            $this->fishZ += ($hookPos->z - $this->fishZ) * self::ATTRACT_SPEED;

            if (mt_rand(1, 100) <= 85) {
                $this->hook->getWorld()->addParticle(
                    new Vector3($this->fishX, $hookPos->y, $this->fishZ),
                    new BubbleParticle()
                );
            }

            $distance = sqrt(($hookPos->x - $this->fishX) ** 2 + ($hookPos->z - $this->fishZ) ** 2);
            if ($distance < self::BITE_DISTANCE) {
                $this->caught = true;
                $this->caughtTimer = mt_rand(20, 49);
                return true;
            }
        }

        return false;
    }

    private function resetToWaiting(): void
    {
        $this->attracted = false;
        $this->caught = false;
        $this->waitTimer = $this->waitChance * 3;
    }

    public function popupTick(int $tickDiff): ?int
    {
        $this->popupTicks += $tickDiff;
        if ($this->popupTicks < 20) {
            return null;
        }
        $this->popupTicks = 0;
        return ++$this->elapsedSeconds;
    }
}