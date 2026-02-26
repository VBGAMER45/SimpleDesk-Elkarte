<?php
/**
 * SimpleDesk Board Index Template
 *
 * Templates for board index helpdesk integration.
 * Renders custom helpdesk icon and stats on the board index page.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Renders a custom icon for helpdesk "boards" on the board index.
 *
 * Displays an anchor element styled with the board's CSS class and
 * an optional tooltip for helpdesk entries shown on the board index.
 *
 * @param array $board Board data with keys: board_class, href, board_tooltip.
 */
function template_bi_shd_icon($board)
{
	echo '
		<a href="', $board['href'], '" class="board_icon ', $board['board_class'], '"', !empty($board['board_tooltip']) ? ' title="' . $board['board_tooltip'] . '"' : '', '></a>';
}

/**
 * Renders helpdesk-related statistics on the board index.
 *
 * Placeholder function for board index stats integration.
 * Can be expanded to display ticket counts, open/closed stats, etc.
 */
function template_bi_shd_stats()
{
	// Placeholder - can be expanded to show helpdesk stats on the board index.
}
