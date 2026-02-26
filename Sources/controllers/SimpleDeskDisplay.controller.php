<?php
/**
 * SimpleDesk Display Controller
 *
 * Handles viewing individual tickets with their replies, action log,
 * and full details. Builds action buttons based on user permissions
 * and ticket state.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Controller for displaying tickets.
 */
class SimpleDeskDisplay_Controller extends Action_Controller
{
	/**
	 * Default entry point - redirects to main helpdesk.
	 */
	public function action_index()
	{
		redirectexit('action=helpdesk');
	}

	/**
	 * Display a single ticket with replies, action log, and action buttons.
	 *
	 * Validates the ticket exists and is visible, loads ticket data including
	 * the opening post, paginated replies, action log entries, and builds
	 * the full set of context-aware action buttons. Marks the ticket as read
	 * for the current user.
	 */
	public function action_ticket()
	{
		global $context, $scripturl, $txt, $modSettings, $user_info;

		$db = database();

		// Setup and initialization
		$ticket_id = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		require_once(SUBSDIR . '/SimpleDeskDisplay.subs.php');
		require_once(SUBSDIR . '/SimpleDeskPost.subs.php');

		loadLanguage('SimpleDesk');
		loadTemplate('SimpleDeskDisplay');
		loadCSSFile('helpdesk.css');
		loadJavascriptFile('helpdesk.js');

		// Validate ticket exists
		if (empty($ticket_id))
			fatal_lang_error('shd_no_ticket', false);

		// -----------------------------------------------------------------
		// 2. Load ticket data with opening post
		// -----------------------------------------------------------------
		$request = shd_db_query('', '
			SELECT hdt.*, hdtr.body, hdtr.smileys_enabled, hdtr.poster_name, hdtr.poster_email,
				hdtr.poster_time, hdtr.poster_ip, hdtr.modified_time, hdtr.modified_name, hdtr.modified_member,
				COALESCE(ms.real_name, hdtr.poster_name) AS starter_name,
				COALESCE(ma.real_name, {string:empty}) AS assigned_name,
				hdd.dept_name
			FROM {db_prefix}helpdesk_tickets AS hdt
				INNER JOIN {db_prefix}helpdesk_ticket_replies AS hdtr ON (hdtr.id_msg = hdt.id_first_msg)
				LEFT JOIN {db_prefix}members AS ms ON (ms.id_member = hdt.id_member_started)
				LEFT JOIN {db_prefix}members AS ma ON (ma.id_member = hdt.id_member_assigned)
				LEFT JOIN {db_prefix}helpdesk_depts AS hdd ON (hdd.id_dept = hdt.id_dept)
			WHERE hdt.id_ticket = {int:ticket}
				AND {query_see_ticket}',
			array(
				'ticket' => $ticket_id,
				'empty' => '',
			)
		);

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		if (empty($row))
			fatal_lang_error('shd_no_ticket', false);

		// Track the department for permission checks
		$dept = (int) $row['id_dept'];
		$context['shd_department'] = $dept;

		// Determine ticket state flags
		$status = (int) $row['status'];
		$is_closed = ($status == TICKET_STATUS_CLOSED);
		$is_deleted = ($status == TICKET_STATUS_DELETED);
		$urgency = (int) $row['urgency'];
		$starter_id = (int) $row['id_member_started'];
		$assigned_id = (int) $row['id_member_assigned'];

		// -----------------------------------------------------------------
		// Permission checks for this ticket
		// -----------------------------------------------------------------
		$can_view_ip = shd_allowed_to(array('shd_view_ip_own', 'shd_view_ip_any'), $dept);
		$can_reply = shd_allowed_to(array('shd_reply_ticket_own', 'shd_reply_ticket_any'), $dept) && !$is_closed && !$is_deleted;
		$can_edit = shd_allowed_to(array('shd_edit_ticket_own', 'shd_edit_ticket_any'), $dept) && !$is_closed && !$is_deleted;
		$can_resolve = shd_allowed_to(array('shd_resolve_ticket_own', 'shd_resolve_ticket_any'), $dept) && !$is_closed && !$is_deleted;
		$can_unresolve = shd_allowed_to(array('shd_unresolve_ticket_own', 'shd_unresolve_ticket_any'), $dept) && $is_closed && !$is_deleted;
		$can_delete = shd_allowed_to(array('shd_delete_ticket_own', 'shd_delete_ticket_any'), $dept) && !$is_deleted;
		$can_restore = shd_allowed_to(array('shd_restore_ticket_own', 'shd_restore_ticket_any'), $dept) && $is_deleted;
		$can_move = shd_allowed_to(array('shd_move_dept_own', 'shd_move_dept_any'), $dept);
		$can_assign = shd_allowed_to(array('shd_assign_ticket_own', 'shd_assign_ticket_any'), $dept) && !$is_closed && !$is_deleted;
		$can_change_privacy = shd_allowed_to(array('shd_alter_privacy_own', 'shd_alter_privacy_any'), $dept) && !$is_closed && !$is_deleted;
		$can_see_deleted = shd_allowed_to('shd_access_recyclebin', $dept);

		// Urgency controls
		$urgency_change = shd_can_alter_urgency($urgency, $starter_id, $is_closed, $is_deleted, $dept);

		// -----------------------------------------------------------------
		// Build $context['ticket']
		// -----------------------------------------------------------------
		$context['ticket'] = array(
			'id' => $ticket_id,
			'display_id' => shd_display_id($ticket_id),
			'dept' => $dept,
			'dept_name' => $row['dept_name'],
			'subject' => $row['subject'],
			'body' => parse_bbc($row['body'], $row['smileys_enabled']),
			'poster_time' => standardTime($row['poster_time']),
			'poster_name' => $row['poster_name'],
			'starter_name' => $row['starter_name'],
			'id_member' => $starter_id,
			'id_member_assigned' => $assigned_id,
			'first_msg' => (int) $row['id_first_msg'],
			'num_replies' => (int) $row['num_replies'],
			'status' => array(
				'level' => $status,
				'label' => isset($txt['shd_status_' . $status]) ? $txt['shd_status_' . $status] : '',
			),
			'urgency' => array(
				'level' => $urgency,
				'label' => isset($txt['shd_urgency_' . $urgency]) ? $txt['shd_urgency_' . $urgency] : '',
				'increase' => $urgency_change['increase'],
				'decrease' => $urgency_change['decrease'],
			),
			'assigned' => array(
				'id' => $assigned_id,
				'name' => !empty($row['assigned_name']) ? $row['assigned_name'] : (isset($txt['shd_unassigned']) ? $txt['shd_unassigned'] : 'Unassigned'),
				'link' => !empty($assigned_id)
					? '<a href="' . $scripturl . '?action=profile;u=' . $assigned_id . '">' . $row['assigned_name'] . '</a>'
					: (isset($txt['shd_unassigned']) ? $txt['shd_unassigned'] : 'Unassigned'),
			),
			'member' => array(
				'id' => $starter_id,
				'name' => $row['starter_name'],
				'link' => !empty($starter_id)
					? '<a href="' . $scripturl . '?action=profile;u=' . $starter_id . '">' . $row['starter_name'] . '</a>'
					: $row['starter_name'],
			),
			'privacy' => array(
				'level' => !empty($row['private']) ? 1 : 0,
				'label' => !empty($row['private'])
					? (isset($txt['shd_ticket_private']) ? $txt['shd_ticket_private'] : 'Private')
					: (isset($txt['shd_ticket_notprivate']) ? $txt['shd_ticket_notprivate'] : 'Not Private'),
				'can_change' => $can_change_privacy,
			),
			'closed' => $is_closed,
			'deleted' => $is_deleted,
			'ip_address' => $can_view_ip ? $row['poster_ip'] : '',
			'modified' => array(),

			// Permission flags
			'can_reply' => $can_reply,
			'can_edit' => $can_edit,
			'can_resolve' => $can_resolve,
			'can_unresolve' => $can_unresolve,
			'can_delete' => $can_delete,
			'can_restore' => $can_restore,
			'can_move' => $can_move,
			'can_assign' => $can_assign,
			'can_view_ip' => $can_view_ip,
		);

		// Modified info for the opening post
		if (!empty($row['modified_time']))
		{
			$context['ticket']['modified'] = array(
				'time' => standardTime($row['modified_time']),
				'name' => $row['modified_name'],
				'id' => (int) $row['modified_member'],
			);
		}

		// -----------------------------------------------------------------
		// 3. Reply count for pagination
		// -----------------------------------------------------------------
		$reply_where = 'hdtr.id_ticket = {int:ticket} AND hdtr.id_msg != {int:first_msg}';
		if (!$can_see_deleted)
			$reply_where .= ' AND hdtr.message_status = {int:normal}';

		$request = shd_db_query('', '
			SELECT COUNT(*)
			FROM {db_prefix}helpdesk_ticket_replies AS hdtr
			WHERE ' . $reply_where,
			array(
				'ticket' => $ticket_id,
				'first_msg' => (int) $row['id_first_msg'],
				'normal' => MSG_STATUS_NORMAL,
			)
		);
		list($total_replies) = $db->fetch_row($request);
		$db->free_result($request);

		$total_replies = (int) $total_replies;
		$per_page = 20;
		$start = isset($_REQUEST['start']) ? max(0, (int) $_REQUEST['start']) : 0;

		// Fix start if beyond total
		if ($start >= $total_replies && $total_replies > 0)
			$start = max(0, (int) (($total_replies - 1) / $per_page) * $per_page);

		$context['page_index'] = constructPageIndex(
			$scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $ticket_id . ';start=%1$d',
			$start,
			$total_replies,
			$per_page
		);
		$context['start'] = $start;

		// -----------------------------------------------------------------
		// 4. Load replies (paginated)
		// -----------------------------------------------------------------
		$reply_status_clause = '';
		if (!$can_see_deleted)
			$reply_status_clause = 'AND hdtr.message_status = {int:normal}';

		$request = shd_db_query('', '
			SELECT hdtr.id_msg, hdtr.id_member, hdtr.body, hdtr.smileys_enabled,
				hdtr.poster_time, hdtr.poster_ip, hdtr.poster_name, hdtr.poster_email,
				hdtr.modified_time, hdtr.modified_name, hdtr.modified_member,
				hdtr.message_status,
				COALESCE(mem.real_name, hdtr.poster_name) AS poster_name_display
			FROM {db_prefix}helpdesk_ticket_replies AS hdtr
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = hdtr.id_member)
			WHERE hdtr.id_ticket = {int:ticket}
				AND hdtr.id_msg != {int:first_msg}
				' . $reply_status_clause . '
			ORDER BY hdtr.id_msg ASC
			LIMIT {int:start}, {int:per_page}',
			array(
				'ticket' => $ticket_id,
				'first_msg' => (int) $row['id_first_msg'],
				'normal' => MSG_STATUS_NORMAL,
				'start' => $start,
				'per_page' => $per_page,
			)
		);

		$context['ticket_replies'] = array();
		$last_msg_id = (int) $row['id_first_msg'];

		while ($reply = $db->fetch_assoc($request))
		{
			$reply_id = (int) $reply['id_msg'];
			$reply_member = (int) $reply['id_member'];
			$reply_status = (int) $reply['message_status'];

			if ($reply_id > $last_msg_id)
				$last_msg_id = $reply_id;

			$is_staff = shd_allowed_to('shd_staff', $dept);

			// Per-reply permission checks
			$can_edit_reply = shd_allowed_to(array('shd_edit_reply_own', 'shd_edit_reply_any'), $dept) && !$is_closed && !$is_deleted && ($reply_status == MSG_STATUS_NORMAL);
			$can_delete_reply = shd_allowed_to(array('shd_delete_reply_own', 'shd_delete_reply_any'), $dept) && !$is_closed && !$is_deleted && ($reply_status == MSG_STATUS_NORMAL);
			$can_restore_reply = shd_allowed_to(array('shd_restore_reply_own', 'shd_restore_reply_any'), $dept) && ($reply_status == MSG_STATUS_DELETED);

			$reply_entry = array(
				'id' => $reply_id,
				'member' => array(
					'id' => $reply_member,
					'name' => $reply['poster_name_display'],
					'link' => !empty($reply_member)
						? '<a href="' . $scripturl . '?action=profile;u=' . $reply_member . '">' . $reply['poster_name_display'] . '</a>'
						: $reply['poster_name_display'],
				),
				'body' => parse_bbc($reply['body'], $reply['smileys_enabled']),
				'time' => standardTime($reply['poster_time']),
				'ip_address' => $can_view_ip ? $reply['poster_ip'] : '',
				'is_staff' => $is_staff,
				'message_status' => $reply_status,
				'can_edit' => $can_edit_reply,
				'can_delete' => $can_delete_reply,
				'can_restore' => $can_restore_reply,
				'modified' => array(),
				'link' => $scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $ticket_id . ';msg=' . $reply_id . '#msg' . $reply_id,
			);

			// Modified info for this reply
			if (!empty($reply['modified_time']))
			{
				$reply_entry['modified'] = array(
					'time' => standardTime($reply['modified_time']),
					'name' => $reply['modified_name'],
					'id' => (int) $reply['modified_member'],
				);
			}

			$context['ticket_replies'][$reply_id] = $reply_entry;
		}
		$db->free_result($request);

		// -----------------------------------------------------------------
		// 4b. Load attachments for ticket and replies
		// -----------------------------------------------------------------
		if (!empty($modSettings['shd_attachments_mode']))
		{
			$all_msg_ids = array((int) $row['id_first_msg']);
			foreach ($context['ticket_replies'] as $reply_entry)
				$all_msg_ids[] = $reply_entry['id'];

			shd_display_load_attachments($ticket_id, $all_msg_ids);
		}

		// -----------------------------------------------------------------
		// 5. Action buttons
		// -----------------------------------------------------------------
		$session_param = $context['session_var'] . '=' . $context['session_id'];

		$context['ticket_buttons'] = array();

		if ($can_reply)
		{
			$context['ticket_buttons']['reply'] = array(
				'text' => 'shd_reply_ticket',
				'url' => $scripturl . '?action=helpdesk;sa=reply;ticket=' . $ticket_id,
				'display' => true,
			);
		}

		if ($can_edit)
		{
			$context['ticket_buttons']['edit'] = array(
				'text' => 'shd_edit_ticket',
				'url' => $scripturl . '?action=helpdesk;sa=editticket;ticket=' . $ticket_id,
				'display' => true,
			);
		}

		if ($can_resolve)
		{
			$context['ticket_buttons']['resolve'] = array(
				'text' => 'shd_resolve_ticket',
				'url' => $scripturl . '?action=helpdesk;sa=resolve;ticket=' . $ticket_id . ';' . $session_param,
				'display' => true,
			);
		}

		if ($can_unresolve)
		{
			$context['ticket_buttons']['unresolve'] = array(
				'text' => 'shd_unresolve_ticket',
				'url' => $scripturl . '?action=helpdesk;sa=resolve;ticket=' . $ticket_id . ';' . $session_param,
				'display' => true,
			);
		}

		if ($can_delete)
		{
			$context['ticket_buttons']['delete'] = array(
				'text' => 'shd_delete_ticket',
				'url' => $scripturl . '?action=helpdesk;sa=deleteticket;ticket=' . $ticket_id . ';' . $session_param,
				'display' => true,
			);
		}

		if ($can_restore)
		{
			$context['ticket_buttons']['restore'] = array(
				'text' => 'shd_restore_ticket',
				'url' => $scripturl . '?action=helpdesk;sa=restoreticket;ticket=' . $ticket_id . ';' . $session_param,
				'display' => true,
			);
		}

		if ($can_move)
		{
			$context['ticket_buttons']['movedept'] = array(
				'text' => 'shd_move_dept',
				'url' => $scripturl . '?action=helpdesk;sa=movedept;ticket=' . $ticket_id,
				'display' => true,
			);
		}

		if ($can_assign)
		{
			$context['ticket_buttons']['assign'] = array(
				'text' => 'shd_assign_ticket',
				'url' => $scripturl . '?action=helpdesk;sa=assign;ticket=' . $ticket_id,
				'display' => true,
			);
		}

		// Mark Unread is available to any logged-in user viewing the ticket
		if (!empty($context['user']['id']))
		{
			$context['ticket_buttons']['markunread'] = array(
				'text' => 'shd_mark_unread',
				'url' => $scripturl . '?action=helpdesk;sa=markunread;ticket=' . $ticket_id . ';' . $session_param,
				'display' => true,
			);
		}

		// -----------------------------------------------------------------
		// 6. Action Log
		// -----------------------------------------------------------------
		$context['ticket_log'] = array();

		if (!empty($modSettings['shd_display_ticket_logs']))
		{
			$request = shd_db_query('', '
				SELECT la.id_action, la.log_time, la.id_member, la.ip, la.action, la.id_ticket, la.id_msg, la.extra,
					COALESCE(mem.real_name, la.ip) AS member_name
				FROM {db_prefix}helpdesk_log_action AS la
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = la.id_member)
				WHERE la.id_ticket = {int:ticket}
				ORDER BY la.log_time DESC
				LIMIT 10',
				array(
					'ticket' => $ticket_id,
				)
			);

			while ($log_row = $db->fetch_assoc($request))
			{
				$context['ticket_log'][] = array(
					'id' => (int) $log_row['id_action'],
					'time' => standardTime($log_row['log_time']),
					'timestamp' => (int) $log_row['log_time'],
					'member' => array(
						'id' => (int) $log_row['id_member'],
						'name' => $log_row['member_name'],
						'link' => !empty($log_row['id_member'])
							? '<a href="' . $scripturl . '?action=profile;u=' . $log_row['id_member'] . '">' . $log_row['member_name'] . '</a>'
							: $log_row['member_name'],
					),
					'ip' => $can_view_ip ? $log_row['ip'] : '',
					'action' => $log_row['action'],
					'id_ticket' => (int) $log_row['id_ticket'],
					'id_msg' => (int) $log_row['id_msg'],
					'extra' => !empty($log_row['extra']) ? json_decode($log_row['extra'], true) : array(),
				);
			}
			$db->free_result($request);
		}

		// -----------------------------------------------------------------
		// 7. Mark as Read
		// -----------------------------------------------------------------
		if (!empty($context['user']['id']))
		{
			$db->insert('replace',
				'{db_prefix}helpdesk_log_read',
				array(
					'id_ticket' => 'int',
					'id_member' => 'int',
					'id_msg' => 'int',
				),
				array(
					$ticket_id,
					$context['user']['id'],
					$last_msg_id,
				),
				array('id_ticket', 'id_member')
			);
		}

		// -----------------------------------------------------------------
		// 8. Page Setup
		// -----------------------------------------------------------------
		$context['page_title'] = $row['subject'];
		$context['sub_template'] = 'shd_display_ticket';
		$context['can_reply'] = $can_reply;

		// Linktree
		$context['linktree'][] = array(
			'name' => $txt['shd_helpdesk'],
			'url' => $scripturl . '?action=helpdesk;sa=main',
		);
		$context['linktree'][] = array(
			'name' => $txt['shd_tickets_open'],
			'url' => $scripturl . '?action=helpdesk;sa=tickets' . (!empty($dept) ? ';dept=' . $dept : ''),
		);
		$context['linktree'][] = array(
			'name' => $row['subject'],
			'url' => $scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $ticket_id,
		);
	}
}
