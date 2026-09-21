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
    private const MAX_VERTICAL_SPEED = 0.1;
    private const MAX_SURFACE_SCAN = 16;
    public CompoundTag $compTag;
    protected bool $touchWaterSound = false;

    public function __construct(Location $location, ?Entity $shootingEntity, ?CompoundTag $nbt = null)
    {
        parent::__construct($location, $shootingEntity, $nbt);
        $this->setCanSaveWithChunk(false);
        if (!($shootingEntity instanceof Player)) {
            $this->flagForDespawn();
        }
    }

    public function flagForDespawn(): void
    {
        $owner = $this->getOwningEntity();

        if ($owner instanceof Player) {
            Main::getInstance()->getSessionManager()->stopFishing($owner);
        }

        parent::flagForDespawn();
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::FISHING_HOOK;
    }

    public function initEntity(CompoundTag $nbt): void
    {
        $this->compTag = $nbt;
        parent::initEntity($nbt);
    }

    protected function getInitialGravity(): float { return 0.04; }

    protected function getInitialDragMultiplier(): float { return 0.01; }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(0.25, 0.25);
    }

    /**
     * @throws RandomException
     */
    protected function entityBaseTick(int $tickDiff = 1): bool
    {
        $hasUpdate = parent::entityBaseTick($tickDiff);
        $player = $this->getOwningEntity();

        if ($player instanceof Player) {
            $session = Main::getInstance()->getSessionManager()->getSession($player);
            $main = Main::getInstance();
            if (
                $session === null ||
                !$session->isHoldingCastRod($player, $main->getRodManager())
            ) {
                $this->flagForDespawn();
                return true;
            }

            $this->tickSession($session, $player, $tickDiff);

            if ($this->shouldDespawnFor($player)) {
                $this->flagForDespawn();
                $hasUpdate = true;
            }
        } else {
            $this->flagForDespawn();
            $hasUpdate = true;
        }

        if ($this->isInWaterBlock() && !$this->touchWaterSound) {
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
        if (!$this->isInWaterBlock()) {
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

    public function isInWaterBlock(): bool
    {
        $p = $this->location;
        return $this->getWorld()->getBlockAt($p->getFloorX(), $p->getFloorY(), $p->getFloorZ()) instanceof Water;
    }

    private function shouldDespawnFor(Player $player): bool
    {
        return !$player->isAlive()
            || $player->isClosed()
            || $player->getLocation()->getWorld()->getFolderName() !== $this->getLocation()->getWorld()->getFolderName()
            || $player->getPosition()->distanceSquared($this->getPosition()) >= 16 ** 2;
    }

    protected function tryChangeMovement(): void
    {
        $surfaceY = $this->findSurfaceY();
        if ($surfaceY === null) {
            parent::tryChangeMovement();
            return;
        }

        $dy = ($surfaceY - $this->location->y) * 0.3;
        $dy = max(-self::MAX_VERTICAL_SPEED, min(self::MAX_VERTICAL_SPEED, $dy));

        $this->motion = new Vector3($this->motion->x * 0.5, $dy, $this->motion->z * 0.5);
    }

    private function findSurfaceY(): ?float
    {
        $world = $this->getWorld();
        $x = $this->location->getFloorX();
        $z = $this->location->getFloorZ();
        $y = $this->location->getFloorY();

        if (!$world->getBlockAt($x, $y, $z) instanceof Water) {
            return null;
        }

        $limit = $y + self::MAX_SURFACE_SCAN;
        while ($y < $limit && $world->getBlockAt($x, $y + 1, $z) instanceof Water) {
            $y++;
        }

        return $y + (8 / 9);
    }
}