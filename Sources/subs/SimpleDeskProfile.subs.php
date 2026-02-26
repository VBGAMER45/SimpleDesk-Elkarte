<?php
/**
 * SimpleDesk Profile Subs
 *
 * Helper functions for helpdesk user profile integration. Adds helpdesk
 * sections to user profiles showing ticket history, notification
 * preferences, and IP tracking for helpdesk replies.
 *
 * Ported from SMF Subs-SimpleDeskProfile.php to ElkArte.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Adds helpdesk areas to the user profile menu.
 *
 * Called via the integrate_pre_profile_areas hook. Adds a helpdesk section
 * to the profile with sub-areas for ticket summary, ticket listing,
 * and notification preferences.
 *
 * ElkArte profile areas use a different format than SMF. Each area needs
 * a label, optional permission, and sub-areas with their own labels
 * and functions.
 *
 * @param array &$profile_areas The profile areas array (passed by reference).
 */
function shd_profile_areas(&$profile_areas)
{
	global $txt, $modSettings, $user_info, $context;

	// Don't add anything if the helpdesk is inactive
	if (empty($modSettings['helpdesk_active']))
		return;

	// Load language strings
	loadLanguage('SimpleDeskProfile');

	// Don't show helpdesk profile if the user can't access the helpdesk
	require_once(SUBSDIR . '/SimpleDesk.subs.php');
	shd_init();

	if (!shd_allowed_to('access_helpdesk', 0))
		return;

	// Determine if we can view this member's profile
	$is_own = !empty($context['member']['id']) && $context['member']['id'] == $user_info['id'];
	$can_view_any = shd_allowed_to('shd_view_profile_any', 0);
	$can_view_own = shd_allowed_to('shd_view_profile_own', 0) && $is_own;

	if (!$can_view_any && !$can_view_own)
		return;

	// Add the helpdesk section to the profile areas
	// Insert after 'info' section if it exists, otherwise append
	$helpdesk_area = array(
		'shd_profile' => array(
			'title' => isset($txt['shd_profile_area']) ? $txt['shd_profile_area'] : 'Helpdesk Profile',
			'areas' => array(
				'hd_summary' => array(
					'label' => isset($txt['shd_profile_main']) ? $txt['shd_profile_main'] : 'Helpdesk Summary',
					'function' => 'shd_profile_summary',
					'enabled' => true,
					'permission' => array(
						'own' => array('profile_view_own'),
						'any' => array('profile_view_any'),
					),
				),
				'hd_showtickets' => array(
					'label' => isset($txt['shd_profile_tickets']) ? $txt['shd_profile_tickets'] : 'Tickets',
					'function' => 'shd_profile_show_tickets',
					'enabled' => true,
					'permission' => array(
						'own' => array('profile_view_own'),
						'any' => array('profile_view_any'),
					),
				),
				'hd_preferences' => array(
					'label' => isset($txt['shd_profile_preferences']) ? $txt['shd_profile_preferences'] : 'Helpdesk Preferences',
					'function' => 'shd_profile_preferences',
					'enabled' => ($is_own || shd_allowed_to('shd_view_preferences_any', 0)),
					'permission' => array(
						'own' => array('profile_view_own'),
						'any' => array('profile_view_any'),
					),
				),
			),
		),
	);

	// Insert the helpdesk section into profile areas
	$profile_areas += $helpdesk_area;
}

/**
 * Displays a helpdesk summary on the user's profile.
 *
 * Shows ticket counts (total, open, closed) for the profile member.
 * This is a placeholder that sets up $context for the template.
 *
 * @param int $memID The member ID whose profile is being viewed.
 */
function shd_profile_summary($memID)
{
	global $context, $txt, $scripturl;

	$db = database();

	require_once(SUBSDIR . '/SimpleDesk.subs.php');
	shd_init();

	loadLanguage('SimpleDeskProfile');

	// Count tickets by status for this member
	$request = shd_db_query('', '
		SELECT status, COUNT(*) AS ticket_count
		FROM {db_prefix}helpdesk_tickets AS hdt
		WHERE hdt.id_member_started = {int:member}
			AND {query_see_ticket}
		GROUP BY status',
		array(
			'member' => $memID,
		)
	);

	$ticket_counts = array(
		'total' => 0,
		'open' => 0,
		'closed' => 0,
	);

	while ($row = $db->fetch_assoc($request))
	{
		$status = (int) $row['status'];
		$count = (int) $row['ticket_count'];

		$ticket_counts['total'] += $count;

		if ($status == TICKET_STATUS_CLOSED)
			$ticket_counts['closed'] += $count;
		elseif ($status != TICKET_STATUS_DELETED)
			$ticket_counts['open'] += $count;
	}
	$db->free_result($request);

	$context['shd_profile'] = array(
		'member_id' => $memID,
		'ticket_counts' => $ticket_counts,
		'show_tickets_link' => $scripturl . '?action=profile;area=hd_showtickets;u=' . $memID,
	);

	$context['page_title'] = (isset($txt['shd_profile_main']) ? $txt['shd_profile_main'] : 'Helpdesk Summary');
	$context['sub_template'] = 'shd_profile_summary';
}

/**
 * Displays a list of helpdesk tickets for a user profile.
 *
 * Shows a paginated, sortable list of all tickets started by the
 * profile member that the current user has permission to view.
 *
 * @param int $memID The member ID whose tickets to display.
 */
function shd_profile_show_tickets($memID)
{
	global $context, $txt, $scripturl, $modSettings;

	$db = database();

	require_once(SUBSDIR . '/SimpleDesk.subs.php');
	shd_init();

	loadLanguage('SimpleDeskProfile');
	loadLanguage('SimpleDesk');

	// Count total tickets for this member
	$request = shd_db_query('', '
		SELECT COUNT(*)
		FROM {db_prefix}helpdesk_tickets AS hdt
		WHERE hdt.id_member_started = {int:member}
			AND {query_see_ticket}',
		array(
			'member' => $memID,
		)
	);
	list($total_tickets) = $db->fetch_row($request);
	$db->free_result($request);
	$total_tickets = (int) $total_tickets;

	// Pagination
	$per_page = 20;
	$start = isset($_REQUEST['start']) ? max(0, (int) $_REQUEST['start']) : 0;

	if ($start >= $total_tickets && $total_tickets > 0)
		$start = max(0, (int) (($total_tickets - 1) / $per_page) * $per_page);

	$context['page_index'] = constructPageIndex(
		$scripturl . '?action=profile;area=hd_showtickets;u=' . $memID . ';start=%1$d',
		$start,
		$total_tickets,
		$per_page
	);
	$context['start'] = $start;

	// Sorting
	$sort_columns = array(
		'ticketid' => 'hdt.id_ticket',
		'subject' => 'hdt.subject',
		'status' => 'hdt.status',
		'urgency' => 'hdt.urgency',
		'updated' => 'hdt.last_updated',
		'replies' => 'hdt.num_replies',
	);

	$sort_field = 'updated';
	$sort_dir = 'desc';

	if (isset($_REQUEST['sort']) && isset($sort_columns[$_REQUEST['sort']]))
		$sort_field = $_REQUEST['sort'];

	if (isset($_REQUEST['asc']))
		$sort_dir = 'asc';

	$order_clause = $sort_columns[$sort_field] . ' ' . strtoupper($sort_dir);

	// Load tickets
	$request = shd_db_query('', '
		SELECT hdt.id_ticket, hdt.subject, hdt.status, hdt.urgency, hdt.num_replies,
			hdt.last_updated, hdt.id_dept, hdt.private,
			COALESCE(ma.real_name, {string:unassigned}) AS assigned_name,
			hdd.dept_name
		FROM {db_prefix}helpdesk_tickets AS hdt
			LEFT JOIN {db_prefix}members AS ma ON (ma.id_member = hdt.id_member_assigned)
			LEFT JOIN {db_prefix}helpdesk_depts AS hdd ON (hdd.id_dept = hdt.id_dept)
		WHERE hdt.id_member_started = {int:member}
			AND {query_see_ticket}
		ORDER BY ' . $order_clause . '
		LIMIT {int:start}, {int:per_page}',
		array(
			'member' => $memID,
			'unassigned' => isset($txt['shd_unassigned']) ? $txt['shd_unassigned'] : 'Unassigned',
			'start' => $start,
			'per_page' => $per_page,
		)
	);

	$context['shd_tickets'] = array();
	while ($row = $db->fetch_assoc($request))
	{
		$context['shd_tickets'][] = array(
			'id' => (int) $row['id_ticket'],
			'display_id' => shd_display_id($row['id_ticket']),
			'subject' => $row['subject'],
			'status' => (int) $row['status'],
			'status_label' => isset($txt['shd_status_' . $row['status']]) ? $txt['shd_status_' . $row['status']] : '',
			'urgency' => (int) $row['urgency'],
			'urgency_label' => isset($txt['shd_urgency_' . $row['urgency']]) ? $txt['shd_urgency_' . $row['urgency']] : '',
			'num_replies' => (int) $row['num_replies'],
			'last_updated' => standardTime($row['last_updated']),
			'assigned_name' => $row['assigned_name'],
			'dept_name' => $row['dept_name'],
			'private' => !empty($row['private']),
			'link' => $scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $row['id_ticket'],
		);
	}
	$db->free_result($request);

	$context['shd_profile_tickets'] = array(
		'member_id' => $memID,
		'total' => $total_tickets,
		'sort' => array(
			'field' => $sort_field,
			'direction' => $sort_dir,
		),
	);

	$context['page_title'] = isset($txt['shd_profile_showtickets']) ? $txt['shd_profile_showtickets'] : 'Show Tickets';
	$context['sub_template'] = 'shd_profile_show_tickets';
}

/**
 * Displays and saves helpdesk notification preferences for a user.
 *
 * Shows checkboxes for notification preferences (new tickets, replies,
 * assignments) and saves them to the helpdesk_preferences table.
 *
 * @param int $memID The member ID whose preferences to manage.
 */
function shd_profile_preferences($memID)
{
	global $context, $txt, $scripturl, $user_info;

	$db = database();

	require_once(SUBSDIR . '/SimpleDesk.subs.php');
	shd_init();

	loadLanguage('SimpleDeskProfile');

	// Permission check: can only edit own preferences (or admin/view_preferences_any)
	$is_own = ($memID == $user_info['id']);
	if (!$is_own && !shd_allowed_to('shd_view_preferences_any', 0))
		fatal_lang_error('no_access', false);

	$is_staff = shd_allowed_to('shd_staff', 0);

	// Define available preferences
	$preference_defs = array(
		'notify_new_reply' => array(
			'label' => isset($txt['shd_profile_notify_new_reply']) ? $txt['shd_profile_notify_new_reply'] : 'Notify me of new replies to my tickets',
			'default' => 1,
			'staff_only' => false,
		),
		'notify_assign' => array(
			'label' => isset($txt['shd_profile_notify_assign']) ? $txt['shd_profile_notify_assign'] : 'Notify me when a ticket is assigned to me',
			'default' => 1,
			'staff_only' => false,
		),
		'notify_new_ticket' => array(
			'label' => isset($txt['shd_profile_notify_new_ticket']) ? $txt['shd_profile_notify_new_ticket'] : 'Notify me of new tickets (staff only)',
			'default' => 1,
			'staff_only' => true,
		),
	);

	// Handle save
	if (isset($_POST['save']) && $is_own)
	{
		checkSession();

		foreach ($preference_defs as $pref_name => $pref_def)
		{
			// Skip staff-only prefs for non-staff
			if ($pref_def['staff_only'] && !$is_staff)
				continue;

			$value = isset($_POST['shd_pref_' . $pref_name]) ? 1 : 0;

			$db->insert('replace',
				'{db_prefix}helpdesk_preferences',
				array(
					'id_member' => 'int',
					'variable' => 'string',
					'value' => 'string',
				),
				array(
					$memID,
					$pref_name,
					(string) $value,
				),
				array('id_member', 'variable')
			);
		}

		// Redirect to avoid double-post
		redirectexit('action=profile;area=hd_preferences;u=' . $memID . ';saved');
	}

	// Load current preferences
	$request = $db->query('', '
		SELECT variable, value
		FROM {db_prefix}helpdesk_preferences
		WHERE id_member = {int:member}',
		array(
			'member' => $memID,
		)
	);

	$current_prefs = array();
	while ($row = $db->fetch_assoc($request))
		$current_prefs[$row['variable']] = $row['value'];

	$db->free_result($request);

	// Build context for the template
	$context['shd_preferences'] = array();
	foreach ($preference_defs as $pref_name => $pref_def)
	{
		// Skip staff-only prefs for non-staff members
		if ($pref_def['staff_only'] && !$is_staff)
			continue;

		$context['shd_preferences'][$pref_name] = array(
			'name' => $pref_name,
			'label' => $pref_def['label'],
			'value' => isset($current_prefs[$pref_name]) ? (int) $current_prefs[$pref_name] : $pref_def['default'],
		);
	}

	$context['shd_profile_prefs'] = array(
		'member_id' => $memID,
		'is_own' => $is_own,
		'saved' => isset($_REQUEST['saved']),
	);

	$context['page_title'] = isset($txt['shd_profile_preferences']) ? $txt['shd_profile_preferences'] : 'Helpdesk Preferences';
	$context['sub_template'] = 'shd_profile_preferences';
}

/**
 * Tracks IP addresses in helpdesk replies.
 *
 * Used by the admin IP tracking page to find helpdesk replies from a
 * specific IP address. Returns data suitable for ElkArte's createList().
 *
 * @param string $ip_string The IP address string to search for.
 * @param string $ip_var The type of IP match ('ip' for exact, 'ip_range' for range).
 * @return array Array with 'rows' containing matching reply data.
 */
function shd_profile_trackip($ip_string, $ip_var)
{
	global $scripturl, $txt;

	$db = database();

	require_once(SUBSDIR . '/SimpleDesk.subs.php');
	shd_init();

	loadLanguage('SimpleDeskProfile');
	loadLanguage('SimpleDesk');

	$ip_results = array();

	// Search for helpdesk replies from this IP
	$request = shd_db_query('', '
		SELECT hdtr.id_msg, hdtr.id_ticket, hdtr.poster_time, hdtr.poster_ip,
			hdtr.id_member, hdtr.poster_name,
			hdt.subject, hdt.id_dept,
			COALESCE(mem.real_name, hdtr.poster_name) AS display_name
		FROM {db_prefix}helpdesk_ticket_replies AS hdtr
			INNER JOIN {db_prefix}helpdesk_tickets AS hdt ON (hdt.id_ticket = hdtr.id_ticket)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = hdtr.id_member)
		WHERE hdtr.poster_ip LIKE {string:ip}
			AND {query_see_ticket}
		ORDER BY hdtr.poster_time DESC
		LIMIT 100',
		array(
			'ip' => $ip_string,
		)
	);

	while ($row = $db->fetch_assoc($request))
	{
		$ip_results[] = array(
			'id' => (int) $row['id_msg'],
			'ticket' => (int) $row['id_ticket'],
			'subject' => $row['subject'],
			'poster' => array(
				'id' => (int) $row['id_member'],
				'name' => $row['display_name'],
				'link' => !empty($row['id_member'])
					? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['display_name'] . '</a>'
					: $row['display_name'],
			),
			'ip' => $row['poster_ip'],
			'time' => standardTime($row['poster_time']),
			'timestamp' => (int) $row['poster_time'],
			'link' => $scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $row['id_ticket'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
		);
	}
	$db->free_result($request);

	return $ip_results;
}
