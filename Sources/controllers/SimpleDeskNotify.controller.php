<?php
/**
 * SimpleDesk Notify Controller
 *
 * Handles ticket notification preferences (monitor/ignore) and sends
 * email notifications for new tickets, replies, and assignments.
 *
 * The controller class manages per-ticket notification preferences
 * (monitor_on, monitor_off, ignore_on, ignore_off). The standalone
 * functions handle the actual notification sending and are called
 * from other controllers during ticket operations.
 *
 * Ported from SMF SimpleDesk-Notifications.php to ElkArte.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Controller for notification preferences.
 */
class SimpleDeskNotify_Controller extends Action_Controller
{
	/**
	 * Default entry point - redirects to main helpdesk.
	 */
	public function action_index()
	{
		redirectexit('action=helpdesk');
	}

	/**
	 * Manage notification settings for a ticket (monitor/ignore toggle).
	 *
	 * Handles four sub-actions via $_REQUEST['sa2']:
	 * - monitor_on: Start monitoring (NOTIFY_ALWAYS)
	 * - monitor_off: Stop monitoring (remove override)
	 * - ignore_on: Start ignoring (NOTIFY_NEVER)
	 * - ignore_off: Stop ignoring (remove override)
	 *
	 * Requires the appropriate shd_monitor_ticket or shd_ignore_ticket permission.
	 * Logs the action and redirects back to the ticket.
	 */
	public function action_notify()
	{
		global $context, $scripturl, $user_info;

		$db = database();

		checkSession('get');

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		loadLanguage('SimpleDesk');

		$ticket_id = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;

		if (empty($ticket_id))
			fatal_lang_error('shd_no_ticket', false);

		// Load the ticket to validate access and get department
		$request = shd_db_query('', '
			SELECT hdt.id_ticket, hdt.id_dept, hdt.id_member_started, hdt.subject, hdt.private
			FROM {db_prefix}helpdesk_tickets AS hdt
			WHERE hdt.id_ticket = {int:ticket}
				AND {query_see_ticket}',
			array(
				'ticket' => $ticket_id,
			)
		);

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		if (empty($row))
			fatal_lang_error('shd_no_ticket', false);

		$dept = (int) $row['id_dept'];
		$starter = (int) $row['id_member_started'];
		$is_own = ($starter == $user_info['id']);

		$context['shd_department'] = $dept;

		// Determine the sub-action
		$sa2 = isset($_REQUEST['sa2']) ? $_REQUEST['sa2'] : '';

		// Get the user's current notification state for this ticket
		$request = $db->query('', '
			SELECT notify_state
			FROM {db_prefix}helpdesk_notify_override
			WHERE id_member = {int:member}
				AND id_ticket = {int:ticket}',
			array(
				'member' => $user_info['id'],
				'ticket' => $ticket_id,
			)
		);

		$current_state = NOTIFY_PREFS;
		if ($db->num_rows($request) > 0)
		{
			$state_row = $db->fetch_assoc($request);
			$current_state = (int) $state_row['notify_state'];
		}
		$db->free_result($request);

		switch ($sa2)
		{
			case 'monitor_on':
				// Permission check: need shd_monitor_ticket_own or _any
				if (!shd_allowed_to('shd_monitor_ticket_any', $dept) && !($is_own && shd_allowed_to('shd_monitor_ticket_own', $dept)))
					fatal_lang_error('cannot_shd_monitor_ticket', false);

				// Set the user's preference to NOTIFY_ALWAYS
				$db->insert('replace',
					'{db_prefix}helpdesk_notify_override',
					array(
						'id_member' => 'int',
						'id_ticket' => 'int',
						'notify_state' => 'int',
					),
					array(
						$user_info['id'],
						$ticket_id,
						NOTIFY_ALWAYS,
					),
					array('id_member', 'id_ticket')
				);

				shd_log_action('monitor', array(
					'ticket' => $ticket_id,
					'subject' => $row['subject'],
				));
				break;

			case 'monitor_off':
				// Permission check
				if (!shd_allowed_to('shd_monitor_ticket_any', $dept) && !($is_own && shd_allowed_to('shd_monitor_ticket_own', $dept)))
					fatal_lang_error('cannot_shd_monitor_ticket', false);

				// Remove the override (revert to default preferences)
				$db->query('', '
					DELETE FROM {db_prefix}helpdesk_notify_override
					WHERE id_member = {int:member}
						AND id_ticket = {int:ticket}',
					array(
						'member' => $user_info['id'],
						'ticket' => $ticket_id,
					)
				);

				shd_log_action('unmonitor', array(
					'ticket' => $ticket_id,
					'subject' => $row['subject'],
				));
				break;

			case 'ignore_on':
				// Permission check: need shd_ignore_ticket_own or _any
				if (!shd_allowed_to('shd_ignore_ticket_any', $dept) && !($is_own && shd_allowed_to('shd_ignore_ticket_own', $dept)))
					fatal_lang_error('cannot_shd_ignore_ticket', false);

				// Set the user's preference to NOTIFY_NEVER
				$db->insert('replace',
					'{db_prefix}helpdesk_notify_override',
					array(
						'id_member' => 'int',
						'id_ticket' => 'int',
						'notify_state' => 'int',
					),
					array(
						$user_info['id'],
						$ticket_id,
						NOTIFY_NEVER,
					),
					array('id_member', 'id_ticket')
				);

				shd_log_action('ignore', array(
					'ticket' => $ticket_id,
					'subject' => $row['subject'],
				));
				break;

			case 'ignore_off':
				// Permission check
				if (!shd_allowed_to('shd_ignore_ticket_any', $dept) && !($is_own && shd_allowed_to('shd_ignore_ticket_own', $dept)))
					fatal_lang_error('cannot_shd_ignore_ticket', false);

				// Remove the override (revert to default preferences)
				$db->query('', '
					DELETE FROM {db_prefix}helpdesk_notify_override
					WHERE id_member = {int:member}
						AND id_ticket = {int:ticket}',
					array(
						'member' => $user_info['id'],
						'ticket' => $ticket_id,
					)
				);

				shd_log_action('unignore', array(
					'ticket' => $ticket_id,
					'subject' => $row['subject'],
				));
				break;

			default:
				fatal_lang_error('shd_no_ticket', false);
		}

		// Redirect back to the ticket
		redirectexit('action=helpdesk;sa=viewticket;ticket=' . $ticket_id);
	}
}

// =========================================================================
// Standalone notification functions
// Called from other controllers during ticket operations.
// =========================================================================

/**
 * Sends notifications when a new ticket is created.
 *
 * Called from SimpleDeskPost_Controller::action_saveticket() after a ticket
 * is successfully created. Determines who should be notified based on
 * their notification preferences and permissions, then sends emails.
 *
 * @param array &$msgOptions Message options (id, body, smileys_enabled).
 * @param array &$ticketOptions Ticket options (id, dept, subject, urgency, private, status).
 * @param array &$posterOptions Poster info (id, name, email, ip).
 */
function shd_notifications_notify_newticket(&$msgOptions, &$ticketOptions, &$posterOptions)
{
	global $modSettings, $scripturl, $user_info, $mbname;

	$db = database();

	// Is the notification system enabled for new tickets?
	if (empty($modSettings['shd_notify_new_ticket']))
		return;

	loadLanguage('SimpleDeskNotifications');

	$ticket_id = $ticketOptions['id'];
	$dept = !empty($ticketOptions['dept']) ? $ticketOptions['dept'] : 0;
	$is_private = !empty($ticketOptions['private']);
	$subject = $ticketOptions['subject'];
	$poster_id = $posterOptions['id'];

	// Get the list of people who can see this ticket
	$visible_members = shd_get_visible_list($dept, $is_private, $poster_id, true, false);

	if (empty($visible_members))
		return;

	// Get staff members (they are the ones who get new ticket notifications)
	$staff = shd_members_allowed_to('shd_staff', $dept);

	// Intersect: only staff who can see the ticket
	$possible = array_intersect($visible_members, $staff);

	// Remove the poster from the notification list
	$possible = array_diff($possible, array($poster_id));

	if (empty($possible))
		return;

	// Check each user's notification preferences
	$notify_list = array();
	foreach ($possible as $member)
	{
		$prefs = shd_load_user_notify_prefs($member);

		// If user has explicit preference for new tickets
		if (isset($prefs['notify_new_ticket']))
		{
			if ($prefs['notify_new_ticket'] == NOTIFY_ALWAYS)
				$notify_list[] = $member;
			elseif ($prefs['notify_new_ticket'] == NOTIFY_NEVER)
				continue;
			else
				$notify_list[] = $member; // Default: notify
		}
		else
		{
			$notify_list[] = $member; // Default: notify staff of new tickets
		}
	}

	if (empty($notify_list))
		return;

	// Build notification data
	$notify_data = array(
		'ticket_id' => $ticket_id,
		'subject' => $subject,
		'ticket_url' => $scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $ticket_id,
		'poster_name' => $posterOptions['name'],
		'poster_id' => $poster_id,
		'body' => !empty($modSettings['shd_notify_with_body']) ? $msgOptions['body'] : '',
		'type' => 'new_ticket',
		'members' => $notify_list,
	);

	shd_notify_users($notify_data);
}

/**
 * Sends notifications when a new reply is posted to a ticket.
 *
 * Called from SimpleDeskPost_Controller::action_savereply() after a reply
 * is successfully created. Notifies the ticket starter, the assigned staff
 * member, previous repliers, and anyone monitoring the ticket.
 *
 * @param array &$msgOptions Message options (id, body, smileys_enabled).
 * @param array &$ticketOptions Ticket options (id, dept, subject, status).
 * @param array &$posterOptions Poster info (id, name, email, ip).
 */
function shd_notifications_notify_newreply(&$msgOptions, &$ticketOptions, &$posterOptions)
{
	global $modSettings, $scripturl, $user_info, $mbname;

	$db = database();

	loadLanguage('SimpleDeskNotifications');

	$ticket_id = $ticketOptions['id'];
	$dept = !empty($ticketOptions['dept']) ? $ticketOptions['dept'] : 0;
	$poster_id = $posterOptions['id'];

	// Load ticket details for notification context
	$request = shd_db_query('', '
		SELECT hdt.id_member_started, hdt.id_member_assigned, hdt.subject,
			hdt.private, hdt.id_dept
		FROM {db_prefix}helpdesk_tickets AS hdt
		WHERE hdt.id_ticket = {int:ticket}',
		array(
			'ticket' => $ticket_id,
		)
	);

	if ($db->num_rows($request) == 0)
	{
		$db->free_result($request);
		return;
	}

	$ticket = $db->fetch_assoc($request);
	$db->free_result($request);

	$starter_id = (int) $ticket['id_member_started'];
	$assigned_id = (int) $ticket['id_member_assigned'];
	$is_private = !empty($ticket['private']);
	$subject = $ticket['subject'];

	// Get the list of people who can see this ticket
	$visible_members = shd_get_visible_list($dept, $is_private, $starter_id, true, false);

	if (empty($visible_members))
		return;

	$notify_list = array();

	// 1. Notify the ticket starter (if not the poster and setting enabled)
	if (!empty($modSettings['shd_notify_new_reply_own']) && $starter_id != $poster_id && in_array($starter_id, $visible_members))
	{
		$notify_list[] = $starter_id;
	}

	// 2. Notify the assigned staff member (if not the poster and setting enabled)
	if (!empty($modSettings['shd_notify_new_reply_assigned']) && !empty($assigned_id) && $assigned_id != $poster_id && in_array($assigned_id, $visible_members))
	{
		$notify_list[] = $assigned_id;
	}

	// 3. Notify previous repliers (if setting enabled)
	if (!empty($modSettings['shd_notify_new_reply_previous']))
	{
		$request = $db->query('', '
			SELECT DISTINCT hdtr.id_member
			FROM {db_prefix}helpdesk_ticket_replies AS hdtr
			WHERE hdtr.id_ticket = {int:ticket}
				AND hdtr.id_member != {int:poster}
				AND hdtr.id_member != {int:zero}
				AND hdtr.message_status = {int:normal}',
			array(
				'ticket' => $ticket_id,
				'poster' => $poster_id,
				'zero' => 0,
				'normal' => MSG_STATUS_NORMAL,
			)
		);

		while ($row = $db->fetch_assoc($request))
		{
			if (in_array((int) $row['id_member'], $visible_members))
				$notify_list[] = (int) $row['id_member'];
		}
		$db->free_result($request);
	}

	// 4. Notify anyone monitoring this ticket (but not ignoring)
	$monitor_list = shd_query_monitor_list($ticket_id);

	if (!empty($monitor_list['monitor']))
	{
		foreach ($monitor_list['monitor'] as $member_id)
		{
			if ($member_id != $poster_id && in_array($member_id, $visible_members))
				$notify_list[] = $member_id;
		}
	}

	// 5. Staff who want all reply notifications
	if (!empty($modSettings['shd_notify_new_reply_any']))
	{
		$staff = shd_members_allowed_to('shd_staff', $dept);
		$staff = array_intersect($staff, $visible_members);
		$staff = array_diff($staff, array($poster_id));

		foreach ($staff as $staff_id)
			$notify_list[] = $staff_id;
	}

	// Remove duplicates and the poster
	$notify_list = array_unique($notify_list);
	$notify_list = array_diff($notify_list, array($poster_id));

	// Remove anyone who is ignoring this ticket
	if (!empty($monitor_list['ignore']))
		$notify_list = array_diff($notify_list, $monitor_list['ignore']);

	// Check individual preferences - remove those who never want reply notifications
	$final_list = array();
	foreach ($notify_list as $member)
	{
		$prefs = shd_load_user_notify_prefs($member);

		if (isset($prefs['notify_new_reply']) && $prefs['notify_new_reply'] == NOTIFY_NEVER)
			continue;

		$final_list[] = $member;
	}

	if (empty($final_list))
		return;

	// Build notification data
	$notify_data = array(
		'ticket_id' => $ticket_id,
		'subject' => $subject,
		'ticket_url' => $scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $ticket_id . '.msg' . $msgOptions['id'] . '#msg' . $msgOptions['id'],
		'poster_name' => $posterOptions['name'],
		'poster_id' => $poster_id,
		'body' => !empty($modSettings['shd_notify_with_body']) ? $msgOptions['body'] : '',
		'type' => 'new_reply',
		'members' => $final_list,
	);

	shd_notify_users($notify_data);
}

/**
 * Sends notifications when a ticket is assigned or unassigned.
 *
 * Called from SimpleDeskAssign_Controller after a ticket assignment change.
 * Notifies the new assignee, the ticket starter (if setting enabled),
 * and anyone monitoring the ticket.
 *
 * @param array &$ticket Ticket data (id, dept, subject, id_member_started, private).
 * @param array &$assignment Assignment data (id_member_assigned, assigned_name).
 */
function shd_notifications_notify_assign(&$ticket, &$assignment)
{
	global $modSettings, $scripturl, $user_info;

	$db = database();

	loadLanguage('SimpleDeskNotifications');

	$ticket_id = $ticket['id'];
	$dept = !empty($ticket['dept']) ? $ticket['dept'] : 0;
	$assigned_id = (int) $assignment['id_member_assigned'];
	$starter_id = (int) $ticket['id_member_started'];
	$is_private = !empty($ticket['private']);
	$subject = $ticket['subject'];

	// Get the list of people who can see this ticket
	$visible_members = shd_get_visible_list($dept, $is_private, $starter_id, true, false);

	if (empty($visible_members))
		return;

	$notify_list = array();

	// 1. Notify the new assignee (if setting enabled and not the current user)
	if (!empty($modSettings['shd_notify_assign_me']) && !empty($assigned_id) && $assigned_id != $user_info['id'] && in_array($assigned_id, $visible_members))
	{
		$notify_list[] = $assigned_id;
	}

	// 2. Notify the ticket starter (if setting enabled and not the current user)
	if (!empty($modSettings['shd_notify_assign_own']) && !empty($starter_id) && $starter_id != $user_info['id'] && in_array($starter_id, $visible_members))
	{
		$notify_list[] = $starter_id;
	}

	// 3. Notify anyone monitoring this ticket
	$monitor_list = shd_query_monitor_list($ticket_id);

	if (!empty($monitor_list['monitor']))
	{
		foreach ($monitor_list['monitor'] as $member_id)
		{
			if ($member_id != $user_info['id'] && in_array($member_id, $visible_members))
				$notify_list[] = $member_id;
		}
	}

	// Remove duplicates, the current user, and anyone ignoring
	$notify_list = array_unique($notify_list);
	$notify_list = array_diff($notify_list, array($user_info['id']));

	if (!empty($monitor_list['ignore']))
		$notify_list = array_diff($notify_list, $monitor_list['ignore']);

	// Check individual preferences
	$final_list = array();
	foreach ($notify_list as $member)
	{
		$prefs = shd_load_user_notify_prefs($member);

		if (isset($prefs['notify_assign']) && $prefs['notify_assign'] == NOTIFY_NEVER)
			continue;

		$final_list[] = $member;
	}

	if (empty($final_list))
		return;

	$assigned_name = !empty($assignment['assigned_name']) ? $assignment['assigned_name'] : '';

	// Build notification data
	$notify_data = array(
		'ticket_id' => $ticket_id,
		'subject' => $subject,
		'ticket_url' => $scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $ticket_id,
		'poster_name' => $user_info['name'],
		'poster_id' => $user_info['id'],
		'body' => '',
		'type' => 'assign',
		'members' => $final_list,
		'assigned_name' => $assigned_name,
	);

	shd_notify_users($notify_data);
}

/**
 * Sends the actual email notifications to a list of members.
 *
 * Loads member data for the recipients, builds the email subject and body
 * from language strings, and sends individual emails via ElkArte's sendmail().
 *
 * @param array $notify_data Notification data array containing:
 *   - ticket_id (int): The ticket ID.
 *   - subject (string): The ticket subject.
 *   - ticket_url (string): Full URL to the ticket.
 *   - poster_name (string): Name of the person who triggered the notification.
 *   - poster_id (int): Member ID of the person who triggered the notification.
 *   - body (string): The message body (if shd_notify_with_body is enabled).
 *   - type (string): Notification type (new_ticket, new_reply, assign).
 *   - members (array): Array of member IDs to notify.
 *   - assigned_name (string, optional): Name of the assignee (for assign type).
 */
function shd_notify_users($notify_data)
{
	global $modSettings, $txt, $mbname, $scripturl;

	$db = database();

	if (empty($notify_data['members']))
		return;

	loadLanguage('SimpleDeskNotifications');

	// Load sendmail if not already loaded
	require_once(SOURCEDIR . '/subs/Mail.subs.php');

	// Load member data for the notification recipients
	$request = $db->query('', '
		SELECT id_member, real_name, email_address, lngfile
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:members})
			AND is_activated = {int:active}',
		array(
			'members' => $notify_data['members'],
			'active' => 1,
		)
	);

	$members = array();
	while ($row = $db->fetch_assoc($request))
	{
		$members[$row['id_member']] = array(
			'id' => (int) $row['id_member'],
			'name' => $row['real_name'],
			'email' => $row['email_address'],
			'language' => $row['lngfile'],
		);
	}
	$db->free_result($request);

	if (empty($members))
		return;

	// Prepare the email body text
	$body_text = '';
	if (!empty($notify_data['body']))
	{
		// Strip BBC and HTML for email
		$body_text = un_htmlspecialchars(strip_tags(parse_bbc($notify_data['body'], false)));
		// Truncate very long messages
		if (strlen($body_text) > 2000)
			$body_text = substr($body_text, 0, 2000) . '...';
	}

	$forum_name = !empty($mbname) ? un_htmlspecialchars($mbname) : 'Forum';

	// Determine subject prefix and body template based on type
	switch ($notify_data['type'])
	{
		case 'new_ticket':
			$email_subject_key = 'shd_notify_ticket_new';
			$email_template = 'shd_notify_newticket';
			break;

		case 'new_reply':
			$email_subject_key = 'shd_notify_ticket_reply';
			$email_template = 'shd_notify_newreply';
			break;

		case 'assign':
			$email_subject_key = 'shd_notify_ticket_assign';
			$email_template = 'shd_notify_assign';
			break;

		default:
			return;
	}

	$email_subject_prefix = isset($txt[$email_subject_key]) ? $txt[$email_subject_key] : 'Helpdesk Notification';

	// Send to each member
	foreach ($members as $member)
	{
		// Build email subject
		$email_subject = $email_subject_prefix . ': ' . un_htmlspecialchars($notify_data['subject']);

		// Build email body
		$email_body = $member['name'] . ",\n\n";

		switch ($notify_data['type'])
		{
			case 'new_ticket':
				$email_body .= sprintf(
					isset($txt['shd_notify_newticket_body']) ? $txt['shd_notify_newticket_body'] : '%1$s has created a new helpdesk ticket: %2$s',
					$notify_data['poster_name'],
					un_htmlspecialchars($notify_data['subject'])
				);
				break;

			case 'new_reply':
				$email_body .= sprintf(
					isset($txt['shd_notify_newreply_body']) ? $txt['shd_notify_newreply_body'] : '%1$s has replied to the helpdesk ticket: %2$s',
					$notify_data['poster_name'],
					un_htmlspecialchars($notify_data['subject'])
				);
				break;

			case 'assign':
				$assigned_name = !empty($notify_data['assigned_name']) ? $notify_data['assigned_name'] : (isset($txt['shd_unassigned']) ? $txt['shd_unassigned'] : 'Unassigned');
				$email_body .= sprintf(
					isset($txt['shd_notify_assign_body']) ? $txt['shd_notify_assign_body'] : 'The helpdesk ticket "%1$s" has been assigned to %2$s.',
					un_htmlspecialchars($notify_data['subject']),
					$assigned_name
				);
				break;
		}

		$email_body .= "\n\n";

		// Include the message body if configured
		if (!empty($body_text))
		{
			$email_body .= (isset($txt['shd_notify_message_body']) ? $txt['shd_notify_message_body'] : 'Message:') . "\n";
			$email_body .= "---\n" . $body_text . "\n---\n\n";
		}

		// Add ticket link
		$email_body .= (isset($txt['shd_notify_view_ticket']) ? $txt['shd_notify_view_ticket'] : 'View the ticket:') . "\n";
		$email_body .= $notify_data['ticket_url'] . "\n\n";

		// Footer
		$email_body .= "---\n" . $forum_name;

		sendmail($member['email'], $email_subject, $email_body, null, null, false, 4);
	}

	// Log the notification if enabled
	if (!empty($modSettings['shd_notify_log']))
	{
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
				!empty($notify_data['poster_id']) ? $notify_data['poster_id'] : 0,
				!empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
				'notify',
				$notify_data['ticket_id'],
				0,
				json_encode(array(
					'type' => $notify_data['type'],
					'members_notified' => count($members),
				)),
			),
			array('id_action')
		);
	}
}

/**
 * Queries the monitor and ignore lists for a given ticket.
 *
 * Returns arrays of member IDs who are explicitly monitoring or ignoring
 * the ticket via the helpdesk_notify_override table.
 *
 * @param int $ticket_id The ticket ID.
 * @return array Associative array with 'monitor' and 'ignore' keys,
 *               each containing an array of member IDs.
 */
function shd_query_monitor_list($ticket_id)
{
	$db = database();

	$result = array(
		'monitor' => array(),
		'ignore' => array(),
	);

	$request = $db->query('', '
		SELECT id_member, notify_state
		FROM {db_prefix}helpdesk_notify_override
		WHERE id_ticket = {int:ticket}',
		array(
			'ticket' => $ticket_id,
		)
	);

	while ($row = $db->fetch_assoc($request))
	{
		$member_id = (int) $row['id_member'];
		$state = (int) $row['notify_state'];

		if ($state == NOTIFY_ALWAYS)
			$result['monitor'][] = $member_id;
		elseif ($state == NOTIFY_NEVER)
			$result['ignore'][] = $member_id;
	}
	$db->free_result($request);

	return $result;
}

/**
 * Gets the list of member IDs who can see a given ticket.
 *
 * Based on the ticket's department, privacy setting, and starter, determines
 * which members have the necessary permissions to view the ticket. Used to
 * filter notification recipients to only those who can actually see the ticket.
 *
 * @param int $dept The department ID.
 * @param bool $private Whether the ticket is private.
 * @param int $ticket_starter The member ID of the ticket starter.
 * @param bool $include_admin Whether to include admin members (default true).
 * @param bool $include_current_user Whether to include the current user (default true).
 * @return array Array of member IDs who can see the ticket.
 */
function shd_get_visible_list($dept, $private, $ticket_starter, $include_admin = true, $include_current_user = true)
{
	global $user_info;

	$db = database();

	// Start with members who can view any ticket in this department
	$viewers_any = shd_members_allowed_to('shd_view_ticket_any', $dept);

	// Add members who can view own tickets (if they are the starter)
	$viewers_own = shd_members_allowed_to('shd_view_ticket_own', $dept);

	// For own-ticket viewers, they can only see if they are the starter
	$visible = $viewers_any;

	// The ticket starter can always see their own ticket (if they have own permission)
	if (!empty($ticket_starter) && in_array($ticket_starter, $viewers_own))
		$visible[] = $ticket_starter;

	// If the ticket is private, further restrict the list
	if ($private)
	{
		$private_any = shd_members_allowed_to('shd_view_ticket_private_any', $dept);
		$private_own = shd_members_allowed_to('shd_view_ticket_private_own', $dept);

		// For private tickets: must have private_any, or be the starter with private_own
		$private_visible = $private_any;

		if (!empty($ticket_starter) && in_array($ticket_starter, $private_own))
			$private_visible[] = $ticket_starter;

		$visible = array_intersect($visible, $private_visible);
	}

	// Optionally exclude the current user
	if (!$include_current_user)
		$visible = array_diff($visible, array($user_info['id']));

	return array_values(array_unique($visible));
}

/**
 * Loads notification preferences for a specific user.
 *
 * Queries the helpdesk_preferences table for notification-related settings.
 * Caches results in a static variable for efficiency when called multiple
 * times for the same user.
 *
 * @param int $member The member ID.
 * @return array Associative array of notification preference name => value.
 */
function shd_load_user_notify_prefs($member)
{
	static $cache = array();

	if (isset($cache[$member]))
		return $cache[$member];

	$db = database();

	$prefs = array();

	$request = $db->query('', '
		SELECT variable, value
		FROM {db_prefix}helpdesk_preferences
		WHERE id_member = {int:member}
			AND variable LIKE {string:notify_prefix}',
		array(
			'member' => $member,
			'notify_prefix' => 'notify_%',
		)
	);

	while ($row = $db->fetch_assoc($request))
		$prefs[$row['variable']] = $row['value'];

	$db->free_result($request);

	$cache[$member] = $prefs;

	return $prefs;
}
