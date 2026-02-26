<?php
/**
 * SimpleDesk Board Index Subs
 *
 * Helper functions for integrating the helpdesk into ElkArte's board index.
 * Creates fake "board" entries for helpdesk departments within board
 * categories, displays ticket counts, handles unread indicators, and
 * replaces redirect board icons with helpdesk-specific icons.
 *
 * Ported from SMF Subs-SimpleDeskBoardIndex.php to ElkArte.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Adds helpdesk departments to the board index as fake board entries.
 *
 * Called from the board index integration point. Checks if the helpdesk is
 * active and board integration is enabled, then queries accessible departments
 * that are assigned to board categories. Creates fake board entries with
 * ticket counts and unread indicators.
 *
 * @param array $boardIndexOptions Board index display options.
 * @param array &$categories The categories array (passed by reference).
 */
function shd_add_to_boardindex($boardIndexOptions, &$categories)
{
	global $modSettings, $context, $txt, $scripturl, $user_info;

	// Nothing to do if helpdesk is inactive or user is a guest.
	if (empty($modSettings['helpdesk_active']) || $user_info['is_guest'])
		return;

	require_once(SUBSDIR . '/SimpleDesk.subs.php');
	shd_init();

	// Check if the user can access the helpdesk at all.
	if (!shd_allowed_to('access_helpdesk', 0))
		return;

	loadLanguage('SimpleDesk');

	$db = database();

	// Determine which departments this user can access.
	$allowed_depts = shd_allowed_to('access_helpdesk', false);

	if (empty($allowed_depts) || $allowed_depts === false)
		return;

	// Fetch departments that are assigned to board categories (board_cat != 0).
	$request = $db->query('', '
		SELECT hd.id_dept, hd.dept_name, hd.description, hd.board_cat,
			hd.before_after, hd.dept_order
		FROM {db_prefix}helpdesk_depts AS hd
		WHERE hd.board_cat != {int:no_cat}
			AND hd.id_dept IN ({array_int:allowed_depts})
		ORDER BY hd.dept_order ASC',
		array(
			'no_cat' => 0,
			'allowed_depts' => $allowed_depts,
		)
	);

	$departments = array();
	$needed_cats = array();
	while ($row = $db->fetch_assoc($request))
	{
		$departments[$row['id_dept']] = array(
			'id_dept' => (int) $row['id_dept'],
			'dept_name' => $row['dept_name'],
			'description' => $row['description'],
			'board_cat' => (int) $row['board_cat'],
			'before_after' => (int) $row['before_after'],
			'dept_order' => (int) $row['dept_order'],
		);

		$needed_cats[$row['board_cat']] = true;
	}
	$db->free_result($request);

	// Nothing to show on the board index.
	if (empty($departments))
		return;

	// Build the department list context for ticket count queries.
	$context['dept_list'] = array();
	foreach ($departments as $id_dept => $dept)
	{
		$context['dept_list'][$id_dept] = array(
			'id_dept' => $id_dept,
			'dept_name' => $dept['dept_name'],
			'tickets' => array(
				'open' => 0,
				'closed' => 0,
			),
			'new' => false,
		);
	}

	// Load ticket counts per department.
	shd_get_ticket_counts();

	// Load unread status per department.
	shd_get_unread_departments();

	// Ensure all needed categories exist in the $categories array.
	// If a department is assigned to a category that wasn't loaded (e.g. an
	// empty category), we need to load it from the database.
	$missing_cats = array();
	foreach ($needed_cats as $cat_id => $dummy)
	{
		if (!isset($categories[$cat_id]))
			$missing_cats[] = $cat_id;
	}

	if (!empty($missing_cats))
	{
		$request = $db->query('', '
			SELECT id_cat, name, cat_order
			FROM {db_prefix}categories
			WHERE id_cat IN ({array_int:cats})
			ORDER BY cat_order ASC',
			array(
				'cats' => $missing_cats,
			)
		);

		while ($row = $db->fetch_assoc($request))
		{
			// Create a minimal category entry if it does not exist.
			if (!isset($categories[$row['id_cat']]))
			{
				$categories[$row['id_cat']] = array(
					'id' => (int) $row['id_cat'],
					'name' => $row['name'],
					'boards' => array(),
					'is_collapsed' => false,
					'new' => false,
				);
			}
		}
		$db->free_result($request);
	}

	// Now inject fake board entries for each department into the appropriate category.
	foreach ($departments as $id_dept => $dept)
	{
		$cat_id = $dept['board_cat'];

		// If the category still doesn't exist, skip.
		if (!isset($categories[$cat_id]))
			continue;

		$fake_board = shd_dept_board($dept);

		// Determine placement: before_after = 0 means before existing boards,
		// before_after = 1 means after existing boards.
		if ($dept['before_after'] == 0)
		{
			// Prepend: insert at the beginning of the boards array.
			$categories[$cat_id]['boards'] = array('shd_dept_' . $id_dept => $fake_board) + $categories[$cat_id]['boards'];
		}
		else
		{
			// Append: add at the end of the boards array.
			$categories[$cat_id]['boards']['shd_dept_' . $id_dept] = $fake_board;
		}

		// Mark the category as having new content if there are unread tickets.
		if (!empty($context['dept_list'][$id_dept]['new']))
			$categories[$cat_id]['new'] = true;
	}

	// Load the board index template for helpdesk entries.
	loadTemplate('SimpleDeskBoardIndex');
}

/**
 * Creates a fake board array entry for a helpdesk department.
 *
 * Builds a board-like array structure that the board index template can
 * render alongside real boards. The fake board uses type 'shd' and is
 * marked as a redirect so the core template treats it appropriately.
 *
 * @param array $dept Department data array with id_dept, dept_name, description.
 * @return array A fake board array compatible with the board index template.
 */
function shd_dept_board($dept)
{
	global $context, $txt, $scripturl;

	$id_dept = (int) $dept['id_dept'];
	$open_tickets = !empty($context['dept_list'][$id_dept]['tickets']['open']) ? $context['dept_list'][$id_dept]['tickets']['open'] : 0;
	$closed_tickets = !empty($context['dept_list'][$id_dept]['tickets']['closed']) ? $context['dept_list'][$id_dept]['tickets']['closed'] : 0;
	$is_new = !empty($context['dept_list'][$id_dept]['new']);

	$board_name = $dept['dept_name'];
	$board_desc = !empty($dept['description']) ? $dept['description'] : '';

	// Build a descriptive string showing ticket counts.
	$count_text = '';
	if (isset($txt['shd_open_tickets']))
		$count_text = sprintf($txt['shd_open_tickets'], $open_tickets);
	else
		$count_text = $open_tickets . ' open';

	if (isset($txt['shd_closed_tickets_short']))
		$count_text .= ', ' . sprintf($txt['shd_closed_tickets_short'], $closed_tickets);
	else
		$count_text .= ', ' . $closed_tickets . ' closed';

	return array(
		'id' => 'shd_' . $id_dept,
		'name' => $board_name,
		'description' => $board_desc,
		'new' => $is_new,
		'type' => 'shd',
		'is_redirect' => true,
		'redirect_newtab' => false,
		'moderators' => array(),
		'link' => '<a href="' . $scripturl . '?action=helpdesk;sa=main;dept=' . $id_dept . '">' . $board_name . '</a>',
		'href' => $scripturl . '?action=helpdesk;sa=main;dept=' . $id_dept,
		'board_class' => 'i-board-redirect' . ($is_new ? ' i-board-new' : ''),
		'children_new' => false,
		'topics' => $open_tickets,
		'posts' => $open_tickets + $closed_tickets,
		'children' => array(),
		'last_post' => array(
			'id' => 0,
			'time' => '',
			'timestamp' => 0,
			'subject' => '',
			'member' => array(
				'id' => 0,
				'username' => '',
				'name' => '',
				'href' => '',
				'link' => '',
			),
			'start' => 'msg0',
		),
		'shd_data' => array(
			'id_dept' => $id_dept,
			'open' => $open_tickets,
			'closed' => $closed_tickets,
			'count_text' => $count_text,
		),
	);
}

/**
 * Queries ticket counts grouped by department and status.
 *
 * Fills ticket counts (open and closed) into $context['dept_list'] for
 * each department. Uses the shd_db_query wrapper for ticket visibility
 * filtering.
 */
function shd_get_ticket_counts()
{
	global $context;

	if (empty($context['dept_list']))
		return;

	$db = database();
	$dept_ids = array_keys($context['dept_list']);

	$request = shd_db_query('', '
		SELECT hdt.id_dept, hdt.status, COUNT(*) AS num_tickets
		FROM {db_prefix}helpdesk_tickets AS hdt
		WHERE hdt.id_dept IN ({array_int:depts})
			AND {query_see_ticket}
		GROUP BY hdt.id_dept, hdt.status',
		array(
			'depts' => $dept_ids,
		)
	);

	while ($row = $db->fetch_assoc($request))
	{
		$dept_id = (int) $row['id_dept'];
		$status = (int) $row['status'];
		$count = (int) $row['num_tickets'];

		if (!isset($context['dept_list'][$dept_id]))
			continue;

		if ($status == TICKET_STATUS_CLOSED)
		{
			$context['dept_list'][$dept_id]['tickets']['closed'] += $count;
		}
		elseif ($status != TICKET_STATUS_DELETED)
		{
			// All non-closed, non-deleted statuses count as open.
			$context['dept_list'][$dept_id]['tickets']['open'] += $count;
		}
	}
	$db->free_result($request);
}

/**
 * Replaces redirect board icons with helpdesk-specific icons in the output buffer.
 *
 * Uses a regex replacement on the HTML output to swap the standard redirect
 * board icon class with the helpdesk icon class for SimpleDesk department
 * entries on the board index.
 *
 * @param string &$buffer The output buffer (passed by reference).
 */
function shd_buffer_boardindex(&$buffer)
{
	global $context;

	if (empty($context['dept_list']))
		return;

	// Replace the redirect board icon with the helpdesk icon for our fake boards.
	// The board index template uses classes like "board_icon i-board-redirect".
	// We replace with a helpdesk-specific class for each department board.
	$buffer = preg_replace(
		'~(<div[^>]*id="board_shd_\d+"[^>]*class="[^"]*)\bi-board-redirect\b~',
		'$1i-helpdesk',
		$buffer
	);

	// Also replace new-indicator icons for helpdesk boards.
	$buffer = preg_replace(
		'~(<div[^>]*id="board_shd_\d+"[^>]*class="[^"]*)\bi-board-new\b~',
		'$1i-helpdesk-new',
		$buffer
	);
}

/**
 * Checks for unread tickets in each department for the current user.
 *
 * Queries the helpdesk_log_read table to determine which departments
 * have tickets that the user has not yet read. Sets the 'new' flag
 * in $context['dept_list'] for departments with unread tickets.
 */
function shd_get_unread_departments()
{
	global $context, $user_info;

	if (empty($context['dept_list']) || empty($user_info['id']))
		return;

	$db = database();
	$dept_ids = array_keys($context['dept_list']);

	// Find departments that have tickets updated since the user last read them.
	// A ticket is unread if it has no entry in helpdesk_log_read for this user,
	// or if the ticket's last message ID is greater than the logged read message.
	$request = shd_db_query('', '
		SELECT hdt.id_dept, COUNT(*) AS unread_count
		FROM {db_prefix}helpdesk_tickets AS hdt
			LEFT JOIN {db_prefix}helpdesk_log_read AS hdlr ON (
				hdlr.id_ticket = hdt.id_ticket
				AND hdlr.id_member = {int:member}
			)
		WHERE hdt.id_dept IN ({array_int:depts})
			AND {query_see_ticket}
			AND hdt.status NOT IN ({array_int:excluded_statuses})
			AND (hdlr.id_msg IS NULL OR hdlr.id_msg < hdt.id_last_msg)
		GROUP BY hdt.id_dept',
		array(
			'member' => $user_info['id'],
			'depts' => $dept_ids,
			'excluded_statuses' => array(TICKET_STATUS_CLOSED, TICKET_STATUS_DELETED),
		)
	);

	while ($row = $db->fetch_assoc($request))
	{
		$dept_id = (int) $row['id_dept'];
		if (isset($context['dept_list'][$dept_id]) && (int) $row['unread_count'] > 0)
			$context['dept_list'][$dept_id]['new'] = true;
	}
	$db->free_result($request);
}
