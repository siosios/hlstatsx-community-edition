<?php
/*
	HLstatsX Community Edition - Real-time player and clan rankings and statistics
	Copyleft (L) 2008-20XX Nicholas Hastings (nshastings@gmail.com)
	http://www.hlxcommunity.com

	HLstatsX Community Edition is a continuation of 
	ELstatsNEO - Real-time player and clan rankings and statistics
	Copyleft (L) 2008-20XX Malte Bayer (steam@neo-soft.org)
	http://ovrsized.neo-soft.org/

	ELstatsNEO is an very improved & enhanced - so called Ultra-Humongus Edition of HLstatsX
	HLstatsX - Real-time player and clan rankings and statistics for Half-Life 2
	http://www.hlstatsx.com/
	Copyright (C) 2005-2007 Tobias Oetzel (Tobi@hlstatsx.com)

	HLstatsX is an enhanced version of HLstats made by Simon Garner
	HLstats - Real-time player and clan rankings and statistics for Half-Life
	http://sourceforge.net/projects/hlstats/
	Copyright (C) 2001  Simon Garner
				
	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

	For support and installation notes visit http://www.hlxcommunity.com
*/

    if (!defined('IN_HLSTATS')) {
        die('Do not access this file directly.');
    }

	// Global Server Chat History
	$showserver = 0;
	if (isset($_GET['server_id'])) {
		$showserver = valid_request(strval($_GET['server_id']), true);
	}

	if ($showserver == 0) {
		$whereclause = "hlstats_Servers.game='$game'";
	} else {
		$whereclause = "hlstats_Servers.game='$game' AND hlstats_Events_Chat.serverId=$showserver";
	}

	$db->query
	("		
		SELECT
			hlstats_Games.name
		FROM
			hlstats_Games
		WHERE
			hlstats_Games.code = '$game'
	");

	if ($db->num_rows() < 1) {
        error("No such game '$game'.");
	}

	list($gamename) = $db->fetch_row();

	$db->free_result();

	pageHeader
	(
		array ($gamename, 'Server Chat Statistics'),
		array ($gamename=>"%s?game=$game", 'Server Chat Statistics'=>'')
	);

	flush();

	$servername = "(All Servers)";
	
	if ($showserver != 0)
	{
		$result=$db->fetch_array
		(
			$db->query
			("
				SELECT
					hlstats_Servers.name
				FROM
					hlstats_Servers
				WHERE
					hlstats_Servers.serverId = ".$db->escape($showserver)."
			")
		);
		$servername = "(" . $result['name'] . ")";
	}

	$delaySql = "";
	$delayChat = (int)CHAT_DELAY_TIME;

	if ($delayChat > 0 && $delayChat <= CHAT_DELAY_MAX_TIME) {
		$delayChatSafeSql = $db->escape($delayChat);
		$delaySql = "AND `eventTime` <= (NOW() - INTERVAL {$delayChatSafeSql} MINUTE)";
	}

	$serversList = getServersByGame($db, $checkGame);
	$filter = getChatFilterParam();

	$deleteDaysSafe = isset($g_options['DeleteDays']) ? (int)$g_options['DeleteDays'] : 30;
	$pageTitle = sprintf('%s %s Server Chat Log (Last %d Days)', eHtml($gamename), eHtml($servername), $deleteDaysSafe);
	$sectionTitle = printSectionTitle($pageTitle);

	// Functions
	// Old limit 50
	function getServersByGame($db, $game, $limit = 0)
	{
		$sqlLimit = "";
		$limit = (int)$limit;
		if ($limit > 0) {
			$sqlLimit = "LIMIT {$limit}";
		}

		$game = $db->escape($game);
		$serversSql = "
			SELECT
				hlstats_Servers.serverId,
				hlstats_Servers.name
			FROM
				hlstats_Servers
			WHERE
				hlstats_Servers.game = '{$game}'
			ORDER BY
				hlstats_Servers.sortorder,
				hlstats_Servers.name,
				hlstats_Servers.serverId ASC
			{$sqlLimit}
		";

		$servers = [];
		$result = $db->query($serversSql);
		if (!$result) {
			return $servers;
		}

		while ($row = $db->fetch_array($result)) {
			$servers[] = [
				'serverId' => (int)$row['serverId'],
				'name'     => $row['name']
			];
		}

		$db->free_result($result);
		return $servers;
	}
?>

<div class="block">
	<?=$sectionTitle;?>
	
	<div class="subblock">
		<div style="float:left;">
			<span>
			<form method="get" action="<?php echo $g_options['scripturl']; ?>" style="margin:0px;padding:0px;">
				<input type="hidden" name="mode" value="chat" />
				<input type="hidden" name="game" value="<?php echo $game; ?>" />
				<strong>&#8226;</strong> Show Chat from

				<select name="server_id">
					<option value="0">All Servers</option>

					<?php foreach($serversList as $srv) : ?>
						<?php $selected = ($showserver == $srv['serverId']) ? 'selected' : ''; ?>

						<option value="<?=eHtml($srv['serverId']);?>" <?=$selected;?>>
							<?=eHtml($srv['name']);?>
						</option>
					<?php endforeach; ?>
				</select>

				Filter: <input type="text" name="filter" value="<?=eHtml($filter); ?>" /> 
				<input type="submit" value="View" class="smallsubmit" />
			</form>
			</span>
			<?php if (!empty($delaySql)) : ?>
				<div style="font-size:0.9em; color:#8d90a3; margin-top:10px;">
					*Messages are delayed by <?=eHtml($delayChat);?> minutes to prevent real-time tracking.
				</div>
			<?php endif; ?>
		</div>
	</div>
	<div style="clear:both;padding-top:10px;"></div>
		<?php
			if ($showserver == 0)
			{
				$table = new Table(
					array
					(
						new TableColumn
						(
							'eventTime',
							'Date',
							'width=16'
						),
						new TableColumn
						(
							'lastName',
							'Player',
							'width=17&sort=no&flag=1&link=' . urlencode('mode=playerinfo&amp;player=%k')
						),
						new TableColumn
						(
							'message',
							'Message',
							'width=34&sort=no&embedlink=yes'
						),
						new TableColumn
						(
							'serverName',
							'Server',
							'width=23&sort=no'
						),
						new TableColumn
						(
							'map',
							'Map',
							'width=10&sort=no'
						)
					),
					'playerId',
					'eventTime',
					'lastName',
					false,
					50,
					"page",
					"sort",
					"sortorder"
				);
			}
			else
			{
				$table = new Table(
					array
					(
						new TableColumn
						(
							'eventTime',
							'Date',
							'width=16'
						),
						new TableColumn
						(
							'lastName',
							'Player',
							'width=24&sort=no&flag=1&link=' . urlencode('mode=playerinfo&amp;player=%k')
						),
						new TableColumn
						(
							'message',
							'Message',
							'width=44&sort=no&embedlink=yes'
						),
						new TableColumn
						(
							'map',
							'Map',
							'width=16&sort=no'
						)
					),
					'playerId',
					'eventTime',
					'lastName',
					false,
					50,
					"page",
					"sort",
					"sortorder"
				);
			}

			$whereclause2 = buildSearchSqlSafe($db, $filter);

			$result = $db->query
			("
				SELECT SQL_NO_CACHE 
					hlstats_Events_Chat.eventTime,
					unhex(replace(hex(hlstats_Players.lastName), 'E280AE', '')) as lastName,
					IF(hlstats_Events_Chat.message_mode=2, CONCAT('(Team) ', hlstats_Events_Chat.message), IF(hlstats_Events_Chat.message_mode=3, CONCAT('(Squad) ', hlstats_Events_Chat.message), hlstats_Events_Chat.message)) AS message,
					hlstats_Servers.name AS serverName,
					hlstats_Events_Chat.playerId,
					hlstats_Players.flag,
					hlstats_Events_Chat.map
				FROM
					hlstats_Events_Chat
				INNER JOIN
					hlstats_Players
				ON
					hlstats_Players.playerId = hlstats_Events_Chat.playerId
				INNER JOIN 
					hlstats_Servers
				ON
					hlstats_Servers.serverId = hlstats_Events_Chat.serverId
				WHERE
					$whereclause $whereclause2
				{$delaySql}
				ORDER BY
					hlstats_Events_Chat.id $table->sortorder
				LIMIT
					$table->startitem,
					$table->numperpage;
			", true, false);

			$db->query
			("
				SELECT
		 			count(*)
				FROM
					hlstats_Events_Chat
				INNER JOIN
					hlstats_Players
				ON
					hlstats_Players.playerId = hlstats_Events_Chat.playerId
				INNER JOIN 
					hlstats_Servers
				ON
					hlstats_Servers.serverId = hlstats_Events_Chat.serverId
				WHERE
					$whereclause $whereclause2
				{$delaySql}
			");

			if ($db->num_rows() < 1) $numitems = 0;
			else 
			{
				list($numitems) = $db->fetch_row();
			}
			$db->free_result();	

			$table->draw($result, $numitems, 95);
		?><br /><br />
	<div class="subblock">
		<div style="float:right;">
			Go to: <a href="<?php echo $g_options["scripturl"] . "?game=$game"; ?>"><?php echo $gamename; ?></a>
		</div>
	</div>
</div>
