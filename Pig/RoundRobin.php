<?php

class Pig_RoundRobin {
	private $teams;
	private $pairs;
	private $games;
	private $numrevolutions;

	public function __construct($teams, $numrevolutions) {
		$this->teams = $teams;		// copy the teams
		$this->pairs = array();		// working pairs
		$this->games = array();	// final matchups
		$this->numrevolutions = $numrevolutions;

		$this->roundRobin();
		$this->optimizeHomeGames();
		$this->formRevolutions();
	}

	/*
	 * Returns array of rounds which are
	 * arrays of matches which are
	 * arrays of teams where [0] is home and [1] is visitor.
	 * Number of rounds is count($return),
	 * number of games per round is count($return[0]).
	 */
	public function getGames() {
		return $this->games;
	}

	private function roundRobin() {
		// Form the initial pairs, round-robin algorithm, thanks to
		// http://www.devenezia.com/downloads/round-robin/index.html
		// btw: store in serialized format (use team.id)
		
		$numrounds = count($this->teams) - 1;
		for($r = 0; $r < $numrounds; $r++) {
			// generate a circle of teams for this round
			$round = array($this->teams[$numrounds]);
			for($i = 0; $i < $numrounds; $i++) {
				$round[] = $this->teams[($r+$i) % ($numrounds)];
			}
			
			// form the pairs by taking the first and last of the array
			$start = 0;
			$end = count($round) - 1;
			while($start < $end) {
				$this->pairs[] = array($round[$start], $round[$end]);
				$start++;
				$end--;
			}
		}
	}

	private function optimizeHomeGames() {
		// Now we have nice schedule with rounds in pairs as numteams/2 blocks,
		// Then we optimize the home/away games by swapping the pairs if the
		// visitor has more away-games or is on longer away-streak (which at the
		// same time turns into home-team having longer home-streak). But theres
		// still a catch, every 2 rounds we swap the pairs if they have the same
		// number of homegames and streak as long.
		$numteams = count($this->teams);
		$roundgames = $numteams / 2;
		$homegames = array_fill_keys($this->teams, 0);
		$streaks = array_fill_keys($this->teams, 0);
		
		$pindex = 0;
		foreach($this->pairs as $pair) {
			// debug($pair);
			$round = floor($pindex / $roundgames);	// FIXME: $pair[n] is not index to 
			$a = $pair[0];
			$b = $pair[1];
			// so favour swapping if visitor has less homegames or are on a longer visitorstreak
			// OR if 2 teams are equal we swap them on every other round (THIS IS ELEMENTAL IN THIS!)
			if( ($homegames[$b] < $homegames[$a] || $streaks[$b] < $streaks[$a]) ||
			    ($homegames[$a] == $homegames[$b] && $streaks[$a] == $streaks[$b] && ($round & 1 == 1)) )
			{
				// swap
				// [a, b] = [b, a];
				$c = $a;
				$a = $b;
				$b = $c;
			}
			
			// emit the pair and modify homegame parameters
			$this->games[] = array($a, $b);
			$homegames[$a] += 1;
			$streaks[$a]++;
			$streaks[$b]--;

			$pindex++;
		}
	}

	private function formRevolutions() {
		// FIXMEEEE
		// final schedule combines the games in ordered -> reversed fashion
		$schedule = array();
		$numgames = count($this->games);
		if($this->numrevolutions > 1) {
			// Form the inverted revolution
			$gamesreverse = array_slice($this->games, 0);
			for($i = 0; $i < $numgames; $i++)
				$gamesreverse[$i] = array($gamesreverse[$i][1], $gamesreverse[$i][0]);
			// Now form the final season by switching between normal and inverted
			for($i = 0; $i < $this->numrevolutions; $i++) {
				// ARGH.. put games or gamesreverse into the list
				// AND WEIRD BUG IN ARRAY CONCANATING
				if(($i & 1) == 0)
					$schedule = array_merge($schedule, $gamesreverse);
				else
					$schedule = array_merge($schedule, $this->games);
			}
		}
		else {
			$schedule = $this->games;
		}
		
		// Reform into games
		// small step.. randomize each 'roundgames' section
		$this->games = array();
		$numgames = count($schedule);
		$roundgames = count($this->teams) / 2;
		for($i = 0; $i < $numgames; $i += $roundgames) {
			$matches = array_slice($schedule, $i, $roundgames);
			shuffle($matches);
			$this->games[] = $matches;
		}
	}
}

/*
// TEST
if(true) {
	$rr = new Pig_RoundRobin(array(1,2,3,4), 2);
	$games = $rr->getGames();
	debug(count($games), count($games[0]), $games);
}
*/