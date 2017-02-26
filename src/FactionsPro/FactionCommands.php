<?php

namespace FactionsPro;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\PluginTask;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\level\level;
use pocketmine\level\Position;

class FactionCommands {

    public $plugin;

    public function __construct(FactionMain $pg) {
        $this->plugin = $pg;
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
        if ($sender instanceof Player) {
            $player = $sender->getPlayer()->getName();
			$create = $this->plugin->prefs->get("CreateCost");
			$claim = $this->plugin->prefs->get("ClaimCost");
			$oclaim = $this->plugin->prefs->get("OverClaimCost");
			$allyr = $this->plugin->prefs->get("AllyCost");
			$allya = $this->plugin->prefs->get("AllyPrice");
			$home = $this->plugin->prefs->get("SetHomeCost");
            if (strtolower($command->getName('c'))) {
                if (empty($args)) {
                    $sender->sendMessage($this->plugin->formatMessage("§bPlease use §e/c help §bfor a list of commands§r"));
                    return true;
                }
                if (count($args == 2)) {

                    ///////////////////////////////// WAR /////////////////////////////////

                    if ($args[0] == "war") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage: §e/c war <clan name:tp>§r"));
                            return true;
                        }
                        if (strtolower($args[1]) == "tp") {
                            foreach ($this->plugin->wars as $r => $f) {
                                $fac = $this->plugin->getPlayerFaction($player);
                                if ($r == $fac) {
                                    $x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
                                    $tper = $this->plugin->war_players[$f][$x];
                                    $sender->teleport($this->plugin->getServer()->getPlayerByName($tper));
                                    return;
                                }
                                if ($f == $fac) {
                                    $x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
                                    $tper = $this->plugin->war_players[$r][$x];
                                    $sender->teleport($this->plugin->getServer()->getPlayer($tper));
                                    return;
                                }
                            }
                            $sender->sendMessage("§cYou must be in a war to do that§r");
                            return true;
                        }
                        if (!(ctype_alnum($args[1]))) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou may only use letters and numbers§r"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cClan does not exist§r"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this§r"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cOnly your clan leader may start wars§r"));
                            return true;
                        }
                        if (!$this->plugin->areEnemies($this->plugin->getPlayerFaction($player), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan is not an enemy of§a $args[1]§r"));
                            return true;
                        } else {
                            $factionName = $args[1];
                            $sFaction = $this->plugin->getPlayerFaction($player);
                            foreach ($this->plugin->war_req as $r => $f) {
                                if ($r == $args[1] && $f == $sFaction) {
                                    foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
                                        $task = new FactionWar($this->plugin, $r);
                                        $handler = $this->plugin->getServer()->getScheduler()->scheduleDelayedTask($task, 20 * 60 * 2);
                                        $task->setHandler($handler);
                                        $p->sendMessage("§bThe war against§a $factionName §band§a $sFaction §bhas started!§r");
                                        if ($this->plugin->getPlayerFaction($p->getName()) == $sFaction) {
                                            $this->plugin->war_players[$sFaction][] = $p->getName();
                                        }
                                        if ($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
                                            $this->plugin->war_players[$factionName][] = $p->getName();
                                        }
                                    }
                                    $this->plugin->wars[$factionName] = $sFaction;
                                    unset($this->plugin->war_req[strtolower($args[1])]);
                                    return true;
                                }
                            }
                            $this->plugin->war_req[$sFaction] = $factionName;
                            foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
                                if ($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
                                    if ($this->plugin->getLeader($factionName) == $p->getName()) {
                                        $p->sendMessage("§a$sFaction §bwants to start a war,§e '/c war§a $sFaction§e' §bto start!§r");
                                        $sender->sendMessage("§aClan war requested§r");
                                        return true;
                                    }
                                }
                            }
                            $sender->sendMessage("§cClan leader is not online.§r");
                            return true;
                        }
                    }

                    /////////////////////////////// CREATE ///////////////////////////////

                    if ($args[0] == "create") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage: §e/c create <clan name>§r"));
                            return true;
                        }
                        if (!(ctype_alnum($args[1]))) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou may only use letters and numbers§r"));
                            return true;
                        }
                        if ($this->plugin->isNameBanned($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThis name is not allowed§r"));
                            return true;
                        }
                        if ($this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe Clan already exists§r"));
                            return true;
                        }
                        if (strlen($args[1]) > $this->plugin->prefs->get("MaxFactionNameLength")) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThat name is too long, please try again§r"));
                            return true;
                        }
                        if ($this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must leave the clan you're in first§r"));
                            return true;
						              } elseif($r = EconomyAPI::getInstance()->reduceMoney($player, $create)) {
                            $factionName = $args[1];
                            $rank = "Leader";
                            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                            $stmt->bindValue(":player", $player);
                            $stmt->bindValue(":faction", $factionName);
                            $stmt->bindValue(":rank", $rank);
                            $result = $stmt->execute();
                            $this->plugin->updateAllies($factionName);
                            $this->plugin->setFactionPower($factionName, $this->plugin->prefs->get("TheDefaultPowerEveryFactionStartsWith"));
                            $this->plugin->updateTag($sender->getName());
                            $sender->sendMessage($this->plugin->formatMessage("§aClan successfully created§r", true));
                            return true;
                        }
						else {

						switch($r){
							case EconomyAPI::RET_INVALID:

								$sender->sendMessage($this->plugin->formatMessage("§bYou do not have enough Money to create a Clan! Need §6$$create."));
								break;
							case EconomyAPI::RET_CANCELLED:

								$sender->sendMessage($this->plugin->formatMessage("§6-ERROR!"));
								break;
							case EconomyAPI::RET_NO_ACCOUNT:
								$sender->sendMessage($this->plugin->formatMessage("§6-ERROR!"));
								break;
						}
					}
        }

                    /////////////////////////////// INVITE ///////////////////////////////

                    if ($args[0] == "invite") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cUsage: §e/c invite <player>§r"));
                            return true;
                        }
                        if ($this->plugin->isFactionFull($this->plugin->getPlayerFaction($player))) {
                            $sender->sendMessage($this->plugin->formatMessage("§cClan is full, please kick players to make room§r"));
                            return true;
                        }
                        $invited = $this->plugin->getServer()->getPlayerExact($args[1]);
                        if (!($invited instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cPlayer not online§r"));
                            return true;
                        }
                        if ($this->plugin->isInFaction($invited) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("§cPlayer is currently in a clan§r"));
                            return true;
                        }
                        if ($this->plugin->prefs->get("OnlyLeadersAndOfficersCanInvite")) {
                            if (!($this->plugin->isOfficer($player) || $this->plugin->isLeader($player))) {
                                $sender->sendMessage($this->plugin->formatMessage("§cOnly your clan leader/officers can invite§r"));
                                return true;
                            }
                        }
                        if ($invited->getName() == $player) {

                            $sender->sendMessage($this->plugin->formatMessage("§cYou can't invite yourself to your own clan§r"));
                            return true;
                        }

                        $factionName = $this->plugin->getPlayerFaction($player);
                        $invitedName = $invited->getName();
                        $rank = "Member";

                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO confirm (player, faction, invitedby, timestamp) VALUES (:player, :faction, :invitedby, :timestamp);");
                        $stmt->bindValue(":player", $invitedName);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":invitedby", $sender->getName());
                        $stmt->bindValue(":timestamp", time());
                        $result = $stmt->execute();
                        $sender->sendMessage($this->plugin->formatMessage("§b$invitedName §ahas been invited§r", true));
                        $invited->sendMessage($this->plugin->formatMessage("§bYou have been invited to§a $factionName.§b Type§e '/c yes' §bor§e '/c no'§b into chat to accept or deny!§r", true));
                      } else {
							          $sender->sendMessage($this->plugin->formatMessage("§cPlayer is offline§r"));
						          }
					          }

                    /////////////////////////////// LEADER ///////////////////////////////

                    if ($args[0] == "leader") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cUsage:§e /c leader <player>§r"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this§r"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this§r"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cAdd player to clan first§r"));
                            return true;
                        }
                        if (!($this->plugin->getServer()->getPlayerExact($args[1]) instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cPlayer not online§r"));
                            return true;
                        }
                        if ($args[1] == $sender->getName()) {

                            $sender->sendMessage($this->plugin->formatMessage("§cYou can't transfer the leadership to yourself§r"));
                            return true;
                        }
                        $factionName = $this->plugin->getPlayerFaction($player);

                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $player);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Member");
                        $result = $stmt->execute();

                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $args[1]);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Leader");
                        $result = $stmt->execute();


                        $sender->sendMessage($this->plugin->formatMessage("§aYou are no longer leader§r", true));
                        $this->plugin->getServer()->getPlayerExact($args[1])->sendMessage($this->plugin->formatMessage("§bYou are now leader \n§bof§a $factionName!§r", true));
                        $this->plugin->updateTag($sender->getName());
                        $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                    }

                    /////////////////////////////// PROMOTE ///////////////////////////////

                    if ($args[0] == "promote") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage: /c promote <player>§r"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this§r"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this§r"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cPlayer is not in this clan§r"));
                            return true;
                        }
                        if ($args[1] == $sender->getName()) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou can't promote yourself§r"));
                            return true;
                        }

                        if ($this->plugin->isOfficer($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cPlayer is already Officer§r"));
                            return true;
                        }
                        $factionName = $this->plugin->getPlayerFaction($player);
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $args[1]);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Officer");
                        $result = $stmt->execute();
                        $player = $this->plugin->getServer()->getPlayerExact($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("§a$args[1] §bhas been promoted to Officer§r", true));

                        if ($player instanceof Player) {
                            $player->sendMessage($this->plugin->formatMessage("§bYou were promoted to officer of §a$factionName!§r", true));
                            $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                            return true;
                        }
                    }

                    /////////////////////////////// DEMOTE ///////////////////////////////

                    if ($args[0] == "demote") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage:§e /c demote <player>§r"));
                            return true;
                        }
                        if ($this->plugin->isInFaction($sender->getName()) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this§r"));
                            return true;
                        }
                        if ($this->plugin->isLeader($player) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this§r"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cPlayer is not in this clan§r"));
                            return true;
                        }

                        if ($args[1] == $sender->getName()) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou can't demote yourself§r"));
                            return true;
                        }
                        if (!$this->plugin->isOfficer($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cPlayer is already Member§r"));
                            return true;
                        }
                        $factionName = $this->plugin->getPlayerFaction($player);
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $args[1]);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Member");
                        $result = $stmt->execute();
                        $player = $this->plugin->getServer()->getPlayerExact($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("§a$args[1] §bhas been demoted to Member§r", true));
                        if ($player instanceof Player) {
                            $player->sendMessage($this->plugin->formatMessage("§cYou were demoted to member of§a $factionName!§r", true));
                            $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                            return true;
                        }
                    }

                    /////////////////////////////// KICK ///////////////////////////////

                    if ($args[0] == "kick") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage:§e /c kick <player>§r"));
                            return true;
                        }
                        if ($this->plugin->isInFaction($sender->getName()) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this§r"));
                            return true;
                        }
                        if ($this->plugin->isLeader($player) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this§r"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cPlayer is not in this clan§r"));
                            return true;
                        }
                        if ($args[1] == $sender->getName()) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou can't kick yourself§r"));
                            return true;
                        }
                        $kicked = $this->plugin->getServer()->getPlayerExact($args[1]);
                        $factionName = $this->plugin->getPlayerFaction($player);
                        $this->plugin->db->query("DELETE FROM master WHERE player='$args[1]';");
                        $sender->sendMessage($this->plugin->formatMessage("§aYou successfully kicked §b$args[1]§r", true));
                        $this->plugin->subtractFactionPower($factionName, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));

                        if ($kicked instanceof Player) {
                            $kicked->sendMessage($this->plugin->formatMessage("§cYou have been kicked from \n §a$factionName §r", true));
                            $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                            return true;
                        }
                    }

                    /////////////////////////////// INFO ///////////////////////////////

                    if (strtolower($args[0]) == 'info') {
                        if (isset($args[1])) {
                            if (!(ctype_alnum($args[1])) | !($this->plugin->factionExists($args[1]))) {
                                $sender->sendMessage($this->plugin->formatMessage("Clan does not exist"));
                                $sender->sendMessage($this->plugin->formatMessage("Make sure the name of the selected clan is ABSOLUTELY EXACT."));
                                return true;
                            }
                            $faction = $args[1];
                            $result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
                            $array = $result->fetchArray(SQLITE3_ASSOC);
                            $power = $this->plugin->getFactionPower($faction);
                            $message = $array["message"];
							              $leader = $this->plugin->getLeader($faction);
							              $numPlayers = $this->plugin->getNumberOfPlayers($faction);
              							$sender->sendMessage(TextFormat::BOLD . TextFormat::GRAY . "------*+=+*------");
							              $sender->sendMessage(TextFormat::BOLD . TextFormat::GOLD . "»§b $faction §6«");
							              $sender->sendMessage("§6Leader:§3 $leader");
							              $sender->sendMessage("§6Players:§f $numPlayers");
							              $sender->sendMessage("§6Power:§f $power");
							              $sender->sendMessage("§6MOTD:§e $message");
							              $sender->sendMessage(TextFormat::BOLD . TextFormat::GRAY . "------*+=+*------");
						              } else {
                            if (!$this->plugin->isInFaction($player)) {
                                $sender->sendMessage($this->plugin->formatMessage("You must be in a clan to use this!"));
                                return true;
                            }
                            $faction = $this->plugin->getPlayerFaction(($sender->getName()));
                            $result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
                            $array = $result->fetchArray(SQLITE3_ASSOC);
                            $power = $this->plugin->getFactionPower($faction);
                            $message = $array["message"];
							              $leader = $this->plugin->getLeader($faction);
							              $numPlayers = $this->plugin->getNumberOfPlayers($faction);
							              $sender->sendMessage(TextFormat::BOLD . TextFormat::GRAY . "------*+=+*------");
							              $sender->sendMessage(TextFormat::BOLD . TextFormat::GOLD . "»§b $faction §6«");
							              $sender->sendMessage("§6Leader:§3 $leader");
							              $sender->sendMessage("§6Players:§f $numPlayers");
							              $sender->sendMessage("§6Power:§f $power");
							              $sender->sendMessage("§6MOTD:§e $message");
							              $sender->sendMessage(TextFormat::BOLD . TextFormat::GRAY . "------*+=+*------");
						            }
                    }
                    if (strtolower($args[0]) == "help") {
                        if (!isset($args[1]) || $args[1] == 1) {
                            $sender->sendMessage("§f====§bClans Help (1/5)§f====" . TextFormat::WHITE . "\n/c about\n/c yes [Accept a Clan Invite]\n/c overclaim [Takeover the land of the requested clan for $$oclaim]\n/c claim [Clam land for $$claim]\n/c create <name> [Make a clan for $$create]\n/c del\n/c demote <player>\n/c no [Deny a Clan Invite]");
                            return true;
                        }
                        if ($args[1] == 2) {
                            $sender->sendMessage("§f====§bClans Help (2/5)§f====" . TextFormat::WHITE . "\n/c home\n/c help <page>\n/c info\n/c info <clan>\n/c invite <player>\n/c kick <player>\n/c leader <player>\n/c leave");
                            return true;
                        }
                        if ($args[1] == 3) {
                            $sender->sendMessage("§f====§bClans Help (3/5)§f====" . TextFormat::WHITE . "\n/c sethome [Set home for your Clan for $$home]\n/c unclaim\n/c unsethome\n/c members - {Members + Statuses}\n/c officers - {Officers + Statuses}\n/c leader - {Leader + Status}\n/c allies - {The allies of your clan");
                            return true;
                        }
                        if ($args[1] == 4) {
                            $sender->sendMessage("§f====§bClans Help (4/5)§f====" . TextFormat::WHITE . "\n/c motd\n/c promote <player>\n/c ally <clan> [Request for an ally for $$allyr]\n/c unally <clan>\n\n/c allyok [Accept a request for alliance and earn $$allya]\n/c allyno [Deny a request for alliance]\n/c allies <clan> - {The allies of your chosen clan}");
                            return true;
                        }
                        if ($args[1] == 5) {
                            $sender->sendMessage("§f====§bClans Help (5/5)§f====" . TextFormat::WHITE . "\n/c membersof <clan>\n/c officersof <clan>\n/c leaderof <clan>\n/c say <send message to everyone in your clan>\n/c pf <player>\n/c tops");
                            return true;
                        } else {
                            $sender->sendMessage("§f====§bClans Help (OP)§f====" . TextFormat::WHITE . "\n/c forceunclaim <clan> [Unclaim a clan plot by force - OP]\n\n/c forcedelete <clan> [Delete a clan by force - OP]\n\n/c addpowerto <clan> <power> [Add positive/negative power to a clan - OP]");
                            return true;
                        }
                    }
                }
                if (count($args == 1)) {

                    /////////////////////////////// CLAIM ///////////////////////////////

                    if (strtolower($args[0]) == 'claim') {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a clan."));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be leader to use this."));
                            return true;
                        }
                        if (!in_array($sender->getPlayer()->getLevel()->getName(), $this->plugin->prefs->get("ClaimWorlds"))) {
                            $sender->sendMessage($this->plugin->formatMessage("You can only claim in clan Worlds: " . implode(" ", $this->plugin->prefs->get("ClaimWorlds"))));
                            return true;
                        }

                        if ($this->plugin->inOwnPlot($sender)) {
                            $sender->sendMessage($this->plugin->formatMessage("Your clan has already claimed this area."));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($sender->getPlayer()->getName());
                        if ($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")) {

                            $needed_players = $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") -
                                    $this->plugin->getNumberOfPlayers($faction);
                            $sender->sendMessage($this->plugin->formatMessage("You need $needed_players more players in your clan to claim a clan land"));
                            return true;
                        }
                        if ($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")) {
                            $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->plugin->getFactionPower($faction);
                            $sender->sendMessage($this->plugin->formatMessage("Your clan doesn't have enough power to claim a land."));
                            $sender->sendMessage($this->plugin->formatMessage("$needed_power power is required but your clan has only $faction_power power."));
                            return true;
                        }

                        $x = floor($sender->getX());
                        $y = floor($sender->getY());
                        $z = floor($sender->getZ());
                        if ($this->plugin->drawPlot($sender, $faction, $x, $y, $z, $sender->getPlayer()->getLevel(), $this->plugin->prefs->get("PlotSize")) == false) {

                            return true;
                        }

                        $sender->sendMessage($this->plugin->formatMessage("Getting your coordinates...", true));
                        $plot_size = $this->plugin->prefs->get("PlotSize");
                        $faction_power = $this->plugin->getFactionPower($faction);
                        $sender->sendMessage($this->plugin->formatMessage("Your land has been claimed.", true));
                    }
                    if (strtolower($args[0]) == 'pos') {
                        $x = floor($sender->getX());
                        $y = floor($sender->getY());
                        $z = floor($sender->getZ());
                        if (!$this->plugin->isInPlot($sender)) {
                            $sender->sendMessage($this->plugin->formatMessage("This plot is not claimed by anyone. You can claim it by typing /c claim", true));
                            return true;
                        }

                        $fac = $this->plugin->factionFromPoint($x, $z);
                        $power = $this->plugin->getFactionPower($fac);
                        $sender->sendMessage($this->plugin->formatMessage("This plot is claimed by $fac with $power power"));
                    }
                    if(strtolower($args[0]) == 'top') {
						$result = $this->plugin->db->query("SELECT * FROM strength ORDER BY power DESC LIMIT 8;");
						 $i = 1;

						while($row = $result->fetchArray(SQLITE3_ASSOC)){
							if($this->plugin->factionExists($row['faction'])) {
						    $fac = $row['faction'];
							$res = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$fac';");
							$arr = $res->fetchArray(SQLITE3_ASSOC);
							$motd = $arr["message"];
							$lead = $this->plugin->getLeader($row['faction']);
							$num = $this->plugin->getNumberOfPlayers($row['faction']);
							$pow = $this->plugin->getFactionPower($row['faction']);
							$sender->sendMessage(TextFormat::BOLD . $i . ". §6$fac\n§4Leader:§f $lead §ePlayers:§f $num §bPower:§f $pow §5MOTD: §o§f$motd\n");
						    $i++;
						}
						}
					}
                    if (strtolower($args[0]) == 'forcedelete') {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /c forcedelete <clan>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested clan doesn't exist."));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be OP to do this."));
                            return true;
                        }
                        $this->plugin->db->query("DELETE FROM master WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM allies WHERE faction1='$args[1]';");
                        $this->plugin->db->query("DELETE FROM allies WHERE faction2='$args[1]';");
                        $this->plugin->db->query("DELETE FROM strength WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM motd WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM home WHERE faction='$args[1]';");
                        $sender->sendMessage($this->plugin->formatMessage("Unwanted clan was successfully deleted and their clan plot was unclaimed!", true));
                    }
                    if (strtolower($args[0]) == 'addpowerto') {
                        if (!isset($args[1]) or ! isset($args[2])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /c addpowerto <clan> <power>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested clan doesn't exist."));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be OP to do this."));
                            return true;
                        }
                        $this->plugin->addFactionPower($args[1], $args[2]);
                        $sender->sendMessage($this->plugin->formatMessage("Successfully added $args[2] power to $args[1]", true));
                    }
                    if (strtolower($args[0]) == 'pf') {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /c pf <player>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The selected player is not in a clan or doesn't exist."));
                            $sender->sendMessage($this->plugin->formatMessage("Make sure the name of the selected player is ABSOLUTELY EXACT."));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("-$args[1] is in $faction-", true));
                    }

                    if (strtolower($args[0]) == 'overclaim') {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a clan."));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be leader to use this."));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($player);
                        if ($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")) {

                            $needed_players = $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") -
                                    $this->plugin->getNumberOfPlayers($faction);
                            $sender->sendMessage($this->plugin->formatMessage("You need $needed_players more players in your clan to overclaim a clan plot"));
                            return true;
                        }
                        if ($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")) {
                            $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->plugin->getFactionPower($faction);
                            $sender->sendMessage($this->plugin->formatMessage("Your clan doesn't have enough power to claim a land."));
                            $sender->sendMessage($this->plugin->formatMessage("$needed_power power is required but your clan has only $faction_power power."));
                            return true;
                        }
                        $sender->sendMessage($this->plugin->formatMessage("Getting your coordinates...", true));
                        $x = floor($sender->getX());
                        $y = floor($sender->getY());
                        $z = floor($sender->getZ());
                        if ($this->plugin->prefs->get("EnableOverClaim")) {
                            if ($this->plugin->isInPlot($sender)) {
                                $faction_victim = $this->plugin->factionFromPoint($x, $z);
                                $faction_victim_power = $this->plugin->getFactionPower($faction_victim);
                                $faction_ours = $this->plugin->getPlayerFaction($player);
                                $faction_ours_power = $this->plugin->getFactionPower($faction_ours);
                                if ($this->plugin->inOwnPlot($sender)) {
                                    $sender->sendMessage($this->plugin->formatMessage("You can't overclaim your own plot."));
                                    return true;
                                } else {
                                    if ($faction_ours_power < $faction_victim_power) {
                                        $sender->sendMessage($this->plugin->formatMessage("You can't overclaim the plot of $faction_victim because your power is lower than theirs."));
                                        return true
                                    } elseif($r = EconomyAPI::getInstance()->reduceMoney($player, $oclaim))
									   {
                                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_ours';");
                                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_victim';");
                                        $arm = (($this->plugin->prefs->get("PlotSize")) - 1) / 2;
                                        $this->plugin->newPlot($faction_ours, $x + $arm, $z + $arm, $x - $arm, $z - $arm);
                                        $sender->sendMessage($this->plugin->formatMessage("The land of $faction_victim has been claimed. It is now yours.", true));
                                        return true;
                                    }
									else {
						// $r is an error code
						    switch($r){
							case EconomyAPI::RET_INVALID:
								# Invalid $amount
								$sender->sendMessage($this->plugin->formatMessage("§3You do not have enough Money to Overclaim! Need §6$oclaim"));
								break;
							case EconomyAPI::RET_CANCELLED:
								# Transaction was cancelled for some reason :/
								$sender->sendMessage($this->plugin->formatMessage("§6-ERROR!"));
								break;
							case EconomyAPI::RET_NO_ACCOUNT:
								$sender->sendMessage($this->plugin->formatMessage("§6-ERROR!"));
								break;
						}
					}

                                }
                            } else {
                                $sender->sendMessage($this->plugin->formatMessage("§cYou are not in claimed land"));
                                return true;
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cInsufficient permissions"));
                            return true;
                        }

					}


                    /////////////////////////////// UNCLAIM ///////////////////////////////

                    if (strtolower($args[0]) == "unclaim") {
                        if (!$this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a clan"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be leader to use this"));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($sender->getName());
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
                        $sender->sendMessage($this->plugin->formatMessage("Your land has been unclaimed", true));
                    }

                    /////////////////////////////// DESCRIPTION ///////////////////////////////

                    if (strtolower($args[0]) == "motd") {
                        if ($this->plugin->isInFaction($sender->getName()) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a clan to use this!"));
                            return true;
                        }
                        if ($this->plugin->isLeader($player) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be leader to use this"));
                            return true;
                        }
                        $sender->sendMessage($this->plugin->formatMessage("Type your message in chat. It will not be visible to other players", true));
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO motdrcv (player, timestamp) VALUES (:player, :timestamp);");
                        $stmt->bindValue(":player", $sender->getName());
                        $stmt->bindValue(":timestamp", time());
                        $result = $stmt->execute();
                    }

                    /////////////////////////////// ACCEPT ///////////////////////////////

                    if (strtolower($args[0]) == "yes") {
                        $player = $sender->getName();
                        $lowercaseName = ($player);
                        $result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("You have not been invited to any clans"));
                            return true;
                        }
                        $invitedTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $invitedTime) <= 60) { //This should be configurable
                            $faction = $array["faction"];
                            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                            $stmt->bindValue(":player", ($player));
                            $stmt->bindValue(":faction", $faction);
                            $stmt->bindValue(":rank", "Member");
                            $result = $stmt->execute();
                            $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->plugin->formatMessage("You successfully joined $faction", true));
                            $this->plugin->addFactionPower($faction, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
                            $this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$player joined the clan", true));
                            $this->plugin->updateTag($sender->getName());
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("Invite has timed out"));
                            $this->plugin->db->query("DELETE * FROM confirm WHERE player='$player';");
                        }
                    }

                    /////////////////////////////// DENY ///////////////////////////////

                    if (strtolower($args[0]) == "no") {
                        $player = $sender->getName();
                        $lowercaseName = ($player);
                        $result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("You have not been invited to any clans"));
                            return true;
                        }
                        $invitedTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $invitedTime) <= 60) { //This should be configurable
                            $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->plugin->formatMessage("Invite declined", true));
                            $this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$player declined the invitation"));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("Invite has timed out"));
                            $this->plugin->db->query("DELETE * FROM confirm WHERE player='$lowercaseName';");
                        }
                    }

                    /////////////////////////////// DELETE ///////////////////////////////

                    if (strtolower($args[0]) == "del") {
                        if ($this->plugin->isInFaction($player) == true) {
                            if ($this->plugin->isLeader($player)) {
                                $faction = $this->plugin->getPlayerFaction($player);
                                $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
                                $this->plugin->db->query("DELETE FROM master WHERE faction='$faction';");
                                $this->plugin->db->query("DELETE FROM allies WHERE faction1='$faction';");
                                $this->plugin->db->query("DELETE FROM allies WHERE faction2='$faction';");
                                $this->plugin->db->query("DELETE FROM strength WHERE faction='$faction';");
                                $this->plugin->db->query("DELETE FROM motd WHERE faction='$faction';");
                                $this->plugin->db->query("DELETE FROM home WHERE faction='$faction';");
                                $sender->sendMessage($this->plugin->formatMessage("clan successfully disbanded and the clan plot was unclaimed", true));
                                $this->plugin->updateTag($sender->getName());
                            } else {
                                $sender->sendMessage($this->plugin->formatMessage("You are not leader!"));
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("You are not in a clan!"));
                        }
                    }

                    /////////////////////////////// LEAVE ///////////////////////////////

                    if (strtolower($args[0] == "leave")) {
                        if ($this->plugin->isLeader($player) == false) {
                            $remove = $sender->getPlayer()->getNameTag();
                            $faction = $this->plugin->getPlayerFaction($player);
                            $name = $sender->getName();
                            $this->plugin->db->query("DELETE FROM master WHERE player='$name';");
                            $sender->sendMessage($this->plugin->formatMessage("You successfully left $faction", true));

                            $this->plugin->subtractFactionPower($faction, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
                            $this->plugin->updateTag($sender->getName());
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("You must delete the clan or give\nleadership to someone else first"));
                        }
                    }

                    /////////////////////////////// SETHOME ///////////////////////////////

                    if (strtolower($args[0] == "sethome")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a clan to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be leader to set home"));
                            return true;
                        }
						            elseif($r = EconomyAPI::getInstance()->reduceMoney($player, $home)){
                        $factionName = $this->plugin->getPlayerFaction($sender->getName());
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO home (faction, x, y, z) VALUES (:faction, :x, :y, :z);");
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":x", $sender->getX());
                        $stmt->bindValue(":y", $sender->getY());
                        $stmt->bindValue(":z", $sender->getZ());
                        $result = $stmt->execute();
                        $sender->sendMessage($this->plugin->formatMessage("Clan home set", true));}
						else {
						// $r is an error code
						    switch($r){
							case EconomyAPI::RET_INVALID:
								# Invalid $amount
								$sender->sendMessage($this->plugin->formatMessage("§3You do not have enough Money to set Clan Home! Need§6 $$home"));
								break;
							case EconomyAPI::RET_CANCELLED:
								# Transaction was cancelled for some reason :/
								$sender->sendMessage($this->plugin->formatMessage("§6-ERROR!"));
								break;
							case EconomyAPI::RET_NO_ACCOUNT:
								$sender->sendMessage($this->plugin->formatMessage("§6-ERROR!"));
								break;
						}
					}
					}

                    /////////////////////////////// UNSETHOME ///////////////////////////////

                    if (strtolower($args[0] == "unsethome")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a clan to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be leader to unset home"));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($sender->getName());
                        $this->plugin->db->query("DELETE FROM home WHERE faction = '$faction';");
                        $sender->sendMessage($this->plugin->formatMessage("Home unset", true));
                    }

                    /////////////////////////////// HOME ///////////////////////////////

                    if (strtolower($args[0] == "home")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a clan to do this"));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($sender->getName());
                        $result = $this->plugin->db->query("SELECT * FROM home WHERE faction = '$faction';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (!empty($array)) {
                            $sender->getPlayer()->teleport(new Position($array['x'], $array['y'], $array['z'], $this->plugin->getServer()->getLevelByName("Factions")));
                            $sender->sendMessage($this->plugin->formatMessage("Teleported home", true));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("Home is not set"));
                        }
                    }

                    /////////////////////////////// POWER ///////////////////////////////
                    if(strtolower($args[0] == "power")) {
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this"));
                            return true;
						}
                        $faction_power = $this->plugin->getFactionPower($this->plugin->getPlayerFaction($sender->getName()));

                        $sender->sendMessage($this->plugin->formatMessage("§bYour clan has§e $faction_power §bpower",true));
                    }
                    if(strtolower($args[0] == "seepower")) {
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage: §e/c seepower <clan>"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§cClan does not exist"));
                            return true;
						}
                        $faction_power = $this->plugin->getFactionPower($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("§a$args[1] §bhas §e$faction_power §bpower.",true));
                    }

                    /////////////////////////////// MEMBERS/OFFICERS/LEADER AND THEIR STATUSES ///////////////////////////////
                    if (strtolower($args[0] == "members")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a clan to do this"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($player), "Member");
                    }
                    if (strtolower($args[0] == "membersof")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /c membersof <clan>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested clan doesn't exist"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Member");
                    }
                    if (strtolower($args[0] == "officers")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a clan to do this"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($player), "Officer");
                    }
                    if (strtolower($args[0] == "officersof")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /c officersof <clan>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested clan doesn't exist"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Officer");
                    }
                    if (strtolower($args[0] == "leader")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a clan to do this"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($player), "Leader");
                    }
                    if (strtolower($args[0] == "leaderof")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /c leaderof <clan>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested clan doesn't exist"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Leader");
                    }
                    if (strtolower($args[0] == "say")) {
                        if (true) {
                            $sender->sendMessage($this->plugin->formatMessage("/c say is disabled"));
                            return true;
                        }
                        if (!($this->plugin->isInFaction($player))) {

                            $sender->sendMessage($this->plugin->formatMessage("You must be in a clan to send clan messages"));
                            return true;
                        }
                        $r = count($args);
                        $row = array();
                        $rank = "";
                        $f = $this->plugin->getPlayerFaction($player);

                        if ($this->plugin->isOfficer($player)) {
                            $rank = "*";
                        } else if ($this->plugin->isLeader($player)) {
                            $rank = "**";
                        }
                        $message = "-> ";
                        for ($i = 0; $i < $r - 1; $i = $i + 1) {
                            $message = $message . $args[$i + 1] . " ";
                        }
                        $result = $this->plugin->db->query("SELECT * FROM master WHERE faction='$f';");
                        for ($i = 0; $resultArr = $result->fetchArray(SQLITE3_ASSOC); $i = $i + 1) {
                            $row[$i]['player'] = $resultArr['player'];
                            $p = $this->plugin->getServer()->getPlayerExact($row[$i]['player']);
                            if ($p instanceof Player) {
                                $p->sendMessage(TextFormat::ITALIC . TextFormat::RED . "<FM>" . TextFormat::AQUA . " <$rank$f> " . TextFormat::GREEN . "<$player> " . ": " . TextFormat::RESET);
                                $p->sendMessage(TextFormat::ITALIC . TextFormat::DARK_AQUA . $message . TextFormat::RESET);
                            }
                        }
                    }


                    ////////////////////////////// ALLY SYSTEM ////////////////////////////////
                    if (strtolower($args[0] == "enemywith")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /c enemywith <clan>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a clan to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be the leader to do this"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested clan doesn't exist"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) == $args[1]) {
                            $sender->sendMessage($this->plugin->formatMessage("Your clan can not enemy with itself"));
                            return true;
                        }
                        if ($this->plugin->areAllies($this->plugin->getPlayerFaction($player), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Your clan is already enemied with $args[1]"));
                            return true;
                        }
                        $fac = $this->plugin->getPlayerFaction($player);
                        $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));

                        if (!($leader instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("The leader of the requested clan is offline"));
                            return true;
                        }
                        $this->plugin->setEnemies($fac, $args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("You are now enemies with $args[1]!", true));
                        $leader->sendMessage($this->plugin->formatMessage("The leader of $fac has declared your clan as an enemy", true));
                    }
                    if (strtolower($args[0] == "ally")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /c ally <clan>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a clan to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be the leader to do this"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested clan doesn't exist"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) == $args[1]) {
                            $sender->sendMessage($this->plugin->formatMessage("Your clan can not ally with itself"));
                            return true;
                        }
                        if ($this->plugin->areAllies($this->plugin->getPlayerFaction($player), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Your clan is already allied with $args[1]"));
                            return true;
                        }
                        $fac = $this->plugin->getPlayerFaction($player);
                        $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                        $this->plugin->updateAllies($fac);
                        $this->plugin->updateAllies($args[1]);

                        if (!($leader instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("The leader of the requested clan is offline"));
                            return true;
                        }
                        if ($this->plugin->getAlliesCount($args[1]) >= $this->plugin->getAlliesLimit()) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested clan has the maximum amount of allies", false));
                            return true;
                        }
                        if ($this->plugin->getAlliesCount($fac) >= $this->plugin->getAlliesLimit()) {
                            $sender->sendMessage($this->plugin->formatMessage("Your clan has the maximum amount of allies", false));
                            return true;
                        }
                        elseif($r = EconomyAPI::getInstance()->reduceMoney($player, $allyr)){
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO alliance (player, faction, requestedby, timestamp) VALUES (:player, :faction, :requestedby, :timestamp);");
                        $stmt->bindValue(":player", $leader->getName());
                        $stmt->bindValue(":faction", $args[1]);
                        $stmt->bindValue(":requestedby", $sender->getName());
                        $stmt->bindValue(":timestamp", time());
                        $result = $stmt->execute();
                        $sender->sendMessage($this->plugin->formatMessage("You requested to ally with $args[1]!\nWait for the leader's response...", true));
                        $leader->sendMessage($this->plugin->formatMessage("The leader of $fac requested an alliance.\nType /c allyok to accept or /c allyno to deny.", true));
                    }
							else {
						// $r is an error code
						    switch($r){
							case EconomyAPI::RET_INVALID:
								# Invalid $amount
								$sender->sendMessage($this->plugin->formatMessage("§3You do not have enough Money to send a Ally Request! Need §6$$allyr"));
								break;
							case EconomyAPI::RET_CANCELLED:
								# Transaction was cancelled for some reason :/
								$sender->sendMessage($this->plugin->formatMessage("§6-ERROR!"));
								break;
							case EconomyAPI::RET_NO_ACCOUNT:
								$sender->sendMessage($this->plugin->formatMessage("§6-ERROR!"));
								break;
						}
					}
        }
                    if (strtolower($args[0] == "unally")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /c unally <clan>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a clan to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be the leader to do this"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested clan doesn't exist"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) == $args[1]) {
                            $sender->sendMessage($this->plugin->formatMessage("Your clan can not unally with itself"));
                            return true;
                        }
                        if (!$this->plugin->areAllies($this->plugin->getPlayerFaction($player), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Your clan is not allied with $args[1]"));
                            return true;
                        }

                        $fac = $this->plugin->getPlayerFaction($player);
                        $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                        $this->plugin->deleteAllies($fac, $args[1]);
                        $this->plugin->deleteAllies($args[1], $fac);
                        $this->plugin->subtractFactionPower($fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                        $this->plugin->subtractFactionPower($args[1], $this->plugin->prefs->get("PowerGainedPerAlly"));
                        $this->plugin->updateAllies($fac);
                        $this->plugin->updateAllies($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("Your clan $fac is no longer allied with $args[1]", true));
                        if ($leader instanceof Player) {
                            $leader->sendMessage($this->plugin->formatMessage("The leader of $fac broke the alliance with your clan $args[1]", false));
                        }
                    }
                    if (strtolower($args[0] == "forceunclaim")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /c forceunclaim <clan>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested clan doesn't exist"));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be OP to do this."));
                            return true;
                        }
                        $sender->sendMessage($this->plugin->formatMessage("Successfully unclaimed the unwanted plot of $args[1]"));
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
                    }

                    if (strtolower($args[0] == "allies")) {
                        if (!isset($args[1])) {
                            if (!$this->plugin->isInFaction($player)) {
                                $sender->sendMessage($this->plugin->formatMessage("You must be in a clan to do this"));
                                return true;
                            }

                            $this->plugin->updateAllies($this->plugin->getPlayerFaction($player));
                            $this->plugin->getAllAllies($sender, $this->plugin->getPlayerFaction($player));
                        } else {
                            if (!$this->plugin->factionExists($args[1])) {
                                $sender->sendMessage($this->plugin->formatMessage("The requested clan doesn't exist"));
                                return true;
                            }
                            $this->plugin->updateAllies($args[1]);
                            $this->plugin->getAllAllies($sender, $args[1]);
                        }
                    }
                    if (strtolower($args[0] == "allyok")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a clan to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be a leader to do this"));
                            return true;
                        }
                        $lowercaseName = ($player);
                        $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("Your clan has not been requested to ally with any clans"));
                            return true;
                        }
                        $allyTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $allyTime) <= 60) { //This should be configurable
                            if($r = EconomyAPI::getInstance()->addMoney($player, $allya)){
                            $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                            $sender_fac = $this->plugin->getPlayerFaction($player);
                            $this->plugin->setAllies($requested_fac, $sender_fac);
                            $this->plugin->setAllies($sender_fac, $requested_fac);
                            $this->plugin->addFactionPower($sender_fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                            $this->plugin->addFactionPower($requested_fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                            $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                            $this->plugin->updateAllies($requested_fac);
                            $this->plugin->updateAllies($sender_fac);
                            $sender->sendMessage($this->plugin->formatMessage("Your clan has successfully allied with $requested_fac", true));
                            $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$player from $sender_fac has accepted the alliance!", true));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("Request has timed out"));
                            $this->plugin->db->query("DELETE * FROM alliance WHERE player='$lowercaseName';");
                        }
                    }
                    if (strtolower($args[0]) == "allyno") {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a clan to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be a leader to do this"));
                            return true;
                        }
                        $lowercaseName = ($player);
                        $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("Your clan has not been requested to ally with any clans"));
                            return true;
                        }
                        $allyTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $allyTime) <= 60) { //This should be configurable
                            $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                            $sender_fac = $this->plugin->getPlayerFaction($player);
                            $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->plugin->formatMessage("Your clan has successfully declined the alliance request.", true));
                            $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$player from $sender_fac has declined the alliance!"));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("Request has timed out"));
                            $this->plugin->db->query("DELETE * FROM alliance WHERE player='$lowercaseName';");
                        }
                    }


                    /////////////////////////////// ABOUT ///////////////////////////////

                    if (strtolower($args[0] == 'about')) {
                        $sender->sendMessage(TextFormat::GREEN . "FactionsPro v1.3.2 by " . TextFormat::BOLD . "toomanypeople");
                    }
                    ////////////////////////////// CHAT ////////////////////////////////
                    if (strtolower($args[0]) == "chat" or strtolower($args[0]) == "c") {

                        $sender->sendMessage($this->plugin->formatMessage("clan chat disabled", false));
                        return true;

                        if ($this->plugin->isInFaction($player)) {
                            if (isset($this->plugin->factionChatActive[$player])) {
                                unset($this->plugin->factionChatActive[$player]);
                                $sender->sendMessage($this->plugin->formatMessage("clan chat disabled", false));
                                return true;
                            } else {
                                $this->plugin->factionChatActive[$player] = 1;
                                $sender->sendMessage($this->plugin->formatMessage("§aclan chat enabled", false));
                                return true;
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("You are not in a clan"));
                            return true;
                        }
                    }
                    if (strtolower($args[0]) == "allychat" or strtolower($args[0]) == "ac") {

                        $sender->sendMessage($this->plugin->formatMessage("clan chat disabled", false));
                        return true;

                        if ($this->plugin->isInFaction($player)) {
                            if (isset($this->plugin->allyChatActive[$player])) {
                                unset($this->plugin->allyChatActive[$player]);
                                $sender->sendMessage($this->plugin->formatMessage("Ally chat disabled", false));
                                return true;
                            } else {
                                $this->plugin->allyChatActive[$player] = 1;
                                $sender->sendMessage($this->plugin->formatMessage("§aAlly chat enabled", false));
                                return true;
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("You are not in a clan"));
                            return true;
                        }
                    }
                }
            }
        } else {
            $this->plugin->getServer()->getLogger()->info($this->plugin->formatMessage("Please run command in game"));
        }
    }

}
