<?php

namespace WorldEdit;

use pocketmine\block\Block;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\Listener;

use pocketmine\item\Item;

use pocketmine\level\Position;

use pocketmine\math\Vector3;

use pocketmine\plugin\PluginBase;

use pocketmine\scheduler\Task;

use pocketmine\utils\Config;

use pocketmine\Player;
use pocketmine\Server;

class WorldEdit extends PluginBase implements Listener{// Wonder if hmy Mr.'s okay but I was referring ...

private $sessions, $board, $count;
private $load = false;
private $task = false;

	public function onEnable(){
		$this->sessions = array();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		if(!file_exists($this->getDataFolder())) @mkdir($this->getDataFolder());
			$this->config = new Config($this->getDataFolder()."config.yml", CONFIG::YAML, array(
							"block-limit" => -1,
							"wand-item" => 292,
							"tick-place" => 200
							));
		$this->config->save();
		$this->wanditem = $this->config->get("wand-item");
		if($this->config->get("tick-place") <= 0){
			$this->getServer()->getLogger()->error("[WorldEdit] tick-place Please in the 1 or more . ");
			$this->getServer()->getPluginManager()->disablePlugin($this);
		}
		$this->tickplace = $this->config->get("tick-place");
	}

	public function onDisable(){
	}

	public function session(Player $player){
		if(!isset($this->sessions[$player->getName()])){
			$this->sessions[$player->getName()] = array(
												  "selection" => array(false,false),
												  "block-limit" => $this->config->get("block-limit"),
												  "wand-usage" => true,
			);
		}
		return $this->sessions[$player->getName()];
	}

	public function countBlocks($selection, &$startX = null, &$startY = null, &$startZ = null){
		if(!is_array($selection) ||
		   $selection[0] === false ||
		   $selection[1] === false ||
		   $selection[0][3] !== $selection[1][3])
			return false;
		$startX = min($selection[0][0], $selection[1][0]);
		$endX = max($selection[0][0], $selection[1][0]);
		$startY = min($selection[0][1], $selection[1][1]);
		$endY = max($selection[0][1], $selection[1][1]);
		$startZ = min($selection[0][2], $selection[1][2]);
		$endZ = max($selection[0][2], $selection[1][2]);
		return ($endX - $startX + 1) * ($endY - $startY + 1) * ($endZ - $startZ + 1);
	}

	public function onBreak(BlockBreakEvent $event){
		$player = $event->getPlayer();
		if($player->getInventory()->getItemInHand()->getID() == $this->wanditem &&
		   $player->hasPermission("we.use") &&
		   $this->session($player)["wand-usage"] === true){
			$position = $event->getBlock();
			$this->sessions[$player->getName()]["selection"][0] = array(round($position->x), round($position->y), round($position->z),$position->level);
			$count = $this->countBlocks($this->sessions[$player->getName()]["selection"]);
			if($count === false)
				$count = "";
			else
				$count = " ($count)";
			$session["selection"] = $this->sessions[$player->getName()]["selection"];
			$block = $event->getBlock();
			$block = " ".$block->getId().":".$block->getDamage();
			$player->sendMessage("the first place (".$session["selection"][0][0].", ".$session["selection"][0][1].", ".$session["selection"][0][2].")$block $count");
			$event->setCancelled(true);
		}
	}

	public function onTouch(PlayerInteractEvent $event){
		$player = $event->getPlayer();
		if($player->getInventory()->getItemInHand()->getID() == $this->wanditem &&
		   $player->hasPermission("we.use") &&
		   $this->session($player)["wand-usage"] === true &&
		   $event->getBlock()->getID() != Block::AIR){
			$position = $event->getBlock();
			$this->sessions[$player->getName()]["selection"][1] = array(round($position->x), round($position->y), round($position->z),$position->level);
			$count = $this->countBlocks($this->sessions[$player->getName()]["selection"]);
			if($count === false)
				$count = "";
			else
				$count = " ($count)";
			$session["selection"] = $this->sessions[$player->getName()]["selection"];
			$block = $event->getBlock();
			$block = " ".$block->getId().":".$block->getDamage();
			$player->sendMessage("the second place (".$session["selection"][1][0].", ".$session["selection"][1][1].", ".$session["selection"][1][2].")$block $count.");
			$event->setCancelled(true);
		}
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		$cmd = $command->getName();
		if($cmd{0} === "/")
			$cmd = substr($cmd, 1);
		switch($cmd){
			case "cut":
				if(!($sender instanceof Player)){
					$sender->sendMessage("§c t does not work only in the game ");
					break;
				}
				if($this->load === true){
					$sender->sendMessage("§cIt is in another process .");
					return true;
				}
				$session = $this->session($sender);
				$count = $this->countBlocks($session["selection"], $startX, $startY, $startZ);
				if($count > $session["block-limit"] && $session["block-limit"] > 0){
					$sender->sendMessage("§cblock limit".$session["block-limit"]." A has been exceeded . ");
					break;
				}
				$this->load = true;
				$this->task = true;
				$this->W_cut($session["selection"], $sender);
				$sender->sendMessage("§bmodification start…");
				return true;
				break;
			case "editwand":
				if(!($sender instanceof Player)){
					$sender->sendMessage(" §cIt does not work only in in the game ");
					break;
				}
				$session = $this->session($sender);
				$session["wand-usage"] = $session["wand-usage"] == true ? false : true;
				$this->sessions[$sender->getName()]["wand-usage"] = $session["wand-usage"];
				$sender->sendMessage("§awand ".($session["wand-usage"] === true ? "on":"off"));
				break;
			case "wand":
				if(!($sender instanceof Player)){
					$sender->sendMessage("§c It does not work only in the game ");
					break;
				}
				if($sender->getInventory()->getItem($this->config->get("wand-item"))->getID() === Item::get($this->config->get("wand-item"))->getID()){
					$sender->sendMessage("§ You have a wand . ");
					break;
				}elseif($sender->getGamemode() === 1){
					$sender->sendMessage("§c You are a creative mode . ");
				}else{
					$sender->getInventory()->addItem(Item::get($this->config->get("wand-item")));
					$sender->sendMessage("§a Broke pos1, is set to pos2 on tap . ");
				}
				break;
			case "desel":
				if(!($sender instanceof Player)){
					$sender->sendMessage("§c It does not work only in the game ");
					break;
				}
				$session = $this->session($sender);
				$session["selection"] = array(false, false);
				$this->sessions[$sender->getName()]["selection"] = $session["selection"];
				$sender->sendMessage("§a It was deleted po ");
				break;
			case "limit":
				if(!isset($args[0]) or trim($args[0]) === "")
					return false;
				$limit = intval($args[0]);
				if($limit < 0){
					$limit = -1;
				}
				if($this->config->get("block-limit") > 0)
					$limit = $limit === -1 ? $this->config->get("block-limit") : min($this->config->get("block-limit"), $limit);
				$session["block-limit"] = $limit;
 				$this->sessions[$sender->getName()]["block-limit"] = $session["block-limit"];
				$sender->sendMessage("§a The limit ".($limit === -1 ? "無限の" : $limit)." It was to block . ");
				break;
			case "set":
				if(!($sender instanceof Player)){
					$sender->sendMessage("§c It does not work only in the game ");
					break;
				}
				if($this->load === true){
					$sender->sendMessage("§c It is in another process . ");
					return true;
				}
				$session = $this->session($sender);
				$count = $this->countBlocks($session["selection"]);
				if($count > $session["block-limit"] and $session["block-limit"] > 0){
					$sender->sendMessage("§c Block limit ".$session["block-limit"]." A has been exceeded . ");
					break;
				}
				if(!isset($args[0]) or $args[0] == ""){
					return false;
				}
				$items = Item::fromString($args[0], true);
				foreach($items as $item){
					if($item->getID() > 0xff){
						$sender->sendMessage("§c The id is not a block . ");
					}
				}
				$this->load = true;
				$this->task = true;
				$this->W_set($session["selection"], $items, $sender);
				$sender->sendMessage("§b Processing ");
				return true;
				break;
			case "replace":
				if(!($sender instanceof Player)){
					$sender->sendMessage("§c It does not work only in the game ");
					break;
				}
				if($this->load === true){
					$sender->sendMessage("§c It is in another process . ");
					return true;
				}
				$session = $this->session($sender);
				$count = $this->countBlocks($session["selection"]);
				if($count > $session["block-limit"] and $session["block-limit"] > 0){
					$sender->sendMessage("§c Block limit ".$session["block-limit"]." A has been exceeded . ");
					break;
				}
				if(!isset($args[0]) or $args[0] == ""){
					return false;
				}
				if(!isset($args[1]) or $args[1] == ""){
					return false;
				}
				$item1 = Item::fromString($args[0]);
				if($item1->getID() > 0xff){
					$sender->sendMessage("§c The id is not a block . ");
					break;
				}
				$items2 = Item::fromString($args[1], true);
				foreach($items2 as $item){
					if($item->getID() > 0xff){
					$sender->sendMessage("§c The id is not a block . ");
					}
				}
				$this->load = true;
				$this->task = true;
				$this->W_replace($session["selection"], $item1, $items2, $sender);
				$sender->sendMessage("§b Processing ... ");
				return true;
				break;
			case "undo":
				if(!($sender instanceof Player)){
					$sender->sendMessage("§c It does not work only in the game ");
					break;
				}
				if($this->load === true){
					$sender->sendMessage("§c It is in another process . ");
					return true;
				}
				$this->load = true;
				$this->task = true;
				$this->W_undo($sender);
				$sender->sendMessage("§bprossessing…");
				return true;
				break;
			case "cancel":
				$this->W_cancel();
				return true;
				break;
		}
		return false;
	}

	public function W_cut($selection, $player, $i = -1, $j = 0, $k = 0){
		if(!is_array($selection) or $selection[0] === false or $selection[1] === false or $selection[0][3] !== $selection[1][3]){
			$player->sendMessage("§c Please set the pos. ");
			return;
		}
		if($this->task === false) return $player->sendMessage("§a It has been canceled . ");
		$totalCount = $this->countBlocks($selection);
		$level = $selection[0][3];
		$startX = min($selection[0][0], $selection[1][0]);
		$endX = max($selection[0][0], $selection[1][0]);
		$startY = min($selection[0][1], $selection[1][1]);
		$endY = max($selection[0][1], $selection[1][1]);
		$startZ = min($selection[0][2], $selection[1][2]);
		$endZ = max($selection[0][2], $selection[1][2]);
		$count = $this->countBlocks($selection);
		for($n = 0; $n < $this->tickplace; $n++){
			$x = $startX + $i;
			$y = $startY + $j;
			$z = $startZ + $k;
			$i++;
			$x = $startX + $i;
			if($x > $endX){
				$i = 0;
				$x = $startX + $i;
				$k++;
				$z = $startZ + $k;
				if($z > $endZ){
					$k = 0;
					$z = $startZ + $k;
					$j++;
					$y = $startY + $j;
					if($y > $endY){
						$this->load = false;
						return $player->sendMessage("§a$count It was erased block . ");
					}
				}
			}
			$b = $level->getBlock(new Vector3($x, $y, $z));
			$this->board[] = [$x, $y, $z, $b->getId(), $b->getDamage(), $level];
			$this->count++;
			unset($b);
			$level->setBlock(new Vector3($x, $y, $z), Block::get(0), false);
		}
		$this->getServer()->getScheduler()->scheduleDelayedTask(new Callback([$this, "W_cut"], [$selection, $player, $i, $j, $k]), 1);
	}

	public function W_set($selection, $blocks, $player, $i = -1, $j = 0, $k = 0, $count = 0){
		if(!is_array($selection) or $selection[0] === false or $selection[1] === false or $selection[0][3] !== $selection[1][3]){
			$player->sendMessage("§c Please set the pos. ");
			return;
		}
		if($this->task === false) return $player->sendMessage("§a Please set the pos. ");
		$totalCount = $this->countBlocks($selection);
		$level = $selection[0][3];
		$bcnt = count($blocks) - 1;
		if($bcnt < 0){
			$player->sendMessage("§c Block is no . ");
			return false;
		}
		$startX = min($selection[0][0], $selection[1][0]);
		$endX = max($selection[0][0], $selection[1][0]);
		$startY = min($selection[0][1], $selection[1][1]);
		$endY = max($selection[0][1], $selection[1][1]);
		$startZ = min($selection[0][2], $selection[1][2]);
		$endZ = max($selection[0][2], $selection[1][2]);
		for($n = 0; $n < $this->tickplace; $n++){
			$x = $startX + $i;
			$y = $startY + $j;
			$z = $startZ + $k;
			$i++;
			$x = $startX + $i;
			if($x > $endX){
				$i = 0;
				$x = $startX + $i;
				$k++;
				$z = $startZ + $k;
				if($z > $endZ){
					$k = 0;
					$z = $startZ + $k;
					$j++;
					$y = $startY + $j;
					if($y > $endY){
						$this->load = false;
						return $player->sendMessage("§a$count We put the block . ");
					}
				}
			}
			$b = $level->getBlock(new Vector3($x, $y, $z));
			$this->board[] = [$x, $y, $z, $b->getId(), $b->getDamage(), $level];
			$this->count++;
			$count++;
			$b = $blocks[mt_rand(0, $bcnt)];
			$level->setBlock(new Vector3($x, $y, $z), $b->getBlock(), false);
		}
		$this->getServer()->getScheduler()->scheduleDelayedTask(new Callback([$this, "W_set"], [$selection, $blocks, $player, $i, $j, $k, $count]), 1);
	}

	public function W_replace($selection, Item $block1, $blocks2, $player, $i = -1, $j = 0, $k = 0, $count = 0){
		if(!is_array($selection) or $selection[0] === false or $selection[1] === false or $selection[0][3] !== $selection[1][3]){
			$player->sendMessage("§c Please set the pos. ");
			return;
		}
		if($this->task === false) return $player->sendMessage("§a It has been canceled . ");
		$totalCount = $this->countBlocks($selection);
		$level = $selection[0][3];
		$id1 = $block1->getID();
		$meta1 = $block1->getDamage();
		$bcnt2 = count($blocks2) - 1;
		if($bcnt2 < 0){
			$player->sendMessage("§c Block is no . ");
			return false;
		}
		$startX = min($selection[0][0], $selection[1][0]);
		$endX = max($selection[0][0], $selection[1][0]);
		$startY = min($selection[0][1], $selection[1][1]);
		$endY = max($selection[0][1], $selection[1][1]);
		$startZ = min($selection[0][2], $selection[1][2]);
		$endZ = max($selection[0][2], $selection[1][2]);
		for($n = 0; $n < $this->tickplace; $n++){
			$x = $startX + $i;
			$y = $startY + $j;
			$z = $startZ + $k;
			$i++;
			$x = $startX + $i;
			if($x > $endX){
				$i = 0;
				$x = $startX + $i;
				$k++;
				$z = $startZ + $k;
				if($z > $endZ){
					$k = 0;
					$z = $startZ + $k;
					$j++;
					$y = $startY + $j;
					if($y > $endY){
						$this->load = false;
						return $player->sendMessage("§a$count We put the block . ");
					}
				}
			}
			$b = $level->getBlock(new Vector3($x, $y, $z));
			if($b->getID() === $id1 and ($meta1 === false or $b->getDamage() === $meta1)){
				$level->setBlock($b, $blocks2[mt_rand(0, $bcnt2)]->getBlock(), false);
				$count++;
			}
			$this->board[] = [$x, $y, $z, $b->getId(), $b->getDamage(), $level];
			$this->count++;
			unset($b);
		}
		$this->getServer()->getScheduler()->scheduleDelayedTask(new Callback([$this, "W_replace"], [$selection, $block1, $blocks2, $player, $i, $j, $k, $count]), 1);
	}

	public function W_undo($player, $i = -1){
		if($this->task === false) return $player->sendMessage("§a It has been canceled . ");
		for($n = 0; $n < $this->tickplace; $n++){
			$i++;
			if($i >= $this->count){
				$this->load = false;
				return $player->sendMessage("§a We fix the block . ");
			}
			$x = $this->board[$i][0];
			$y = $this->board[$i][1];
			$z = $this->board[$i][2];
			$id = $this->board[$i][3];
			$meta = $this->board[$i][4];
			$level = $this->board[$i][5];
			$level->setBlock(new Vector3($x, $y, $z), Block::get($id,$meta), false);
		}
		$this->getServer()->getScheduler()->scheduleDelayedTask(new Callback([$this, "W_undo"], [$player, $i]), 1);
	}

	private function W_cancel(){
		$this->task = false;
		$this->load = false;
	}


}

class Callback extends Task{

	public function __construct(callable $callable, array $args = array()){
		$this->callable = $callable;
		$this->args = $args;
		$this->args[] = $this;
	}

	public function getCallable(){
		return $this->callable;
	}

	public function onRun($currentTicks){
		call_user_func_array($this->callable, $this->args);
	}

}
