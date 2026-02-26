<?php
/**
 * SimpleDesk Assign Controller
 *
 * Handles assigning tickets to staff members. Provides both the
 * assignment form (for users with shd_assign_ticket_any) and
 * quick self-assign/unassign (for users with shd_assign_ticket_own).
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Controller for ticket assignment.
 */
class SimpleDeskAssign_Controller extends Action_Controller
{
	/**
	 * Default entry point - redirects to main helpdesk.
	 */
	public function action_index()
	{
		redirectexit('action=helpdesk');
	}

	/**
	 * Display assignment options or perform quick self-assign/unassign.
	 *
	 * If the user has shd_assign_ticket_any, shows the full assignment form
	 * with a dropdown of possible assignees. If the user only has
	 * shd_assign_ticket_own + shd_staff, performs immediate self-assign
	 * or self-unassign without showing a form.
	 */
	public function action_assign()
	{
		global $context, $scripturl, $txt, $modSettings, $user_info;

		$db = database();

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		loadLanguage('SimpleDesk');

		$ticket_id = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;

		if (empty($ticket_id))
			fatal_lang_error('shd_no_ticket', false);

		// Track where to return the user after assignment
		$context['shd_return_to'] = isset($_REQUEST['home']) ? 'home' : '';

		// Load the ticket
		$request = shd_db_query('', '
			SELECT hdt.id_ticket, hdt.id_member_started, hdt.id_member_assigned,
				hdt.private, hdt.subject, hdt.id_dept, hdt.status,
				hdd.dept_name
			FROM {db_prefix}helpdesk_tickets AS hdt
				LEFT JOIN {db_prefix}helpdesk_depts AS hdd ON (hdd.id_dept = hdt.id_dept)
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
		$status = (int) $row['status'];
		$ticket_owner = (int) $row['id_member_started'];
		$current_assignee = (int) $row['id_member_assigned'];
		$is_private = !empty($row['private']);

		$context['shd_department'] = $dept;
		$context['ticket_id'] = $ticket_id;

		// Cannot assign closed or deleted tickets
		if ($status == TICKET_STATUS_CLOSED || $status == TICKET_STATUS_DELETED)
			fatal_lang_error('shd_cannot_assign', false);

		// Path 1: Full assignment form (assign to any staff member)
		if (shd_allowed_to('shd_assign_ticket_any', $dept))
		{
			$possible = $this->shd_get_possible_assignees($is_private, $ticket_owner, $dept);

			// Build the member list: 0 => Unassigned, plus all possible assignees
			$context['member_list'] = array(
				0 => isset($txt['shd_unassigned']) ? $txt['shd_unassigned'] : 'Unassigned',
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
					$context['member_list'][(int) $member['id_member']] = $member['real_name'];

				$db->free_result($request);
			}

			$context['ticket_assigned'] = $current_assignee;
			$context['ticket_subject'] = $row['subject'];

			// Load template and set up page
			loadTemplate('SimpleDeskAssign');
			loadCSSFile('helpdesk.css');

			$context['page_title'] = (isset($txt['shd_ticket_assign_ticket']) ? $txt['shd_ticket_assign_ticket'] : 'Assign Ticket') . ' - ' . $row['subject'];
			$context['sub_template'] = 'shd_assign';

			$context['linktree'][] = array(
				'name' => $txt['shd_helpdesk'],
				'url' => $scripturl . '?action=helpdesk;sa=main',
			);
			$context['linktree'][] = array(
				'name' => $row['subject'],
				'url' => $scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $ticket_id,
			);
			$context['linktree'][] = array(
				'name' => isset($txt['shd_ticket_assign_ticket']) ? $txt['shd_ticket_assign_ticket'] : 'Assign Ticket',
			);
		}
		// Path 2: Quick self-assign/unassign (staff with own-assign only)
		elseif (shd_allowed_to('shd_assign_ticket_own', $dept) && shd_allowed_to('shd_staff', $dept))
		{
			if ($current_assignee == 0)
			{
				// Currently unassigned: assign to self
				shd_log_action('assign', array(
					'ticket' => $ticket_id,
					'subject' => $row['subject'],
					'assigned_name' => $user_info['name'],
					'assigned_id' => $user_info['id'],
				));

				$this->shd_commit_assignment($ticket_id, $user_info['id'], $dept);
			}
			elseif ($current_assignee == $user_info['id'])
			{
				// Assigned to self: unassign
				shd_log_action('unassign', array(
					'ticket' => $ticket_id,
					'subject' => $row['subject'],
				));

				$this->shd_commit_assignment($ticket_id, 0, $dept);
			}
			else
			{
				// Assigned to someone else: not allowed
				fatal_lang_error('shd_cannot_assign', false);
			}
		}
		else
		{
			fatal_lang_error('shd_cannot_assign', false);
		}
	}

	/**
	 * Process the assignment form submission.
	 *
	 * Validates the selected assignee against the list of possible
	 * assignees, logs the action, and commits the assignment change.
	 */
	public function action_assign2()
	{
		global $context, $scripturl, $txt, $modSettings, $user_info;

		$db = database();

		checkSession();

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		loadLanguage('SimpleDesk');

		$ticket_id = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;

		if (empty($ticket_id))
			fatal_lang_error('shd_no_ticket', false);

		$context['shd_return_to'] = isset($_REQUEST['home']) ? 'home' : '';

		// If the cancel button was pressed, redirect back
		if (isset($_POST['cancel']))
		{
			if (!empty($context['shd_return_to']) && $context['shd_return_to'] == 'home')
				redirectexit('action=helpdesk;sa=main');
			else
				redirectexit('action=helpdesk;sa=viewticket;ticket=' . $ticket_id);
		}

		$assignee = isset($_REQUEST['to_user']) ? (int) $_REQUEST['to_user'] : 0;

		// Load the ticket
		$request = shd_db_query('', '
			SELECT hdt.id_ticket, hdt.id_member_started, hdt.id_member_assigned,
				hdt.private, hdt.subject, hdt.id_dept, hdt.status,
				hdd.dept_name
			FROM {db_prefix}helpdesk_tickets AS hdt
				LEFT JOIN {db_prefix}helpdesk_depts AS hdd ON (hdd.id_dept = hdt.id_dept)
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
		$status = (int) $row['status'];
		$ticket_owner = (int) $row['id_member_started'];
		$current_assignee = (int) $row['id_member_assigned'];
		$is_private = !empty($row['private']);

		$context['shd_department'] = $dept;
		$context['ticket_id'] = $ticket_id;

		// Cannot assign closed or deleted tickets
		if ($status == TICKET_STATUS_CLOSED || $status == TICKET_STATUS_DELETED)
			fatal_lang_error('shd_cannot_assign', false);

		// Path 1: Full assignment (any staff member)
		if (shd_allowed_to('shd_assign_ticket_any', $dept))
		{
			if ($assignee == 0)
			{
				// Unassigning
				shd_log_action('unassign', array(
					'ticket' => $ticket_id,
					'subject' => $row['subject'],
				));

				$this->shd_commit_assignment($ticket_id, 0, $dept);
			}
			else
			{
				// Validate that the assignee is in the possible list
				$possible = $this->shd_get_possible_assignees($is_private, $ticket_owner, $dept);

				if (!in_array($assignee, $possible))
					fatal_lang_error('shd_assigned_not_permitted', false);

				// Load the assignee's name for the log
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
					fatal_lang_error('shd_assigned_not_permitted', false);
				}

				$member_row = $db->fetch_assoc($request);
				$db->free_result($request);

				shd_log_action('assign', array(
					'ticket' => $ticket_id,
					'subject' => $row['subject'],
					'assigned_name' => $member_row['real_name'],
					'assigned_id' => $assignee,
				));

				$this->shd_commit_assignment($ticket_id, $assignee, $dept);
			}
		}
		// Path 2: Self-assign/unassign only
		elseif (shd_allowed_to('shd_assign_ticket_own', $dept) && shd_allowed_to('shd_staff', $dept))
		{
			if ($current_assignee == 0)
			{
				// Unassigned: assign to self
				shd_log_action('assign', array(
					'ticket' => $ticket_id,
					'subject' => $row['subject'],
					'assigned_name' => $user_info['name'],
					'assigned_id' => $user_info['id'],
				));

				$this->shd_commit_assignment($ticket_id, $user_info['id'], $dept);
			}
			elseif ($current_assignee == $user_info['id'])
			{
				// Assigned to self: unassign
				shd_log_action('unassign', array(
					'ticket' => $ticket_id,
					'subject' => $row['subject'],
				));

				$this->shd_commit_assignment($ticket_id, 0, $dept);
			}
			else
			{
				fatal_lang_error('shd_cannot_assign', false);
			}
		}
		else
		{
			fatal_lang_error('shd_cannot_assign', false);
		}
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
	private function shd_get_possible_assignees($is_private, $ticket_owner, $dept)
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
	 * Commits an assignment change to the database and redirects.
	 *
	 * Uses shd_modify_ticket_post() to update the ticket's assigned member,
	 * clears the active ticket cache, and redirects the user based on
	 * their return-to preference.
	 *
	 * @param int $ticket_id The ticket ID.
	 * @param int $assignment The member ID to assign (0 for unassign).
	 * @param int $dept The department ID.
	 */
	private function shd_commit_assignment($ticket_id, $assignment, $dept)
	{
		global $context, $user_info;

		$db = database();

		// Update the ticket's assigned member directly
		$db->query('', '
			UPDATE {db_prefix}helpdesk_tickets
			SET id_member_assigned = {int:assigned},
				last_updated = {int:time}
			WHERE id_ticket = {int:ticket}',
			array(
				'assigned' => $assignment,
				'time' => time(),
				'ticket' => $ticket_id,
			)
		);

		// Clear the active tickets cache
		shd_clear_active_tickets($dept);

		// Redirect based on return preference
		if (!empty($context['shd_return_to']) && $context['shd_return_to'] == 'home')
			redirectexit('action=helpdesk;sa=main');
		else
			redirectexit('action=helpdesk;sa=viewticket;ticket=' . $ticket_id);
	}
}
