<?php
/**
 * SimpleDesk Stats Plugin
 *
 * Provides a statistics page in the admin panel showing ticket counts,
 * urgency breakdowns, averages, user/staff totals, and historical data.
 *
 * Ported from SMF SimpleDesk SDPluginStats.php to ElkArte.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Add stats subsection to admin menu.
 *
 * @param array &$admin_areas The admin areas array.
 */
function shd_stats_adminmenu(&$admin_areas)
{
	global $context, $modSettings, $txt;

	if (allowedTo('admin_forum') && !empty($modSettings['shdp_enable_stats']))
		$admin_areas['helpdesk']['areas']['helpdesk']['subsections']['stats'] = array($txt['shdp_stats']);
}

/**
 * Register stats tab in admin info panel.
 *
 * @param array &$subactions The admin info subactions array.
 */
function shd_stats_hdadmininfo(&$subactions)
{
	global $context, $modSettings, $txt;

	if (!allowedTo('admin_forum') || empty($modSettings['shdp_enable_stats']))
		return;

	$subactions['stats'] = array(
		'function' => 'shd_stats_source',
		'icon' => 'reports.png',
		'title' => $txt['shdp_stats'],
	);

	$context[$context['admin_menu_name']]['tab_data']['tabs']['stats'] = $subactions['stats'];
}

/**
 * Add stats enable option to the helpdesk admin options form.
 *
 * @param array &$config_vars The config vars array.
 */
function shd_stats_admin(&$config_vars)
{
	$config_vars[] = '';
	$config_vars[] = array('check', 'shdp_enable_stats');
}

/**
 * Source function for the stats page. Gathers all statistics and loads template.
 */
function shd_stats_source()
{
	global $modSettings, $context;

	// All possible stat categories.
	$stats = array(
		'status',
		'today',
		'most',
		'average',
		'totals',
		'urgency',
		'history',
	);

	// Loop and gather stat data.
	$context['shd_stats'] = array();
	foreach ($stats as $function)
	{
		$func = 'shd_stats_' . $function;
		$context['shd_stats'][$function] = $func();
	}

	loadTemplate('SDPluginStats');
	$context['sub_template'] = 'shd_stats';
}

/**
 * Gets ticket status counts (new, pending, closed, deleted, etc).
 *
 * @return array Status counts keyed by TICKET_STATUS_* constants.
 */
function shd_stats_status()
{
	$db = database();

	$status = array(
		TICKET_STATUS_NEW => 0,
		TICKET_STATUS_PENDING_STAFF => 0,
		TICKET_STATUS_PENDING_USER => 0,
		TICKET_STATUS_CLOSED => 0,
		TICKET_STATUS_WITH_SUPERVISOR => 0,
		TICKET_STATUS_ESCALATED => 0,
		TICKET_STATUS_DELETED => 0,
	);

	$request = $db->query('', '
		SELECT COUNT(id_ticket) AS count, status
		FROM {db_prefix}helpdesk_tickets
		WHERE status IN ({array_int:status})
		GROUP BY status',
		array(
			'status' => array_keys($status),
		)
	);

	while ($row = $db->fetch_assoc($request))
		$status[$row['status']] = $row['count'];
	$db->free_result($request);

	$status['total_open'] = $status[TICKET_STATUS_NEW] + $status[TICKET_STATUS_PENDING_STAFF] + $status[TICKET_STATUS_PENDING_USER] + $status[TICKET_STATUS_WITH_SUPERVISOR] + $status[TICKET_STATUS_ESCALATED];
	$status['total_closed'] = $status[TICKET_STATUS_CLOSED] + $status[TICKET_STATUS_DELETED];
	$status['total_total'] = $status['total_open'] + $status['total_closed'];

	if ($status['total_open'] == $status['total_closed'])
		$status['ratio'] = '1:1';
	elseif ($status['total_open'] == 0)
		$status['ratio'] = '0:' . $status['total_closed'];
	elseif ($status['total_closed'] == 0)
		$status['ratio'] = $status['total_open'] . ':0';
	elseif ($status['total_open'] > $status['total_closed'])
		$status['ratio'] = round($status['total_open'] / $status['total_closed']) . ':1';
	elseif ($status['total_open'] < $status['total_closed'])
		$status['ratio'] = '1:' . round($status['total_closed'] / $status['total_open']);

	return $status;
}

/**
 * Gets today's ticket open/closed counts.
 *
 * @return array Counts keyed by TICKET_STATUS_NEW and TICKET_STATUS_CLOSED.
 */
function shd_stats_today()
{
	$db = database();

	$actions = array(
		'newticket' => TICKET_STATUS_NEW,
		'unresolve' => TICKET_STATUS_NEW,
		'resolve' => TICKET_STATUS_CLOSED,
	);

	$totals = array(
		TICKET_STATUS_NEW => 0,
		TICKET_STATUS_CLOSED => 0,
	);

	$request = $db->query('', '
		SELECT COUNT(la.id_ticket) AS count, t.status
		FROM {db_prefix}helpdesk_log_action AS la
			INNER JOIN {db_prefix}helpdesk_tickets AS t ON (la.id_ticket = t.id_ticket)
		WHERE la.action IN ({array_string:actions})
			AND la.log_time > {int:today}
		GROUP BY la.action, t.status',
		array(
			'actions' => array_keys($actions),
			'today' => mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y')),
		)
	);

	while ($row = $db->fetch_assoc($request))
		$totals[$row['status']] = $row['count'];
	$db->free_result($request);

	return $totals;
}

/**
 * Gets the most tickets opened/closed in a single day.
 *
 * @return array Keyed by status, each value is [count, log_time].
 */
function shd_stats_most()
{
	$db = database();

	$actions = array(
		TICKET_STATUS_NEW => array('newticket'),
		TICKET_STATUS_CLOSED => array('resolve'),
	);

	$most = array(
		TICKET_STATUS_NEW => array(0, 0),
		TICKET_STATUS_CLOSED => array(0, 0),
	);

	foreach ($actions as $id_action => $action)
	{
		$request = $db->query('', '
			SELECT COUNT(la.id_ticket) AS count, t.status, MAX(log_time) AS log_time
			FROM {db_prefix}helpdesk_log_action AS la
				INNER JOIN {db_prefix}helpdesk_tickets AS t ON (la.id_ticket = t.id_ticket)
			WHERE
				la.action IN ({array_string:actions})
				AND t.status = {int:status}
			GROUP BY
				UNIX_TIMESTAMP() - log_time < {int:24hrs} AND UNIX_TIMESTAMP() - log_time + {int:24hrs} > 0
			ORDER BY count DESC
			LIMIT 1',
			array(
				'actions' => $action,
				'status' => $id_action,
				'24hrs' => 86400,
			)
		);

		while ($row = $db->fetch_assoc($request))
			$most[$row['status']] = array($row['count'], $row['log_time']);
		$db->free_result($request);
	}

	return $most;
}

/**
 * Gets average ticket action counts.
 *
 * @return array Keyed by TICKET_STATUS_* with average counts.
 */
function shd_stats_average()
{
	$db = database();

	$actions = array(
		TICKET_STATUS_NEW => array('newticket', 'unresolve'),
		TICKET_STATUS_CLOSED => array('resolve'),
		TICKET_STATUS_PENDING_STAFF => array('assign'),
	);

	$average = array(
		TICKET_STATUS_NEW => 0,
		TICKET_STATUS_CLOSED => 0,
		TICKET_STATUS_PENDING_STAFF => 0,
	);

	foreach ($actions as $id_action => $action)
	{
		$request = $db->query('', '
			SELECT AVG(la.id_ticket) AS count, t.status
			FROM {db_prefix}helpdesk_log_action AS la
				INNER JOIN {db_prefix}helpdesk_tickets AS t ON (la.id_ticket = t.id_ticket)
			WHERE la.action IN ({array_string:actions})
			GROUP BY t.status',
			array(
				'actions' => $action,
			)
		);

		while ($row = $db->fetch_assoc($request))
			$average[$row['status']] = $row['count'];
		$db->free_result($request);
	}

	return $average;
}

/**
 * Gets user/staff/admin totals based on helpdesk roles.
 *
 * @return array Keyed by ROLE_USER, ROLE_STAFF, ROLE_ADMIN with member counts.
 */
function shd_stats_totals()
{
	$db = database();

	// Count admins separately.
	$admins = array();
	$request = $db->query('', '
		SELECT id_member
		FROM {db_prefix}members
		WHERE id_group = {int:admin} OR FIND_IN_SET({int:admin}, additional_groups)',
		array(
			'admin' => 1,
		)
	);

	while ($row = $db->fetch_assoc($request))
		$admins[] = $row['id_member'];
	$db->free_result($request);

	if (empty($admins))
		$admins = array(0);

	$request = $db->query('', '
		SELECT COUNT(mem.id_member) AS count, hdr.template
		FROM {db_prefix}members AS mem
			INNER JOIN {db_prefix}helpdesk_role_groups AS hdrg ON (mem.id_group = hdrg.id_group OR FIND_IN_SET(hdrg.id_group, mem.additional_groups))
			INNER JOIN {db_prefix}helpdesk_roles AS hdr ON (hdrg.id_role = hdr.id_role)
		WHERE mem.id_member NOT IN ({array_int:admins})
		GROUP BY hdr.template',
		array(
			'admins' => $admins,
		)
	);

	$totals = array(
		ROLE_USER => 0,
		ROLE_STAFF => 0,
		ROLE_ADMIN => 0,
	);

	while ($row = $db->fetch_assoc($request))
		$totals[$row['template']] = $row['count'];
	$db->free_result($request);

	// Add in the admins.
	if (empty($totals[ROLE_ADMIN]))
		$totals[ROLE_ADMIN] += count($admins);

	return $totals;
}

/**
 * Gets urgency breakdown for open vs closed tickets.
 *
 * @return array With 'open' and 'closed' sub-arrays keyed by TICKET_URGENCY_* constants.
 */
function shd_stats_urgency()
{
	$db = database();

	$urgency_template = array(
		TICKET_URGENCY_LOW => 0,
		TICKET_URGENCY_MEDIUM => 0,
		TICKET_URGENCY_HIGH => 0,
		TICKET_URGENCY_VHIGH => 0,
		TICKET_URGENCY_SEVERE => 0,
		TICKET_URGENCY_CRITICAL => 0,
	);

	$urgency = array(
		'open' => $urgency_template,
		'closed' => $urgency_template,
	);

	// Map status to open/closed.
	$status_map = array(
		TICKET_STATUS_NEW => 'open',
		TICKET_STATUS_PENDING_STAFF => 'open',
		TICKET_STATUS_PENDING_USER => 'open',
		TICKET_STATUS_CLOSED => 'closed',
		TICKET_STATUS_WITH_SUPERVISOR => 'open',
		TICKET_STATUS_ESCALATED => 'open',
		TICKET_STATUS_DELETED => 'closed',
	);

	$request = $db->query('', '
		SELECT COUNT(id_ticket) AS count, urgency, status
		FROM {db_prefix}helpdesk_tickets
		WHERE status IN ({array_int:status})
		GROUP BY urgency, status',
		array(
			'status' => array_keys($status_map),
		)
	);

	while ($row = $db->fetch_assoc($request))
	{
		$category = isset($status_map[$row['status']]) ? $status_map[$row['status']] : 'open';
		if (isset($urgency[$category][$row['urgency']]))
			$urgency[$category][$row['urgency']] = $row['count'];
	}
	$db->free_result($request);

	return $urgency;
}

/**
 * Gets historical ticket activity data by year, month, and day.
 *
 * @return array Nested array: year => [open, resolved, assigned, reopen, child => month => ...].
 */
function shd_stats_history()
{
	$db = database();

	$tasks = array(
		'open' => 0,
		'resolved' => 0,
		'assigned' => 0,
		'reopen' => 0,
		'child' => array(),
	);

	$conversion = array(
		'newticket' => 'open',
		'assign' => 'assigned',
		'resolve' => 'resolved',
		'reopen' => 'reopen',
	);

	$request = $db->query('', '
		SELECT log_time, action
		FROM {db_prefix}helpdesk_log_action
		WHERE action IN ({array_string:valid_actions})',
		array(
			'valid_actions' => array('newticket', 'assign', 'reopen', 'resolve', 'close'),
		)
	);

	$history = array();
	while ($row = $db->fetch_assoc($request))
	{
		list($year, $month, $day) = explode(' ', date('Y n d', $row['log_time']));

		if (!isset($conversion[$row['action']]))
			continue;

		$action_key = $conversion[$row['action']];

		// Yearly stats
		if (!isset($history[$year]))
			$history[$year] = $tasks;
		$history[$year][$action_key] += 1;

		// Monthly stats
		if (!isset($history[$year]['child'][$month]))
			$history[$year]['child'][$month] = $tasks;
		$history[$year]['child'][$month][$action_key] += 1;

		// Daily stats
		if (!isset($history[$year]['child'][$month]['child'][$day]))
			$history[$year]['child'][$month]['child'][$day] = $tasks;
		$history[$year]['child'][$month]['child'][$day][$action_key] += 1;
	}
	$db->free_result($request);

	return $history;
}
