<?php

declare(strict_types=1);

namespace pup\fishing;

use pocketmine\block\Water;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\Location;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\ThrowSound;
use pocketmine\world\sound\XpLevelUpSound;
use pup\fishing\entities\FishingHook;
use pup\fishing\session\SessionManager;
use Random\RandomException;

final class EventListener implements Listener
{
    private const float PITCH_NEAR = 60.0;
    private const float PITCH_FAR = -30.0;

    private const int MAX_SCAN_DEPTH = 16;

    /**
     * @throws RandomException
     */
    public function onPlayerItemUse(PlayerItemUseEvent $event): void
    {
        $item = $event->getItem();
        $player = $event->getPlayer();

        if ($item->getTypeId() !== ItemTypeIds::FISHING_ROD || !($item instanceof Durable)) {
            return;
        }
        $event->cancel();

        if ($player->hasItemCooldown($item)) {
            return;
        }

        $player->resetItemCooldown($item, 8);
        $manager = Main::getInstance()->getSessionManager();

        if (!$manager->isFishing($player)) {
            $this->castLine($player, $item);
        } else {
            $this->reelIn($player, $manager, $item);
        }

        $player->broadcastAnimation(new ArmSwingAnimation($player));
    }

    private function castLine(Player $player, Durable $item): void
    {
        $castPos = $this->findCastPosition($player);

        if ($castPos === null) {
            return;
        }

        $location = $player->getLocation();
        $world = $player->getWorld();

        $hook = new FishingHook(Location::fromObject($castPos, $world, $location->yaw, $location->pitch), $player);
        $hook->spawnToAll();
        $world->addSound($location, new ThrowSound());
        $item->applyDamage(1);
        $player->getInventory()->setItemInHand($item);
    }

    /**
     * @throws RandomException
     */
    private function reelIn(Player $player, SessionManager $manager, Item $rodItem): void
    {
        $session = $manager->getSession($player);
        if ($session === null) {
            return;
        }

        $hook = $session->getHook();
        if ($hook->isFlaggedForDespawn()) {
            return;
        }

        $caughtSomething = $session->isCaught();
        $dropPosition = $hook->getPosition();

        $hook->flagForDespawn();

        if ($caughtSomething) {
            $this->handleFishingDrop($player, $dropPosition, $rodItem);
        }
    }

    private function findCastPosition(Player $player): ?Vector3
    {
        $world = $player->getWorld();
        $position = $player->getEyePos();
        $direction = $player->getDirectionVector();
        $motion = $direction->multiply(1.5);

        for($tick = 0; $tick < 40; $tick++) {
            $nextPosition = $position->addVector($motion);
            $block = $world->getBlock($nextPosition->floor());
        }
        if($block instanceof Water){
            $x = $nextPosition->x;
            $z = $nextPosition->z;

            $waterY = $nextPosition->y;

            while(
                $world->getBlockAt(
                    (int) floor($x),
                    (int) floor($waterY + 1),
                    (int) floor($z)
                )
            ) {
                $waterY++;
            }
            return new Vector3($x, $waterY + 1, $z);
        }
        $position = $nextPosition;
        $motion = $motion->subtract(0, 0.03, 0);
        $motion = $motion->multiply(0.99);
        return null;
    }

    /**
     * @throws RandomException
     */
    private function handleFishingDrop(Player $player, Vector3 $dropPosition, Item $rodItem): void
    {
        $main = Main::getInstance();
        $world = $player->getWorld();

        $rod = $main->getRodManager()->getRodFromItem($rodItem);
        $caught = $main->getLootTableManager()->rollCatch($rod);

        $display = clone $caught;
        $display->setCount(1);
        $display->setLore(["fishing_animation_item"]);
        $name = $display->hasCustomName() ? $display->getCustomName() : $display->getName();


        $itemEntity = new ItemEntity(Location::fromObject($dropPosition->add(0, 2, 0), $world, random_int(0,359), 0), $display);
        $itemEntity->setPickupDelay(300);
        $itemEntity->setDespawnDelay(60);
        $itemEntity->setNameTag($name);
        $itemEntity->setNameTagVisible();
        $itemEntity->setNameTagAlwaysVisible();
        $itemEntity->setHasGravity(false);
        $itemEntity->spawnToAll();

        if ($player->getInventory()->canAddItem($caught)) {
            $player->getInventory()->addItem($caught);
        } else {
            $world->dropItem($dropPosition->add(0, 2, 0), $caught);
        }

        $world->addSound($dropPosition, new XpLevelUpSound(30));
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        $manager = Main::getInstance()->getSessionManager();
        $player = $event->getPlayer();
        $hook = $manager->getFishingHook($player);

        if(!$hook === null){
            $hook->flagForDespawn();
        }

        if ($manager->isFishing($player)) {
            $manager->stopFishing($player);
        }
    }
}