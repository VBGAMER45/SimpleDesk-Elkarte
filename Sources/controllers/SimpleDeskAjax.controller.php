<?php
/**
 * SimpleDesk Ajax Controller
 *
 * Handles AJAX requests for helpdesk operations. Returns JSON responses
 * for ticket privacy toggling, urgency changes, quoting, canned replies,
 * assignment operations, and notification recipient listing.
 *
 * Reached via ?action=helpdesk;sa=ajax;op={subaction}
 *
 * Ported from SMF SimpleDesk-AjaxHandler.php to ElkArte 1.1.9.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Controller for AJAX-based helpdesk actions.
 */
class SimpleDeskAjax_Controller extends Action_Controller
{
	/**
	 * Main AJAX dispatcher.
	 *
	 * Routes to the appropriate sub-action based on the 'op' request parameter.
	 * All sub-actions return JSON and terminate via die().
	 */
	public function action_index()
	{
		global $context, $modSettings, $user_info;

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		if (empty($modSettings['helpdesk_active']))
			$this->_ajax_error('shd_inactive');

		if ($user_info['is_guest'])
			$this->_ajax_error('cannot_access_helpdesk');

		if (!shd_allowed_to('access_helpdesk', 0))
			$this->_ajax_error('cannot_access_helpdesk');

		loadLanguage('SimpleDesk');

		$op = isset($_REQUEST['op']) ? $_REQUEST['op'] : '';

		$subActions = array(
			'privacy' => 'action_privacy',
			'urgency' => 'action_urgency',
			'urgencylist' => 'action_urgencylist',
			'quote' => 'action_quote',
			'canned' => 'action_canned',
			'assign' => 'action_assign',
			'assign2' => 'action_assign2',
			'notify' => 'action_notify',
		);

		if (isset($subActions[$op]))
			$this->{$subActions[$op]}();
		else
			$this->_ajax_error('shd_no_ticket');
	}

	/**
	 * Toggle ticket privacy via AJAX.
	 *
	 * Verifies the ticket exists, the user has the shd_alter_privacy_any or
	 * shd_alter_privacy_own permission, flips the privacy flag, logs the
	 * action, and returns the new privacy state.
	 */
	public function action_privacy()
	{
		global $context, $user_info, $txt;

		$db = database();

		checkSession('get');

		$ticket_id = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;

		if (empty($ticket_id))
			$this->_ajax_error('shd_no_ticket');

		// Load the ticket
		$request = shd_db_query('', '
			SELECT hdt.id_ticket, hdt.id_member_started, hdt.private, hdt.subject,
				hdt.status, hdt.id_dept
			FROM {db_prefix}helpdesk_tickets AS hdt
			WHERE hdt.id_ticket = {int:ticket}
				AND {query_see_ticket}',
			array(
				'ticket' => $ticket_id,
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			$this->_ajax_error('shd_no_ticket');
		}

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		$dept = (int) $row['id_dept'];
		$status = (int) $row['status'];
		$starter_id = (int) $row['id_member_started'];
		$is_private = !empty($row['private']);
		$subject = $row['subject'];

		// Cannot change privacy on closed or deleted tickets
		if ($status == TICKET_STATUS_CLOSED || $status == TICKET_STATUS_DELETED)
			$this->_ajax_error('shd_cannot_change_privacy');

		// Permission check
		$is_own = ($starter_id == $user_info['id']);
		$can_any = shd_allowed_to('shd_alter_privacy_any', $dept);
		$can_own = shd_allowed_to('shd_alter_privacy_own', $dept) && $is_own;

		if (!$can_any && !$can_own)
			$this->_ajax_error('shd_cannot_change_privacy');

		// Toggle the privacy flag
		$new_private = $is_private ? 0 : 1;

		$msgOptions = array();
		$ticketOptions = array(
			'id' => $ticket_id,
			'dept' => $dept,
			'private' => $new_private,
		);
		$posterOptions = array();

		require_once(SUBSDIR . '/SimpleDeskPost.subs.php');
		shd_modify_ticket_post($msgOptions, $ticketOptions, $posterOptions);

		// Log the action
		$log_action = $new_private ? 'markprivate' : 'marknotprivate';
		shd_log_action($log_action, array(
			'ticket' => $ticket_id,
			'subject' => $subject,
		));

		// Return the new state
		$this->_ajax_response(array(
			'success' => true,
			'privacy' => $new_private,
			'label' => $new_private
				? (isset($txt['shd_ticket_private']) ? $txt['shd_ticket_private'] : 'Private')
				: (isset($txt['shd_ticket_notprivate']) ? $txt['shd_ticket_notprivate'] : 'Not Private'),
		));
	}

	/**
	 * Change ticket urgency via AJAX.
	 *
	 * Accepts a direction parameter (increase/decrease), verifies the user
	 * has permissions for the urgency change via shd_can_alter_urgency(),
	 * updates the database, logs the action, and returns the new urgency level.
	 */
	public function action_urgency()
	{
		global $context, $user_info, $txt;

		$db = database();

		checkSession('get');

		$ticket_id = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;

		if (empty($ticket_id))
			$this->_ajax_error('shd_no_ticket');

		$change = isset($_REQUEST['change']) ? $_REQUEST['change'] : '';

		if (!in_array($change, array('increase', 'decrease')))
			$this->_ajax_error('shd_cannot_change_urgency');

		// Load the ticket
		$request = shd_db_query('', '
			SELECT hdt.id_ticket, hdt.id_member_started, hdt.subject,
				hdt.urgency, hdt.status, hdt.id_dept
			FROM {db_prefix}helpdesk_tickets AS hdt
			WHERE hdt.id_ticket = {int:ticket}
				AND {query_see_ticket}',
			array(
				'ticket' => $ticket_id,
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			$this->_ajax_error('shd_no_ticket');
		}

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		$dept = (int) $row['id_dept'];
		$status = (int) $row['status'];
		$starter_id = (int) $row['id_member_started'];
		$urgency = (int) $row['urgency'];
		$subject = $row['subject'];

		$is_closed = ($status == TICKET_STATUS_CLOSED);
		$is_deleted = ($status == TICKET_STATUS_DELETED);

		// Use the standard urgency permission check
		$can_urgency = shd_can_alter_urgency($urgency, $starter_id, $is_closed, $is_deleted, $dept);

		if ($change === 'increase' && !empty($can_urgency['increase']))
			$new_urgency = $urgency + 1;
		elseif ($change === 'decrease' && !empty($can_urgency['decrease']))
			$new_urgency = $urgency - 1;
		else
			$this->_ajax_error('shd_cannot_change_urgency');

		// Clamp to valid range
		if ($new_urgency < TICKET_URGENCY_LOW)
			$new_urgency = TICKET_URGENCY_LOW;
		if ($new_urgency > TICKET_URGENCY_CRITICAL)
			$new_urgency = TICKET_URGENCY_CRITICAL;

		// Update the ticket urgency
		$msgOptions = array();
		$ticketOptions = array(
			'id' => $ticket_id,
			'dept' => $dept,
			'urgency' => $new_urgency,
		);
		$posterOptions = array();

		require_once(SUBSDIR . '/SimpleDeskPost.subs.php');
		shd_modify_ticket_post($msgOptions, $ticketOptions, $posterOptions);

		// Log the action
		$log_action = ($change === 'increase') ? 'urgency_increase' : 'urgency_decrease';
		shd_log_action($log_action, array(
			'ticket' => $ticket_id,
			'subject' => $subject,
			'urgency' => $new_urgency,
		));

		// Recalculate what the user can do now with the new urgency
		$new_can_urgency = shd_can_alter_urgency($new_urgency, $starter_id, $is_closed, $is_deleted, $dept);

		$this->_ajax_response(array(
			'success' => true,
			'urgency' => $new_urgency,
			'label' => isset($txt['shd_urgency_' . $new_urgency]) ? $txt['shd_urgency_' . $new_urgency] : '',
			'can_increase' => !empty($new_can_urgency['increase']),
			'can_decrease' => !empty($new_can_urgency['decrease']),
		));
	}

	/**
	 * Get available urgency options for a ticket.
	 *
	 * Returns the array of urgency levels the current user is permitted to set,
	 * along with the current urgency of the ticket.
	 */
	public function action_urgencylist()
	{
		global $context, $user_info, $txt;

		$db = database();

		$ticket_id = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;

		if (empty($ticket_id))
			$this->_ajax_error('shd_no_ticket');

		// Load the ticket
		$request = shd_db_query('', '
			SELECT hdt.id_ticket, hdt.id_member_started, hdt.urgency,
				hdt.status, hdt.id_dept
			FROM {db_prefix}helpdesk_tickets AS hdt
			WHERE hdt.id_ticket = {int:ticket}
				AND {query_see_ticket}',
			array(
				'ticket' => $ticket_id,
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			$this->_ajax_error('shd_no_ticket');
		}

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		$dept = (int) $row['id_dept'];
		$status = (int) $row['status'];
		$starter_id = (int) $row['id_member_started'];
		$urgency = (int) $row['urgency'];

		$is_closed = ($status == TICKET_STATUS_CLOSED);
		$is_deleted = ($status == TICKET_STATUS_DELETED);
		$is_own = ($starter_id == $user_info['id']);

		// Build the list of available urgency levels
		$options = array();

		// Everyone can see Low and Medium
		$options[] = array(
			'id' => TICKET_URGENCY_LOW,
			'label' => isset($txt['shd_urgency_0']) ? $txt['shd_urgency_0'] : 'Low',
		);
		$options[] = array(
			'id' => TICKET_URGENCY_MEDIUM,
			'label' => isset($txt['shd_urgency_1']) ? $txt['shd_urgency_1'] : 'Medium',
		);

		// High is available if user can alter urgency
		if (shd_allowed_to('shd_alter_urgency_any', $dept) || ($is_own && shd_allowed_to('shd_alter_urgency_own', $dept)))
		{
			$options[] = array(
				'id' => TICKET_URGENCY_HIGH,
				'label' => isset($txt['shd_urgency_2']) ? $txt['shd_urgency_2'] : 'High',
			);

			// Very High and above require higher urgency permissions
			if (shd_allowed_to('shd_alter_urgency_higher_any', $dept) || ($is_own && shd_allowed_to('shd_alter_urgency_higher_own', $dept)))
			{
				$options[] = array(
					'id' => TICKET_URGENCY_VHIGH,
					'label' => isset($txt['shd_urgency_3']) ? $txt['shd_urgency_3'] : 'Very High',
				);
				$options[] = array(
					'id' => TICKET_URGENCY_SEVERE,
					'label' => isset($txt['shd_urgency_4']) ? $txt['shd_urgency_4'] : 'Severe',
				);
				$options[] = array(
					'id' => TICKET_URGENCY_CRITICAL,
					'label' => isset($txt['shd_urgency_5']) ? $txt['shd_urgency_5'] : 'Critical',
				);
			}
		}

		// Also provide can_increase/can_decrease for convenience
		$can_urgency = shd_can_alter_urgency($urgency, $starter_id, $is_closed, $is_deleted, $dept);

		$this->_ajax_response(array(
			'success' => true,
			'current' => $urgency,
			'current_label' => isset($txt['shd_urgency_' . $urgency]) ? $txt['shd_urgency_' . $urgency] : '',
			'options' => $options,
			'can_increase' => !empty($can_urgency['increase']),
			'can_decrease' => !empty($can_urgency['decrease']),
		));
	}

	/**
	 * Get quote text for replying to a ticket message.
	 *
	 * Loads the raw message body from the helpdesk_ticket_replies table,
	 * un-preparses it for BBC editing, wraps it in [quote] BBC tags with
	 * author information, and returns the quote text.
	 */
	public function action_quote()
	{
		global $context, $user_info;

		$db = database();

		$msg_id = isset($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : 0;

		if (empty($msg_id))
			$this->_ajax_error('shd_no_ticket');

		// Load the message and verify ticket visibility
		$request = shd_db_query('', '
			SELECT hdtr.id_msg, hdtr.id_ticket, hdtr.body, hdtr.poster_time,
				hdtr.id_member, hdtr.poster_name, hdtr.message_status,
				COALESCE(mem.real_name, hdtr.poster_name) AS real_name,
				hdt.id_dept, hdt.subject
			FROM {db_prefix}helpdesk_ticket_replies AS hdtr
				INNER JOIN {db_prefix}helpdesk_tickets AS hdt ON (hdt.id_ticket = hdtr.id_ticket)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = hdtr.id_member)
			WHERE hdtr.id_msg = {int:msg}
				AND {query_see_ticket}',
			array(
				'msg' => $msg_id,
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			$this->_ajax_error('shd_no_ticket');
		}

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		// Only quote normal messages (not deleted)
		if ((int) $row['message_status'] != MSG_STATUS_NORMAL)
		{
			// If user can see deleted replies, allow quoting; otherwise error
			$dept = (int) $row['id_dept'];
			if (!shd_allowed_to('shd_access_recyclebin', $dept))
				$this->_ajax_error('shd_no_reply');
		}

		// Un-preparsecode the body for editing
		require_once(SUBSDIR . '/Post.subs.php');
		$body = un_preparsecode($row['body']);

		// Build the quote block
		$poster_name = $row['real_name'];
		$poster_date = standardTime($row['poster_time']);
		$poster_link = !empty($row['id_member']) ? 'link=' . $row['id_member'] : '';

		// Build the [quote] tag
		$quote = '[quote author=' . $poster_name;
		if (!empty($poster_link))
			$quote .= ' ' . $poster_link;
		$quote .= ' date=' . $row['poster_time'] . ']' . "\n";
		$quote .= $body . "\n";
		$quote .= '[/quote]' . "\n";

		$this->_ajax_response(array(
			'success' => true,
			'quote' => $quote,
		));
	}

	/**
	 * Get canned reply body.
	 *
	 * Loads the canned reply content from the helpdesk_cannedreplies table,
	 * verifies the reply is active and visible to the current user based on
	 * their staff/user status and the reply's department assignments, and
	 * returns the body content un-preparsed for the editor.
	 */
	public function action_canned()
	{
		global $context, $user_info;

		$db = database();

		$reply_id = isset($_REQUEST['reply']) ? (int) $_REQUEST['reply'] : 0;

		if (empty($reply_id))
			$this->_ajax_error('shd_no_ticket');

		// Optionally accept a department for filtering
		$dept = isset($_REQUEST['dept']) ? (int) $_REQUEST['dept'] : 0;

		// Load the canned reply
		$request = $db->query('', '
			SELECT cr.id_reply, cr.id_cat, cr.title, cr.body,
				cr.vis_user, cr.vis_staff, cr.active
			FROM {db_prefix}helpdesk_cannedreplies AS cr
			WHERE cr.id_reply = {int:reply}',
			array(
				'reply' => $reply_id,
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			$this->_ajax_error('shd_no_ticket');
		}

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		// Must be active
		if (empty($row['active']))
			$this->_ajax_error('shd_no_ticket');

		// Visibility check: staff or user?
		$is_staff = shd_allowed_to('shd_staff', $dept);

		if ($is_staff && empty($row['vis_staff']))
			$this->_ajax_error('shd_no_ticket');
		elseif (!$is_staff && empty($row['vis_user']))
			$this->_ajax_error('shd_no_ticket');

		// If a department is specified, check the reply is assigned to it
		if (!empty($dept))
		{
			$request = $db->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}helpdesk_cannedreplies_depts
				WHERE id_reply = {int:reply}',
				array(
					'reply' => $reply_id,
				)
			);
			list($dept_count) = $db->fetch_row($request);
			$db->free_result($request);

			// If the reply has department restrictions, check if our dept is included
			if ($dept_count > 0)
			{
				$request = $db->query('', '
					SELECT COUNT(*)
					FROM {db_prefix}helpdesk_cannedreplies_depts
					WHERE id_reply = {int:reply}
						AND id_dept = {int:dept}',
					array(
						'reply' => $reply_id,
						'dept' => $dept,
					)
				);
				list($has_dept) = $db->fetch_row($request);
				$db->free_result($request);

				if (empty($has_dept))
					$this->_ajax_error('shd_no_ticket');
			}
			// If no department restrictions, the reply is available to all departments
		}

		// Un-preparsecode the body for the editor
		require_once(SUBSDIR . '/Post.subs.php');
		$body = un_preparsecode($row['body']);

		$this->_ajax_response(array(
			'success' => true,
			'title' => $row['title'],
			'body' => $body,
		));
	}

	/**
	 * Get assignment options for a ticket (list of assignable staff).
	 *
	 * Loads the list of staff members who can be assigned to the ticket,
	 * considering the ticket's privacy state, department, and mod settings.
	 * Returns an array of member IDs and names.
	 */
	public function action_assign()
	{
		global $context, $user_info, $modSettings, $txt;

		$db = database();

		$ticket_id = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;

		if (empty($ticket_id))
			$this->_ajax_error('shd_no_ticket');

		// Load the ticket
		$request = shd_db_query('', '
			SELECT hdt.id_ticket, hdt.id_member_started, hdt.id_member_assigned,
				hdt.private, hdt.subject, hdt.id_dept, hdt.status
			FROM {db_prefix}helpdesk_tickets AS hdt
			WHERE hdt.id_ticket = {int:ticket}
				AND {query_see_ticket}',
			array(
				'ticket' => $ticket_id,
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			$this->_ajax_error('shd_no_ticket');
		}

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		$dept = (int) $row['id_dept'];
		$status = (int) $row['status'];
		$ticket_owner = (int) $row['id_member_started'];
		$current_assignee = (int) $row['id_member_assigned'];
		$is_private = !empty($row['private']);

		// Cannot assign closed or deleted tickets
		if ($status == TICKET_STATUS_CLOSED || $status == TICKET_STATUS_DELETED)
			$this->_ajax_error('shd_cannot_assign');

		// Permission check
		if (!shd_allowed_to('shd_assign_ticket_any', $dept) && !shd_allowed_to('shd_assign_ticket_own', $dept))
			$this->_ajax_error('shd_cannot_assign');

		// Get possible assignees using the same logic as the Assign controller
		$possible = $this->_get_possible_assignees($is_private, $ticket_owner, $dept);

		$member_list = array(
			array(
				'id' => 0,
				'name' => isset($txt['shd_unassigned']) ? $txt['shd_unassigned'] : 'Unassigned',
			),
		);

		if (!empty($possible))
		{
			$request = $db->query('', '
				SELECT id_member, real_name
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:members})
				ORDER BY real_name',
				array(
					'members' => $possible,
				)
			);

			while ($member = $db->fetch_assoc($request))
			{
				$member_list[] = array(
					'id' => (int) $member['id_member'],
					'name' => $member['real_name'],
				);
			}
			$db->free_result($request);
		}

		$this->_ajax_response(array(
			'success' => true,
			'current_assignee' => $current_assignee,
			'members' => $member_list,
		));
	}

	/**
	 * Perform ticket assignment via AJAX.
	 *
	 * Assigns the ticket to the specified member, updates the database,
	 * logs the action, triggers notifications, and returns success/failure.
	 */
	public function action_assign2()
	{
		global $context, $user_info, $modSettings, $txt;

		$db = database();

		checkSession('get');

		$ticket_id = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;
		$assignee = isset($_REQUEST['to_user']) ? (int) $_REQUEST['to_user'] : -1;

		if (empty($ticket_id))
			$this->_ajax_error('shd_no_ticket');

		if ($assignee < 0)
			$this->_ajax_error('shd_cannot_assign');

		// Load the ticket
		$request = shd_db_query('', '
			SELECT hdt.id_ticket, hdt.id_member_started, hdt.id_member_assigned,
				hdt.private, hdt.subject, hdt.id_dept, hdt.status
			FROM {db_prefix}helpdesk_tickets AS hdt
			WHERE hdt.id_ticket = {int:ticket}
				AND {query_see_ticket}',
			array(
				'ticket' => $ticket_id,
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			$this->_ajax_error('shd_no_ticket');
		}

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		$dept = (int) $row['id_dept'];
		$status = (int) $row['status'];
		$ticket_owner = (int) $row['id_member_started'];
		$current_assignee = (int) $row['id_member_assigned'];
		$is_private = !empty($row['private']);
		$subject = $row['subject'];

		// Cannot assign closed or deleted tickets
		if ($status == TICKET_STATUS_CLOSED || $status == TICKET_STATUS_DELETED)
			$this->_ajax_error('shd_cannot_assign');

		// Full assignment permission check
		$can_assign_any = shd_allowed_to('shd_assign_ticket_any', $dept);
		$can_assign_own = shd_allowed_to('shd_assign_ticket_own', $dept) && shd_allowed_to('shd_staff', $dept);

		if (!$can_assign_any && !$can_assign_own)
			$this->_ajax_error('shd_cannot_assign');

		// If only has own-assign, validate the assignment
		if (!$can_assign_any && $can_assign_own)
		{
			// Can only assign to self or unassign self
			if ($assignee != 0 && $assignee != $user_info['id'])
				$this->_ajax_error('shd_cannot_assign');

			if ($assignee == 0 && $current_assignee != $user_info['id'])
				$this->_ajax_error('shd_cannot_assign');
		}

		$assigned_name = '';

		if ($assignee == 0)
		{
			// Unassigning
			shd_log_action('unassign', array(
				'ticket' => $ticket_id,
				'subject' => $subject,
			));

			$assigned_name = isset($txt['shd_unassigned']) ? $txt['shd_unassigned'] : 'Unassigned';
		}
		else
		{
			// Validate that the assignee is in the possible list (for assign_any)
			if ($can_assign_any)
			{
				$possible = $this->_get_possible_assignees($is_private, $ticket_owner, $dept);

				if (!in_array($assignee, $possible))
					$this->_ajax_error('shd_assigned_not_permitted');
			}

			// Load the assignee's name
			$request = $db->query('', '
				SELECT real_name
				FROM {db_prefix}members
				WHERE id_member = {int:member}',
				array(
					'member' => $assignee,
				)
			);

			if ($db->num_rows($request) == 0)
			{
				$db->free_result($request);
				$this->_ajax_error('shd_assigned_not_permitted');
			}

			$member_row = $db->fetch_assoc($request);
			$db->free_result($request);

			$assigned_name = $member_row['real_name'];

			shd_log_action('assign', array(
				'ticket' => $ticket_id,
				'subject' => $subject,
				'assigned_name' => $assigned_name,
				'assigned_id' => $assignee,
			));
		}

		// Perform the assignment
		$db->query('', '
			UPDATE {db_prefix}helpdesk_tickets
			SET id_member_assigned = {int:assigned},
				last_updated = {int:time}
			WHERE id_ticket = {int:ticket}',
			array(
				'assigned' => $assignee,
				'time' => time(),
				'ticket' => $ticket_id,
			)
		);

		// Clear the active tickets cache
		shd_clear_active_tickets($dept);

		// Send assignment notifications
		require_once(CONTROLLERDIR . '/SimpleDeskNotify.controller.php');

		$ticket_data = array(
			'id' => $ticket_id,
			'dept' => $dept,
			'subject' => $subject,
			'id_member_started' => $ticket_owner,
			'private' => $is_private,
		);
		$assignment_data = array(
			'id_member_assigned' => $assignee,
			'assigned_name' => $assigned_name,
		);

		shd_notifications_notify_assign($ticket_data, $assignment_data);

		$this->_ajax_response(array(
			'success' => true,
			'assigned_id' => $assignee,
			'assigned_name' => $assigned_name,
		));
	}

	/**
	 * Get notification recipients for a ticket.
	 *
	 * Returns the list of users who will be notified about changes to
	 * this ticket, broken down by notification type (monitor, starter,
	 * assigned, etc.), along with users who are ignoring the ticket.
	 */
	public function action_notify()
	{
		global $context, $user_info, $txt;

		$db = database();

		$ticket_id = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;

		if (empty($ticket_id))
			$this->_ajax_error('shd_no_ticket');

		// Load the ticket
		$request = shd_db_query('', '
			SELECT hdt.id_ticket, hdt.id_member_started, hdt.id_member_assigned,
				hdt.private, hdt.subject, hdt.id_dept
			FROM {db_prefix}helpdesk_tickets AS hdt
			WHERE hdt.id_ticket = {int:ticket}
				AND {query_see_ticket}',
			array(
				'ticket' => $ticket_id,
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			$this->_ajax_error('shd_no_ticket');
		}

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		$dept = (int) $row['id_dept'];
		$starter_id = (int) $row['id_member_started'];
		$assigned_id = (int) $row['id_member_assigned'];
		$is_private = !empty($row['private']);

		// Get the visible member list (who can see this ticket)
		require_once(CONTROLLERDIR . '/SimpleDeskNotify.controller.php');
		$visible_members = shd_get_visible_list($dept, $is_private, $starter_id, true, true);

		// Get the monitor/ignore lists
		$monitor_list = shd_query_monitor_list($ticket_id);

		// Build the notification recipients list
		$notify_members = array();

		// Ticket starter
		if (!empty($starter_id) && in_array($starter_id, $visible_members))
		{
			$notify_members[$starter_id] = array(
				'id' => $starter_id,
				'type' => 'starter',
			);
		}

		// Assigned staff
		if (!empty($assigned_id) && in_array($assigned_id, $visible_members))
		{
			if (isset($notify_members[$assigned_id]))
				$notify_members[$assigned_id]['type'] .= ',assigned';
			else
				$notify_members[$assigned_id] = array(
					'id' => $assigned_id,
					'type' => 'assigned',
				);
		}

		// Monitoring members
		if (!empty($monitor_list['monitor']))
		{
			foreach ($monitor_list['monitor'] as $member_id)
			{
				if (in_array($member_id, $visible_members))
				{
					if (isset($notify_members[$member_id]))
						$notify_members[$member_id]['type'] .= ',monitor';
					else
						$notify_members[$member_id] = array(
							'id' => $member_id,
							'type' => 'monitor',
						);
				}
			}
		}

		// Previous repliers
		$request = $db->query('', '
			SELECT DISTINCT hdtr.id_member
			FROM {db_prefix}helpdesk_ticket_replies AS hdtr
			WHERE hdtr.id_ticket = {int:ticket}
				AND hdtr.id_member != {int:zero}
				AND hdtr.message_status = {int:normal}',
			array(
				'ticket' => $ticket_id,
				'zero' => 0,
				'normal' => MSG_STATUS_NORMAL,
			)
		);

		while ($reply_row = $db->fetch_assoc($request))
		{
			$member_id = (int) $reply_row['id_member'];
			if (in_array($member_id, $visible_members))
			{
				if (isset($notify_members[$member_id]))
					$notify_members[$member_id]['type'] .= ',replier';
				else
					$notify_members[$member_id] = array(
						'id' => $member_id,
						'type' => 'replier',
					);
			}
		}
		$db->free_result($request);

		// Load member names
		$member_ids = array_keys($notify_members);
		$ignore_ids = !empty($monitor_list['ignore']) ? $monitor_list['ignore'] : array();

		$all_ids = array_unique(array_merge($member_ids, $ignore_ids));

		$member_names = array();
		if (!empty($all_ids))
		{
			$request = $db->query('', '
				SELECT id_member, real_name
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:members})',
				array(
					'members' => $all_ids,
				)
			);

			while ($name_row = $db->fetch_assoc($request))
				$member_names[(int) $name_row['id_member']] = $name_row['real_name'];

			$db->free_result($request);
		}

		// Build final response arrays
		$recipients = array();
		foreach ($notify_members as $member_id => $info)
		{
			// Remove those who are ignoring the ticket
			if (in_array($member_id, $ignore_ids))
				continue;

			$recipients[] = array(
				'id' => $member_id,
				'name' => isset($member_names[$member_id]) ? $member_names[$member_id] : '',
				'type' => $info['type'],
			);
		}

		$ignoring = array();
		foreach ($ignore_ids as $member_id)
		{
			$ignoring[] = array(
				'id' => $member_id,
				'name' => isset($member_names[$member_id]) ? $member_names[$member_id] : '',
			);
		}

		$this->_ajax_response(array(
			'success' => true,
			'recipients' => $recipients,
			'ignoring' => $ignoring,
		));
	}

	/**
	 * Returns an array of member IDs who can be assigned to a ticket.
	 *
	 * Calculates the intersection of staff members, those who can view the
	 * ticket (considering privacy), and applies admin exclusion and
	 * self-ticket restrictions from mod settings.
	 *
	 * @param bool $is_private Whether the ticket is private.
	 * @param int $ticket_owner The member ID of the ticket starter.
	 * @param int $dept The department ID.
	 * @return array Array of member IDs who can be assigned.
	 */
	private function _get_possible_assignees($is_private, $ticket_owner, $dept)
	{
		global $modSettings;

		$db = database();

		// Start with all staff in the department
		$staff = shd_members_allowed_to('shd_staff', $dept);

		if (empty($staff))
			return array();

		// If the ticket is private, restrict to those who can view private tickets
		if ($is_private)
		{
			$private_viewers = shd_members_allowed_to('shd_view_ticket_private_any', $dept);
			$staff = array_intersect($staff, $private_viewers);
		}

		// Staff must also be able to view tickets in this department
		$ticket_viewers = shd_members_allowed_to('shd_view_ticket_any', $dept);
		$staff = array_intersect($staff, $ticket_viewers);

		// If admins should not be assignable, remove them
		if (!empty($modSettings['shd_admins_not_assignable']))
		{
			$request = $db->query('', '
				SELECT id_member
				FROM {db_prefix}members
				WHERE id_group = {int:admin_group}
					OR FIND_IN_SET({int:admin_group}, additional_groups)',
				array(
					'admin_group' => 1,
				)
			);

			$admins = array();
			while ($row = $db->fetch_assoc($request))
				$admins[] = (int) $row['id_member'];
			$db->free_result($request);

			if (!empty($admins))
				$staff = array_diff($staff, $admins);
		}

		// If staff cannot be assigned to their own tickets
		if (!empty($modSettings['shd_staff_ticket_self']) && $modSettings['shd_staff_ticket_self'] == 1)
		{
			// Setting means "staff can self-assign" - no removal needed
		}
		elseif (empty($modSettings['shd_staff_ticket_self']))
		{
			// Setting is off: remove the ticket owner from the list
			$staff = array_diff($staff, array($ticket_owner));
		}

		return array_values(array_unique($staff));
	}

	/**
	 * Sends a JSON success response and terminates.
	 *
	 * Clears any output buffering, sets the JSON content-type header,
	 * encodes the data, and exits.
	 *
	 * @param array $data The data to encode as JSON.
	 */
	private function _ajax_response($data)
	{
		// Clean any output buffering
		if (ob_get_level() > 0)
			ob_end_clean();

		header('Content-Type: application/json; charset=UTF-8');
		die(json_encode($data));
	}

	/**
	 * Sends a JSON error response and terminates.
	 *
	 * Looks up the error key in the language strings and returns it as a
	 * JSON error response.
	 *
	 * @param string $error_key The language string key for the error message.
	 */
	private function _ajax_error($error_key)
	{
		global $txt;

		$message = isset($txt[$error_key]) ? $txt[$error_key] : $error_key;

		$this->_ajax_response(array(
			'success' => false,
			'error' => $message,
		));
	}
}
