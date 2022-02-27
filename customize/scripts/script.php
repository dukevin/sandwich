#!/usr/bin/php
<?php
/* Requires to be started with SPAWN_SCRIPT so getenv works. +ap required*/
/* Todo: disco fog, fort mode zones don't spawn, reg fort with base res, spec stay in $players*/
$dir = "/home/duke/aa/servers/sandwich/var/";
$dessertRounds = 10;	//serve dessert (play a minigame) after this many rounds
$pink = "0xffa0e0";
/*** End editable settings ***/
$game = GameManager::getInstance();
$roundsPlayed = 0;
$go_str = "";
$players = array();
$playerStat = array();
$turbos = array();
$game_time = 0;
while(!feof(STDIN))
{
	$line = rtrim(fgets(STDIN, 1024));
	$p = explode(" ", $line);
	if($p[0] == "INVALID_COMMAND") //INVALID_COMMAND /missile dukevin@rx 73.134.88.149 -2 fl
	{
		$p[1] = strtolower($p[1]);
		if($p[1] == "/stats" || $p[1] == "/stat" || $p[1] == "/s")
		{
			printStats($p[2]);
		}
		else if($p[1] == "/shop")
			Shop::view($p[2]);
		else if($p[1] == "/res" || $p[1] == "/r" || $p[1] == "/respawn" || $p[1] == "/rse")
		{
			$p[1] = "/res";
			if(Shop::buy($p[2],$p[1])) 
			{
				$str = "";
				if(!empty($p[5])) {
					$on = $p[5];
					$str = " on '".$on."'";
				}
				else
					$on = $p[2];
				c($players[$p[2]]." 0xaadeffbuys and uses a respawn".$str."!");
				s("RESPAWN ".$on);
			}
		}
		else if($p[1] == "/now") 
		{
			if(Shop::buy($p[2], $p[1])) {
				c($players[$p[2]]." 0xaadeffbuys early dessert! (Next round is dessert)");
				$roundsPlayed = 9;
			}
		}
		else if($p[1] == "/order") {
			if(empty($p[5]) || !in_array($p[5], GameManager::$games_list)) {
				if(!empty($p[5]))
					pm($p[2], "0xff8080Error: Invalid minigame name.");
				pm($p[2], "List of available minigames: ".implode(", ",GameManager::$games_list));
				pm($p[2], "Usage: /order <minigame>");
				continue;
			}
			if(Shop::buy($p[2], $p[1])) {
				c($players[$p[2]]." 0xaadefforders ".$p[5]::$display_name." for the next dessert! (in ".($dessertRounds-($roundsPlayed%$dessertRounds))." rounds)");
				$next_game = [$p[2], $p[5]];
			}
		}
		else if($p[1] == "/buffet") {
			if(empty($p[5]) || !in_array($p[5], GameManager::$games_list)) {
				if(!empty($p[5]))
					pm($p[2], "0xff8080Error: Invalid minigame name.");
				pm($p[2], "List of available minigames: ".implode(", ",GameManager::$games_list));
				pm($p[2], "Usage: /buffet <minigame>");
				continue;
			}
			if(Shop::buy($p[2], $p[1])) {
				c($players[$p[2]]." 0xaadefforders ".$p[5]::$display_name." for the rest of the match!");
				$roundsPlayed = 1;
				$game->pickGame($p[2], $p[5]);
			}
		}
		else if($p[1] == "/speed")
		{
			if(Shop::buy($p[2], $p[1])) {
				if($turbos[$p[2]] < 1)
					$turbos[$p[2]] += 1;
				else
					$turbos[$p[2]] += 0.5;
				c($players[$p[2]]." 0xaadeffbuys extra speed! (".($turbos[$p[2]]*50).")");
			}
		}
		else if($p[1] == "/tel" || $p[1] == "/t" || $p[1] == "/tele" || $p[1] == "/teleport")
		{
			$p[1] = "/tel"; 
			$name = $p[2];
			if($playerStat[$name]->teles <= 0)
			{
				if(!Shop::buy($name, $p[1])) 
					continue;
				$playerStat[$name]->teles = 4;
				pm($name, "You had no teleports left so you bought ".$playerStat[$name]->teles." more.");
				pm($name,"0xRESETTHint: You can also jump to a random location by typing: {$pink}/tel r");
			}
			if($p[5] == "r")
				$cmd = mt_rand(10,490)." ".mt_rand(10,490)." abs";
			else
				$cmd = "30";
			$playerStat[$name]->teles--;
			pm($name, "0xRESETT".$playerStat[$name]->teles." teles remaining.");
			s("TELEPORT ".$name." ".$cmd);
			c($players[$name]." 0xaadeffteleports!");
		}
		else if($p[1] == "/play" || $p[1] == "/mode")
		{
			if(!is_admin($p))
				continue;
			if($game->pickGame($p[2], $p[5], true))
				$next_game = [$p[2], $p[5]];
		}
		else if($p[1] == "/end")
		{
			if(!is_admin($p))
				continue;
			$game->endGame($p[2]);
		}
		else if($p[1] == "/reshuffle")
		{
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
				$tmp = $game->cur_game;
				pm($p[2], "Round: ".$roundsPlayed." | Game: ".$tmp::$display_name." | Players: ".implode(", ",$players));
				continue;
			}
			if(is_numeric($p[5])) {
				$input = abs(round($p[5]));
				c("Admin: Setting round number to ".$input);
				$roundsPlayed = $input;
			}
			if($p[5] == "write") {
				writePlayerToFile($p[2], $playerStat[$p[2]]);
			}
			if($p[5] == "read") {
				if(!array_key_exists($p[2], $playerStat))
					$playerStat[$p[2]] = readPlayerFromFile($p[2]);
			}
			if($p[5] == "credits") {
				$playerStat[$p[2]]->credits = $p[6];
			}
		}
		else if($p[1] == "/playlist" || $p[1] == "/minigames")
		{
			if(!is_admin($p))
				continue;
			$game->playlist($p[2]);
		}
		else if($p[1] == "/a" || $p[1] == "/t" || $p[1] == "/c")
		{
			$game->cur_game->playercmd($p[2]);
		}
		else {
			pm($p[2], "Invalid command ".$p[1].", try: 0xffffff/stats /shop /res /now /order /buffet /speed /tel /a");
			if(is_admin($p, false))
				pm($p[2], "0x808080Admin commands: /playlist /play /end /reshuffle /debug");
		}
	}
	if($p[0] == "GAME_TIME")
	{
		$game_time = $p[1];
		$game->cur_game->timedEvents($p[1]);
		foreach($turbos as $i=>$t)
			s("SET_CYCLE_SPEED $i ".($t*50));
	}
	if($p[0] == "TARGETZONE_PLAYER_ENTER") //TARGETZONE_PLAYER_ENTER 2 zonename 100 100 dukevin 90.8824 103.506 1 0 37.6366
	{
		$game->cur_game->targetZoneEnter($p);
	}
	if($p[0] == "ROUND_FINISHED" || $p[0] == "MATCH_ENDED")
	{
		if(++$roundsPlayed % $dessertRounds == 0)
		{
			if(!empty($next_game))
				$game->pickGame($next_game[0], $next_game[1]);
			else {
				c("Picking dessert...");
				$game->randomGame();
			}
		}
		else if(!is_a($game->cur_game, "None") && ($roundsPlayed-1) % $dessertRounds == 0)
		{
			unset($game->cur_game, $next_game);
			$game->cur_game = new None();
		}
		else
		{
			if($roundsPlayed % $dessertRounds == 0)
				c($pink."Dessert time!");
			else
				c($pink.($dessertRounds-($roundsPlayed%$dessertRounds))."0xffffff more rounds until dessert");
		}
		foreach($playerStat as $i=>&$ps) {
			$ps->time += $game_time;
			$ps->roundsPlayed++;
		}
	}
	if($p[0] == "ROUND_COMMENCING") //ROUND_COMMENCING 6 10
	{
		unset($ais, $turbos);
		$ais = array();
		$turbos = array();
	}
	if($p[0] == "ROUND_WINNER")
	{
		if(!array_key_exists($p[2], $players))
			continue;
		$playerStat[$p[2]]->roundsWon++;
	}
	if($p[0] == "ROUND_STARTED")
	{
		$game->cur_game->roundStart();
		if(!is_a($game->cur_game, "None"))
			$game->cur_game->displayInfo();
		else
		{
			switch(rand(1,4))
			{
				case 1: $blurb = "Open play is encouraged by settings, so try to follow"; break;
				case 2: $blurb = "Rubber depletes faster during backdoors and grinds"; break;
				case 3: $blurb = "Use brakes (v) to help with escapes; no need to run"; break;
				case 4: $blurb = "Winning matches gives you credits to spend by typing 0xffa0d0/shop"; break;
			}
			c("0xffffff# 0xffaa92Be 0xaf5617s0xd39d59a0x86b325n0x789919d0xd12e15w0xa2548ei0xffb830c0xc27938h0xd2883fe0xd39d59d0xRESETT: 0xa0a0a0".$blurb);
		}
	}
	if($p[0] == "PLAYER_ENTERED_GRID" || $p[0] == "PLAYER_RENAMED") //PLAYER_ENTERED_GRID uniquename 73.134.88.149 uniqueName //PLAYER_RENAMED uniquename dukevin@rx 73.134.88.149 1 uniqueName
	{
		$raw_name = $p[1];
		$display_name = $p[3];
		if($p[0] == "PLAYER_RENAMED") {
			$raw_name = $p[2];
			$display_name = $p[5];
			if(array_key_exists($p[1], $players)) //don't write if a spectator renames
				if(is_auth($p[1]) || $playerStat[$p[1]]->time > 600) //only write logged in with 600+
					writePlayerToFile($p[1], $playerStat[$p[1]]);
			unset($players[$p[1]]);
			unset($playerStat[$p[1]]);
		}
		$players[$raw_name] = "";
		$playerStat[$raw_name] = readPlayerFromFile($raw_name);
		printStats($raw_name, false, $display_name);
	}
	if($p[0] == "PLAYER_AI_ENTERED")
		$ais[] = $p[1];
	if($p[0] == "PLAYER_COLORED_NAME")
	{
		if(!array_key_exists($p[1], $players)) //only add colored name if the person is in the players array
			continue;
		$raw_name = $p[1];
		array_shift($p);
		array_shift($p);
		$players[$raw_name] = implode(" ",$p);
	}
	if($p[0] == "PLAYER_LEFT")
	{
		$playerStat[$p[1]]->time += $game_time;
		printStats($p[1], false, $players[$p[1]]);
		if(!is_auth($p[1]) && $playerStat[$p[1]]->time < 600 || !array_key_exists($p[1], $playerStat)) 
		{ 
			if(!array_key_exists($p[1], $playerStat))
				c("Not saving data for ".$p[1]);
			else
				c("Not saving data for ".$p[1]." as they are not logged in and played less than 10 mins.");
		}
		else
			writePlayerToFile($p[1], $playerStat[$p[1]]);
		unset($playerStat[$p[1]]);
		unset($players[$p[1]]);
	}
	if($p[0] == "MATCH_ENDED")
	{
		if($roundsPlayed % $dessertRounds != 0)
			$game->endGame();
		foreach($playerStat as $i=>&$ps)
			$ps->matchesPlayed++;
		foreach($players as $i=>$_)
			printStats($i);
		//show ladder scores
	}
	if($p[0] == "MATCH_WINNER") //MATCH_WINNER uniquename dukevin@rx
	{
		if(!array_key_exists($p[2], $players))
			continue;
		if(sizeof($p) > 3) //it was a team game
		{
			foreach($players as $i=>$_)
			{
				if(in_array($i, $p)) {
					c("0xffffff# ".$players[$i]."0x00ffff earns {$pink}\$3 0x00fffffor being on the winning team!");
					$playerStat[$i]->credits += 3;
					$playerStat[$i]->matchesWon++;
					$playerStat[$i]->roundsWon++;
				}
				else {
					pm($i, "0xRESETTYou earned {$pink}\$1 0xRESETTcredit for participating. Spend credits by typing 0xaadeff/shop");
					$playerStat[$i]->credits += 1;
				}
				
			}
			continue;
		}
		c("0xffffff# ".$players[$p[2]]."0x00ffff earns {$pink}\$5 0x00ffffcredits for winning!");
		$playerStat[$p[2]]->credits += 5;
		foreach($players as $i=>$_)
		{
			if($i == $p[2]) {
				$playerStat[$i]->matchesWon++;
				$playerStat[$i]->roundsWon++;
				continue;
			}
			$playerStat[$i]->credits += 1;
			pm($i, "0xRESETTYou earned {$pink}\$1 0xRESETTcredit for participating. Spend credits by typing 0xaadeff/shop");
		}
	}
	if(preg_match("/^DEATH_FRAG|DEATH_ZOMBIEZONE|DEATH_SHOT_FRAG|DEATH_DEATHZONE|DEATH_SHOT_SUICIDE|DEATH_RUBBERZONE/", $line))
	{
		if(!array_key_exists($p[1], $playerStat))
			continue;
		$playerStat[$p[1]]->deaths += 1;
		if(!empty(trim($p[2])))
			if(array_key_exists($p[2], $playerStat))
				$playerStat[$p[2]]->kills += 1;
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
			return unserialize($col[1]);
	}
	return new PlayerStat();
}
class PlayerStat
{
	public function __construct() {
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
}
class Shop
{
	static $wares = [
		'/res' => ['name'=>"Respawns", 'cost'=>1,      'description'=>"Respawn any time on command", 'command'=>"/res"],
		'/speed' => ['name'=>"Speed", 'cost'=>1,       'description'=>"Your speed is doubled for the round", 'command'=>"/speed"],
		'/tel' => ['name'=>"Teleport", 'cost'=>1,       'description'=>"Teleport yourself. Contains 4 per purchase", 'command'=>"/tel"],
		'/now' => ['name'=>"Dessert Now", 'cost'=>3,   'description'=>"Go straight into the dessert round", 'command'=>"/now"], //Life's short, eat dessert first
		'/order'=>['name'=>"Special Order", 'cost'=>4, 'description'=>"Choose the next dessert minigame", 'command'=>"/order"],
		'/buffet'=>['name'=>"Buffet", 'cost'=>9,       'description'=>"A dessert lasting the whole match", 'command'=>"/buffet"]
	];
	static $header = " Shop ";
	static function view($p)
	{
		global $playerStat;
		$footer = " You have \$".$playerStat[$p]->credits." ";
		$len = 56-strlen(Shop::$header);
		$len2 = 56-strlen($footer);
		pm($p,"0xffffff".str_repeat("-",$len/2).Shop::$header.str_repeat("-",$len/2));
		foreach(Shop::$wares as $item)
			pm($p, $item['command']."0x808080".str_repeat(".", 9-strlen($item['command']))."0x50a0ff".$item['name']."0x808080".str_repeat(".", 15-strlen($item['name']))."0xffff00\$".$item['cost']."0x808080".str_repeat(".",5).$item['description']);
		pm($p,"0xffffff".str_repeat("-",$len2/2).$footer.str_repeat("-",$len2/2));
	}
	static function buy($p, $cmd)
	{
		global $playerStat;
		$ps = $playerStat[$p];
		if(!array_key_exists($cmd, Shop::$wares))
			return false;
		$cost = Shop::$wares[$cmd]['cost'];
		if($ps->credits < $cost) {
			pm($p, "You don't have enough credits. $cmd costs \$".$cost." you only have $".$ps->credits);
			return false;
		}
		$game = GameManager::getInstance();
		$mode = $game->cur_game;
		if(($cmd == "/speed" || $cmd == "/tele") && (is_a($mode,"htf") || is_a($mode,"ctf")))
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
function printStats($player, $pm = true, $display_name = "")
{
	global $playerStat;
	if(!array_key_exists($player, $playerStat)) {
		c($player." stats not loaded");
		return;
	}
	$wht = "0xRESETT";
	$gry = "0xa0a0f0";
	$scores = readLadder($player);
	$ps = $playerStat[$player];
	if($pm)
		$subject = "Your";
	else
		$subject = $display_name."{$gry}'s";
	$str1 = $gry.$subject." Overall Rank: {$wht}".$scores[0]."{$gry}, K/D: {$wht}".round($ps->kills/$ps->deaths,2)."{$gry}, Kills: {$wht}".$ps->kills."{$gry}, Credits: {$wht}\$".$ps->credits;
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
	public static $games_list = ["shooting", "map", "axes", "sumo", "fort", "nano", "htf", "koh", "dz", "collecting", "turbo", "pets", "teams", "ctf", "macro", "bots", "longwall", "bone", "classic", "bombs"];
	private $games_available;

	public function __construct()
	{
		$this->cur_game = new None();
		$this->games_available = GameManager::$games_list;
		shuffle($this->games_available);
	}
	public function reshuffle()
	{
		$this->cur_game = new None();
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
		$str = strtolower($str);
		if(in_array($str, GameManager::$games_list) && class_exists($str))
		{
			if($as_admin)
				c("By order of 0xffffff".$player."0xaadeff: The mode is set to ".$str::$display_name."");
			unset($this->cur_game);
			s("WAIT_FOR_EXTERNAL_SCRIPT_TIMEOUT 4");
			s("CENTER_MESSAGE ".$str::$display_name);
			$this->cur_game = new $str();
			$this->cur_game->roundStart(); //not needed, but makes it easier to debug by starting game prematurely
		}
		else {
			if(empty($str)) {
				c("Usage: /play <gametype>");
				return false;
			}
			c("Error: '".$str."' is not a valid minigame, available are: ".implode(", ",GameManager::$games_list));
			return false;
		}
		return true;
	}
	public function endGame($player = null)
	{
		$game = $this->cur_game;
		if($player != null && $this->cur_game != null)
			c("By order of 0xffffff".$player."0xaadeff: The mode ".$game::$display_name." has ended");
		unset($this->cur_game);
		$this->cur_game = new None();
	}
	public function playlist($player)
	{
		global $pink;
		foreach($this->games_available as $i=>$g)
		{
			if($i==0)
				pm($player, $pink." | 0xffffffNext > 0xaadeff".$g);
			else
				pm($player, $pink." | 0xffffff".($i+1).". 0xaadeff".$g);
		}
		foreach(GameManager::$games_list as $g)
		{
			if(!in_array($g, $this->games_available))
				pm($player, $pink." | 0x808080Done - 0xa0a0a0".$g);
		}
	}
}

abstract class Minigame
{
	abstract public function __construct();			//minigame settings
	abstract public function __destruct();			//undo minigame settings for regular play
	public function timedEvents($time) {}			//events that require time during the minigame
	public function targetZoneEnter($arr) {}		//ladderlog event when a player hits a targetzone as an array
	public function roundStart() {}					//triggered when the ROUND_COMMENCING event happens, good time to spawn zones
	public function playercmd($player) {pm($player, "This mode has no special action you can perform.");} //player typed /a
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
		s("WAIT_FOR_EXTERNAL_SCRIPT_TIMEOUT 0");
		s("SIZE_FACTOR -3");
		s("RESOURCE_REPOSITORY_SERVER http://rxtron.com/aa/resource/");
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
	function __construct()
	{
		$rand = mt_rand(3,8);
		if($rand == 4)
			$rand = 6;
		s("ARENA_AXES ".$rand);
		Axes::$description = "The axes have changed to ".$rand;
	}
	function __destruct()
	{
		s("ARENA_AXES 4");
	}
}
class Collecting extends Minigame
{
	static $display_name = "Coin Collecting";
	static $description = "Collect enough coins for points 0x808080(5 coins = 1 pt)";
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
			$speed = mt_rand(10,115);
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
			$speed = mt_rand(-130,130);
			s("SPAWN_ZONE n coin target ".mt_rand(10,490)." ".mt_rand(10,490)." 3 0 ".$speed." ".$speed." true 255 255 0 0 1");
		}
		if($time % 45 == 0 && $time != 0)
		{
			$speed = mt_rand(10,150);
			s("SPAWN_ZONE n coin target L 256 316 298 298 316 256 298 214 256 196 214 214 196 256 214 298 256 376 340 340 376 256 340 172 256 136 172 172 136 256 172 340 256 436 383 383 436 256 383 129 256 76 129 129 76 256 129 383 256 496 425 425 496 256 425 87 256 16 87 87 16 256 87 425 500 500 500 0 0 0 0 500 0 500 Z 3 0 $speed $speed true 255 255 0 0 1");
			s("SPAWN_ZONE n coin target L 298 298 316 256 298 214 256 196 214 214 196 256 214 298 256 376 340 340 376 256 340 172 256 136 172 172 136 256 172 340 256 436 383 383 436 256 383 129 256 76 129 129 76 256 129 383 256 496 425 425 496 256 425 87 256 16 87 87 16 256 87 425 500 500 500 0 0 0 0 500 0 500 Z 3 0 $speed $speed true 255 255 0 0 1");
			s("SPAWN_ZONE n coin target L 316 256 298 214 256 196 214 214 196 256 214 298 256 376 340 340 376 256 340 172 256 136 172 172 136 256 172 340 256 436 383 383 436 256 383 129 256 76 129 129 76 256 129 383 256 496 425 425 496 256 425 87 256 16 87 87 16 256 87 425 500 500 500 0 0 0 0 500 0 500 Z 3 0 $speed $speed true 255 255 0 0 1");
			s("SPAWN_ZONE n coin target L 298 214 256 196 214 214 196 256 214 298 256 376 340 340 376 256 340 172 256 136 172 172 136 256 172 340 256 436 383 383 436 256 383 129 256 76 129 129 76 256 129 383 256 496 425 425 496 256 425 87 256 16 87 87 16 256 87 425 500 500 500 0 0 0 0 500 0 500 Z 3 0 $speed $speed true 255 255 0 0 1");
			s("SPAWN_ZONE n coin target L 256 196 214 214 196 256 214 298 256 376 340 340 376 256 340 172 256 136 172 172 136 256 172 340 256 436 383 383 436 256 383 129 256 76 129 129 76 256 129 383 256 496 425 425 496 256 425 87 256 16 87 87 16 256 87 425 500 500 500 0 0 0 0 500 0 500 Z 3 0 $speed $speed true 255 255 0 0 1");
		}
	}
	function targetZoneEnter($event)
	{
		global $players;
		$player = $event[5];
		$this->coins[$player] += 1;
		if($this->coins[$player] == 5)
		{
			s("ADD_SCORE_PLAYER ".$player." 1");
			$this->coins[$player] = 0;
			c($players[$player]."0xaadeff earns 1 point!");
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
	function __construct()
	{
		if(Map::$play == 0)
			shuffle(Map::$maps);
		$map_name = explode("-",Map::$maps[Map::$play]);
		if($map_name[1] < 1000) //its not a wik map
		{
			s("RESOURCE_REPOSITORY_SERVER http://rxtron.com/aa/resource/");
			s("MAP_FILE rxfreaks/hft/".Map::$maps[Map::$play].".aamap.xml");
			c("0xbf00bfMap0xffffff: $map_name[0] \n");
			Map::$cur_map = $map_name[0];
		}
		else
		{
			s("RESOURCE_REPOSITORY_SERVER http://rxtron.com/aa/resource/");
			s("MAP_FILE Wik/dogfight/".Map::$maps[Map::$play].".aamap.xml");
			c("0xbf00bfMap: $map_name[0]\n");
			Map::$cur_map = $map_name[0];
		}
		if(++Map::$play >= sizeof(Map::$maps))
			Map::$play = 0;
	}
	function __destruct()
	{
		Map::$cur_map = "none";
		s("RESOURCE_REPOSITORY_SERVER http://rxtron.com/aa/resource/");
		s("MAP_FILE Anonymous/polygon/regular/square-1.0.1.aamap.xml");
	}
}
class Fort extends Minigame
{
	static $display_name = "Wild Fort";
	static $description = "Team-based fortress with wacky maps"; 
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
	function __construct()
	{
		s("INCLUDE teams.cfg");
		s("INCLUDE fort.cfg");
		s("RESOURCE_REPOSITORY_SERVER http://rxtron.com/aa/resource/");
		$line = explode("|",Fort::$maps[mt_rand(0,sizeof(Fort::$maps)-1)]);
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
		c("0xbf00bfMap: ".$this->name);
		c($this->hint);
	}
	function timedEvents($time)
	{
		if($time == 0) {
			c("0xbf00bfMap: ".$this->name);
			c($this->hint);
		}
	}
	function __destruct()
	{
		foreach($this->settings as $s) {
			$s = explode(" ",$s)[0];
			undo($s);
		}
		unload("fort.cfg");
		unload("teams.cfg");
		s("RESOURCE_REPOSITORY_SERVER http://rxtron.com/aa/resource/");
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
		$this->respawn_time = 10;
		s("KOH_SCORE 2");
		s("SCORE_KILL 0");
	}
	function roundStart()
	{
		s("RESPAWN_TIME 10");
		$rand = mt_rand(1,4);
		if($rand == 1)
		{
			//if(mt_rand(1,2) == 1)
			//	s("INCLUDE teams.cfg");
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
			//if(mt_rand(1,4) == 1)
			//	s("INCLUDE teams.cfg");
		}
		if($rand == 4)
		{
			//if(mt_rand(1,2) == 1)
			//	s("INCLUDE teams.cfg");
			$speed = mt_rand(1,50);
			s("KOH_SCORE 2");
			s("SPAWN_ZONE koh L 100 100 100 400 400 400 400 100 Z ".mt_rand(30, 50)." 0 $speed $speed 0 false");
		}
	}
	function timedEvents($time)
	{
		if($time % 60 == 0 && $time != 0) 
		{
			$this->respawn_time += 5;
			s("RESPAWN_TIME ".$this->respawn_time);
		}
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
	static $description = "There's a lot of bots who want you dead 0x808080(0 pts for kills, 5 pts for surviving)";
	function __construct()
	{
		$num_ais = mt_rand(5, 21);
		s("NUM_AIS ".$num_ais);
		s("SP_NUM_AIS ".$num_ais);
		s("NUM_AIS_PER_ROUND ".$num_ais);
		s("SCORE_KILL 0");
		s("SCORE_WIN 5");
	}
	function timedEvents($time)
	{
		global $ais;
		if($time % 5 == 0) 
		{
			foreach($ais as $a)
				s("SET_CYCLE_RUBBER $a 12");
		}
		if($time % 30 == 0 && $time != 0)
			s("AI_IQ 0");
		if($time % 60 == 0 && $time != 0) {
			c("Shooting enabled! Hold brakes (v) to fire.");
			s("INCLUDE shooting.cfg");
		}
		if($time >= 90) 
		{
			if($time % 90 == 0)
				c("Bot speed decrease!");
			foreach($ais as $a)
				s("SET_CYCLE_SPEED $a 0");
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
		s("SPAWN_ZONE rubber L 400 100 400 400 100 400 100 100 Z 20 0 0 30 -0.2 false 4.5 9 15 0");
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
	static $description = "Normal settings, but work as a team!";
	function __construct()
	{
		s("INCLUDE teams.cfg");
	}
	function __destruct()
	{
		unload("teams.cfg"); //doesn't work
	}
}
class Turbo extends Minigame
{
	static $display_name = "Turbo Button";
	static $description = "Press brakes (v) to boost";
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
				s("SPAWN_ZONE zombieOwner $i $i 250 250 10 0 0 0 false");
	}
	function __destruct()
	{
		undo("SCORE_KILL");
		undo("SCORE_ZOMBIE_ZONE");
		undo("ZOMBIE_ZONE_SPEED");
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
}
class Macro extends Minigame
{
	static $display_name = "Macro";
	static $description = "Huge map, huge acceleration. Touch zones for speed";
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
		s("RESOURCE_REPOSITORY_SERVER http://rxtron.com/aa/resource/");
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
	}
	function __destruct()
	{
		undo("FLAG_TEAM");
		undo("FLAG_HOLD_SCORE");
		undo("FLAG_HOLD_SCORE_TIME");
		undo("CYCLE_ACCEL_RIM");
		undo("RESPAWN_TIME");
		s("MAP_FILE Anonymous/polygon/regular/square-1.0.1.aamap.xml");
	}
}
class Ctf extends Minigame
{
	static $display_name = "Capture the Flag";
	static $description = "Team based CTF! 0x808080(Flag capture = 15pts)";
	function __construct()
	{
		s("INCLUDE teams.cfg");
		s("INCLUDE ctf.cfg");
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
	static $description = "Press brakes (v) to leave a bomb 0x808080(Bomb kills = 3 pts, regular kills = 0 pts)";
	function __construct()
	{
		s("INCLUDE bombs.cfg");
	}
	function __destruct()
	{
		unload("bombs.cfg");
	}
}

function readLadder($p)
{
	global $dir;
	$lf = file($dir."ladder.txt");
	$scores = ["None",0,0];
	foreach($lf as $i=>$l)
	{
		if(preg_match("/$p/", $l)) {
			$scores[0] = $i+1;
			break;
		}
	}
	$lf = file($dir."won_rounds.txt");
	foreach($lf as $i=>$l)
	{
		$a = preg_split('/\s\s+/', $l);
		if(trim($a[1]) == $p) {
			$scores[1] = $a[0];
			break;
		}
	}
	$lf = file($dir."won_matches.txt");
	foreach($lf as $i=>$l)	
	{
		$a = preg_split('/\s\s+/', $l);
		if(trim($a[1]) == $p) {
			$scores[2] = $a[0];
			break;
		}
	}
	return $scores;
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
	if($p instanceof Player)
		$p = $p->name;
	echo "player_message {$p} \"0xaadeff{$string}\"\n ";
}
?>