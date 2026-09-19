<?php

declare(strict_types=1);

namespace pup\fishing;

use pocketmine\block\Air;
use pocketmine\block\Water;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\Location;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Durable;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\ThrowSound;
use pup\fishing\entities\FishingHook;
use pup\fishing\session\SessionManager;

final class EventListener implements Listener
{
    public function onPlayerItemUse(PlayerItemUseEvent $event): void
    {
        $item = $event->getItem();
        $player = $event->getPlayer();

        if ($item->getTypeId() !== ItemTypeIds::FISHING_ROD || !($item instanceof Durable)) {
            return;
        }

        if ($player->hasItemCooldown($item)) {
            $event->cancel();
            return;
        }

        $player->resetItemCooldown($item, 8);
        $manager = Main::getInstance()->getSessionManager();

        if (!$manager->isFishing($player)) {
            $this->castLine($player, $item);
        } else {
            $this->reelIn($player, $manager);
        }

        $player->broadcastAnimation(new ArmSwingAnimation($player));
    }

    private function castLine(Player $player, Durable $item): void
    {
        $location = $player->getLocation();
        $world = $player->getWorld();
        $targetPos = $this->getTargetPosition($player, rand(6, 10));

        $block = $world->getBlockAt($targetPos->getFloorX(), $targetPos->getFloorY(), $targetPos->getFloorZ());

        if (!($block instanceof Water) && !($block instanceof Air)) {
            return;
        }

        $hook = new FishingHook(Location::fromObject($targetPos, $world, $location->yaw, $location->pitch), $player);
        $hook->spawnToAll();
        $world->addSound($location, new ThrowSound());
        $item->applyDamage(1);
        $player->getInventory()->setItemInHand($item);
    }

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

        $caughtSomething = $session->isCaught();
        $dropPosition = $hook->getPosition();

        $hook->flagForDespawn();

        if ($caughtSomething) {
            $this->handleFishingDrop($player, $dropPosition);
        }
    }

    private function getTargetPosition(Player $player, float $distance = 6.0): Vector3
    {
        return $player->getEyePos()->addVector($player->getDirectionVector()->multiply($distance));
    }

    private function handleFishingDrop(Player $player, Vector3 $dropPosition): void
    {
        $world = $player->getWorld();

        $item = clone VanillaItems::RAW_FISH();
        $item->setLore(["fishing_animation_item"]);
        $item->setCount(1);

        $itemEntity = new ItemEntity(Location::fromObject($dropPosition->add(0, 2, 0), $world, lcg_value() * 360, 0), $item);
        $itemEntity->setPickupDelay(300);
        $itemEntity->setDespawnDelay(60);
        $itemEntity->setNameTag($item->getName()); //TODO: Custom name?
        $itemEntity->setNameTagVisible();
        $itemEntity->setNameTagAlwaysVisible();
        $itemEntity->setHasGravity(false);
        $itemEntity->spawnToAll();

        if ($player->getInventory()->canAddItem($item)) {
            $player->getInventory()->addItem($item);
        } else {
            $world->dropItem($dropPosition->add(0, 2, 0), $item);
        }
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        $manager = Main::getInstance()->getSessionManager();
        $player = $event->getPlayer();

        if ($manager->isFishing($player)) {
            $manager->stopFishing($player);
        }
    }
}