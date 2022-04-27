#!/usr/bin/php
<?php
/* Created by dukevin (dukevinjduke@gmail.com) 2022 */
/* Requires to be started with SPAWN_SCRIPT so getenv works. +ap required*/
/* Bugs: Some fort mode zones don't spawn like tele zones, spectators stay in $players arr*/
/* New features to add: disco fog mode, highscores for camping, /mix for adding physics to modes, football mode, rework collecting*/

$dir = "/home/duke/aa/servers/sandwich/var/";
$dessertRounds = 10;				//serve dessert (play a minigame) after this many rounds

$enabled_dessert = true;			//enable dessert mode (minigames which play periodically)			
$enabled_shop = true;				//enable the use of the shop and earning of credits
$enabled_shop_non_respawns = true;	//enable buying shop items besides respawns such as /speed
$enabled_killstreaks = true;		//enable killstreaks
$enabled_bounty = true;				//enable bounties every 5 rounds
$enabled_round_message = true;		//enable the round_console_message from script
//disabling everything still allows uers to track their stats and see the ladder highscores

$pink = "0xffa0e0";
$gry = "0xaadeff";
/*** End editable settings ***/

/** Todo: Writing to file should be in PlayerStat destructor **/
$game = GameManager::getInstance();
$game_time = 0;
$go_str = "";
$players = array();
$playerStat = array();
$turbos = array();
$bounty = null;
$time_last = strtotime("00:00:00");

c(basename(__FILE__)." started succesfully");
while(!feof(STDIN))
{
	$line = rtrim(fgets(STDIN));
	$p = explode(" ", $line);
	if($p[0] == "INVALID_COMMAND") //INVALID_COMMAND /missile dukevin@rx 73.134.88.149 -2 fl
	{
		$p[1] = strtolower($p[1]);
		if($p[1] == "/stats" || $p[1] == "/stat" || $p[1] == "/s")
		{
			if(isset($p[5])) {
				$search = closestMatch(implode(" ",array_splice($p, 5)) ,$p[2]);
				printStats($p[2], true, $players[$search], $search);
			}
			else {
				pm($p[2], "0xRESETTHint: You can also search other players with /stats <name>");
				printStats($p[2]);
			}
		}
		else if($p[1] == "/ladder")
		{
			if(isset($p[5]))
				$search = closestMatch(implode(" ",array_splice($p, 5)) ,$p[2]);
			else {
				$search = $p[2];
				pm($p[2], "0xRESETTHint: You can also search other players with /ladder <name>");
			}
			readAndPrintTopLadder($p[2], $search);
		}
		else if($p[1] == "/shop") {
			if(feature_disabled($p[2], $enabled_shop)) continue;
			Shop::view($p[2]);
		}
		else if($p[1] == "/res" || $p[1] == "/r" || $p[1] == "/respawn" || $p[1] == "/rse" || $p[1] == "//res" || $p[1] == "/re" || $p[1] == "/rs")
		{
			if(feature_disabled($p[2], $enabled_shop)) continue;
			$p[1] = "/res";
			if(is_a($game->cur_game, "camping")) {
				pm($name, "You cannot respawn during this mode.");
				continue;
			}
			if(Shop::buy($p[2],$p[1])) 
			{
				$str = "";
				if(!empty($p[5])) {
					$on = $p[5];
					$str = " on '".$on."'";
				}
				else
					$on = $p[2];
				c($players[$p[2]]." {$gry}buys and uses a respawn".$str."!");
				s("RESPAWN ".$on);
			}
		}
		else if($p[1] == "/now") 
		{
			if(feature_disabled($p[2], $enabled_dessert)) continue;
			if($game->roundsTillDessert == 1) {
				pm($p[2], "Next round is already dessert, be patient!");
				continue;
			}
			if(Shop::buy($p[2], $p[1])) {
				c($players[$p[2]]." {$gry}buys early dessert! (Next round is dessert)");
				$game->roundsTillDessert = 1;
			}
		}
		else if($p[1] == "/order") 
		{
			if(feature_disabled($p[2], $enabled_dessert)) continue;
			if(empty($p[5]) || !in_array($p[5], GameManager::$games_list)) 
			{
				if(!empty($p[5]))
					pm($p[2], "0xff8080Error: Invalid minigame name.");
				pm($p[2], "List of available minigames:");
				GameManager::listGames($p[2], "order");
				pm($p[2], "Usage: /order <minigame>   0xRESETT(Press PAGE UP on your keyboard to scroll up)");
				continue;
			}
			if($game->roundsTillDessert == 0) {
				pm($p[2], "You cannot order at this time, wait until next round.");
				continue;
			}
			if(Shop::buy($p[2], $p[1])) {
				$tmp = $game->roundsTillDessert;
				if($tmp <= 0) $tmp = $dessertRounds;
				c($players[$p[2]]." {$gry}orders {$pink}".$p[5]::$display_name."{$gry} for the next dessert! (in ".$tmp." rounds)");
				$game->nextGame = [$p[2], $p[5]];
			}
		}
		else if(($p[1] == "/buffet")) 
		{
			if(feature_disabled($p[2], $enabled_dessert)) continue;
			if(empty($p[5]) || !in_array($p[5], GameManager::$games_list)) 
			{
				if(!empty($p[5]))
					pm($p[2], "0xff8080Error: Invalid minigame name.");
				pm($p[2], "List of available minigames:");
				GameManager::listGames($p[2], "buffet");
				pm($p[2], "Usage: /buffet <minigame>   0xRESETT(Press PAGE UP on your keyboard to scroll up)");
				continue;
			}
			if(Shop::buy($p[2], $p[1])) 
			{
				$game->roundsTillDessert = $dessertRounds;
				c($players[$p[2]]." {$gry}orders all-you-can-eat {$pink}".$p[5]::$display_name."{$gry} for ".$game->roundsTillDessert." rounds!");
				$game->pickGame($p[2], $p[5]);
			}
		}
		else if($p[1] == "/speed")
		{
			if(feature_disabled($p[2], ($enabled_shop_non_respawns && $enabled_shop))) continue;
			if(Shop::buy($p[2], $p[1])) {
				if($turbos[$p[2]] < 1)
					$turbos[$p[2]] += 1;
				else
					$turbos[$p[2]] += 0.5;
				c($players[$p[2]]." {$gry}buys extra speed! (".($turbos[$p[2]]*50).")");
			}
		}
		else if($p[1] == "/tel" || $p[1] == "/t" || $p[1] == "/tele" || $p[1] == "/teleport")
		{
			$p[1] = "/tel"; 
			if(feature_disabled($p[2], ($enabled_shop_non_respawns && $enabled_shop))) continue;
			$name = $p[2];
			if(is_a($game->cur_game, "camping")) {
				pm($name, "You cannot teleport during this mode.");
				continue;
			}
			if($playerStat[$name]->teles <= 0)
			{
				if(!Shop::buy($name, $p[1])) 
					continue;
				$playerStat[$name]->teles = 4;
				pm($name, "You had no teleports left so you bought ".$playerStat[$name]->teles." more.");
				pm($name,"0xRESETTHint: Random tele: {$pink}/tel r0xRESETT, Coords: {$pink}/tel <x> <y>");
			}
			$playerStat[$name]->teles--;
			pm($name, "0xRESETT".$playerStat[$name]->teles." teles remaining.");

			if($p[5] == "r") {
				$cmd = mt_rand(10,490)." ".mt_rand(10,490)." abs";
				c($players[$name]." {$gry}teleports to a random location!");
			}
			else if(is_numeric($p[5]) && is_numeric($p[6]))
			{
				$p[5] = $p[5] > 500 ? 500 : round($p[5]);
				$p[5] = $p[5] < 0 ? 0 : round($p[5]);
				$p[6] = $p[6] > 500 ? 500 : round($p[6]);
				$p[6] = $p[6] < 0 ? 0 : round($p[6]);
				$cmd = $p[5]." ".$p[6]." abs";
				c($players[$name]." {$gry}teleports to (".$p[5].", ".$p[6].")!");
			}
			else {
				$cmd = "30";
				c($players[$name]." {$gry}teleports!");
			}
			s("TELEPORT ".$name." ".$cmd);
		}
		else if($p[1] == "/a" || $p[1] == "/c")
		{
			if(feature_disabled($p[2], $enabled_dessert)) continue;
			$game->cur_game->playercmd($p[2]);
		}
		else if($p[1] == "/play" || $p[1] == "/mode")
		{
			if(feature_disabled($p[2], $enabled_dessert)) continue;
			if(!is_admin($p))
				continue;
			if($game->pickGame($p[2], $p[5], true))
				$game->nextGame = [$p[2], $p[5]];
		}
		else if($p[1] == "/end")
		{
			if(feature_disabled($p[2], $enabled_dessert)) continue;
			if(!is_admin($p))
				continue;
			$game->endGame($p[2]);
		}
		else if($p[1] == "/reshuffle")
		{
			if(feature_disabled($p[2], $enabled_dessert)) continue;
			if(!is_admin($p))
				continue;
			pm($p[2], "Minigames have been reshuffled. Next game is: ".$game->reshuffle());
		}
		else if($p[1] == "/debug")
		{
			if(!is_admin($p))
				continue;
			if(empty($p[5]))
			{
				$tmp = get_class($game->cur_game);
				pm($p[2], "Round: ".$game->roundsPlayed." | roundsTillDessert: ".$game->roundsTillDessert." | Players: ".implode(", ",$players));
				continue;
			}
			if(is_numeric($p[5])) {
				$input = abs(round($p[5]));
				$game->roundsTillDessert = ($input % $dessertRounds);
				c("Admin: Setting dessert rounds left to ".$game->roundsTillDessert);
			}
			if($p[5] == "write") {
				pm($p[2], $p[2].": saving data to file");
				writePlayerToFile($p[2], $playerStat[$p[2]]);
			}
			if($p[5] == "read") {
				if(!array_key_exists($p[2], $playerStat)) {
					pm($p[2], $p[2].": reading save data");
					$playerStat[$p[2]] = readPlayerFromFile($p[2]);
				}
			}
			if($p[5] == "credits") {
				pm($p[2], $p[2]." is cheating!");
				$playerStat[$p[2]]->credits = $p[6];
			}
		}
		else if($p[1] == "/playlist" || $p[1] == "/minigames")
		{
			if(feature_disabled($p[2], $enabled_dessert)) continue;
			if(!is_admin($p))
				continue;
			$game->playlist($p[2]);
		}
		else if($p[1] == "/rounds_served") 
		{
			if(!is_admin($p))
				continue;
			if(empty($p[5]) || !is_numeric($p[5])) {
				pm($p[2], "Dessert is served every ".$dessertRounds." rounds.");
				pm($p[2], "Usage: /rounds_served <amount>");
				continue;
			}
			$p[5] = round(abs($p[5]));
			$dessertRounds = $p[5];
			c("Dessert is now served every ".$dessertRounds." rounds.");
		}
		else if($p[1] == "/features") 
		{
			if(!is_admin($p))
				continue;
			$settings = ["enabled_dessert","enabled_shop","enabled_shop_non_respawns","enabled_killstreaks","enabled_bounty"];
			$enabled = "0x80ff80YES";
			$disabled = "0xff8080NO";
			if(empty($p[5]))
			{
				pm($p[2], "Current features:");
				foreach($settings as $i=>$s)
					pm($p[2], ($i+1).") ".$s." ".($$s ? $enabled : $disabled));
				c("Usage: /features <line #>");
				continue;
			}
			$p[5] -= 1;
			if(!array_key_exists($p[5], $settings)){
				pm($p[2], "Formatting error. Usage: Line number of the setting to toggle");
				continue;
			}
			${$settings[$p[5]]} ^= 1;
			pm($p[2], "Set ".$settings[$p[5]]." to ".(${$settings[$p[5]]} ? $enabled : $disabled));
		}
		else 
		{
			$cmds = "";
			if($enabled_shop) {
				$cmds .= " /shop /res";
				if($enabled_shop_non_respawns) {
					$cmds .= " /speed /tel";
				}
			}
			if($enabled_dessert)
				$cmds .= " /now /order /buffet /a";
			pm($p[2], "Invalid command ".$p[1].", try: 0xffffff/stats /ladder".$cmds);
			if(is_admin($p, false)) {
				if($enabled_dessert)
					$cmd .= "/playlist /play /end /reshuffle /rounds_served ";
				pm($p[2], "0x808080Admin commands: ".$cmd." /features /debug");
			}
		}
	}
	if($p[0] == "GAME_TIME")
	{
		$game_time = $p[1];
		$game->cur_game->timedEvents($p[1]);
		foreach($turbos as $i=>$t)
			s("SET_CYCLE_SPEED $i ".($t*50));
	}
	if(($p[0] == "TARGETZONE_PLAYER_ENTER" || $p[0] == "WINZONE_PLAYER_ENTER") && $enabled_dessert) 
	{   //TARGETZONE_PLAYER_ENTER 2 zonename 100 100 dukevin 90.8824 103.506 1 0 37.6366
		$game->cur_game->targetZoneEnter($p);
	}
	if($p[0] == "ROUND_FINISHED" || $p[0] == "MATCH_ENDED") //if MATCH_ENDED is changed, update GameManager::nextRound()
	{
		$time_now = strtotime($p[2]);
		if(abs($time_now - $time_last) < 12)
			continue;
		$time_last = $time_now;
		foreach($playerStat as $i=>&$ps) {
			$ps->time += $game_time;
			$ps->roundsPlayed++;
		}
		Shop::$evt = "END";
		$game->nextRound($p[0]);
		if(!$enabled_dessert)
			continue;
		if($game->roundsTillDessert == 0) //time for dessert
		{
			if(!empty($game->nextGame)) //play user-selected minigame
				$game->pickGame($game->nextGame[0], $game->nextGame[1]);
			else //play random dessert 
			{
				if(is_a($game->cur_game, "None")) //only play dessert if another one wasn't just playing
				{
					$game->cur_game = new None();
					c("Picking dessert...");
					$game->randomGame();
				}
				else { //play regular
					unset($game->cur_game, $game->nextGame);
					$game->cur_game = new None();
				}
			}
		}
		else if(!is_a($game->cur_game, "None") && $game->roundsTillDessert < 0) //end dessert 
		{
			unset($game->cur_game, $game->nextGame);
			$game->cur_game = new None();
		}
		else
		{
			if(!is_a($game->cur_game, "None")) 
			{
				$tmp = get_class($game->cur_game);
				$str = "";
				if($game->matchEndsGame())
					$str = " or until match winner"; 
				c("0xRESETT".$tmp::$display_name." plays for ".$pink.($game->roundsTillDessert)."0xffffff more rounds".$str);
			}
			else {
				if($game->roundsTillDessert <= -1) $game->roundsTillDessert = $dessertRounds;
				c($pink.($game->roundsTillDessert)."0xffffff more rounds until dessert");
			}
		}
	}
	if($p[0] == "ROUND_COMMENCING") //ROUND_COMMENCING 6 10
	{
		unset($ais, $turbos);
		$ais = array();
		$turbos = array();
		Shop::$evt = $p[0];
	}
	if($p[0] == "ROUND_WINNER")
	{
		if(!array_key_exists($p[2], $players))
			continue;
		$playerStat[$p[2]]->roundsWon++;
		if($bounty && is_a($bounty, "Bounty") && $bounty->target) {
			$bounty->survive();
			$bounty = null;
		}
		Shop::$evt = "END";
	}
	if($p[0] == "ROUND_STARTED")
	{
		Shop::$evt = $p[0];
		if(!is_a($game->cur_game, "None"))
			$game->cur_game->displayInfo();
		else
		{
			if($game->roundsPlayed % 5 == 0 && count($players) >= 3)
			{
				$bounty = new Bounty(count($players));
				continue;
			}
			if(!$enabled_round_message) continue;
			switch(rand(1,4))
			{
				case 1: $blurb = "Open play is encouraged by settings, so try to follow"; break;
				case 2: $blurb = "Rubber depletes faster during backdoors and grinds"; break;
				case 3: $blurb = "Use brakes (v) to help with escapes; no need to run"; break;
				case 4: $blurb = "Winning matches gives you credits to spend by typing 0xffa0d0/shop"; break;
			}
			c("0xffffff# 0xffaa92Be 0xaf5617s0xd39d59a0x86b325n0x789919d0xd12e15w0xa2548ei0xffb830c0xc27938h0xd2883fe0xd39d59d0xRESETT: 0xa0a0a0".$blurb);
		}
		$game->cur_game->roundStart();
	}
	if($p[0] == "PLAYER_ENTERED_GRID" || $p[0] == "PLAYER_RENAMED") //PLAYER_ENTERED_GRID uniquename 73.134.88.149 uniqueName //PLAYER_RENAMED uniquename dukevin@rx 73.134.88.149 1 uniqueName
	{ //BUG: A spectator who joins does not get added to the player array (no +ap event is fired)
		$raw_name = $p[1];
		$display_name = implode(" ",array_splice($p, 3));
		if($p[0] == "PLAYER_RENAMED") 
		{
			if(!array_key_exists($p[1], $players)) //if oldname is not in player array, we can assume they renamed as a spectator who entered as one
				continue;
			if(is_auth($p[2])) //a user logged in - handle it in PLAYER_LOGIN event
				continue;
			$raw_name = $p[2];
			if($p[4] == 0) //user is not logged in 
				pm($raw_name, "You are not logged in so renaming caused you to lose your stats since they are tied to your display name instead.");
			$display_name = implode(" ",array_splice($p, 5));
			if(array_key_exists($p[1], $players)) //only write if player exists and...
			{
				if($p[4] == 1 || $playerStat[$p[1]]->time > 600) //only write logged in with 600+
					writePlayerToFile($p[1], $playerStat[$p[1]]);
			}
			unset($players[$p[1]]);
			unset($playerStat[$p[1]]);
		}
		$players[$raw_name] = "";
		$playerStat[$raw_name] = readPlayerFromFile($raw_name);
		printStats($raw_name, false, $display_name);
	}
	if($p[0] == "PLAYER_LOGIN") //PLAYER_LOGIN dog_water dukevin@rx
	{
		$playerStat[$p[2]] = readPlayerFromFile($p[2]);
		if(strlen($players[$p[2]]) == 0)
			$players[$p[2]] = $p[1];
		if(strlen($players[$p[1]]) == 0)
			$players[$p[1]] = $p[1];
		printStats($p[2], false, $players[$p[1]]);
		unset($players[$p[1]]); 
		unset($playerStat[$p[1]]);
	}
	if($p[0] == "PLAYER_AI_ENTERED")
		$ais[] = $p[1];
	if($p[0] == "PLAYER_COLORED_NAME")
	{
		if(!array_key_exists($p[1], $players)) //only add colored name if the person is in the players array
			continue;
		$players[$p[1]] = implode(" ",array_splice($p,2));
	}
	if($p[0] == "PLAYER_LEFT")
	{
		if(!array_key_exists($p[1], $playerStat))
			continue;
		$playerStat[$p[1]]->time += $game_time;
		if(!is_auth($p[1]) && $playerStat[$p[1]]->time < 600 || !array_key_exists($p[1], $playerStat)) 
		{ 
			if(!array_key_exists($p[1], $playerStat))
				c("Not saving data for ".$p[1]);
			else
				c("Not saving data for ".$p[1]." (not logged in and played less than 10 mins)");
		}
		else {
			printStats($p[1], false, $players[$p[1]]);
			writePlayerToFile($p[1], $playerStat[$p[1]]);
		}
		unset($playerStat[$p[1]]);
		unset($players[$p[1]]);
	}
	if($p[0] == "MATCH_WINNER") //MATCH_WINNER uniquename dukevin@rx MATCH_WINNER bananas hardleft stephen
	{
		$winners = array_splice($p, 2);		
		foreach($players as $i=>$_)
		{
			$playerStat[$i]->matchesPlayed++;
			if(in_array($i, $winners))
			{
				$reason = count($winners) == 1 ? "winning!" : "being on the winning team!";
				if($enabled_shop) c("0xffffff# ".$players[$i]."0x00ffff earns {$pink}\$5 0x00ffffcredits for ".$reason);
				$playerStat[$i]->credits += 5;
				$playerStat[$i]->matchesWon++;
				$playerStat[$i]->roundsWon++;
			}
			else
			{
				if($enabled_shop) pm($i, "0xRESETTYou earned {$pink}\$1 0xRESETTcredit for participating. Spend credits by typing {$gry}/shop");
				$playerStat[$i]->credits += 1;
			}
		}
		sleep(2);
		foreach($players as $i=>$_)
			readAndPrintTopLadder($i, $i);
		sleep(3);
		foreach($players as $i=>$_)
			printStats($i);
	}
	if(preg_match("/^DEATH_FRAG|DEATH_ZOMBIEZONE|DEATH_SHOT_FRAG|DEATH_DEATHZONE|DEATH_SHOT_SUICIDE|DEATH_RUBBERZONE|DEATH_SUICIDE/", $line))
	{
		if(!array_key_exists($p[1], $playerStat))
			continue;
		$game->cur_game->playerDied($p[1]);
		if($p[0] == "DEATH_SUICIDE") continue;

		$playerStat[$p[1]]->deaths += 1;
		if(!empty($p[2]))
			if(array_key_exists($p[2], $playerStat))
			{
				killStreak($p[1], $p[2]);
				$playerStat[$p[2]]->kills += 1;
				if($bounty && is_a($bounty,"Bounty") && $bounty->target == $p[1]) {
					$bounty->award($p[2]);
					$bounty = null;
				}
			}
	}
}

function killStreak($victim, $killer)
{
	global $players, $pink, $gry, $playerStat, $enabled_killstreaks, $enabled_shop;
	static $killstreak = [];
	if(count($players) <= 2 || !$enabled_killstreaks) //killsreaks not active for 2 players
		return;
	$killstreak[$killer][] = $victim;
	if(@count($killstreak[$victim]) >= 5)
		c($players[$killer]."{$gry} ended ".$players[$victim]."{$gry}'s killstreak of 0xffffff".count($killstreak[$victim])."{$gry}!");
	unset($killstreak[$victim]);
	$numKills = count($killstreak[$killer]);
	if($numKills == 5)
	{
		c($players[$killer]."{$gry} is on a killing spree! (5 kills)");
		if($enabled_shop) pm($killer, "  Killing spree = {$pink}\$2");
		$playerStat[$killer]->credits += 2;
	}
	else if($numKills == 10)
	{
		c($players[$killer]."{$gry} is on a killing FRENZY! (10 kills)");
		if($enabled_shop) pm($killer, "  Killing frenzy = {$pink}\$5");
		$playerStat[$killer]->credits += 5;
	}
	else if($numKills == 15)
	{
		c($players[$killer]."{$gry} is on a RUNNING RIOT! (15 kills)");
		if($enabled_shop) pm($killer, "  Running Riot = {$pink}\$8");
		$playerStat[$killer]->credits += 8;
	}
	else if($numKills == 20)
	{
		c($players[$killer]."{$gry} is on a RAMPAGE! (20 kills)");
		if($enabled_shop) pm($killer, "  Rampage = {$pink}\$13");
		$playerStat[$killer]->credits += 13;
	}
	else if($numKills == 25)
	{
		c($players[$killer]."{$gry} is UNTOUCHABLE! (25 kills)");
		if($enabled_shop) pm($killer, "  Untouchable = {$pink}\$15");
		$playerStat[$killer]->credits += 15;
	}
	else if($numKills >= 30 && $numKills % 5 == 0)
	{
		c($players[$killer]."{$gry} is INVINCIBLE! (".$numKills." kills)");
		if($enabled_shop) pm($killer, "  Invincible = {$pink}\$20");
		$playerStat[$killer]->credits += 20;
	}
}

function writePlayerToFile($player, $playerStat) 
{
	global $dir;
	$fname = $dir."savedata.txt";
	$contents = file_get_contents($fname);
	if($contents === false) {
		c("WARNING: Save data not found.");
		fopen($fname ,'w');
		writePlayerToFile($player, $playerStat);
	}
	if(!is_a($playerStat, "PlayerStat")) {
		c("Not saving corrupt savedata for $player");
		return;
	}
	$lines = explode("\n", $contents);
	foreach($lines as $i=>&$line)
	{
		$cols = explode(" ", $line);
		if($cols[0] == $player)
		{
			$cols[1] = serialize($playerStat);
			$lines[$i] = implode(" ", $cols);
			file_put_contents($fname, implode("\n", $lines));
			return;
		}
	}
	file_put_contents($fname, $player." ".serialize($playerStat)."\n", FILE_APPEND);
}
function readPlayerFromFile($player)
{
	global $dir;
	$fname = $dir."savedata.txt";
	$file = file($fname);
	if($file === false) {
		c("WARNING: Save data file does not exist.");
		fopen($fname ,'w');
		return new PlayerStat();
	};
	foreach($file as $line)
	{
		$col = explode(" ",$line);
		if($player == $col[0])
		{
			$ret = unserialize($col[1]);
			//can't use is_a() since updating the PlayerStat class will always trigger this
			if(!property_exists($ret, "time")) { 
				$ret = new PlayerStat();
				c("Resetting corrupt savedata for $player");
			}
			return $ret;
		}
	}
	return new PlayerStat();
}
class PlayerStat
{
	public function __construct() 
	{
		$this->credits = 0;
		$this->kills = 0;
		$this->deaths = 0;
		$this->time = 0;
		$this->roundsPlayed = 0;
		$this->roundsWon = 0;
		$this->matchesPlayed = 0;
		$this->matchesWon = 0;
		$this->teles = 0;
	}
	public $credits;
	public $kills;
	public $deaths;
	public $roundsPlayed;
	public $matchesPlayed;
	public $matchesWon;
	public $time;
	public $teles;
	public function __destruct() {} //one day, writePlayerToFile will be in the destructor so crashes still write
}
class Shop
{
	static $wares = [
		'/res' => ['name'=>"Respawns", 'cost'=>1, 'description'=>"Respawn any time on command", 'command'=>"/res", 'type'=>"res"],
		'/speed' => ['name'=>"Speed", 'cost'=>1,  'description'=>"Your speed is doubled for the round", 'command'=>"/speed", 'type'=>"nonres"],
		'/tel' => ['name'=>"Teleport", 'cost'=>1, 'description'=>"Teleport yourself. Contains 4 per purchase", 'command'=>"/tel", 'type'=>"nonres"],
		'/now' => ['name'=>"Dessert Now", 'cost'=>3, 'description'=>"Go straight into the dessert round", 'command'=>"/now", 'type'=>"dessert"],
		'/order'=>['name'=>"Special Order", 'cost'=>4, 'description'=>"Choose the next dessert minigame", 'command'=>"/order", 'type'=>"dessert"],
		'/buffet'=>['name'=>"Buffet", 'cost'=>9, 'description'=>"Choose a dessert lasting for 10 rounds", 'command'=>"/buffet", 'type'=>"dessert"]
	];
	static $header = " Shop ";
	static $evt = "";
	static function view($p)
	{
		global $playerStat, $enabled_shop_non_respawns, $enabled_dessert;
		$footer = " You have \$".$playerStat[$p]->credits." ";
		$len = 56-strlen(Shop::$header);
		$len2 = 56-strlen($footer);
		pm($p,"0xffffff".str_repeat("-",$len/2).Shop::$header.str_repeat("-",$len/2));
		foreach(Shop::$wares as $i =>$item) 
		{
			if(!$enabled_shop_non_respawns && $item['type'] == "nonres")
				continue;
			if(!$enabled_dessert && $item['type'] == "dessert")
				continue;
			pm($p, $item['command']."0x808080".str_repeat(".", 9-strlen($item['command']))."0x50a0ff".$item['name']."0x808080".str_repeat(".", 15-strlen($item['name']))."0xffff00\$".$item['cost']."0x808080".str_repeat(".",5).$item['description']);
		}
		pm($p,"0xffffff".str_repeat("-",$len2/2).$footer.str_repeat("-",$len2/2));
	}
	static function buy($p, $cmd)
	{
		global $playerStat, $enabled_shop_non_respawns, $enabled_dessert;
		$ps = $playerStat[$p];
		if(!array_key_exists($cmd, Shop::$wares))
			return false;
		$cost = Shop::$wares[$cmd]['cost'];
		if($ps->credits < $cost) {
			if(!isset($ps->credits)) {
				pm($p, "Your stats are not loaded yet, possibly due to just logging in.");
				return false;
			}
			pm($p, "You don't have enough credits. $cmd costs \$".$cost." you only have $".$ps->credits);
			return false;
		}
		$game = GameManager::getInstance();
		$mode = $game->cur_game;
		if(Shop::$evt == "END")
		{
			pm($p, "The round has already ended.");
			return false;
		}
		if(($cmd == "/speed" || $cmd == "/tel") && (is_a($mode,"htf") || is_a($mode,"ctf") || is_a($mode,"reflex") || is_a($mode,"fort") || is_a($mode,"camping")))
		{
			pm($p, "You cannot purchase ".$cmd." during ".$mode::$display_name);
			return false;
		}
		$playerStat[$p]->credits -= $cost;
		$str = $cost == 1 ? "" : "s";
		pm($p, "You spent \$".$cost." credit".$str." and have \$".$playerStat[$p]->credits." left.");
		return true;
	}
}
class Bounty 
{
	public $amount;
	public $target;
	function __construct($num)
	{
		global $players, $enabled_bounty;
		if(!$enabled_bounty)
			return;
		if($num <= 3)
			$bounty = array_rand(array_flip([1, 2]));
		else if($num == 4)
			$bounty = array_rand(array_flip([2, 2, 3]));
		else if($num == 5)
			$bounty = array_rand(array_flip([3, 3, 4]));
		else if($num == 6)
			$bounty = array_rand(array_flip([3,4,4,5,5]));
		else if($num > 6)
			$bounty = 5;
		$this->amount = $bounty;
		$this->target = array_rand($players);
		$this->announce();
	}
	function announce()
	{
		global $pink, $players, $gry, $enabled_shop;
		if($this->amount == 0)
			$this->amount = 1;
		$plur = $this->amount == 1 ? "" : "s";
		$award = $enabled_shop ? "credit" : "point";
		$prefx = $enabled_shop ? "\$" : "";
		c("WANTED! There is a bounty on ".$players[$this->target].$gry." for ".$pink.$prefx.$this->amount.$gry." ".$award.$plur."!");
		pm($this->target, "Survive this bounty for ".$pink.$prefx.$this->amount.$gry." ".$award.$plur);
	}
	function award($killer)
	{
		global $players, $playerStat, $pink, $gry, $enabled_shop;
		$award = $enabled_shop ? "" : "point";
		$prefx = $enabled_shop ? "\$" : "";
		$playerStat[$killer]->credits += $this->amount;
		c($players[$killer]."{$gry} claims the {$pink}".$prefx.$this->amount."{$gry} ".$award." bounty on ".$players[$this->target]."{$gry}'s head!");
		$this->target = $this->amount = 0;
		if(!$enabled_shop) s("ADD_SCORE_PLAYER ".$players[$killer]." ".$this->amount);
	}
	function survive()
	{
		global $players, $playerStat, $pink, $gry, $enabled_shop;
		$playerStat[$this->target]->credits += $this->amount;
		if(!$enabled_shop) s("ADD_SCORE_PLAYER ".$players[$this->target]." ".$this->amount);
		$award = $enabled_shop ? "" : "point";
		$prefx = $enabled_shop ? "\$" : "";
		c($players[$this->target]."{$gry} survives the bounty earning ".$pink.$prefx.$this->amount.$gry." ".$award.$plur."!");
		$this->target = $this->amount = 0;
	}
}

function printStats($player, $pm = true, $display_name = "", $search = null)
{
	global $playerStat, $enabled_shop;
	if(!isset($search))
		$search = $player;
	if(!array_key_exists($search, $playerStat)) {
		c($search." stats not loaded");
		return;
	}
	$wht = "0xRESETT";
	$gry = "0xa0a0f0";
	$scores = readLadder($search);
	$ps = $playerStat[$search];
	$subject = $pm && $search == $player ? "Your" : $display_name."{$gry}'s";
	$credits_str = $enabled_shop ? ", Credits: {$wht}\$".$ps->credits : "";
	$str1 = $gry.$subject." Overall Rank: {$wht}".$scores[0]." of ".$scores[3]."{$gry}, K/D: {$wht}".round($ps->kills/$ps->deaths,2)."{$gry}, Kills: {$wht}".$ps->kills."{$gry}".$credits_str;
	$str2 = $gry."  Time played: {$wht}".round($ps->time/3600,1)." hrs{$gry}, Round wins: {$wht}".$scores[1]." (".round(($ps->roundsWon/$ps->roundsPlayed)*100)."%){$gry}, Match wins: {$wht}".$scores[2]." (".round(($ps->matchesWon/$ps->matchesPlayed)*100)."%)";
	if($pm) {
		pm($player, $str1);
		pm($player, $str2);
	}
	else {
		c($str1);
		c($str2);
	}
}

class GameManager
{
	private static $instance = null;
	public $cur_game;
	public static $games_list = ["shooting", "map", "axes", "sumo", "fort", "wildfort", "nano", "htf", "koh", "dz", "collecting", "turbo", "pets", "teams", "ctf", "macro", "bots", "longwall", "bone", "classic", "bombs", "reflex", "dodgeball", "camping"];
	public static $match_ends_games = ["ctf", "htf", "fort"]; //games which end when the match does instead of 10 rounds
	private $games_available;
	public $roundsPlayed;
	public $roundsTillDessert;
	public $nextGame;
	public function __construct()
	{
		global $dessertRounds;
		$this->roundsPlayed = 0;
		$this->roundsTillDessert = $dessertRounds;
		$this->cur_game = new None();
		$this->games_available = GameManager::$games_list;
		shuffle($this->games_available);
	}
	public function nextRound($evt)
	{
		global $dessertRounds;
		$this->cur_game->roundEnd();
		$this->roundsPlayed++;

		if($evt == "MATCH_ENDED" && $this->matchEndsGame())
			$this->endGame();
		if($this->roundsTillDessert < 0)
			$this->roundsTillDessert = $dessertRounds-1;
		else
			$this->roundsTillDessert--;
	}
	public function matchEndsGame()
	{
		return in_array(strtolower(get_class($this->cur_game)), GameManager::$match_ends_games) ? true : false;
	}
	public function reshuffle()
	{
		$this->games_available = GameManager::$games_list;
		shuffle($this->games_available);
		return $this->games_available[0];
	}
	public static function getInstance()
	{
		if(GameManager::$instance == null)
			GameManager::$instance = new GameManager();
		return GameManager::$instance;
	}
	public function randomGame()
	{
		if(empty($this->games_available))
		{
			$this->games_available = GameManager::$games_list;
			shuffle($this->games_available);
		}
		unset($this->cur_game);
		$this->rouletteAndSet(array_shift($this->games_available));
	}
	public function rouletteAndSet($game)
	{
		global $pink;
		$tmp = GameManager::$games_list;
		shuffle($tmp);
		s("WAIT_FOR_EXTERNAL_SCRIPT_TIMEOUT 4");
		foreach($tmp as $t)
		{
			s("CENTER_MESSAGE ".$t::$display_name);
			usleep(100000);
		}
		s("CENTER_MESSAGE ".$game::$display_name);
		$this->cur_game = new $game();
		$this->cur_game->displayInfo();
	}
	public function pickGame($player, $str, $as_admin = false)
	{
		global $gry;
		$str = strtolower($str);
		if(in_array($str, GameManager::$games_list) && class_exists($str))
		{
			if($as_admin)
				c("By order of 0xffffff".$player."{$gry}: The mode is set to ".$str::$display_name."");
			unset($this->cur_game);
			s("WAIT_FOR_EXTERNAL_SCRIPT_TIMEOUT 4");
			s("CENTER_MESSAGE ".$str::$display_name);
			$this->cur_game = new $str();
		}
		else 
		{
			if(empty($str)) 
			{
				if(empty($player))
					c("ERROR: The script failed switching modes");
				pm($player, "Usage: /play <gametype>");
				return false;
			}
			c("Error: '".$str."' is not a valid minigame, available are: ".implode(", ",GameManager::$games_list));
			return false;
		}
		return true;
	}
	public function endGame($player = null)
	{
		global $gry, $dessertRounds;
		$game = get_class($this->cur_game);
		if($player != null && $this->cur_game != null)
			c("By order of 0xffffff".$player."{$gry}: The mode ".$game::$display_name." has ended");
		$this->roundsTillDessert = $dessertRounds;
		unset($this->cur_game);
		$this->cur_game = new None();
	}
	public function playlist($player)
	{
		global $pink, $gry;
		foreach($this->games_available as $i=>$g)
		{
			if($i==0)
				pm($player, $pink." | 0xffffffNext > {$gry}".$g);
			else
				pm($player, $pink." | 0xffffff".($i+1).". {$gry}".$g);
		}
		foreach(GameManager::$games_list as $g)
		{
			if(!in_array($g, $this->games_available))
				pm($player, $pink." | 0x808080Done - 0xa0a0a0".$g);
		}
	}
	public static function listGames($player, $cmd = null)
	{
		global $gry;
		$width = 84; //width of screen in chars
		if($cmd)
			$cmd = "0xffffff/".$cmd;
		foreach(GameManager::$games_list as $g)
		{
			$pre = "$cmd $g ";
			$sub = str_repeat(" ",27-strlen($pre)).$gry." : ".$g::$display_name;
			$desc = explode("0x", $g::$description)[0];
			$end = "  (".trim($desc);
			if(strlen($pre.$sub.$end) >= $width)
				$end = substr($end,0,$width-strlen($pre.$sub.$end))."...)";
			else
				$end .= ")";
			pm($player, $pre."0xffffff".$sub."0x808080".$end." ");
			usleep(10000);
		}
		usleep(100000);
	}
}

abstract class Minigame
{
	abstract public function __construct();			//minigame settings
	abstract public function __destruct();			//undo minigame settings for regular play
	public function timedEvents($time) {}			//events that require time during the minigame
	public function targetZoneEnter($arr) {}		//ladderlog event when a player hits a targetzone as an array
	public function roundStart() {}					//triggered when the ROUND_COMMENCING event happens, good time to spawn zones
	public function roundEnd() {}					//triggered when ROUND_FINIED|MATCH_ENDED, good time to pick new randoms for a buffet
	public function playercmd($player) {pm($player, "This mode has no special action you can perform.");} //player typed /a
	public function playerDied($player) {} 			//triggered when a player died
	public function displayInfo() {
		global $pink;
		c($pink."# DESSERT MINIGAME: 0xffff00".static::$display_name.$pink." :: 0xffffff".static::$description);
	}
}

class None extends Minigame
{
	static $display_name = "Normal";
	static $description = "Normal play";
	function __construct()
	{	//just some essential settings in case something doesn't get set back right
		s("SCORE_KILL 2");
		s("ARENA_AXES 4");
		s("SIZE_FACTOR -3");
		s("WAIT_FOR_EXTERNAL_SCRIPT_TIMEOUT 0");
		s("MAP_FILE Anonymous/polygon/regular/square-1.0.1.aamap.xml");
	}
	function __destruct()
	{
		s("WIN_ZONE_MIN_ROUND_TIME 600");
	}
}
class Shooting extends Minigame
{
	static $display_name = "Shooting";
	static $description = "Hold down brakes (v) to fire a shot, or a full tank for a megashot";
	function __construct()
	{
		s("INCLUDE shooting.cfg");
	}
	function __destruct()
	{
		unload("shooting.cfg");
	}
}
class Dz extends Minigame
{
	static $display_name = "Death Zone Aversion";
	static $description = "Avoid the deathzones 0x808080(-2 pts for dz)";
	function __construct()
	{
		s("SCORE_DEATHZONE -2");
	}
	function __destruct()
	{
		undo("SCORE_DEATHZONE");
	}
	function timedEvents($time)
	{
		if($time % 2 == 0)
			s("SPAWN_ZONE death ".$this->r()." ".$this->r()." 1 3 0 0 false 255 0 0 ".mt_rand(3,35). " 1");
		if($time % 60 == 0 && $time != 0)
			s("SPAWN_ZONE target ".$this->r()." ".$this->r()." 1 0 0 0 false 0 255 0 0 1");
	}
	function r()
	{
		if(rand(1,2) == 1)
			return mt_rand(10, 490);
		return rand(10, 490);
	}
}
class Axes extends Minigame
{
	static $display_name = "Axes";
	static $description = "The axes have changed";
	public $roundsPlayed = -1;
	function __construct()
	{
		$rand = mt_rand(3,7);
		if($rand == 4)
			$rand = 8;
		s("ARENA_AXES ".$rand);
		Axes::$description = "The axes have changed to ".$rand;
	}
	function roundEnd()
	{
		$this->roundsPlayed++;
		if($this->roundsPlayed <= 0)
			return;
		$this->__construct(); //pick new axes for buffets
	}
	function __destruct()
	{
		Axes::$description = "The axes have changed";
		s("ARENA_AXES 4");
	}
}
class Collecting extends Minigame
{
	static $display_name = "Coin Collecting";
	static $description = "Collect the moving zones for points 0x808080(5 coins = 1 pt)";
	private $coins;
	function __construct() 
	{
		global $players;
		$coins = array_fill_keys($players, 0);
		s("TARGET_INITIAL_SCORE 0");
	}
	function __destruct() 
	{
		undo("TARGET_INITIAL_SCORE");
	}
	function timedEvents($time)
	{
		if($time % 30 == 0)
		{
			$speed = mt_rand(10,60);
			if(mt_rand(1,2) == 1)
			{
				s("SPAWN_ZONE n coin target 250 250 3 0 ".$speed." ".($speed/2)." true 255 255 0 0 1");
				s("SPAWN_ZONE n coin target 250 250 3 0 ".$speed." ".$speed." true 255 255 0 0 1");
				s("SPAWN_ZONE n coin target 250 250 3 0 ".($speed/2)." ".$speed." true 255 255 0 0 1");
				s("SPAWN_ZONE n coin target 250 250 3 0 -".$speed." ".$speed." true 255 255 0 0 1");
				s("SPAWN_ZONE n coin target 250 250 3 0 -".$speed." ".($speed/2)." true 255 255 0 0 1");
				s("SPAWN_ZONE n coin target 250 250 3 0 -".$speed." -".$speed." true 255 255 0 0 1");
				s("SPAWN_ZONE n coin target 250 250 3 0 ".($speed/2)." -".$speed." true 255 255 0 0 1");
				s("SPAWN_ZONE n coin target 250 250 3 0 ".$speed." -".$speed." true 255 255 0 0 1");
			}
			else
			{
				s("SPAWN_ZONE n coin target 50 10 3 0 0 ".$speed." true 255 255 0 0 1");
				s("SPAWN_ZONE n coin target 100 500 3 0 0 ".-$speed." true 255 255 0 0 1");
				s("SPAWN_ZONE n coin target 150 10 3 0 0 ".$speed." true 255 255 0 0 1");
				s("SPAWN_ZONE n coin target 200 500 3 0 0 ".-$speed." true 255 255 0 0 1");
				s("SPAWN_ZONE n coin target 250 10 3 0 0 ".$speed." true 255 255 0 0 1");
				s("SPAWN_ZONE n coin target 300 500 3 0 0 ".-$speed." true 255 255 0 0 1");
				s("SPAWN_ZONE n coin target 350 10 3 0 0 ".$speed." true 255 255 0 0 1");
				s("SPAWN_ZONE n coin target 400 500 3 0 0 ".-$speed." true 255 255 0 0 1");
				s("SPAWN_ZONE n coin target 450 10 3 0 0 ".$speed." true 255 255 0 0 1");
			}
		}
		if($time % 5 == 0 && $time != 0)
		{
			$speed = mt_rand(-100,100);
			s("SPAWN_ZONE n coin target ".mt_rand(10,490)." ".mt_rand(10,490)." 3 0 ".$speed." ".$speed." true 255 255 0 0 1");
		}
		if($time % 45 == 0 && $time != 0)
		{
			$speed = mt_rand(10,60);
			s("SPAWN_ZONE n coin target L 256 316 298 298 316 256 298 214 256 196 214 214 196 256 214 298 256 376 340 340 376 256 340 172 256 136 172 172 136 256 172 340 256 436 383 383 436 256 383 129 256 76 129 129 76 256 129 383 256 496 425 425 496 256 425 87 256 16 87 87 16 256 87 425 500 500 500 0 0 0 0 500 0 500 Z 3 0 $speed $speed true 255 255 0 0 1");
			s("SPAWN_ZONE n coin target L 298 298 316 256 298 214 256 196 214 214 196 256 214 298 256 376 340 340 376 256 340 172 256 136 172 172 136 256 172 340 256 436 383 383 436 256 383 129 256 76 129 129 76 256 129 383 256 496 425 425 496 256 425 87 256 16 87 87 16 256 87 425 500 500 500 0 0 0 0 500 0 500 Z 3 0 $speed $speed true 255 255 0 0 1");
			s("SPAWN_ZONE n coin target L 316 256 298 214 256 196 214 214 196 256 214 298 256 376 340 340 376 256 340 172 256 136 172 172 136 256 172 340 256 436 383 383 436 256 383 129 256 76 129 129 76 256 129 383 256 496 425 425 496 256 425 87 256 16 87 87 16 256 87 425 500 500 500 0 0 0 0 500 0 500 Z 3 0 $speed $speed true 255 255 0 0 1");
			s("SPAWN_ZONE n coin target L 298 214 256 196 214 214 196 256 214 298 256 376 340 340 376 256 340 172 256 136 172 172 136 256 172 340 256 436 383 383 436 256 383 129 256 76 129 129 76 256 129 383 256 496 425 425 496 256 425 87 256 16 87 87 16 256 87 425 500 500 500 0 0 0 0 500 0 500 Z 3 0 $speed $speed true 255 255 0 0 1");
			s("SPAWN_ZONE n coin target L 256 196 214 214 196 256 214 298 256 376 340 340 376 256 340 172 256 136 172 172 136 256 172 340 256 436 383 383 436 256 383 129 256 76 129 129 76 256 129 383 256 496 425 425 496 256 425 87 256 16 87 87 16 256 87 425 500 500 500 0 0 0 0 500 0 500 Z 3 0 $speed $speed true 255 255 0 0 1");
		}
		if($time >= 100 && $time % 5 == 0) 
		{
			if($time == 100)
				c("Deathzones incoming!");
			$speed = mt_rand(-100,100);
			s("SPAWN_ZONE death ".mt_rand(10,490)." ".mt_rand(10,490)." 12 0 ".$speed." ".$speed." true 255 0 0 0 1");
		}
	}
	function targetZoneEnter($event)
	{
		global $players, $gry;
		$player = $event[5];
		$this->coins[$player] += 1;
		if($this->coins[$player] == 5)
		{
			s("ADD_SCORE_PLAYER ".$player." 1");
			$this->coins[$player] = 0;
			c($players[$player]."{$gry} earns 1 point!");
		}
		else
			pm($player, $this->coins[$player]."/5 coins");
	} 
}
class Sumo extends Minigame
{
	static $display_name = "Sumo";
	static $description = "Sumo with submarine physics 0x808080(2 pts for conqure, 1 pt for kills)";
	function __construct()
	{
		s("INCLUDE sumo.cfg");
	}
	function __destruct()
	{
		unload("sumo.cfg");
	}
}
class Map extends Minigame
{
	static $display_name = "Map";
	static $description = "Same game, different map. ";
	static $maps = array(
		"arrow-290712", "button-020413", "chico-120714", "chimney-270612", "circle-240511", "cross-090714", "curse-090714", "diablo-120712", "dots-180814", "dumbbell-050714", "eight-100714",
		"eihwaz-280712", "flipped-150213", "flux-090714", "hexatron-100714", "hexawarp-190213", "honeycomb-100714", "magnet-090714", "molecule-100714", "octatron-100714", "octawarp-100414",
		"orbit-100714", "pause-110714", "pentagon-080714",  "prism-110714", "racetrack-070814", "rhombus-110714", "ring-110714", "room-260511", "shift-110714", "silo-110714", "sixpetals-110714",
		"spiral-110714", "star-120714", "tetrawarp-190213", "tiles-270612", "triangle-120714", "void-050712", "wave-060714", "window-120714", "zet-250511", "zone-160313", "barrier-2",
		"boxes-3", "compass-4", "donut-3", "hexagon-1", "pillars-3", "plus-1", "redicle-1", "rooms-3", "star-3"
	);
	static $play = 0;
	static $cur_map = "none";
	public $roundsPlayed = -1;
	function __construct()
	{
		if(Map::$play == 0)
			shuffle(Map::$maps);
		$map_name = explode("-",Map::$maps[Map::$play]);
		if($map_name[1] < 1000) //its not a wik map
		{
			s("MAP_FILE rxfreaks/hft/".Map::$maps[Map::$play].".aamap.xml");
			Map::$cur_map = $map_name[0];
		}
		else
		{
			s("MAP_FILE Wik/dogfight/".Map::$maps[Map::$play].".aamap.xml");
			Map::$cur_map = $map_name[0];
		}
		if(++Map::$play >= sizeof(Map::$maps))
			Map::$play = 0;
	}
	function roundStart()
	{
		c("0xbf00bfMap0xffffff: ".Map::$cur_map);
	}
	function roundEnd()
	{
		$this->roundsPlayed++;
		if($this->roundsPlayed <= 0)
			return;
		$this->__construct();
	}
	function __destruct()
	{
		Map::$cur_map = "none";
		s("MAP_FILE Anonymous/polygon/regular/square-1.0.1.aamap.xml");
	}
}
class Wildfort extends Minigame
{
	static $display_name = "Wild Fort";
	static $description = "Capture the enemy bases on a wacky map"; 
	static $maps = array(
		"Abductors		| map_file Wik/fortress/Abductors_sty-200614.aamap.xml		| cycle_accel_rim 5 | Rim acceleration. Teleporters might abduct you.",
		"And180SomeMore	| map_file Wik/fortress/And180SomeMore-050913.aamap.xml		| cycle_accel 0 | cycle_brake_refill 1 | cycle_speed 6 | cycle_speed_decay_above 0 | cycle_start_speed 10 | cycle_turn_speed_factor 1.05 | Turn to gain speed.",
		"Antithesis		| map_file Wik/fortress/Antithesis-190314.aamap.xml		| spawn_wingmen_back 0 |It's shorter straight ahead.",
		"BeadCurtains	| map_file Wik/fortress/BeadCurtains-270414.aamap.xml		|",
		"BeatingHeart	| map_file Wik/fortress/BeatingHeart_sty-010314.aamap.xml	| arena_axes 8 | sp_walls_length 260 | walls_length 260 | 8 axes. Pulsing zones.",
		"BinaryStar		| map_file Wik/fortress/BinaryStar-140813.aamap.xml		| arena_axes 12 | cycle_delay 0.05 | sp_walls_length 240 | walls_length 240 | 12 axes.",
		"BroodChamber	| map_file Wik/fortress/BroodChamber-140813.aamap.xml		| cycle_delay 0.05 | fortress_conquest_rate 0.9 | fortress_defend_rate 0.8 | fortress_max_per_team 12 | sp_walls_length 120 | walls_length 120 | 6 axes.",
		"Bumpers		| map_file Wik/fortress/Bumpers-140813.aamap.xml		| cycle_rubber 10 | cycle_speed 42 | fortress_conquest_rate 100 | fortress_max_per_team 12 | sp_walls_length 200 | walls_length 200 | 12 axes. Touch the forts very gently.",
		"ChainLink		| map_file Wik/fortress/ChainLink-140813.aamap.xml		| arena_axes 16 | cycle_brake -60 | cycle_brake_deplete 0 | cycle_rubber 10 | cycle_speed 6 | cycle_speed_decay_above 0.7 | fortress_conquest_rate 100 | fortress_max_per_team 24 | sp_walls_length 300 | walls_length 300 | 16 axes. Brake is turbo.",
		"Chizra			| map_file Wik/fortress/Chizra-140813.aamap.xml			| sp_walls_length 350 | walls_length 350 |Defenders may turn left twice.",
		"Conception		| map_file Wik/fortress/Conception-140813.aamap.xml		| arena_axes 8 | fortress_conquest_rate 0.2 | fortress_conquest_decay_rate 0 | fortress_defend_rate 0.1 | sp_walls_length 200 | sp_walls_stay_up_delay 0 | walls_length 200 | walls_stay_up_delay 0 | 8 axes. Shrinking forts. Conquer through the small gap.",
		"Crossfire		| map_file Wik/fortress/Crossfire_sty-140813.aamap.xml		| cycle_accel_rim 10 | sp_walls_length 50 | walls_length 50 | Rim acceleration. Catch the fort while being shot at.",
		"Debris			| map_file Wik/fortress/Debris-140813.aamap.xml			| cycle_boost_rim 20 | explosion_radius 10 | fortress_collapse_speed 0 | spawn_wingmen_back -4 | spawn_wingmen_side 8 | sp_explosion_radius 10 | sp_walls_stay_up_delay -1 | walls_stay_up_delay -1 | Rim break boost. Dead tails won't vanish.",
		"DensityStress		| map_file Wik/fortress/DensityStress-140813.aamap.xml		|",
		"Diamond		| map_file Wik/fortress/Diamond_sty-140813.aamap.xml		| cycle_rubber_time 100000 | cycle_rubber 10 | Rubber refill only at rubber stations.",
		"Divisions		| map_file Wik/fortress/Divisions_sty-100314.aamap.xml		| fortress_max_per_team 16 |",
		"DutchRadiologist	| map_file Wik/fortress/DutchRadiologist-310113.aamap.xml	| cycle_accel_enemy 2 | cycle_accel_rim -20 | cycle_accel_self 2 | cycle_accel_team 2 | cycle_wall_near 10 | sp_walls_length 320 | walls_length 320 | win_zone_expansion 0 | win_zone_initial_size 0 | Rim deceleration.",
		"EnemyAtTheGate		| map_file Wik/fortress/EnemyAtTheGate-140813.aamap.xml		| 2 winzones.",
		"Entropy		| map_file Wik/fortress/Entropy_sty-140813.aamap.xml		| arena_axes 16 | cycle_rubber 10 | cycle_speed 42 | fortress_conquest_rate 100 | fortress_max_per_team 32 | sp_walls_length 100 | walls_length 100 | 16 axes.",
		"Equalizer		| map_file Wik/fortress/Equalizer_sty-091013.aamap.xml		| fortress_conquest_rate 0.9 | fortress_defend_rate 0.8 | fortress_max_per_team 8 |",
		"FeelingLucky		| map_file Wik/fortress/FeelingLucky-140813.aamap.xml		| fortress_conquest_rate 100 | fortress_max_per_team 5 | team_blue_1 0 | team_blue_2 0 | team_green_1 0 | team_green_2 0 | team_red_1 15 | team_red_2 15 | sp_walls_length 1 | walls_length 1 | 5 forts per team. Watch closely: deathzones shrink, forts do not.",
		"Fields			| map_file Wik/fortress/Fields_sty-300414.aamap.xml		| Avoid the dangerzone around each fort.",
		"FortressForest		| map_file Wik/fortress/FortressForest-140813.aamap.xml		| arena_axes 8 | cycle_brake -50 | cycle_brake_deplete 0 | cycle_speed 30 | cycle_speed_decay_above 1 | fortress_conquest_rate 0.9 | fortress_defend_rate 0.8 | fortress_max_per_team 18 | sp_walls_length 200 | walls_length 200 | 8 axes. Every 15 seconds a fort grows on a pillar, 18 per team. Brake is turbo.",
		"GaltonBoard		| map_file Wik/fortress/GaltonBoard-140813.aamap.xml		| fortress_max_per_team 5 | sp_walls_length 250 | spawn_wingmen_side 1.5 | walls_length 250 |",
		"Halftime		| map_file Wik/fortress/Halftime-140813.aamap.xml		| spawn_wingmen_back 1.5 | spawn_wingmen_side 1.5 | sp_walls_length 200 | walls_length 200 |",
		"Hideout			| map_file Wik/fortress/Hideout-190314.aamap.xml		| cycle_accel_rim 2 | fortress_max_per_team 3 |",
		"HighSix			| map_file Wik/fortress/HighSix-140813.aamap.xml		| cycle_delay 0.05 | fortress_conquest_rate 0.5 | fortress_defend_rate 0.4 | fortress_max_per_team 6 | sp_walls_length 170 | walls_length 170 | 6 axes.",
		"Husk			| map_file Wik/fortress/Husk-220314.aamap.xml			| cycle_boost_rim 10 | spawn_wingmen_back 0 | Rim break boost.",
		"Inside			| map_file Wik/fortress/Inside-140813.aamap.xml			| arena_axes 16 | cycle_rubber 10 | cycle_speed 42 | fortress_conquest_rate 0.02 | fortress_defend_rate 0.02 | fortress_conquest_decay_rate 0 | sp_walls_length 200 | spawn_wingmen_back -1 | spawn_wingmen_side 4 | walls_length 200 | 16 axes.",
		"IvoryTowers		| map_file Wik/fortress/IvoryTowers_sty-251114.aamap.xml	| cycle_accel_rim 5 | spawn_wingmen_back -1 | spawn_wingmen_side 11 | target_lifetime -1 | target_survive_time 1 | Rim acceleration. Unleash fury by conquering a small target.",
		"Killswitch		| map_file Wik/fortress/Killswitch_sty-150813.aamap.xml		| sp_walls_stay_up_delay 0 | spawn_wingmen_back 0 | spawn_wingmen_side 10 | target_lifetime -1 | target_survive_time 5 | walls_stay_up_delay 0 | Got ganked? Use the one-time emergency switch.",
		"LargeHatronCollider	| map_file Wik/fortress/LargeHatronCollider-140813.aamap.xml	| cycle_brake 200 | cycle_accel_rim 2 | cycle_accel_team 0 | cycle_speed_decay_above 0.05 | sp_walls_length 250 | sp_walls_stay_up_delay 0 | spawn_wingmen_side 0.1 | spawn_wingmen_back 0 | walls_length 250 | walls_stay_up_delay 0 | Brake at around 310.",
		"LinearAccelerator	| map_file Wik/fortress/LinearAccelerator-140813.aamap.xml	| cycle_rubber 10 | fortress_conquest_rate 0.5 | fortress_defend_rate 0.4 | fortress_max_per_team 6 | sp_walls_length 200 | walls_length 200 |",
		"Magma			| map_file Wik/fortress/Magma_sty-111013.aamap.xml		| A sea of pulsing deathzones.",
		"MasterAndServant	| map_file Wik/fortress/MasterAndServant-200314.aamap.xml	| cycle_accel_self 2 | cycle_accel_team 2 | spawn_wingmen_back 0 |",
		"Meathook		| map_file Wik/fortress/Meathook-140813.aamap.xml		| fortress_max_per_team 2 | sp_walls_length 500 | walls_length 500 |",
		"Membranes		| map_file Wik/fortress/Membranes-140813.aamap.xml		| spawn_wingmen_side 0.5 | spawn_wingmen_back 2 | Note to center: dead end ahead.",
		"Niches			| map_file Wik/fortress/Niches-140813.aamap.xml			| cycle_accel_rim 25 | cycle_wall_near 1 | fortress_conquest_rate 0.9 | fortress_defend_rate 0.8 | fortress_max_per_team 12 | sp_walls_length 100 | walls_length 100 | Rim acceleration.",
		"NuclearDecay		| map_file Wik/fortress/NuclearDecay_sty-301213.aamap.xml	| cycle_accel_rim 5 | spawn_wingmen_back 0.5 | spawn_wingmen_side 1 | Rim acceleration.",
		"Nut			| map_file Wik/fortress/Nut-260614.aamap.xml			| cycle_accel 0 | cycle_brake 25 | cycle_speed 42 | cycle_speed_min 0.01 | cycle_speed_decay_below 0.1 | cycle_start_speed 0 | cycle_turn_speed_factor 1 | fortress_conquest_rate 100 | fortress_max_per_team 15 | sp_walls_length 50 | spawn_wingmen_back 0 | spawn_wingmen_side 1 | walls_length 50 | 6 axes. Auto-acceleration. Weak brake.",
		"Ornament		| map_file Wik/fortress/Ornament-120114.aamap.xml		| arena_axes 16 | cycle_brake 100 | cycle_rubber 10 | cycle_speed 42 | fortress_conquest_rate 100 | fortress_max_per_team 17 | sp_walls_length 300 | walls_length 300 | 16 axes.",
		"PalaceOfTheHighOnes	| map_file Wik/fortress/PalaceOfTheHighOnes-140813.aamap.xml	| fortress_max_per_team 3 | Warped 6 axes.",
		"PlusOne			| map_file Wik/fortress/PlusOne-261013.aamap.xml		| spawn_wingmen_side 5 | sp_walls_length 350 | walls_length 350 | 5 axes. Think before you grind.",
		"Poleis			| map_file Wik/fortress/Poleis-160813.aamap.xml			| cycle_boostfactor_rim 2 | cycle_rubber_timebased 1 | Rim break boost.",
		"ProtectiveCustody	| map_file Wik/fortress/ProtectiveCustody-140813.aamap.xml	|",
		"Qapla			| map_file Wik/fortress/Qapla-230614.aamap.xml			| fortress_max_per_team 2 |",
		"QuantumLeap		| map_file Wik/fortress/QuantumLeap_sty-080314.aamap.xml	| Teleport yourself to the enemy via the back room.",
		"Quartered		| map_file Wik/fortress/Quartered-110214.aamap.xml		| cycle_speed 9 | cycle_speed_decay_below 1 | cycle_start_speed 1 | spawn_wingmen_back 0 | spawn_wingmen_side 1 | sp_walls_length 100 | walls_length 100 |",
		"Retreat			| map_file Wik/fortress/Retreat-140813.aamap.xml		| fortress_conquest_rate 0.5 | fortress_defend_rate 0.4 | fortress_max_per_team 5 |",
		"RollerBearing		| map_file Wik/fortress/RollerBearing-140813.aamap.xml		| fortress_conquest_rate 0.9 | fortress_defend_rate 0.8 | fortress_max_per_team 12 |",
		"RuinsInTheWasteland	| map_file Wik/fortress/RuinsInTheWasteland-050314.aamap.xml	| arena_axes 6 | cycle_delay 0.05 | sp_walls_length 300 | walls_length 300 | win_zone_expansion 300 | 6 axes. 2 winzones.",
		"SeeYouInHell		| map_file Wik/fortress/SeeYouInHell-160113.aamap.xml		| arena_axes 8 | sp_walls_length 300 | walls_length 300 | 8 axes.",
		"Shaman			| map_file Wik/fortress/Shaman_sty-230714.aamap.xml		| Main gate blocked? Travel the outerworld to gain side entrance.",
		"Shareholder		| map_file Wik/fortress/Shareholder-110214.aamap.xml		| spawn_wingmen_back 1 | spawn_wingmen_side 2 |",
		"Shockwave		| map_file Wik/fortress/Shockwave-140813.aamap.xml		| arena_axes 16 | cycle_brake 250 | cycle_brake_deplete 0.5 | cycle_rubber 10 | cycle_rubber_wall_shrink 10 | cycle_speed 78 | cycle_turn_speed_factor 0.945 | fortress_collapse_speed -100 | fortress_conquest_rate 100 | fortress_max_per_team 98 | spawn_wingmen_back 0 | spawn_wingmen_side 10 | 16 axes. Exploding forts. Quadbinding works good when turning.",
		"SlinkingAway		| map_file Wik/fortress/SlinkingAway_sty-050314.aamap.xml	| It creeps.",
		"Teamocide		| map_file Wik/fortress/Teamocide-271213.aamap.xml		| fortress_conquered_kill_min 1 | fortress_conquest_rate 100 | fortress_max_per_team 11 | spawn_wingmen_back 1 | Each fortress is one life.",
		"Termites		| map_file Wik/fortress/Termites-301113.aamap.xml		| cycle_delay 0.05 | fortress_max_per_team 2 | sp_walls_length 160 | walls_length 160 | 6 axes.",
		"Timebomb		| map_file Wik/fortress/Timebomb-140813.aamap.xml		| After some time, one of the deathzones will grow.",
		"Viewfinder		| map_file Wik/fortress/Viewfinder-180214.aamap.xml		| arena_axes 16 | cycle_brake 100 | cycle_rubber 10 | cycle_speed 42 | fortress_conquest_rate 0.2 | fortress_conquest_decay_rate 0 | fortress_defend_rate 0.2 | fortress_max_per_team 2 | sp_walls_length 100 | walls_length 100 | 16 axes. Split immediately.",
		"Wallhugger		| map_file Wik/fortress/Wallhugger_sty-020314.aamap.xml		| cycle_accel_rim 5 | cycle_rubber_time 1 | fortress_max_per_team 8 | fortress_conquest_rate 0.5 | fortress_defend_rate 0.4 | sp_walls_length 1 | walls_length 1 | win_zone_initial_size 220 | Rim acceleration. Just catch the forts.",
		"Warpdrive		| map_file Wik/fortress/Warpdrive-140813.aamap.xml		| cycle_delay 0.03 | cycle_rubber 10 | 8 axes. Beware of relativistic effects on your tail.",
		"Whirlwind		| map_file Wik/fortress/Whirlwind_sty-140813.aamap.xml		| Set up defense as quickly as possible.",
		"YinYangShattered	| map_file Wik/fortress/YinYangShattered-140813.aamap.xml	| cycle_accel 0 | cycle_brake 0 | cycle_speed 600 | cycle_speed_min 0 | cycle_speed_decay_below 0.01 | cycle_start_speed 0 | cycle_turn_speed_factor 0.9 | Auto-acceleration. Turn to lose speed."
	);
	private $hint;
	private $name;
	private $settings = [];
	public $roundsPlayed = -1;
	function __construct()
	{
		s("INCLUDE teams.cfg");
		s("INCLUDE fort.cfg");
		s("SCORE_WIN 5");
		s("SP_SCORE_WIN 5");
		$line = explode("|",Wildfort::$maps[mt_rand(0,sizeof(Wildfort::$maps)-1)]);
		foreach($line as $i => $l)
		{
			$l = trim($l);
			if($i == 0) {
				$this->name = $l;
				continue;
			}
			if($i == sizeof($line)-1) {
				$hint = trim($line[$i]);
				$hint = empty($hint) ? "0x808080(none)" : $hint;
				$hint = "0xffff00Hint:0xffffff ".$hint."";
				$this->hint = $hint; 
				continue;
			}
			$this->settings[] = trim(strtoupper($l));
			s($l);
		}
	}
	function roundStart()
	{
		c("0xbf00bfMap: ".$this->name);
		c($this->hint);
	}
	function roundEnd()
	{
		$this->roundsPlayed++;
		if($this->roundsPlayed <= 0)
			return;
		while($this->settings)
			undo(explode(" ",array_pop($this->settings))[0]);
		$this->__construct();
	}
	function __destruct()
	{
		foreach($this->settings as $s)
			undo(explode(" ",$s)[0]);
		unload("fort.cfg");
		unload("teams.cfg");
		s("MAP_FILE Anonymous/polygon/regular/square-1.0.1.aamap.xml");
	}
}
class Fort extends Minigame
{
	static $display_name = "Fort";
	static $description = "Fort with submarine physics and respawn zones. 0x808080(10 pts for base capture)";
	function __construct()
	{
		s("INCLUDE fort.cfg");
		s("INCLUDE teams.cfg");
		s("SIZE_FACTOR 0");
		s("SP_SIZE_FACTOR 0");
		s("CYCLE_RESPAWN_ZONE 1");
		s("CYCLE_RESPAWN_ZONE_GROWTH -0.001");
		s("FORTRESS_CONQUERED_SCORE 10");
		s("CYCLE_RESPAWN_ZONE_TYPE 1");
		s("MAP_FILE rxfreaks/classic/fort-1.2.aamap.xml");
	}
	function __destruct()
	{
		unload("fort.cfg");
		unload("teams.cfg");
		undo("SIZE_FACTOR");
		undo("SP_SIZE_FACTOR");
		undo("CYCLE_RESPAWN_ZONE");
		undo("FORTRESS_CONQUERED_SCORE");
		undo("CYCLE_RESPAWN_ZONE_TYPE");
		s("MAP_FILE Anonymous/polygon/regular/square-1.0.1.aamap.xml");
	}
}
class Nano extends Minigame
{
	static $display_name = "Nano";
	static $description = "Everything's smaller";
	function __construct()
	{
		s("INCLUDE nano.cfg");
	}
	function __destruct()
	{
		unload("nano.cfg");
	}
}
class Koh extends Minigame
{
	static $display_name = "King of the Hill";
	static $description = "Be the only one inside the zone for 5 seconds! 0x808080(0 pts for kills, 10s respawns)";
	private $respawn_time;
	function __construct()
	{
		$this->respawn_time = 0;
		s("KOH_SCORE 2");
		s("SCORE_KILL 0");
	}
	function roundStart()
	{
		$rand = mt_rand(1,4);
		if($rand == 2) mt_rand(1,4); //2 is half as likely to appear
		if($rand == 1)
		{
			s("KOH_SCORE 2");
			s("SPAWN_ZONE koh 250 250 ".mt_rand(30, 60)." 0 0 0 false");
		}
		if($rand == 2)
		{
			s("KOH_SCORE 1");
			s("SPAWN_ZONE koh 250 250 20 0 0 0 false");
			s("SPAWN_ZONE koh 250 250 30 0 0 0 false");
			s("SPAWN_ZONE koh 250 250 40 0 0 0 false");
			s("SPAWN_ZONE koh 250 250 50 0 0 0 false");
			s("SPAWN_ZONE koh 250 250 60 0 0 0 false");
			s("SPAWN_ZONE koh 250 250 70 0 0 0 false");
		}
		if($rand == 3)
		{
			global $players;
			s("KOH_SCORE 2");
			s("SPAWN_ZONE koh 150 150 40 0 0 0 false");
			s("SPAWN_ZONE koh 150 350 40 0 0 0 false");
			if(sizeof($players) >= 3) 
				s("SPAWN_ZONE koh 350 150 40 0 0 0 false");
			if(sizeof($players) >= 4)
				s("SPAWN_ZONE koh 350 350 40 0 0 0 false");
		}
		if($rand == 4)
		{
			$speed = mt_rand(1,50);
			s("KOH_SCORE 2");
			s("SPAWN_ZONE koh L 100 100 100 400 400 400 400 100 Z ".mt_rand(30, 50)." 0 $speed $speed 0 false");
		}
	}
	function timedEvents($time)
	{
		if($time == 0)
			s("RESPAWN_TIME 0");
		if($time % 12 == 0 && $time != 0) 
		{
			$this->respawn_time += 1;
			s("RESPAWN_TIME ".$this->respawn_time);
		}
		if($time == 300)
			$this->respawn_time *= 2;
	}
	function __destruct()
	{
		undo("RESPAWN_TIME");
		undo("SCORE_KILL");
		s("COLLAPSE_ZONE");
	}
}
class Bots extends Minigame
{
	static $display_name = "Bot Invasion";
	static $description = "Survive the bot onslaught 0x808080(0 pts for kills, 5 pts for surviving)";
	function __construct()
	{
		$num_ais = mt_rand(5, 21);
		s("NUM_AIS ".$num_ais);
		s("SP_NUM_AIS ".$num_ais);
		s("NUM_AIS_PER_ROUND ".$num_ais);
		s("SCORE_KILL 0");
		s("SCORE_WIN 5");
		s("SHOT_PENETRATE_WALLS 1");
	}
	function timedEvents($time)
	{
		global $ais;
		if($time % 5 == 0) 
		{
			foreach($ais as $a)
				s("SET_CYCLE_RUBBER $a 12");
		}
		if($time >= 30)
		{
			if($time % 30 == 0)
				c("Bot speed decrease!");
			foreach($ais as $a)
				s("SET_CYCLE_SPEED $a 0");
		}
		if($time % 60 == 0 && $time != 0) {
			c("Shooting enabled! Hold brakes (v) to fire.");
			s("INCLUDE shooting.cfg");
			s("ZOMBIE_ZONE 0");
		}
	}
	function __destruct()
	{
		s("NUM_AIS 0");
		s("SP_NUM_AIS 1");
		s("SCORE_KILL 2");
		undo("SCORE_WIN");
		undo("AI_IQ");
		unload("shooting.cfg");
	}
}
class Longwall extends Minigame
{
	static $display_name = "Long Walls";
	static $description = "Cycle walls are infinitely long";
	function __construct()
	{
		s("WALLS_LENGTH -1");
		s("SP_WALLS_LENGTH -1");
		s("CYCLE_WALLS_LENGTH -1");
		s("WALLS_STAY_UP_DELAY -1");
		s("SP_WALLS_STAY_UP_DELAY -1");
		s("CYCLE_WALLS_STAY_UP_DELAY -1");
	}
	function __destruct()
	{
		undo("WALLS_LENGTH");
		undo("SP_WALLS_LENGTH");
		undo("CYCLE_WALLS_LENGTH");
		undo("WALLS_STAY_UP_DELAY");
		undo("SP_WALLS_STAY_UP_DELAY");
		undo("CYCLE_WALLS_STAY_UP_DELAY");
	}
}
class Bone extends Minigame
{
	static $display_name = "Bone Wand";
	static $description = "Your cycle walls shrink as you lose rubber; hit the zone to heal and grow walls back.";
	function __construct()
	{
		s("CYCLE_RUBBER 100");
		s("CYCLE_RUBBER_TIME 9999");
		s("CYCLE_RUBBER_WALL_SHRINK 15");
		s("WALLS_LENGTH 1500");
		s("SP_WALLS_LENGTH 1500");
		s("CYCLE_WALLS_LENGTH 1500");
	}
	function roundStart()
	{
		s("SPAWN_ZONE rubber L 100 100 100 400 400 400 400 100 Z 20 0 0 30 -0.2 false 4.5 9 15 0");
		s("SPAWN_ZONE rubber L 400 100 400 400 100 400 100 100 Z 20 0 0 -30 -0.2 false 4.5 9 15 0");
	}
	function __destruct()
	{
		undo("CYCLE_RUBBER");
		undo("CYCLE_RUBBER_TIME");
		undo("CYCLE_RUBBER_WALL_SHRINK");
		undo("WALLS_LENGTH");
		undo("SP_WALLS_LENGTH");
		undo("CYCLE_WALLS_LENGTH");
		s("COLLAPSE_ZONE");
	}
}
class Classic extends Minigame
{
	static $display_name = "Classic Physics";
	static $description = "The same binds, speed, and rubber as sumo/fort";
	function __construct()
	{
		s("INCLUDE vanilla.cfg");
	}
	function __destruct()
	{
		unload("vanilla.cfg");
	}
}
class Teams extends Minigame
{
	static $display_name = "Team Deathmatch";
	static $description = "Normal settings, but work as a team! Touch the zones to revive your teammate 0x808080(5 pts for kills)";
	function __construct()
	{
		s("INCLUDE teams.cfg");
		s("SCORE_KILL 5");
		s("CYCLE_RESPAWN_ZONE 1");
		s("CYCLE_RESPAWN_ZONE_GROWTH -0.001");
		s("MIN_PLAYERS 4");
		s("AI_IQ 1000");
		s("SP_MIN_PLAYERS 3");
	}
	function __destruct()
	{
		unload("teams.cfg");
		undo("SCORE_KILL");
		undo("CYCLE_RESPAWN_ZONE");
		undo("MIN_PLAYERS");
		undo("SP_MIN_PLAYERS");
	}
}
class Turbo extends Minigame
{
	static $display_name = "Turbo Button";
	static $description = "Press brakes (v) to boost. The zones speed you up";
	function __construct()
	{
		$rand = mt_rand(10,40);
		s("CYCLE_BRAKE -".$rand);
	}
	function roundStart()
	{
		s("SPAWN_ZONE acceleration ".mt_rand(5,20)." ".mt_rand(10,490)." ".mt_rand(10,490)." 10 0 0 0 true ");
		s("SPAWN_ZONE acceleration ".mt_rand(5,20)." ".mt_rand(10,490)." ".mt_rand(10,490)." 10 0 0 0 true ");
	}
	function __destruct()
	{
		undo("CYCLE_BRAKE");
	}
}
class Pets extends Minigame
{
	static $display_name = "Pet Zombies";
	static $description = "Feed your pet zombie by letting it eat another player 0x808080(3 pts for zombie kill, 0 pts for kills)";
	private $speed;
	function __construct()
	{
		s("SCORE_KILL 0");
		s("SCORE_ZOMBIE_ZONE 3");
		s("ZOMBIE_ZONE_RADIUS 6");
		$this->speed = getenv("ARMAGETRONAD_ZOMBIE_ZONE_SPEED");
	}
	function roundStart()
	{
		global $players;
		foreach($players as $i=>$p)
			if(!is_numeric($i))
				s("SPAWN_ZONE n $i zombieOwner $i $i 250 250 10 0 0 0 false");
	}
	function __destruct()
	{
		undo("SCORE_KILL");
		undo("SCORE_ZOMBIE_ZONE");
		undo("ZOMBIE_ZONE_SPEED");
	}
	function roundEnd()
	{
		c("Hint: Typing /a will reset your pet");
	}
	function timedEvents($time)
	{
		if($time % 60 == 0 && $time != 0)
		{
			$this->speed += 10;
			s("ZOMBIE_ZONE_SPEED ".$this->speed);
			c("Pet speed increased! (".$this->speed.")");
		}
	}
	function playercmd($name)
	{
		s("COLLAPSE_ZONE $name");
		s("SPAWN_ZONE n $name zombieOwner $name $name 250 250 10 0 0 0 false");
	}
}
class Macro extends Minigame
{
	static $display_name = "Macro";
	static $description = "Huge map, huge acceleration. Zones speed you up";
	function __construct()
	{
		$rand = mt_rand(1,3);
		s("SIZE_FACTOR ".$rand);
		s("SP_SIZE_FACTOR ".$rand);
		s("CYCLE_ACCEL ".(50*$rand));
		//s("CYCLE_BRAKE ".(-10*$rand));
	}
	function roundStart()
	{
		if(mt_rand(1,2) == 2)
		{
			for($i=0; $i<mt_rand(0,8); $i++)
				s("SPAWN_ZONE acceleration ".mt_rand(5,50)." ".mt_rand(10,490)." ".mt_rand(10,490)." 10 0 ".mt_rand(-100, 100)." ".mt_rand(-100, 100)." true ");
		}
		else
		{
			for($i=0; $i<mt_rand(0,8); $i++)
				s("SPAWN_ZONE acceleration ".mt_rand(5,50)." ".mt_rand(10,490)." ".mt_rand(10,490)." 10 0 0 0 true ");
		}
	}
	function __destruct()
	{
		undo("SIZE_FACTOR");
		undo("SP_SIZE_FACTOR");
		undo("CYCLE_ACCEL");
		//undo("CYCLE_BRAKE");
		s("COLLAPSE_ZONE");
	}
}
class Htf extends Minigame
{
	static $display_name = "Hold the Flag";
	static $description = "Hold the flag! 0x808080(Holding for 10s earns 1 pt, kill are 0 pts)";
	private $respawn_time;
	function __construct()
	{
		$this->respawn_time = 0;
		s("FLAG_TEAM 0");
		s("FLAG_HOLD_SCORE 1");
		s("FLAG_HOLD_SCORE_TIME 10");
		s("CYCLE_ACCEL_RIM 5");
		s("SCORE_KILL 0");
		s("FLAG_HOLD_TIME 21");
		s("FLAG_DROP_TIME 4");
		s("FLAG_HOLD_TIME_DROP 1");
		s("MAP_FILE rxfreaks/custom/htfdzquadcross-7.aamap.xml");
	}
	function timedEvents($time)
	{
		if($time == 0)
			s("RESPAWN_TIME 0");
		if($time % 12 == 0 && $time != 0) 
		{
			$this->respawn_time += 1;
			s("RESPAWN_TIME ".$this->respawn_time);
		}
		if($time == 300)
			$this->respawn_time *= 2;
	}
	function __destruct()
	{
		undo("FLAG_TEAM");
		undo("FLAG_HOLD_SCORE");
		undo("FLAG_HOLD_SCORE_TIME");
		undo("CYCLE_ACCEL_RIM");
		undo("RESPAWN_TIME");
		undo("FLAG_HOLD_TIME");
		s("MAP_FILE Anonymous/polygon/regular/square-1.0.1.aamap.xml");
	}
}
class Ctf extends Minigame
{
	static $display_name = "Capture the Flag";
	static $description = "Take the opponent's flag and bring it home 0x808080(Flag capture = 15pts)";
	function __construct()
	{
		s("LIMIT_SCORE 15"); //temporarily let normal mode award match winner before scores are cleared
		s("START_NEW_MATCH");
		s("INCLUDE teams.cfg");
		s("INCLUDE ctf.cfg");
		s("CENTER_MESSAGE Capture the Flag");
	}
	function __destruct()
	{
		unload("ctf.cfg");
		unload("teams.cfg");
	}
}
class Bombs extends Minigame
{
	static $display_name = "Bomb Laying";
	static $description = "Leave a bomb by pressing brakes (v) 0x808080(Bomb kills = 3 pts, regular kills = 0 pts)";
	function __construct()
	{
		s("INCLUDE bombs.cfg");
	}
	function __destruct()
	{
		unload("bombs.cfg");
	}
}
class Reflex extends Minigame
{
	static $display_name = "Reflex Challenge";
	static $description = "Test your reflexes by racing to the finish 0x808080(1st = 10 pts, 2nd = 8 pts, 3rd = 6 pts, Finishing = 4 pts)";
	static $play = 0;
	public $roundsPlayed = -1;
	public $cur_map;
	public $settings = [];
	private $winners = [];
	private $first = 999;
	static $maps = [
		"Reflex Tunnel | rxfreaks/race/tunnel-6.aamap.xml | SIZE_FACTOR -7 | SP_SIZE_FACTOR -7",
		"Spiral | rxfreaks/race/spiral-9.aamap.xml | SIZE_FACTOR -5 | SP_SIZE_FACTOR -5 | CYCLE_START_SPEED 80 | CYCLE_SPEED_DECAY_ABOVE 0.1 ",
		"3mazes | Light/race/3mazes-1.0.2.aamap.xml| SIZE_FACTOR -3 | SP_SIZE_FACTOR -3",
		"ZigZag | Light/race/zigzag-1.0.2.aamap.xml| SIZE_FACTOR -4 | SP_SIZE_FACTOR -4",
		"DoubleBind | rxfreaks/race/doublebind-3.aamap.xml| SIZE_FACTOR -6 | SP_SIZE_FACTOR -6",
		"Maze | Light/race/dungeon-1.0.1.aamap.xml | SIZE_FACTOR -6 | SP_SIZE_FACTOR -6",
		"Grind | rxfreaks/race/blah-2.4.aamap.xml | CYCLE_RUBBER 10 ",
		"Intestines |  rxfreaks/race/maze-7.2.aamap.xml | SIZE_FACTOR -7.5 | SP_SIZE_FACTOR -7.5 | SPAWN_WINGMEN_SIDE 0",
		"Octagone | rxfreaks/race/octa-10.aamap.xml | SIZE_FACTOR -7 | SP_SIZE_FACTOR -7",
		"Microhell | pdbq/race/microhell-1.0.3.aamap.xml | SIZE_FACTOR -5 | SP_SIZE_FACTOR -5 "
	];
	function __construct()
	{
		s("INCLUDE reflex.cfg");
		if(Reflex::$play == 0) {
			shuffle(Reflex::$maps);
			c("0xRESETTShuffled maps.");
		}
		$ar = explode("|", Reflex::$maps[Reflex::$play]);
		$this->cur_map = trim($ar[0]);
		for($i=1; $i<count($ar); $i++)
		{
			if($i==1)
				s("MAP_FILE ".trim($ar[$i]));
			else {
				$this->settings[] = trim($ar[$i]);
				s(trim($ar[$i]));
			}
		}
		Reflex::$play++;
		if(Reflex::$play >= sizeof(Reflex::$maps))
			Reflex::$play = 0;
	}
	function roundStart()
	{
		unset($this->winners);
		$this->winners = [];
		$this->first = 999;
		c("0xbf00bfMap0xffffff: ".$this->cur_map);
	}
	function roundEnd()
	{
		$this->roundsPlayed++;
		if($this->roundsPlayed <= 0)
			return;
		while($this->settings)
			undo(explode(" ",array_pop($this->settings))[0]);
		$this->__construct();
	}
	function targetZoneEnter($e)     //TARGETZONE_PLAYER_ENTER 1  31 650 dukevin@rx 42.4338 622.168 0 1 23.8931
	{								 //WINZONE_PLAYER_ENTER    1  464 40 dukevin@rx 460.553 38.2499 0 1 53.0025
		global $players, $gry;
		$winner = $e[5];
		if(in_array($winner, $this->winners))
			return;
		$this->winners[] = $winner;
		$time = array_pop($e);
		if(count($this->winners) == 1)
		{
			c($players[$winner].$gry." finished the reflex challenge 1st in 0x80ff80".$time."s{$gry}! (10 pts)");
			s("ADD_SCORE_PLAYER ".$winner." 10");
			$this->first = $time;
			s("RACE_END_DELAY ".round($time/2));
		}
		else if(count($this->winners) == 2)
		{
			c($players[$winner].$gry." finished the reflex challenge 2nd in 0xff8080+".($time-$this->first)."s{$gry}! (8 pts)");
			s("ADD_SCORE_PLAYER ".$winner." 8");
		}
		else if(count($this->winners) == 3)
		{
			c($players[$winner].$gry." finished the reflex challenge 3rd in 0xff8080+".($time-$this->first)."s{$gry}! (6 pts)");
			s("ADD_SCORE_PLAYER ".$winner." 6");
		}
		else
		{
			c($players[$winner].$gry." finished the reflex challenge ".count($this->winners)."th in 0xff8080+".($time-$this->first)."s{$gry}! (4 pts)");
			s("ADD_SCORE_PLAYER ".$winner." 4");
		}
		if(count($this->winners) == count($players))
			s("DECLARE_ROUND_WINNER ".$this->winners[0]);
	}
	function __destruct()
	{
		unload("reflex.cfg");
		s("MAP_FILE Anonymous/polygon/regular/square-1.0.1.aamap.xml");
		foreach($this->settings as $s)
			undo(explode(" ",$s)[0]);
	}
}
class Dodgeball extends Minigame
{
	static $display_name = "Dodgeball";
	static $description = "Hit the enemy team with your ball. Brakes (v) to hit the ball. 0x808080(5 pts for kills)";
	function __construct()
	{
		//BUG: Ball can sometimes get stuck against the wall
		s("include dodgeball.cfg");
	}
	function roundStart()
	{
		s("CYCLE_RUBBER 20");
		c("0xRESETTHint: Press brakes (v) to hit the ball quickly.");
		s("SPAWN_ZONE ballTeam blueberries 250 150 32 0.01");
		s("SPAWN_ZONE ballTeam bananas 250 350 32 0.01");
	}
	function __destruct()
	{
		unload("teams.cfg");
		unload("dodgeball.cfg");
		undo("CYCLE_RUBBER");
	}
}
class Camping extends Minigame
{
	static $display_name = "Camping";
	static $description = "Survive as long as you can on 3 stages 0x808080(5 pts for last standing)";
	static $level2_spawns = ["40 296", "136 296", "232 296", "328 296", "424 296", "40 216", "136 216", "232 216", "328 216", "424 216"];
	static $level3_spawns = ["40 104", "136 104", "232 104", "328 104", "424 104", "40 24", "136 24", "232 24", "328 24", "424 24"];
	public $player_levels;
	public $time;
	public $level2_spawns_remaining;
	public $level3_spawns_remaining;
	function __construct()
	{
		s("MAP_FILE rxfreaks/custom/camping-2.aamap.xml");
		s("CYCLE_ACCEL 4");
		s("CYCLE_RUBBER 5");
		s("CYCLE_WALLS_LENGTH -1");
		s("WALLS_LENGTH -1");
		s("SP_WALLS_LENGTH -1");
		s("WALLS_STAY_UP_DELAY -1");
		s("SP_WALLS_STAY_UP_DELAY -1");
		s("GAME_TYPE 0");
		s("SP_GAME_TYPE 0");
		s("CYCLE_INVULNERABLE_TIME 0");
		s("ZONE_ALPHA_SERVER 0");
		s("CYCLE_WALL_TIME 3");
		s("CYCLE_START_SPEED 10");
		s("EXPLOSION_RADIUS 0");
		s("KILL_ALL");
	}
	function roundStart()
	{
		$this->level2_spawns_remaining = Camping::$level2_spawns;
		$this->level3_spawns_remaining = Camping::$level3_spawns;
		global $players;
		foreach($players as $p=>$_)
			$this->player_levels[$p] = 1;
	}
	function timedEvents($time)
	{
		if($time == 0)
			$this->time = microtime(true);
	}
	function playerDied($player)
	{
		if(!array_key_exists($player, $this->player_levels))
			$this->player_levels[$player] = 1;
		$this->player_levels[$player]++;
		if($this->player_levels[$player] == 2)
		{
			pm($player, "Get ready for stage 2...");
			$coord = array_pop($this->level2_spawns_remaining);
			if(!$coord) $coord = Camping::$level2_spawns[0];
			s("DELAY_COMMAND +1 SPAWN_ZONE rubber ".$coord." 50 -5 0 0 -50");
			s("DELAY_COMMAND +1 RESPAWN_PLAYER ".$player." ".$coord." 1 0");
		}
		if($this->player_levels[$player] == 3)
		{
			pm($player, "Get ready for stage 3...");
			$coord = array_pop($this->level3_spawns_remaining);
			if(!$coord) $coord = Camping::$level3_spawns[0];
			s("DELAY_COMMAND +1 SPAWN_ZONE rubber ".$coord." 50 -5 0 0 -50");
			s("DELAY_COMMAND +1 RESPAWN_PLAYER ".$player." ".$coord." 1 0");
		}
		if($this->player_levels[$player] > 3)
		{
			$time = round(microtime(true)-$this->time, 2);
			global $players, $gry;
			c($players[$player]."{$gry} survived for 0xRESETT".$time."s");
			$alive = "";
			foreach($this->player_levels as $p=>$l)
			{
				if($l <= 3) 
				{
					if($alive != "") //someone else is alive
						return;
					$alive = trim($p);
				}
			}
			if(empty($alive))
				return;
			s("ADD_SCORE_PLAYER ".$alive." 5");
			c($players[$alive]."0xffffff won 5 points for being the last alive.");
			//BUG: Earning points in camping which causes a match win will end the game
		}
	}
	function __destruct()
	{
		s("MAP_FILE Anonymous/polygon/regular/square-1.0.1.aamap.xml");
		undo("CYCLE_RUBBER");
		undo("WALLS_STAY_UP_DELAY");
		undo("SP_WALLS_STAY_UP_DELAY");
		undo("CYCLE_WALLS_LENGTH");
		undo("WALLS_LENGTH");
		undo("SP_WALLS_LENGTH");
		undo("GAME_TYPE");
		undo("SP_GAME_TYPE");
		undo("CYCLE_INVULNERABLE_TIME");
		undo("ZONE_ALPHA_SERVER");
		undo("CYCLE_WALL_TIME");
		undo("CYCLE_START_SPEED");
		undo("EXPLOSION_RADIUS");
		undo("CYCLE_ACCEL");
	}
}

function readLadder($p)
{
	global $dir;
	$lf = file($dir."ladder.txt");
	$scores = ["None",0,0,0,-1,0,-1,0];
	$scores[3] = count($lf);
	foreach($lf as $i=>$l)
	{
		if(preg_match("/$p/", $l)) {
			$scores[0] = $i+1;
			break;
		}
	}
	$lf = file($dir."won_rounds.txt");
	$scores[5] = count($lf);
	foreach($lf as $i=>$l)
	{
		$a = preg_split('/\s\s+/', $l);
		if(trim($a[1]) == $p) {
			$scores[4] = $i+1;
			$scores[1] = $a[0];
			break;
		}
	}
	$lf = file($dir."won_matches.txt");
	$scores[7] = count($lf);
	foreach($lf as $i=>$l)	
	{
		$a = preg_split('/\s\s+/', $l);
		if(trim($a[1]) == $p) {
			$scores[2] = $a[0];
			$scores[6] = $i+1;
			break;
		}
	}
	return $scores;
}
function readAndPrintTopLadder($p, $s) //$p = player requesting, $s = player being searched
{
	global $dir;
	$full_width = 84;
	$col_width = 24;
	$wht = "0xffffff";
	$gry = "0xa0a0a0";
	
	$header1_C = " Ladder ";
	$header1_L = str_repeat("-", (($col_width-strlen($header1_C))/2)-1 );
	$header1_R = str_repeat("-", strlen($header1_L) );
	$header2_C = " Won Rounds ";
	$header2_L = str_repeat("-", ($col_width-strlen($header2_C)-1)/2 );
	$header2_R = str_repeat("-", strlen($header2_L) );
	$header3_C = " Won Matches ";
	$header3_L = str_repeat("-", ($col_width-strlen($header3_C)-1)/2 );
	$header3_R = str_repeat("-", strlen($header3_L) );
	$header_full = $wht.$header1_L.$header1_C.$header1_R." | ".$header2_L.$header2_C.$header2_R." | ".$header3_L.$header3_C.$header3_R;
	pm($p, $header_full);
	
	$obj = gatherData($s, ["ladder.txt", "won_rounds.txt", "won_matches.txt"]);
	for($j=0,$i=0;$j<count($obj[0]);$j+=3)
	{
	  pm($p,$obj[$i][$j]."| ".$obj[$i][$j+1].":".$obj[$i][$j+2]."|".$obj[$i+1][$j]."| ".$obj[$i+1][$j+1].":".$obj[$i+1][$j+2]."|".$obj[$i+2][$j]."| ".$obj[$i+2][$j+1].":".$obj[$i+2][$j+2]);
	  usleep(20000);
	}
}
function gatherData($s, $files)
{
	global $dir;
	$data = [];
	foreach($files as $f => $file)
	{
		$lf = file($dir.$file);
		$found = false;
		foreach($lf as $i=>$l)
		{
			$row = explode(" ",$l);
			$name = trim(end($row));
			if($name == $s) {
				$found = $i+1;
				break;
			}
		}
		foreach($lf as $i=>$l)
		{
			$row = explode(" ",$l);
			$name = trim(end($row));
			if($i+1==1||$i+1==2||$i+1==3||$name==$s||$i+1==count($lf)||$found&&$i==$found-2||($found<=3||!$found)&&$i+1==4||($found<=3||!$found)&&$i+1==5||$found==4&&$i+1==5||$i+1==4&&$found==count($lf))
			{
				$data[$f][] = $f == 0 ? padPlace($i+1, true, $name==$s) : padPlace($i+1, false, $name==$s);
				$data[$f][] = $i+1==count($lf)&&$name!=$s ? padName("...", false) : padName($name, $name==$s);
				$data[$f][] = padScore(round($row[0]), $name==$s); 
			}
		}
	}
	return $data;
}
function padPlace($num, $first = false, $is_user = false)
{
	$hlt = "0xaadeff";
	$count = strlen($num);
	$color = "0xffffff";
	$gry = "0xa0a0a0";
	switch($num) 
	{
		case 1: $color="0xffd700"; break;
		case 2: $color="0xbec2cb"; break;
		case 3: $color="0xb08d57"; break;
		default: {
			if($is_user)
				$color=$hlt;
			else
				$color="0xffffff";
		}
	}
	if($is_user) 
		$num .= $hlt;
	else $num .= "0xffffff";
	if($count == 1)
		return $first ? $color.$num." " : " ".$color.$num." ";
	if($count == 2)
		return $first ? $color.$num : " ".$color.$num;
	if($count == 3)
		return $first ? $color."9+".$gry : $color.$num;
	return $color."99+".$gry;
}
function padScore($num, $is_user = false)
{
	$hlt = "0xaadeff";
	$color = "0xa0a0a0";
	$wht = "0xffffff";
	if($is_user)
		$color = $hlt;
	if(strlen($num) == 1)
		return " ".$num."  ".$wht;
	if(strlen($num) == 2)
		return " ".$num." ".$wht;
	if(strlen($num) == 3)
		return " ".$num.$wht;
	return $color.$num.$wht;
}
function padName($name, $is_user = false)
{
	$hlt = "0xaadeff";
	$color = "0xa0a0a0";
	if($is_user) 
		$color = $hlt;
	return $color.substr($name.str_repeat(" ",13),0,14);
}

function closestMatch($str, $pm = false)
{
	global $players;
	$shortest = -1;
	foreach($players as $i => $p)
	{
		$lev = levenshtein(strtolower($str),strtolower(strip0x($p)), 0,1,1);
		if($lev <= $shortest || $shortest < 0)
		{
			$closest = $i;
			$shortest = $lev;
		}
	}
	if($pm)
		pm($pm, "searching for '$closest'..."); //remove later for privacy
	return $closest;
}
function feature_disabled($player, $var) //if var is false, it's disabled
{
	if($var == true)
		return false;
	pm($player, "This feature is disabled. Use /features to re-enable it.");
	return true;
}
function strip0x($str)
{
	if($str === false)
		return false;
	return preg_replace('/0x[0-9a-fA-F]{6}/', '', $str);
}
function is_admin($p, $warn = true)
{
	if($p[4] > 1) {
		if($warn)
			pm($p[2], "Your access level is not high enough for that command. Required is Administrator.");
		return false;
	}
	return true;
}
function s($setting)
{
	echo $setting." \n";
}
function undo($setting)
{
	s($setting." ".getenv("ARMAGETRONAD_".$setting));
}
function unload($file)
{
	global $dir;
	$file = file($dir.$file) or die("ERROR: Couldn't open file $file");
	foreach($file as $line)
	{
		$p = explode(" ", $line);
		s($p[0]." ".getenv("ARMAGETRONAD_".$p[0]));
	}
}
function is_auth($p)
{
	return strpos($p, '@') !== false;
}
function c($string)
{
	echo "console_message 0xaadeff$string \n";
}
function pm($p, $string)
{
	echo "player_message {$p} \"0xaadeff{$string}\"\n ";
}
?>
