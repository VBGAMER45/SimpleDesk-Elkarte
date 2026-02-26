<?php
/**
 * SimpleDesk Display Subs
 *
 * Helper functions for ticket display: loading ticket data,
 * formatting replies, attachment handling, and action log entries.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Loads basic ticket data from the database.
 *
 * Returns an associative array with the ticket's core fields,
 * or false if the ticket doesn't exist or the user can't see it.
 *
 * @param int $ticket_id The ticket ID to load
 * @return array|false Ticket data array or false
 */
function shd_load_ticket($ticket_id)
{
	global $user_info;

	$db = database();

	$request = shd_db_query('', '
		SELECT hdt.id_ticket, hdt.id_dept, hdt.id_first_msg, hdt.id_last_msg,
			hdt.id_member_started, hdt.id_member_updated, hdt.id_member_assigned,
			hdt.subject, hdt.status, hdt.urgency, hdt.private, hdt.num_replies,
			hdt.deleted_replies, hdt.last_updated, hdt.withdeleted,
			hdd.dept_name
		FROM {db_prefix}helpdesk_tickets AS hdt
			LEFT JOIN {db_prefix}helpdesk_depts AS hdd ON (hdd.id_dept = hdt.id_dept)
		WHERE hdt.id_ticket = {int:ticket}
			AND {query_see_ticket}',
		array(
			'ticket' => $ticket_id,
		)
	);

	if ($db->num_rows($request) == 0)
	{
		$db->free_result($request);
		return false;
	}

	$row = $db->fetch_assoc($request);
	$db->free_result($request);

	return $row;
}

/**
 * Loads the full opening message for a ticket.
 *
 * @param int $first_msg_id The ID of the ticket's first message
 * @return array|false Message data or false
 */
function shd_load_ticket_message($first_msg_id)
{
	$db = database();

	$request = $db->query('', '
		SELECT hdtr.id_msg, hdtr.id_member, hdtr.body, hdtr.smileys_enabled,
			hdtr.poster_time, hdtr.poster_ip, hdtr.poster_name, hdtr.poster_email,
			hdtr.modified_time, hdtr.modified_name, hdtr.modified_member,
			hdtr.message_status
		FROM {db_prefix}helpdesk_ticket_replies AS hdtr
		WHERE hdtr.id_msg = {int:msg}',
		array(
			'msg' => $first_msg_id,
		)
	);

	if ($db->num_rows($request) == 0)
	{
		$db->free_result($request);
		return false;
	}

	$row = $db->fetch_assoc($request);
	$db->free_result($request);

	return $row;
}

/**
 * Counts the number of visible replies for a ticket.
 *
 * @param int $ticket_id The ticket ID
 * @param int $first_msg The ID of the first message (excluded from count)
 * @param bool $include_deleted Whether to include deleted replies
 * @return int Reply count
 */
function shd_count_ticket_replies($ticket_id, $first_msg, $include_deleted = false)
{
	$db = database();

	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}helpdesk_ticket_replies
		WHERE id_ticket = {int:ticket}
			AND id_msg != {int:first_msg}' . ($include_deleted ? '' : '
			AND message_status = {int:normal}'),
		array(
			'ticket' => $ticket_id,
			'first_msg' => $first_msg,
			'normal' => MSG_STATUS_NORMAL,
		)
	);

	list($count) = $db->fetch_row($request);
	$db->free_result($request);

	return (int) $count;
}

/**
 * Loads paginated replies for a ticket.
 *
 * @param int $ticket_id The ticket ID
 * @param int $first_msg The first message ID (excluded)
 * @param int $start Pagination start offset
 * @param int $per_page Number of replies per page
 * @param bool $include_deleted Whether to include deleted replies
 * @return array Array of reply data
 */
function shd_load_ticket_replies($ticket_id, $first_msg, $start, $per_page, $include_deleted = false)
{
	global $scripturl, $txt, $user_info;

	$db = database();

	$request = $db->query('', '
		SELECT hdtr.id_msg, hdtr.id_member, hdtr.body, hdtr.smileys_enabled,
			hdtr.poster_time, hdtr.poster_ip, hdtr.poster_name, hdtr.poster_email,
			hdtr.modified_time, hdtr.modified_name, hdtr.modified_member,
			hdtr.message_status,
			COALESCE(mem.real_name, hdtr.poster_name) AS poster_name_display
		FROM {db_prefix}helpdesk_ticket_replies AS hdtr
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = hdtr.id_member)
		WHERE hdtr.id_ticket = {int:ticket}
			AND hdtr.id_msg != {int:first_msg}' . ($include_deleted ? '' : '
			AND hdtr.message_status = {int:normal}') . '
		ORDER BY hdtr.id_msg ASC
		LIMIT {int:start}, {int:per_page}',
		array(
			'ticket' => $ticket_id,
			'first_msg' => $first_msg,
			'start' => $start,
			'per_page' => $per_page,
			'normal' => MSG_STATUS_NORMAL,
		)
	);

	$replies = array();
	while ($row = $db->fetch_assoc($request))
	{
		$replies[$row['id_msg']] = array(
			'id' => (int) $row['id_msg'],
			'member' => array(
				'id' => (int) $row['id_member'],
				'name' => $row['poster_name_display'],
				'link' => !empty($row['id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name_display'] . '</a>' : $row['poster_name_display'],
			),
			'body' => parse_bbc($row['body'], $row['smileys_enabled']),
			'time' => standardTime($row['poster_time']),
			'timestamp' => (int) $row['poster_time'],
			'ip_address' => $row['poster_ip'],
			'message_status' => (int) $row['message_status'],
			'is_deleted' => ($row['message_status'] == MSG_STATUS_DELETED),
			'modified' => null,
			'link' => $scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $ticket_id . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
		);

		if (!empty($row['modified_time']))
		{
			$replies[$row['id_msg']]['modified'] = array(
				'time' => standardTime($row['modified_time']),
				'timestamp' => (int) $row['modified_time'],
				'name' => $row['modified_name'],
				'id' => (int) $row['modified_member'],
			);
		}
	}
	$db->free_result($request);

	return $replies;
}

/**
 * Loads action log entries for a specific ticket.
 *
 * @param int $ticket_id The ticket ID
 * @param int $limit Maximum entries to return
 * @return array Array of log entries
 */
function shd_load_ticket_log($ticket_id, $limit = 10)
{
	$db = database();

	$request = $db->query('', '
		SELECT la.id_action, la.log_time, la.id_member, la.ip, la.action, la.id_ticket,
			la.id_msg, la.extra,
			COALESCE(mem.real_name, {string:unknown}) AS member_name
		FROM {db_prefix}helpdesk_log_action AS la
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = la.id_member)
		WHERE la.id_ticket = {int:ticket}
		ORDER BY la.log_time DESC
		LIMIT {int:limit}',
		array(
			'ticket' => $ticket_id,
			'limit' => $limit,
			'unknown' => 'Unknown',
		)
	);

	$log = array();
	while ($row = $db->fetch_assoc($request))
	{
		$log[] = array(
			'id' => (int) $row['id_action'],
			'time' => standardTime($row['log_time']),
			'timestamp' => (int) $row['log_time'],
			'member_name' => $row['member_name'],
			'member_id' => (int) $row['id_member'],
			'ip' => $row['ip'],
			'action' => $row['action'],
			'msg_id' => (int) $row['id_msg'],
			'extra' => $row['extra'],
		);
	}
	$db->free_result($request);

	return $log;
}

/**
 * Checks if a set of members are helpdesk staff.
 *
 * @param array $member_ids Array of member IDs to check
 * @param int $dept Department ID
 * @return array Associative array of member_id => is_staff (bool)
 */
function shd_check_staff_status($member_ids, $dept = 0)
{
	if (empty($member_ids))
		return array();

	$db = database();

	// Get all members who have staff-level roles
	$request = $db->query('', '
		SELECT DISTINCT hdrg.id_group
		FROM {db_prefix}helpdesk_role_groups AS hdrg
			INNER JOIN {db_prefix}helpdesk_roles AS hdr ON (hdrg.id_role = hdr.id_role)
		WHERE hdr.template IN ({array_int:staff_templates})',
		array(
			'staff_templates' => array(ROLE_STAFF, ROLE_ADMIN),
		)
	);

	$staff_groups = array();
	while ($row = $db->fetch_assoc($request))
		$staff_groups[] = (int) $row['id_group'];
	$db->free_result($request);

	if (empty($staff_groups))
	{
		$result = array();
		foreach ($member_ids as $id)
			$result[$id] = false;
		return $result;
	}

	// Check which members are in staff groups
	$request = $db->query('', '
		SELECT id_member, id_group, additional_groups
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:members})',
		array(
			'members' => $member_ids,
		)
	);

	$result = array();
	foreach ($member_ids as $id)
		$result[$id] = false;

	while ($row = $db->fetch_assoc($request))
	{
		if (in_array((int) $row['id_group'], $staff_groups))
		{
			$result[(int) $row['id_member']] = true;
			continue;
		}

		if (!empty($row['additional_groups']))
		{
			$additional = array_map('intval', explode(',', $row['additional_groups']));
			if (!empty(array_intersect($additional, $staff_groups)))
				$result[(int) $row['id_member']] = true;
		}
	}
	$db->free_result($request);

	return $result;
}

/**
 * Formats text for ticket/reply display: parses BBC and handles smileys.
 *
 * @param string $text Raw text from the database
 * @param bool $smileys_enabled Whether smileys are enabled for this message
 * @return string Formatted HTML
 */
function shd_format_text($text, $smileys_enabled = true)
{
	return parse_bbc($text, $smileys_enabled);
}
