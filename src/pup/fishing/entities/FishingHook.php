<?php

declare(strict_types=1);

namespace pup\fishing\entities;

use pocketmine\block\Water;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Projectile;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\world\particle\BubbleParticle;
use pocketmine\world\sound\CauldronEmptyWaterSound;
use pup\fishing\animations\FishHookAnimation;
use pup\fishing\animations\FishHookAnimationType;
use pup\fishing\Main;
use pup\fishing\session\FishingSession;
use Random\RandomException;

final class FishingHook extends Projectile
{
    protected bool $touchWaterSound = false;
    public CompoundTag $compTag;

    public static function getNetworkTypeId(): string
    {
        return EntityIds::FISHING_HOOK;
    }

    protected function getInitialDragMultiplier(): float
    {
        return 0;
    }

    protected function getInitialGravity(): float
    {
        return 0;
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(0.25, 0.25);
    }

    public function initEntity(CompoundTag $nbt): void
    {
        $this->compTag = $nbt;
        $this->setHasGravity(false);
        parent::initEntity($nbt);
    }

    public function __construct(Location $location, ?Entity $shootingEntity, ?CompoundTag $nbt = null)
    {
        parent::__construct($location, $shootingEntity, $nbt);
        if ($shootingEntity instanceof Player) {
            Main::getInstance()->getSessionManager()->startFishing($shootingEntity, $this);
        } else {
            $this->flagForDespawn();
        }
    }

    /**
     * @throws RandomException
     */
    protected function entityBaseTick(int $tickDiff = 1): bool
    {
        $hasUpdate = parent::entityBaseTick($tickDiff);
        $player = $this->getOwningEntity();

        $this->driftTowardsWaterSurface();

        if ($player instanceof Player) {
            $session = Main::getInstance()->getSessionManager()->getSession($player);
            if ($session !== null) {
                $this->tickSession($session, $player, $tickDiff);
            }

            if ($this->shouldDespawnFor($player)) {
                $this->flagForDespawn();
                $hasUpdate = true;
            }
        } else {
            $this->flagForDespawn();
            $hasUpdate = true;
        }

        if ($this->isOverWater() && !$this->touchWaterSound) {
            $this->getWorld()->addSound($this->location, new CauldronEmptyWaterSound());
            $this->touchWaterSound = true;
        }

        return $hasUpdate;
    }

    /**
     * @throws RandomException
     */
    private function tickSession(FishingSession $session, Player $player, int $tickDiff): void
    {
        if (!$this->isOverWater()) {
            return;
        }

        $seconds = $session->popupTick($tickDiff);
        if ($seconds !== null) {
            $player->sendPopup("$seconds");
        }

        if ($session->tick($tickDiff)) {
            $this->broadcastAnimation(new FishHookAnimation($this, FishHookAnimationType::HOOK));
            $this->broadcastAnimation(new FishHookAnimation($this, FishHookAnimationType::BUBBLE));
            $this->broadcastAnimation(new FishHookAnimation($this, FishHookAnimationType::TEASE));
            $this->getWorld()->addParticle($this->getPosition(), new BubbleParticle());
            $this->getWorld()->addSound($this->location, new CauldronEmptyWaterSound());
        }
    }

    private function shouldDespawnFor(Player $player): bool
    {
        if (
            $player->getInventory()->getItemInHand()->getTypeId() !== ItemTypeIds::FISHING_ROD ||
            !$player->isAlive() ||
            $player->isClosed() ||
            $player->getLocation()->getWorld()->getFolderName() !== $this->getLocation()->getWorld()->getFolderName()
        ) {
            return true;
        }

        return $player->getPosition()->distance($this->getPosition()) >= 32;
    }

    private function driftTowardsWaterSurface(): void
    {
        $highestBlockY = $this->getPosition()->getWorld()->getHighestBlockAt(
            $this->getPosition()->getFloorX(),
            $this->getPosition()->getFloorZ()
        );
        $y = $highestBlockY - $this->getPosition()->y + 1;
        $this->setMotion(new Vector3(0, $y, 0));
    }

    public function flagForDespawn(): void
    {
        $owner = $this->getOwningEntity();

        if ($owner instanceof Player) {
            Main::getInstance()->getSessionManager()->stopFishing($owner);
        }

        parent::flagForDespawn();
    }

    public function isOverWater(): bool
    {
        $pos = $this->getPosition();
        $block = $this->getWorld()->getBlockAt($pos->getFloorX(), $pos->getFloorY() - 1, $pos->getFloorZ());
        return $block instanceof Water;
    }
}