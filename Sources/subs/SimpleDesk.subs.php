<?php
/**
 * SimpleDesk Helpdesk - Core Subsystem
 *
 * Core helper functions: initialization, constants, permissions,
 * ticket queries, logging, language loading.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Initializes key values for SimpleDesk (constants, defaults, permissions).
 * Safe to call multiple times.
 */
function shd_init()
{
	global $modSettings, $user_info, $context;
	static $called = null;

	if (!empty($called))
		return;

	$called = true;
	$context['shd_home'] = 'action=helpdesk;sa=main';

	// Version
	if (!defined('SHD_VERSION'))
		define('SHD_VERSION', 'SimpleDesk 2.1.5');

	// Ticket statuses
	if (!defined('TICKET_STATUS_NEW'))
	{
		define('TICKET_STATUS_NEW', 0);
		define('TICKET_STATUS_PENDING_STAFF', 1);
		define('TICKET_STATUS_PENDING_USER', 2);
		define('TICKET_STATUS_CLOSED', 3);
		define('TICKET_STATUS_WITH_SUPERVISOR', 4);
		define('TICKET_STATUS_ESCALATED', 5);
		define('TICKET_STATUS_DELETED', 6);
		define('TICKET_STATUS_HOLD', 7);
	}

	// Urgency levels
	if (!defined('TICKET_URGENCY_LOW'))
	{
		define('TICKET_URGENCY_LOW', 0);
		define('TICKET_URGENCY_MEDIUM', 1);
		define('TICKET_URGENCY_HIGH', 2);
		define('TICKET_URGENCY_VHIGH', 3);
		define('TICKET_URGENCY_SEVERE', 4);
		define('TICKET_URGENCY_CRITICAL', 5);
	}

	// Message statuses
	if (!defined('MSG_STATUS_NORMAL'))
	{
		define('MSG_STATUS_NORMAL', 0);
		define('MSG_STATUS_DELETED', 1);
	}

	// Relationship types
	if (!defined('RELATIONSHIP_LINKED'))
	{
		define('RELATIONSHIP_LINKED', 0);
		define('RELATIONSHIP_DUPLICATED', 1);
		define('RELATIONSHIP_ISPARENT', 2);
		define('RELATIONSHIP_ISCHILD', 3);
	}

	// Custom field types
	if (!defined('CFIELD_TICKET'))
	{
		define('CFIELD_TICKET', 1);
		define('CFIELD_REPLY', 2);
		define('CFIELD_TICKETREPLY', 3);

		define('CFIELD_PLACE_DETAILS', 1);
		define('CFIELD_PLACE_INFO', 2);
		define('CFIELD_PLACE_PREFIX', 3);
		define('CFIELD_PLACE_PREFIXFILTER', 4);

		define('CFIELD_TYPE_TEXT', 1);
		define('CFIELD_TYPE_LARGETEXT', 2);
		define('CFIELD_TYPE_INT', 3);
		define('CFIELD_TYPE_FLOAT', 4);
		define('CFIELD_TYPE_SELECT', 5);
		define('CFIELD_TYPE_CHECKBOX', 6);
		define('CFIELD_TYPE_RADIO', 7);
		define('CFIELD_TYPE_MULTI', 8);
	}

	// Notification preferences
	if (!defined('NOTIFY_PREFS'))
	{
		define('NOTIFY_PREFS', 0);
		define('NOTIFY_ALWAYS', 1);
		define('NOTIFY_NEVER', 2);
	}

	// Role types
	if (!defined('ROLE_USER'))
	{
		define('ROLE_USER', 1);
		define('ROLE_STAFF', 2);
		define('ROLE_ADMIN', 4);
	}

	// Role permission types
	if (!defined('ROLEPERM_DISALLOW'))
	{
		define('ROLEPERM_DISALLOW', 0);
		define('ROLEPERM_ALLOW', 1);
		define('ROLEPERM_DENY', 2);
	}

	$context['shd_scripts_version'] = 'beta1';
	$context['shd_css_version'] = 'rc1';

	// Zero-fill for ticket numbers
	if (empty($modSettings['shd_zerofill']) || $modSettings['shd_zerofill'] < 0)
		$modSettings['shd_zerofill'] = 0;

	// Set up defaults
	$defaults = array(
		'shd_attachments_mode' => 'ticket',
		'shd_ticketnav_style' => 'sd',
		'shd_staff_badge' => 'nobadge',
		'shd_privacy_display' => 'smart',
	);

	foreach ($defaults as $var => $val)
		if (empty($modSettings[$var]))
			$modSettings[$var] = $val;

	$modSettings['helpdesk_active'] = true;

	// Load permissions subsystem and user permissions
	require_once(SUBSDIR . '/SimpleDeskPermissions.subs.php');
	shd_load_user_perms();

	// Maintenance mode check
	if (!empty($modSettings['shd_maintenance_mode']))
	{
		$modSettings['helpdesk_active'] &= ($user_info['is_admin'] || shd_allowed_to('admin_helpdesk', 0));
	}

	$context['shd_plugins'] = empty($modSettings['shd_enabled_plugins']) || empty($modSettings['helpdesk_active']) ? array() : explode(',', $modSettings['shd_enabled_plugins']);
}

/**
 * Query wrapper that injects {query_see_ticket} for ticket visibility.
 */
function shd_db_query($identifier, $db_string, $db_values = array(), $connection = null)
{
	global $user_info;

	$db = database();

	$replacements = array(
		'{query_see_ticket}' => $user_info['query_see_ticket'],
	);

	$db_string = str_replace(array_keys($replacements), array_values($replacements), $db_string);
	$db_values['user_info_id'] = $user_info['id'];

	return $db->query($identifier, $db_string, $db_values, $connection);
}

/**
 * Returns the count of active tickets for the menu display.
 */
function shd_get_active_tickets()
{
	global $modSettings, $user_info, $context, $txt;

	if (empty($modSettings['helpdesk_active']))
		return 0;

	if (empty($txt['shd_helpdesk']))
		$txt['shd_helpdesk'] = 'Helpdesk';

	if (!$modSettings['helpdesk_active'] || $context['user']['is_guest'] || !empty($modSettings['shd_hidemenuitem']))
		return 0;

	if (!empty($context['active_tickets_raw']))
		return $context['active_tickets_raw'];

	$temp = cache_get_data('shd_active_tickets_' . $user_info['id'], 180);
	if ($temp !== null)
	{
		$context['active_tickets_raw'] = $temp;
		return $context['active_tickets_raw'];
	}

	$db = database();

	if (shd_allowed_to('shd_staff', 0))
		$status = array(TICKET_STATUS_NEW, TICKET_STATUS_PENDING_STAFF);
	else
		$status = array(TICKET_STATUS_PENDING_USER);

	$request = shd_db_query('', '
		SELECT COUNT(id_ticket)
		FROM {db_prefix}helpdesk_tickets AS hdt
		WHERE {query_see_ticket} AND status IN ({array_int:status})',
		array(
			'status' => $status,
		)
	);
	$row = $db->fetch_row($request);
	$db->free_result($request);

	$context['active_tickets_raw'] = !empty($row[0]) ? (int) $row[0] : 0;

	cache_put_data('shd_active_tickets_' . $user_info['id'], $context['active_tickets_raw'], 120);

	return $context['active_tickets_raw'];
}

/**
 * Clears the cache of active tickets.
 */
function shd_clear_active_tickets($dept = 0)
{
	global $modSettings;

	$members = shd_members_allowed_to('access_helpdesk', $dept);
	foreach ($members as $member)
	{
		cache_put_data('shd_active_tickets_' . $member, null, 120);
		cache_put_data('shd_ticket_count_' . $member, null, 120);
	}

	if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
		updateSettings(array('settings_updated' => time()));
}

/**
 * Adds an action to the helpdesk internal action log.
 */
function shd_log_action($action, $params, $do_last_update = true)
{
	global $user_info, $modSettings;
	static $last_cache;

	$db = database();

	// Update ticket's last_updated time
	if ($do_last_update && isset($params['ticket']) && ((int) $params['ticket'] != 0) && (empty($last_cache[$params['ticket']]) || $last_cache[$params['ticket']] < time() - 2))
	{
		$last_cache[$params['ticket']] = time();
		$db->query('', '
			UPDATE {db_prefix}helpdesk_tickets
			SET last_updated = {int:new_time}
			WHERE id_ticket = {int:ticket}',
			array(
				'new_time' => $last_cache[$params['ticket']],
				'ticket' => $params['ticket'],
			)
		);
	}

	if (!empty($modSettings['shd_disable_action_log']))
		return;

	// Check if this action should be logged
	$logopt = array(
		'newticket' => 'shd_logopt_newposts',
		'newticketproxy' => 'shd_logopt_newposts',
		'editticket' => 'shd_logopt_editposts',
		'newreply' => 'shd_logopt_newposts',
		'editreply' => 'shd_logopt_editposts',
		'resolve' => 'shd_logopt_resolve',
		'unresolve' => 'shd_logopt_resolve',
		'assign' => 'shd_logopt_assign',
		'unassign' => 'shd_logopt_assign',
		'markprivate' => 'shd_logopt_privacy',
		'marknotprivate' => 'shd_logopt_privacy',
		'urgency_increase' => 'shd_logopt_urgency',
		'urgency_decrease' => 'shd_logopt_urgency',
		'urgency_change' => 'shd_logopt_urgency',
		'delete' => 'shd_logopt_delete',
		'delete_reply' => 'shd_logopt_delete',
		'restore' => 'shd_logopt_restore',
		'restore_reply' => 'shd_logopt_restore',
		'permadelete' => 'shd_logopt_permadelete',
		'permadelete_reply' => 'shd_logopt_permadelete',
		'move_dept' => 'shd_logopt_move_dept',
		'rel_linked' => 'shd_logopt_relationships',
		'rel_duplicated' => 'shd_logopt_relationships',
		'rel_parent' => 'shd_logopt_relationships',
		'rel_child' => 'shd_logopt_relationships',
		'rel_delete' => 'shd_logopt_relationships',
	);

	if (empty($logopt[$action]) || empty($modSettings[$logopt[$action]]))
		return;

	$ticket_id = 0;
	if (!empty($params['ticket']))
	{
		$ticket_id = (int) $params['ticket'];
		unset($params['ticket']);
	}

	$msg_id = 0;
	if (!empty($params['msg']))
	{
		$msg_id = (int) $params['msg'];
		unset($params['msg']);
	}

	$db->insert('',
		'{db_prefix}helpdesk_log_action',
		array(
			'log_time' => 'int',
			'id_member' => 'int',
			'ip' => 'string',
			'action' => 'string',
			'id_ticket' => 'int',
			'id_msg' => 'int',
			'extra' => 'string',
		),
		array(
			time(),
			$user_info['id'],
			$user_info['ip'],
			$action,
			$ticket_id,
			$msg_id,
			json_encode($params),
		),
		array('id_action')
	);
}

/**
 * Determines if the current user can raise/lower the urgency of a ticket.
 */
function shd_can_alter_urgency($urgency, $ticket_starter, $closed, $deleted, $dept)
{
	global $user_info;

	$can_urgency = array(
		'increase' => false,
		'decrease' => false,
	);

	if ($closed || $deleted)
		return $can_urgency;

	if (shd_allowed_to('shd_alter_urgency_any', $dept))
	{
		if (shd_allowed_to('shd_alter_urgency_higher_any', $dept) || (shd_allowed_to('shd_alter_urgency_higher_own', $dept) && $ticket_starter == $user_info['id']))
			$can_urgency = array(
				'increase' => ($urgency < TICKET_URGENCY_CRITICAL),
				'decrease' => ($urgency > TICKET_URGENCY_LOW),
			);
		else
			$can_urgency = array(
				'increase' => ($urgency < TICKET_URGENCY_HIGH),
				'decrease' => ($urgency > TICKET_URGENCY_LOW && $urgency < TICKET_URGENCY_VHIGH),
			);
	}
	elseif (shd_allowed_to('shd_alter_urgency_own', $dept) && $ticket_starter == $user_info['id'])
		$can_urgency = array(
			'increase' => ($urgency < (shd_allowed_to('shd_alter_urgency_higher_own', $dept) ? TICKET_URGENCY_CRITICAL : TICKET_URGENCY_HIGH)),
			'decrease' => ($urgency > TICKET_URGENCY_LOW && $urgency <= (shd_allowed_to('shd_alter_urgency_higher_own', $dept) ? TICKET_URGENCY_CRITICAL : TICKET_URGENCY_VHIGH)),
		);

	return $can_urgency;
}

/**
 * Counts helpdesk tickets by status for the current user.
 */
function shd_count_helpdesk_tickets($status = '', $is_staff = false)
{
	global $context, $user_info;

	$db = database();

	if (empty($context['ticket_count']))
	{
		$context['ticket_count'] = array();
		for ($i = 0; $i <= 7; $i++)
			$context['ticket_count'][$i] = 0;

		$cache_id = 'shd_ticket_count_' . (!empty($context['shd_department']) ? 'dept' . $context['shd_department'] . '_' : '') . $user_info['id'];

		$temp = cache_get_data($cache_id, 180);
		if ($temp !== null)
			$context['ticket_count'] = $temp;
		else
		{
			$request = shd_db_query('', '
				SELECT status, COUNT(status) AS tickets
				FROM {db_prefix}helpdesk_tickets AS hdt
				WHERE {query_see_ticket}' . (!empty($context['shd_department']) ? '
					AND id_dept = ' . (int) $context['shd_department'] : '') . '
				GROUP BY status',
				array()
			);

			while ($row = $db->fetch_assoc($request))
				$context['ticket_count'][$row['status']] = $row['tickets'];

			$db->free_result($request);

			$context['ticket_count']['assigned'] = 0;
			if (shd_allowed_to('shd_staff', 0))
			{
				$request = shd_db_query('', '
					SELECT status, COUNT(status) AS tickets
					FROM {db_prefix}helpdesk_tickets AS hdt
					WHERE {query_see_ticket}
						AND id_member_assigned = {int:user}' . (!empty($context['shd_department']) ? '
						AND id_dept = ' . (int) $context['shd_department'] : '') . '
					GROUP BY status',
					array(
						'user' => $context['user']['id'],
					)
				);

				while ($row = $db->fetch_assoc($request))
				{
					if (!in_array($row['status'], array(TICKET_STATUS_CLOSED, TICKET_STATUS_DELETED)))
					{
						$context['ticket_count']['assigned'] += $row['tickets'];
						$context['ticket_count'][$row['status']] -= $row['tickets'];
					}
				}
				$db->free_result($request);
			}

			if (shd_allowed_to('shd_access_recyclebin'))
			{
				$request = shd_db_query('', '
					SELECT COUNT(id_ticket) AS tickets
					FROM {db_prefix}helpdesk_tickets AS hdt
					WHERE {query_see_ticket}' . (!empty($context['shd_department']) ? '
						AND id_dept = ' . (int) $context['shd_department'] : '') . '
						AND hdt.withdeleted = {int:has_deleted}
						AND hdt.status != {int:ticket_deleted}',
					array(
						'has_deleted' => MSG_STATUS_DELETED,
						'ticket_deleted' => TICKET_STATUS_DELETED,
					)
				);
				list($count) = $db->fetch_row($request);
				$db->free_result($request);

				$context['ticket_count']['withdeleted'] = $count;
			}
			else
				$context['ticket_count']['withdeleted'] = 0;

			cache_put_data($cache_id, $context['ticket_count'], 180);
		}
	}

	switch ($status)
	{
		case 'open':
			return (
				$context['ticket_count'][TICKET_STATUS_NEW] +
				$context['ticket_count'][TICKET_STATUS_PENDING_STAFF] +
				$context['ticket_count'][TICKET_STATUS_PENDING_USER] +
				$context['ticket_count'][TICKET_STATUS_WITH_SUPERVISOR] +
				$context['ticket_count'][TICKET_STATUS_ESCALATED] +
				$context['ticket_count'][TICKET_STATUS_HOLD] +
				$context['ticket_count']['assigned']
			);
		case 'assigned':
			return $context['ticket_count']['assigned'];
		case 'new':
			return $context['ticket_count'][TICKET_STATUS_NEW];
		case 'staff':
			if ($is_staff)
				return $context['ticket_count'][TICKET_STATUS_PENDING_STAFF] +
					$context['ticket_count'][TICKET_STATUS_WITH_SUPERVISOR] +
					$context['ticket_count'][TICKET_STATUS_ESCALATED] +
					$context['ticket_count'][TICKET_STATUS_HOLD];
			else
				return $context['ticket_count'][TICKET_STATUS_NEW] +
					$context['ticket_count'][TICKET_STATUS_PENDING_STAFF] +
					$context['ticket_count'][TICKET_STATUS_WITH_SUPERVISOR] +
					$context['ticket_count'][TICKET_STATUS_ESCALATED] +
					$context['ticket_count'][TICKET_STATUS_HOLD];
		case 'with_user':
			return $context['ticket_count'][TICKET_STATUS_PENDING_USER];
		case 'closed':
			return $context['ticket_count'][TICKET_STATUS_CLOSED];
		case 'recycled':
			return $context['ticket_count'][TICKET_STATUS_DELETED];
		case 'withdeleted':
			return $context['ticket_count']['withdeleted'];
		default:
			$total = 0;
			for ($i = 0; $i <= 7; $i++)
				$total += $context['ticket_count'][$i];
			return $total + $context['ticket_count']['assigned'];
	}
}

/**
 * Returns a list of member IDs who have a given permission in a given department.
 */
function shd_members_allowed_to($permission, $dept = 0)
{
	$db = database();

	$members = array();

	// Get roles with this permission via their templates
	$request = $db->query('', '
		SELECT hdrg.id_group
		FROM {db_prefix}helpdesk_role_groups AS hdrg
			INNER JOIN {db_prefix}helpdesk_roles AS hdr ON (hdrg.id_role = hdr.id_role)
			INNER JOIN {db_prefix}helpdesk_dept_roles AS hddr ON (hdr.id_role = hddr.id_role)
		WHERE hddr.id_dept = {int:dept}
			OR {int:dept} = 0',
		array(
			'dept' => $dept,
		)
	);

	$groups = array();
	while ($row = $db->fetch_assoc($request))
		$groups[] = (int) $row['id_group'];
	$db->free_result($request);

	if (empty($groups))
		return $members;

	$groups = array_unique($groups);

	// Get members in those groups
	$request = $db->query('', '
		SELECT id_member
		FROM {db_prefix}members
		WHERE id_group IN ({array_int:groups})
			OR FIND_IN_SET({raw:groups_find}, additional_groups)',
		array(
			'groups' => $groups,
			'groups_find' => implode(', additional_groups) OR FIND_IN_SET(', $groups),
		)
	);

	while ($row = $db->fetch_assoc($request))
		$members[] = (int) $row['id_member'];
	$db->free_result($request);

	return array_unique($members);
}

/**
 * Wrapper to call fatal_lang_error for SimpleDesk errors.
 */
function shd_fatal_lang_error($error, $log = 'general', $sprintf = array())
{
	fatal_lang_error($error, $log, $sprintf);
}

/**
 * Internal fatal error handler.
 */
function shd_fatal_error($error)
{
	fatal_error($error);
}

/**
 * Returns the mapping of sort field names to SQL expressions.
 * Used by the ticket listing to allow sortable column headers.
 */
function shd_get_sort_methods()
{
	return array(
		'ticketid' => array(
			'sql' => 'hdt.id_ticket',
		),
		'ticketname' => array(
			'sql' => 'hdt.subject',
		),
		'replies' => array(
			'sql' => 'hdt.num_replies',
		),
		'urgency' => array(
			'sql' => 'hdt.urgency',
		),
		'updated' => array(
			'sql' => 'hdt.last_updated',
		),
		'assigned' => array(
			'sql' => 'ma.real_name',
			'join' => 'LEFT JOIN {db_prefix}members AS ma ON (ma.id_member = hdt.id_member_assigned)',
		),
		'status' => array(
			'sql' => 'hdt.status',
		),
		'starter' => array(
			'sql' => 'ms.real_name',
			'join' => 'LEFT JOIN {db_prefix}members AS ms ON (ms.id_member = hdt.id_member_started)',
		),
	);
}

/**
 * Parses a sort string like "updated_desc" into its components.
 * Returns array with 'item' (field name) and 'dir' (asc/desc).
 */
function shd_parse_sort($sort_string, $default_item = 'updated', $default_dir = 'desc')
{
	$sort_methods = shd_get_sort_methods();

	if (empty($sort_string))
		return array('item' => $default_item, 'dir' => $default_dir);

	// Parse "fieldname_direction"
	$last_underscore = strrpos($sort_string, '_');
	if ($last_underscore === false)
		return array('item' => $default_item, 'dir' => $default_dir);

	$item = substr($sort_string, 0, $last_underscore);
	$dir = substr($sort_string, $last_underscore + 1);

	if (!isset($sort_methods[$item]))
		$item = $default_item;

	if (!in_array($dir, array('asc', 'desc')))
		$dir = $default_dir;

	return array('item' => $item, 'dir' => $dir);
}

/**
 * Returns the display ID for a ticket (zero-padded).
 */
function shd_display_id($id_ticket)
{
	global $modSettings;

	$zerofill = !empty($modSettings['shd_zerofill']) ? $modSettings['shd_zerofill'] : 5;
	return str_pad($id_ticket, $zerofill, '0', STR_PAD_LEFT);
}

/**
 * Marks a ticket as read for the current user.
 */
function shd_mark_ticket_read($id_ticket)
{
	global $user_info;

	$db = database();

	// Update or insert the read timestamp
	$db->insert('replace',
		'{db_prefix}helpdesk_log_read',
		array(
			'id_ticket' => 'int',
			'id_member' => 'int',
			'id_msg' => 'int',
		),
		array(
			$id_ticket,
			$user_info['id'],
			0,
		),
		array('id_ticket', 'id_member')
	);
}

/**
 * Builds the navigation context for the helpdesk pages.
 * Sets $context['shd_navigation'] with links for the sidebar/top nav.
 */
function shd_build_navigation($current_area = 'tickets')
{
	global $context, $scripturl, $txt;

	$dept_link = !empty($context['shd_department']) ? ';dept=' . $context['shd_department'] : '';

	$context['shd_navigation'] = array();

	// Home / Departments (if multi-dept)
	if (!empty($context['shd_multi_dept']))
	{
		$context['shd_navigation']['home'] = array(
			'title' => $txt['shd_home'],
			'link' => $scripturl . '?action=helpdesk;sa=main',
			'active' => ($current_area == 'home'),
		);
	}

	// Open Tickets
	$open_count = shd_count_helpdesk_tickets('open', !empty($context['shd_is_staff']));
	$context['shd_navigation']['tickets'] = array(
		'title' => $txt['shd_tickets_open'],
		'link' => $scripturl . '?action=helpdesk;sa=tickets' . $dept_link,
		'active' => ($current_area == 'tickets'),
		'count' => $open_count,
	);

	// Closed Tickets
	if (shd_allowed_to(array('shd_view_closed_own', 'shd_view_closed_any'), $context['shd_department']))
	{
		$closed_count = shd_count_helpdesk_tickets('closed');
		$context['shd_navigation']['closedtickets'] = array(
			'title' => $txt['shd_tickets_closed'],
			'link' => $scripturl . '?action=helpdesk;sa=closedtickets' . $dept_link,
			'active' => ($current_area == 'closedtickets'),
			'count' => $closed_count,
		);
	}

	// Recycle Bin
	if (shd_allowed_to('shd_access_recyclebin', $context['shd_department']))
	{
		$recycled_count = shd_count_helpdesk_tickets('recycled');
		$context['shd_navigation']['recyclebin'] = array(
			'title' => $txt['shd_tickets_recycled'],
			'link' => $scripturl . '?action=helpdesk;sa=recyclebin' . $dept_link,
			'active' => ($current_area == 'recyclebin'),
			'count' => $recycled_count,
		);
	}

	// New Ticket
	if (shd_allowed_to('shd_new_ticket', $context['shd_department']))
	{
		$context['shd_navigation']['newticket'] = array(
			'title' => $txt['shd_new_ticket'],
			'link' => $scripturl . '?action=helpdesk;sa=newticket' . $dept_link,
			'active' => ($current_area == 'newticket'),
		);
	}
}

/**
 * Determines the appropriate status for a ticket after a given action.
 *
 * Handles status transitions for actions like resolving, unresolving, deleting,
 * restoring, replying, and creating new tickets. For reply-based transitions,
 * checks whether the replier is a staff member to determine if the ticket
 * should be pending staff or pending user.
 *
 * @param string $action The action being performed: new, resolve, unresolve,
 *                       deleteticket, restoreticket, deletereply, restorereply,
 *                       reply, topictoticket
 * @param int $starter_id The member ID of the ticket starter (default 0)
 * @param int $replier_id The member ID of the last replier (default 0)
 * @param int $replies The number of replies on the ticket (default -1)
 * @param int $dept The department ID (default -1)
 * @return int One of the TICKET_STATUS_* constants
 */
function shd_determine_status($action, $starter_id = 0, $replier_id = 0, $replies = -1, $dept = -1)
{
	static $staff_cache = array();

	switch ($action)
	{
		case 'new':
			return TICKET_STATUS_NEW;

		case 'resolve':
			return TICKET_STATUS_CLOSED;

		case 'deleteticket':
			return TICKET_STATUS_DELETED;

		// For unresolve, restoreticket, deletereply, restorereply, reply, topictoticket:
		// Determine based on reply count and staff status of the last replier
		case 'unresolve':
		case 'restoreticket':
		case 'deletereply':
		case 'restorereply':
		case 'reply':
		case 'topictoticket':
			// No replies means it is a new ticket
			if ($replies == 0)
				return TICKET_STATUS_NEW;

			// Check if the replier is a staff member
			if ($dept == -1)
				$dept = 0;

			if (!isset($staff_cache[$dept]))
				$staff_cache[$dept] = shd_members_allowed_to('shd_staff', $dept);

			$replier_is_staff = in_array($replier_id, $staff_cache[$dept]);

			if ($replier_is_staff)
			{
				// Staff replied: if staff is also the ticket starter, pending staff;
				// otherwise (staff replied to someone else's ticket), pending user
				if ($replier_id == $starter_id)
					return TICKET_STATUS_PENDING_STAFF;
				else
					return TICKET_STATUS_PENDING_USER;
			}
			else
			{
				// Non-staff (user) replied: pending staff
				return TICKET_STATUS_PENDING_STAFF;
			}

		default:
			return TICKET_STATUS_NEW;
	}
}

/**
 * Recalculates ticket statistics after reply deletion or restoration.
 *
 * Counts normal and deleted replies (excluding the first message), finds the
 * last normal reply, determines the starter and last replier member IDs, and
 * updates the ticket record with the recalculated values.
 *
 * @param int $ticket The ticket ID to recalculate
 * @return array Array of ($starter_id, $replier_id, $num_replies)
 */
function shd_recalc_ids($ticket)
{
	$db = database();

	// Get the first message ID for this ticket
	$request = $db->query('', '
		SELECT id_first_msg
		FROM {db_prefix}helpdesk_tickets
		WHERE id_ticket = {int:ticket}',
		array(
			'ticket' => $ticket,
		)
	);

	if ($db->num_rows($request) == 0)
	{
		$db->free_result($request);
		return array(0, 0, 0);
	}

	list($first_msg) = $db->fetch_row($request);
	$db->free_result($request);

	$first_msg = (int) $first_msg;

	// Count normal replies (excluding first message)
	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}helpdesk_ticket_replies
		WHERE id_ticket = {int:ticket}
			AND id_msg != {int:first_msg}
			AND message_status = {int:normal}',
		array(
			'ticket' => $ticket,
			'first_msg' => $first_msg,
			'normal' => MSG_STATUS_NORMAL,
		)
	);
	list($num_replies) = $db->fetch_row($request);
	$db->free_result($request);
	$num_replies = (int) $num_replies;

	// Count deleted replies (excluding first message)
	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}helpdesk_ticket_replies
		WHERE id_ticket = {int:ticket}
			AND id_msg != {int:first_msg}
			AND message_status = {int:deleted}',
		array(
			'ticket' => $ticket,
			'first_msg' => $first_msg,
			'deleted' => MSG_STATUS_DELETED,
		)
	);
	list($deleted_replies) = $db->fetch_row($request);
	$db->free_result($request);
	$deleted_replies = (int) $deleted_replies;

	// Get the last normal reply message ID (could be the first msg if no replies)
	$request = $db->query('', '
		SELECT id_msg
		FROM {db_prefix}helpdesk_ticket_replies
		WHERE id_ticket = {int:ticket}
			AND message_status = {int:normal}
		ORDER BY id_msg DESC
		LIMIT 1',
		array(
			'ticket' => $ticket,
			'normal' => MSG_STATUS_NORMAL,
		)
	);

	if ($db->num_rows($request) > 0)
	{
		list($id_last_msg) = $db->fetch_row($request);
		$id_last_msg = (int) $id_last_msg;
	}
	else
	{
		$id_last_msg = $first_msg;
	}
	$db->free_result($request);

	// Get the starter member ID (from the first message)
	$request = $db->query('', '
		SELECT id_member
		FROM {db_prefix}helpdesk_ticket_replies
		WHERE id_msg = {int:first_msg}',
		array(
			'first_msg' => $first_msg,
		)
	);
	list($starter) = $db->fetch_row($request);
	$db->free_result($request);
	$starter = (int) $starter;

	// Get the last replier member ID (from the last normal message)
	$request = $db->query('', '
		SELECT id_member
		FROM {db_prefix}helpdesk_ticket_replies
		WHERE id_msg = {int:last_msg}',
		array(
			'last_msg' => $id_last_msg,
		)
	);
	list($replier) = $db->fetch_row($request);
	$db->free_result($request);
	$replier = (int) $replier;

	// Determine if there are any deleted replies (for the withdeleted flag)
	$withdeleted = ($deleted_replies > 0) ? MSG_STATUS_DELETED : MSG_STATUS_NORMAL;

	// Update the ticket record
	$db->query('', '
		UPDATE {db_prefix}helpdesk_tickets
		SET num_replies = {int:num_replies},
			deleted_replies = {int:deleted_replies},
			id_last_msg = {int:id_last_msg},
			id_member_started = {int:starter},
			id_member_updated = {int:replier},
			withdeleted = {int:withdeleted}
		WHERE id_ticket = {int:ticket}',
		array(
			'num_replies' => $num_replies,
			'deleted_replies' => $deleted_replies,
			'id_last_msg' => $id_last_msg,
			'starter' => $starter,
			'replier' => $replier,
			'withdeleted' => $withdeleted,
			'ticket' => $ticket,
		)
	);

	return array($starter, $replier, $num_replies);
}

/**
 * Returns a profile link for a member name, or just the name if not viewable.
 *
 * @param string $name The member's display name
 * @param int $id The member's ID (0 for guest/system)
 * @return string HTML link to the profile, or just the name
 */
function shd_profile_link($name, $id = 0)
{
	global $user_info, $scripturl;
	static $any = null;
	static $own = null;

	if ($any === null)
	{
		$any = allowedTo('profile_view_any') || shd_allowed_to('shd_view_profile_any', 0);
		$own = allowedTo('profile_view_own') || shd_allowed_to('shd_view_profile_own', 0);
	}

	if (empty($id))
		return $name;
	elseif ($any || ($own && $id == $user_info['id']))
		return '<a href="' . $scripturl . '?action=profile;u=' . $id . '">' . $name . '</a>';

	return $name;
}

/**
 * Returns the URL for a SimpleDesk image file.
 *
 * Checks the plugins image directory first, then falls back to the standard simpledesk images.
 *
 * @param string $filename The image filename
 * @return string Full URL to the image
 */
function shd_image_url($filename)
{
	global $settings;

	if (file_exists($settings['default_theme_dir'] . '/images/sd_plugins/' . $filename))
		return $settings['default_theme_url'] . '/images/sd_plugins/' . $filename;

	return $settings['default_theme_url'] . '/images/simpledesk/' . $filename;
}

/**
 * Wrapper for constructPageIndex that replaces the expand_pages span with an ellipsis.
 *
 * @param string $base_url Base URL for pagination links
 * @param int &$start Starting item reference (may be adjusted)
 * @param int $max_value Total number of items
 * @param int $num_per_page Items per page
 * @param bool $flexible_start Whether to allow flexible start values
 * @return string HTML page index string
 */
function shd_no_expand_pageindex($base_url, &$start, $max_value, $num_per_page, $flexible_start = false)
{
	return preg_replace('~<span class="expand_pages"([^<]+)~i', '<span style="font-weight: bold;"> ... ', constructPageIndex($base_url, $start, $max_value, $num_per_page, $flexible_start));
}
