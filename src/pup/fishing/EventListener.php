<?php

declare(strict_types=1);

namespace pup\fishing;

use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\Location;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Durable;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\ThrowSound;
use pocketmine\world\sound\XpLevelUpSound;
use pup\fishing\entities\FishingHook;
use pup\fishing\items\rods\CustomRod;
use pup\fishing\session\SessionManager;
use Random\RandomException;

final class EventListener implements Listener
{

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
        $location = $player->getLocation();
        $world = $player->getWorld();
        $main = Main::getInstance();
        $session = $main->getSessionManager();
        $rod = $main->getRodManager()->getRodFromItem($item);

        $hook = new FishingHook(
            Location::fromObject($player->getEyePos(), $world, $location->yaw, $location->pitch),
            $player
        );
        $session->startFishing($player, $hook, $rod, $player->getInventory()->getHeldItemIndex());
        $hook->setMotion($player->getDirectionVector()->multiply(1.5));
        $hook->spawnToAll();

        $world->addSound($location, new ThrowSound());
        $item->applyDamage(1);
        $player->getInventory()->setItemInHand($item);
    }

    /**
     * @throws RandomException
     */
    private function reelIn(Player $player, SessionManager $manager): void
    {
        $session = $manager->getSession($player);
        if ($session === null) {
            return;
        }

        $hook = $session->getHook();
        if ($hook->isFlaggedForDespawn()) {
            return;
        }

        $main = Main::getInstance();
        $valid = $session->isHoldingCastRod($player, $main->getRodManager());
        $caughtSomething = $session->isCaught();
        $dropPosition = $hook->getPosition();

        $hook->flagForDespawn();

        if ($valid && $caughtSomething) {
            $this->handleFishingDrop($player, $dropPosition, $session->getRod());
        }
    }

    /**
     * @throws RandomException
     */
    private function handleFishingDrop(Player $player, Vector3 $dropPosition, CustomRod $rodItem): void
    {
        $main = Main::getInstance();
        $world = $player->getWorld();

        $caught = $main->getLootTableManager()->rollCatch($rodItem);

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

        $world->addSound($dropPosition, new XpLevelUpSound(100000));
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        $manager = Main::getInstance()->getSessionManager();
        $player = $event->getPlayer();
        $hook = $manager->getFishingHook($player);

        $hook?->flagForDespawn();

        if ($manager->isFishing($player)) {
            $manager->stopFishing($player);
        }
    }
}