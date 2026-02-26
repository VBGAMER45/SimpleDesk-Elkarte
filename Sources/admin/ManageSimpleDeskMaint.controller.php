<?php
/**
 * SimpleDesk Maintenance Controller
 *
 * Handles helpdesk maintenance tasks: reattribute tickets, mass department
 * moves, find and repair database inconsistencies, and search index
 * management.
 *
 * Ported from SimpleDesk-AdminMaint.php (SMF version).
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Controller for helpdesk maintenance operations.
 *
 * Dispatched from ManageSimpleDesk_Controller when sa=maintenance.
 * Uses the 'do' parameter for internal sub-action routing since 'sa'
 * is already consumed by the parent controller.
 *
 * URL pattern: action=admin;area=helpdesk;sa=maintenance;do=X
 */
class ManageSimpleDeskMaint_Controller extends Action_Controller
{
	/**
	 * Entry point for helpdesk maintenance.
	 *
	 * Routes to the appropriate sub-action based on $_REQUEST['do'].
	 * Loads required language and template files, verifies admin access.
	 */
	public function action_index()
	{
		global $context, $txt;

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		isAllowedTo('admin_forum');

		loadLanguage('SimpleDesk');
		loadLanguage('SimpleDeskAdmin');
		loadTemplate('SimpleDeskAdminMaint');

		$subActions = array(
			'main' => 'action_home',
			'reattribute' => 'action_reattribute',
			'massdeptmove' => 'action_massdeptmove',
			'findrepair' => 'action_findrepair',
			'search' => 'action_search',
		);

		$do = isset($_REQUEST['do']) ? $_REQUEST['do'] : 'main';

		if (isset($subActions[$do]))
			$this->{$subActions[$do]}();
		else
			$this->action_home();
	}

	/**
	 * Maintenance home page.
	 *
	 * Displays the maintenance landing page with links to sub-tasks
	 * and the mass department move form. Loads the department list
	 * for the move form dropdown.
	 */
	public function action_home()
	{
		global $context, $txt, $scripturl;

		$db = database();

		$context['page_title'] = $txt['shd_admin_maint_title'];
		$context['sub_template'] = 'shd_admin_maint_home';

		// Load department list for the mass dept move form.
		$context['dept_list'] = array();

		$request = $db->query('', '
			SELECT id_dept, dept_name
			FROM {db_prefix}helpdesk_depts
			ORDER BY dept_order ASC',
			array()
		);

		while ($row = $db->fetch_assoc($request))
			$context['dept_list'][$row['id_dept']] = $row['dept_name'];

		$db->free_result($request);
	}

	/**
	 * Reattribute helpdesk posts from one user/email to another member.
	 *
	 * Accepts a type (email, name, or starter), a "from" value identifying
	 * the source, and a target member ID. Updates ticket starter/updater
	 * fields and reply member IDs as appropriate, then logs the action.
	 *
	 * Requires POST with session validation.
	 */
	public function action_reattribute()
	{
		global $context, $txt, $scripturl;

		$db = database();

		checkSession();

		$context['page_title'] = $txt['shd_admin_maint_title'];
		$context['sub_template'] = 'shd_admin_maint_reattributedone';

		// What type of reattribution are we doing?
		$type = isset($_POST['type']) ? $_POST['type'] : '';
		if (!in_array($type, array('email', 'name', 'starter')))
			fatal_lang_error('shd_admin_maint_findrepair_error', false);

		// Who are we attributing from?
		$from = isset($_POST['from']) ? trim($_POST['from']) : '';
		if (empty($from))
			fatal_lang_error('shd_admin_maint_findrepair_error', false);

		// Who are we attributing to?
		$to_member = isset($_POST['to']) ? (int) $_POST['to'] : 0;
		if (empty($to_member))
			fatal_lang_error('shd_admin_maint_findrepair_error', false);

		// Verify the target member exists.
		$request = $db->query('', '
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_member = {int:member}
			LIMIT 1',
			array(
				'member' => $to_member,
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			fatal_lang_error('shd_admin_maint_findrepair_error', false);
		}

		$member_info = $db->fetch_assoc($request);
		$db->free_result($request);

		$context['reattribute_done'] = array(
			'tickets' => 0,
			'replies' => 0,
			'to_name' => $member_info['real_name'],
		);

		// Build the conditions based on the type.
		if ($type === 'email')
		{
			$from = Util::htmlspecialchars($from);

			// Find replies by this email address (for guest posts).
			$request = $db->query('', '
				SELECT id_msg, id_ticket
				FROM {db_prefix}helpdesk_ticket_replies
				WHERE poster_email = {string:email}
					AND id_member = {int:zero}',
				array(
					'email' => $from,
					'zero' => 0,
				)
			);

			$reply_ids = array();
			$ticket_ids = array();

			while ($row = $db->fetch_assoc($request))
			{
				$reply_ids[] = (int) $row['id_msg'];
				$ticket_ids[] = (int) $row['id_ticket'];
			}
			$db->free_result($request);

			if (!empty($reply_ids))
			{
				// Update the replies.
				$db->query('', '
					UPDATE {db_prefix}helpdesk_ticket_replies
					SET id_member = {int:member}
					WHERE id_msg IN ({array_int:msgs})',
					array(
						'member' => $to_member,
						'msgs' => $reply_ids,
					)
				);
				$context['reattribute_done']['replies'] = count($reply_ids);

				// Now update the tickets where this person was the starter or updater.
				$ticket_ids = array_unique($ticket_ids);

				foreach ($ticket_ids as $ticket)
					$this->shd_update_ticket_members($ticket, $to_member);

				$context['reattribute_done']['tickets'] = count($ticket_ids);
			}
		}
		elseif ($type === 'name')
		{
			$from = Util::htmlspecialchars($from);

			// Find replies by this poster name (for guest posts).
			$request = $db->query('', '
				SELECT id_msg, id_ticket
				FROM {db_prefix}helpdesk_ticket_replies
				WHERE poster_name = {string:name}
					AND id_member = {int:zero}',
				array(
					'name' => $from,
					'zero' => 0,
				)
			);

			$reply_ids = array();
			$ticket_ids = array();

			while ($row = $db->fetch_assoc($request))
			{
				$reply_ids[] = (int) $row['id_msg'];
				$ticket_ids[] = (int) $row['id_ticket'];
			}
			$db->free_result($request);

			if (!empty($reply_ids))
			{
				$db->query('', '
					UPDATE {db_prefix}helpdesk_ticket_replies
					SET id_member = {int:member}
					WHERE id_msg IN ({array_int:msgs})',
					array(
						'member' => $to_member,
						'msgs' => $reply_ids,
					)
				);
				$context['reattribute_done']['replies'] = count($reply_ids);

				$ticket_ids = array_unique($ticket_ids);

				foreach ($ticket_ids as $ticket)
					$this->shd_update_ticket_members($ticket, $to_member);

				$context['reattribute_done']['tickets'] = count($ticket_ids);
			}
		}
		elseif ($type === 'starter')
		{
			// Reattribute by member ID (used for merging accounts).
			$from_member = (int) $from;
			if (empty($from_member))
				fatal_lang_error('shd_admin_maint_findrepair_error', false);

			// Update replies.
			$request = $db->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}helpdesk_ticket_replies
				WHERE id_member = {int:from}',
				array(
					'from' => $from_member,
				)
			);
			list($reply_count) = $db->fetch_row($request);
			$db->free_result($request);

			$db->query('', '
				UPDATE {db_prefix}helpdesk_ticket_replies
				SET id_member = {int:to}
				WHERE id_member = {int:from}',
				array(
					'to' => $to_member,
					'from' => $from_member,
				)
			);
			$context['reattribute_done']['replies'] = (int) $reply_count;

			// Update tickets where this was the starter.
			$request = $db->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}helpdesk_tickets
				WHERE id_member_started = {int:from}',
				array(
					'from' => $from_member,
				)
			);
			list($ticket_count) = $db->fetch_row($request);
			$db->free_result($request);

			$db->query('', '
				UPDATE {db_prefix}helpdesk_tickets
				SET id_member_started = {int:to}
				WHERE id_member_started = {int:from}',
				array(
					'to' => $to_member,
					'from' => $from_member,
				)
			);

			// Update tickets where this was the updater.
			$db->query('', '
				UPDATE {db_prefix}helpdesk_tickets
				SET id_member_updated = {int:to}
				WHERE id_member_updated = {int:from}',
				array(
					'to' => $to_member,
					'from' => $from_member,
				)
			);

			// Update assigned tickets.
			$db->query('', '
				UPDATE {db_prefix}helpdesk_tickets
				SET id_member_assigned = {int:to}
				WHERE id_member_assigned = {int:from}',
				array(
					'to' => $to_member,
					'from' => $from_member,
				)
			);

			$context['reattribute_done']['tickets'] = (int) $ticket_count;
		}

		// Log the admin action.
		shd_log_action('maint_reattribute', array(
			'type' => $type,
			'from' => $from,
			'to' => $member_info['real_name'],
			'to_id' => $to_member,
			'tickets' => $context['reattribute_done']['tickets'],
			'replies' => $context['reattribute_done']['replies'],
		), false);
	}

	/**
	 * Mass move tickets between departments.
	 *
	 * Accepts source and destination department IDs, optional status
	 * filters (open, closed, deleted), and optional date range filters.
	 * Moves matching tickets to the target department in batches, clears
	 * relevant caches, and logs the action.
	 *
	 * Requires POST with session validation.
	 */
	public function action_massdeptmove()
	{
		global $context, $txt, $scripturl;

		$db = database();

		checkSession();

		$context['page_title'] = $txt['shd_admin_maint_title'];
		$context['sub_template'] = 'shd_admin_maint_massdeptmovedone';

		$id_dept_from = isset($_POST['id_dept_from']) ? (int) $_POST['id_dept_from'] : 0;
		$id_dept_to = isset($_POST['id_dept_to']) ? (int) $_POST['id_dept_to'] : 0;

		// Must have valid, distinct departments.
		if (empty($id_dept_from) || empty($id_dept_to) || $id_dept_from === $id_dept_to)
			fatal_lang_error('shd_admin_maint_findrepair_error', false);

		// Verify both departments exist.
		$request = $db->query('', '
			SELECT id_dept, dept_name
			FROM {db_prefix}helpdesk_depts
			WHERE id_dept IN ({array_int:depts})',
			array(
				'depts' => array($id_dept_from, $id_dept_to),
			)
		);

		$depts = array();
		while ($row = $db->fetch_assoc($request))
			$depts[(int) $row['id_dept']] = $row['dept_name'];
		$db->free_result($request);

		if (!isset($depts[$id_dept_from]) || !isset($depts[$id_dept_to]))
			fatal_lang_error('shd_admin_maint_findrepair_error', false);

		// Build the WHERE clause based on status filters.
		$where_clauses = array('id_dept = {int:dept_from}');
		$where_params = array('dept_from' => $id_dept_from);
		$status_list = array();

		// Status filters: moveopen, moveclosed, movedeleted
		if (!empty($_POST['moveopen']))
		{
			$status_list[] = TICKET_STATUS_NEW;
			$status_list[] = TICKET_STATUS_PENDING_STAFF;
			$status_list[] = TICKET_STATUS_PENDING_USER;
			$status_list[] = TICKET_STATUS_WITH_SUPERVISOR;
			$status_list[] = TICKET_STATUS_ESCALATED;
			$status_list[] = TICKET_STATUS_HOLD;
		}
		if (!empty($_POST['moveclosed']))
			$status_list[] = TICKET_STATUS_CLOSED;
		if (!empty($_POST['movedeleted']))
			$status_list[] = TICKET_STATUS_DELETED;

		// If no status selected, nothing to do.
		if (empty($status_list))
			fatal_lang_error('shd_admin_maint_findrepair_error', false);

		$where_clauses[] = 'status IN ({array_int:status_list})';
		$where_params['status_list'] = $status_list;

		// Date filters (optional).
		if (!empty($_POST['date_from']))
		{
			$date_from = strtotime($_POST['date_from']);
			if ($date_from !== false)
			{
				$where_clauses[] = 'last_updated >= {int:date_from}';
				$where_params['date_from'] = $date_from;
			}
		}

		if (!empty($_POST['date_to']))
		{
			$date_to = strtotime($_POST['date_to']);
			if ($date_to !== false)
			{
				// Set to end of the given day.
				$date_to += 86399;
				$where_clauses[] = 'last_updated <= {int:date_to}';
				$where_params['date_to'] = $date_to;
			}
		}

		// Count how many tickets will be moved.
		$request = $db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}helpdesk_tickets
			WHERE ' . implode(' AND ', $where_clauses),
			$where_params
		);
		list($tickets_moved) = $db->fetch_row($request);
		$db->free_result($request);

		$tickets_moved = (int) $tickets_moved;

		// Perform the move.
		if ($tickets_moved > 0)
		{
			$db->query('', '
				UPDATE {db_prefix}helpdesk_tickets
				SET id_dept = {int:dept_to}
				WHERE ' . implode(' AND ', $where_clauses),
				array_merge($where_params, array('dept_to' => $id_dept_to))
			);

			// Clear cache for both departments.
			shd_clear_active_tickets($id_dept_from);
			shd_clear_active_tickets($id_dept_to);
		}

		$context['massdeptmove_done'] = array(
			'tickets' => $tickets_moved,
			'from_name' => $depts[$id_dept_from],
			'to_name' => $depts[$id_dept_to],
		);

		// Log the admin action.
		shd_log_action('maint_massdeptmove', array(
			'tickets' => $tickets_moved,
			'from_dept' => $id_dept_from,
			'from_dept_name' => $depts[$id_dept_from],
			'to_dept' => $id_dept_to,
			'to_dept_name' => $depts[$id_dept_to],
		), false);
	}

	/**
	 * Find and repair database inconsistencies in the helpdesk.
	 *
	 * Runs 7 maintenance steps:
	 * 1. Zero-entry tickets (id_ticket = 0) - assign new IDs
	 * 2. Zero-entry messages (id_msg = 0) - assign new IDs
	 * 3. Deleted reply counts - recalculate num_replies and deleted_replies
	 * 4. First/last message - recalculate id_first_msg and id_last_msg
	 * 5. Status - fix tickets with wrong status based on their messages
	 * 6. Starter/updater - fix id_member_started and id_member_updated
	 * 7. Invalid department - tickets in non-existent departments
	 *
	 * After all steps, clears cache. Shows results template.
	 */
	public function action_findrepair()
	{
		global $context, $txt;

		$db = database();

		checkSession();

		$context['page_title'] = $txt['shd_admin_maint_title'];
		$context['sub_template'] = 'shd_admin_maint_findrepairdone';
		$context['repair_results'] = array();

		// ================================================================
		// Step 1: Zero-entry tickets (id_ticket = 0)
		// ================================================================
		$request = $db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}helpdesk_tickets
			WHERE id_ticket = {int:zero}',
			array(
				'zero' => 0,
			)
		);
		list($zero_tickets) = $db->fetch_row($request);
		$db->free_result($request);

		$context['repair_results']['zero_tickets'] = (int) $zero_tickets;

		// If there are zero-entry tickets, they need to be given proper IDs.
		// In practice, this is extremely rare and indicates direct DB corruption.
		// The auto_increment should handle new inserts, but id=0 rows are anomalous.
		if ($zero_tickets > 0)
		{
			// Get the current max ticket ID.
			$request = $db->query('', '
				SELECT MAX(id_ticket)
				FROM {db_prefix}helpdesk_tickets',
				array()
			);
			list($max_ticket) = $db->fetch_row($request);
			$db->free_result($request);

			$max_ticket = (int) $max_ticket;

			// We cannot easily "assign new IDs" to id=0 rows through UPDATE
			// because multiple rows may have id=0. Handle one at a time.
			for ($i = 0; $i < $zero_tickets; $i++)
			{
				$max_ticket++;
				$db->query('', '
					UPDATE {db_prefix}helpdesk_tickets
					SET id_ticket = {int:new_id}
					WHERE id_ticket = {int:zero}
					LIMIT 1',
					array(
						'new_id' => $max_ticket,
						'zero' => 0,
					)
				);
			}
		}

		// ================================================================
		// Step 2: Zero-entry messages (id_msg = 0)
		// ================================================================
		$request = $db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}helpdesk_ticket_replies
			WHERE id_msg = {int:zero}',
			array(
				'zero' => 0,
			)
		);
		list($zero_msgs) = $db->fetch_row($request);
		$db->free_result($request);

		$context['repair_results']['zero_msgs'] = (int) $zero_msgs;

		if ($zero_msgs > 0)
		{
			$request = $db->query('', '
				SELECT MAX(id_msg)
				FROM {db_prefix}helpdesk_ticket_replies',
				array()
			);
			list($max_msg) = $db->fetch_row($request);
			$db->free_result($request);

			$max_msg = (int) $max_msg;

			for ($i = 0; $i < $zero_msgs; $i++)
			{
				$max_msg++;
				$db->query('', '
					UPDATE {db_prefix}helpdesk_ticket_replies
					SET id_msg = {int:new_id}
					WHERE id_msg = {int:zero}
					LIMIT 1',
					array(
						'new_id' => $max_msg,
						'zero' => 0,
					)
				);
			}
		}

		// ================================================================
		// Step 3: Recalculate num_replies and deleted_replies
		// ================================================================
		$context['repair_results']['reply_count_fixes'] = 0;

		$request = $db->query('', '
			SELECT hdt.id_ticket, hdt.num_replies, hdt.deleted_replies,
				hdt.id_first_msg,
				COALESCE(counts.actual_replies, 0) AS actual_replies,
				COALESCE(counts.actual_deleted, 0) AS actual_deleted
			FROM {db_prefix}helpdesk_tickets AS hdt
				LEFT JOIN (
					SELECT id_ticket,
						SUM(CASE WHEN message_status = {int:normal} THEN 1 ELSE 0 END) - 1 AS actual_replies,
						SUM(CASE WHEN message_status = {int:deleted} THEN 1 ELSE 0 END) AS actual_deleted
					FROM {db_prefix}helpdesk_ticket_replies
					GROUP BY id_ticket
				) AS counts ON (hdt.id_ticket = counts.id_ticket)',
			array(
				'normal' => MSG_STATUS_NORMAL,
				'deleted' => MSG_STATUS_DELETED,
			)
		);

		while ($row = $db->fetch_assoc($request))
		{
			$actual_replies = max(0, (int) $row['actual_replies']);
			$actual_deleted = max(0, (int) $row['actual_deleted']);

			if ((int) $row['num_replies'] != $actual_replies || (int) $row['deleted_replies'] != $actual_deleted)
			{
				$db->query('', '
					UPDATE {db_prefix}helpdesk_tickets
					SET num_replies = {int:num_replies},
						deleted_replies = {int:deleted_replies},
						withdeleted = {int:withdeleted}
					WHERE id_ticket = {int:ticket}',
					array(
						'num_replies' => $actual_replies,
						'deleted_replies' => $actual_deleted,
						'withdeleted' => $actual_deleted > 0 ? MSG_STATUS_DELETED : MSG_STATUS_NORMAL,
						'ticket' => $row['id_ticket'],
					)
				);
				$context['repair_results']['reply_count_fixes']++;
			}
		}
		$db->free_result($request);

		// ================================================================
		// Step 4: Recalculate id_first_msg and id_last_msg
		// ================================================================
		$context['repair_results']['first_last_fixes'] = 0;

		$request = $db->query('', '
			SELECT hdt.id_ticket, hdt.id_first_msg, hdt.id_last_msg,
				MIN(hdtr.id_msg) AS actual_first,
				MAX(hdtr_normal.id_msg) AS actual_last
			FROM {db_prefix}helpdesk_tickets AS hdt
				LEFT JOIN {db_prefix}helpdesk_ticket_replies AS hdtr ON (hdt.id_ticket = hdtr.id_ticket)
				LEFT JOIN {db_prefix}helpdesk_ticket_replies AS hdtr_normal ON (hdt.id_ticket = hdtr_normal.id_ticket AND hdtr_normal.message_status = {int:normal})
			GROUP BY hdt.id_ticket, hdt.id_first_msg, hdt.id_last_msg',
			array(
				'normal' => MSG_STATUS_NORMAL,
			)
		);

		while ($row = $db->fetch_assoc($request))
		{
			$actual_first = (int) $row['actual_first'];
			$actual_last = !empty($row['actual_last']) ? (int) $row['actual_last'] : $actual_first;

			if ((int) $row['id_first_msg'] != $actual_first || (int) $row['id_last_msg'] != $actual_last)
			{
				$db->query('', '
					UPDATE {db_prefix}helpdesk_tickets
					SET id_first_msg = {int:first_msg},
						id_last_msg = {int:last_msg}
					WHERE id_ticket = {int:ticket}',
					array(
						'first_msg' => $actual_first,
						'last_msg' => $actual_last,
						'ticket' => $row['id_ticket'],
					)
				);
				$context['repair_results']['first_last_fixes']++;
			}
		}
		$db->free_result($request);

		// ================================================================
		// Step 5: Fix ticket status based on messages
		// ================================================================
		$context['repair_results']['status_fixes'] = 0;

		// Tickets marked as closed/deleted but with no actual basis for that status.
		// We only fix tickets that have zero normal messages (orphaned) to "new".
		$request = $db->query('', '
			SELECT hdt.id_ticket, hdt.status, hdt.num_replies,
				hdt.id_member_started, hdt.id_member_updated, hdt.id_dept,
				COUNT(hdtr.id_msg) AS msg_count
			FROM {db_prefix}helpdesk_tickets AS hdt
				LEFT JOIN {db_prefix}helpdesk_ticket_replies AS hdtr ON (hdt.id_ticket = hdtr.id_ticket AND hdtr.message_status = {int:normal})
			GROUP BY hdt.id_ticket, hdt.status, hdt.num_replies,
				hdt.id_member_started, hdt.id_member_updated, hdt.id_dept
			HAVING msg_count = 0 AND hdt.status NOT IN ({array_int:skip_statuses})',
			array(
				'normal' => MSG_STATUS_NORMAL,
				'skip_statuses' => array(TICKET_STATUS_DELETED),
			)
		);

		while ($row = $db->fetch_assoc($request))
		{
			// Ticket has no normal messages but is not deleted -- this is wrong.
			// If it has deleted messages, mark it deleted. Otherwise, mark it new.
			$check = $db->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}helpdesk_ticket_replies
				WHERE id_ticket = {int:ticket}',
				array(
					'ticket' => $row['id_ticket'],
				)
			);
			list($total_msgs) = $db->fetch_row($check);
			$db->free_result($check);

			// If there are messages but all deleted, keep it as deleted.
			// If there are no messages at all, it is an orphan -- mark as new.
			$new_status = ((int) $total_msgs > 0) ? TICKET_STATUS_DELETED : TICKET_STATUS_NEW;

			$db->query('', '
				UPDATE {db_prefix}helpdesk_tickets
				SET status = {int:status}
				WHERE id_ticket = {int:ticket}',
				array(
					'status' => $new_status,
					'ticket' => $row['id_ticket'],
				)
			);
			$context['repair_results']['status_fixes']++;
		}
		$db->free_result($request);

		// ================================================================
		// Step 6: Fix id_member_started and id_member_updated
		// ================================================================
		$context['repair_results']['starter_updater_fixes'] = 0;

		$request = $db->query('', '
			SELECT hdt.id_ticket, hdt.id_first_msg, hdt.id_last_msg,
				hdt.id_member_started, hdt.id_member_updated,
				hdtr_first.id_member AS actual_starter,
				hdtr_last.id_member AS actual_updater
			FROM {db_prefix}helpdesk_tickets AS hdt
				LEFT JOIN {db_prefix}helpdesk_ticket_replies AS hdtr_first ON (hdt.id_first_msg = hdtr_first.id_msg)
				LEFT JOIN {db_prefix}helpdesk_ticket_replies AS hdtr_last ON (hdt.id_last_msg = hdtr_last.id_msg)',
			array()
		);

		while ($row = $db->fetch_assoc($request))
		{
			$actual_starter = (int) $row['actual_starter'];
			$actual_updater = (int) $row['actual_updater'];

			if ((int) $row['id_member_started'] != $actual_starter || (int) $row['id_member_updated'] != $actual_updater)
			{
				$db->query('', '
					UPDATE {db_prefix}helpdesk_tickets
					SET id_member_started = {int:starter},
						id_member_updated = {int:updater}
					WHERE id_ticket = {int:ticket}',
					array(
						'starter' => $actual_starter,
						'updater' => $actual_updater,
						'ticket' => $row['id_ticket'],
					)
				);
				$context['repair_results']['starter_updater_fixes']++;
			}
		}
		$db->free_result($request);

		// ================================================================
		// Step 7: Invalid department - tickets in non-existent departments
		// ================================================================
		$context['repair_results']['invalid_dept_fixes'] = 0;

		$request = $db->query('', '
			SELECT hdt.id_ticket, hdt.id_dept
			FROM {db_prefix}helpdesk_tickets AS hdt
				LEFT JOIN {db_prefix}helpdesk_depts AS hdd ON (hdt.id_dept = hdd.id_dept)
			WHERE hdd.id_dept IS NULL',
			array()
		);

		$orphan_tickets = array();
		while ($row = $db->fetch_assoc($request))
			$orphan_tickets[] = (int) $row['id_ticket'];
		$db->free_result($request);

		if (!empty($orphan_tickets))
		{
			$context['repair_results']['invalid_dept_fixes'] = count($orphan_tickets);

			// Create a "Recovered Tickets" department if it does not already exist.
			$request = $db->query('', '
				SELECT id_dept
				FROM {db_prefix}helpdesk_depts
				WHERE dept_name = {string:recovered}
				LIMIT 1',
				array(
					'recovered' => 'Recovered Tickets',
				)
			);

			if ($db->num_rows($request) > 0)
			{
				list($recovered_dept) = $db->fetch_row($request);
				$recovered_dept = (int) $recovered_dept;
			}
			else
			{
				// Get the next dept_order value.
				$order_request = $db->query('', '
					SELECT MAX(dept_order)
					FROM {db_prefix}helpdesk_depts',
					array()
				);
				list($max_order) = $db->fetch_row($order_request);
				$db->free_result($order_request);

				$new_order = (int) $max_order + 1;

				$db->insert('insert',
					'{db_prefix}helpdesk_depts',
					array(
						'dept_name' => 'string',
						'description' => 'string',
						'board_cat' => 'int',
						'before_after' => 'int',
						'dept_order' => 'int',
						'dept_theme' => 'int',
						'autoclose_days' => 'int',
					),
					array(
						'Recovered Tickets',
						'Department created by maintenance to hold tickets from deleted departments.',
						0,
						0,
						$new_order,
						0,
						0,
					),
					array('id_dept')
				);

				$recovered_dept = $db->insert_id('{db_prefix}helpdesk_depts', 'id_dept');
			}
			$db->free_result($request);

			// Move orphan tickets to the recovered department.
			$db->query('', '
				UPDATE {db_prefix}helpdesk_tickets
				SET id_dept = {int:dept}
				WHERE id_ticket IN ({array_int:tickets})',
				array(
					'dept' => $recovered_dept,
					'tickets' => $orphan_tickets,
				)
			);
		}

		// Clear all ticket caches.
		shd_clear_active_tickets(0);

		// Log the admin action.
		shd_log_action('maint_findrepair', array(
			'zero_tickets' => $context['repair_results']['zero_tickets'],
			'zero_msgs' => $context['repair_results']['zero_msgs'],
			'reply_count_fixes' => $context['repair_results']['reply_count_fixes'],
			'first_last_fixes' => $context['repair_results']['first_last_fixes'],
			'status_fixes' => $context['repair_results']['status_fixes'],
			'starter_updater_fixes' => $context['repair_results']['starter_updater_fixes'],
			'invalid_dept_fixes' => $context['repair_results']['invalid_dept_fixes'],
		), false);
	}

	/**
	 * Search index settings and rebuild.
	 *
	 * If saving, persists search index settings (min_size, max_size,
	 * prefix_size, charset). If rebuilding, truncates and repopulates
	 * the search index tables by tokenizing all ticket subjects and
	 * message bodies. Processes in batches with pagination via
	 * $_REQUEST['start'].
	 *
	 * Displays search settings template.
	 */
	public function action_search()
	{
		global $context, $txt, $scripturl, $modSettings;

		$db = database();

		require_once(SUBSDIR . '/SimpleDeskSearch.subs.php');

		$context['page_title'] = $txt['shd_admin_maint_title'];
		$context['sub_template'] = 'shd_admin_maint_search';

		// Load current search settings for display.
		$context['shd_search_settings'] = array(
			'min_size' => !empty($modSettings['shd_search_min_size']) ? (int) $modSettings['shd_search_min_size'] : 3,
			'max_size' => !empty($modSettings['shd_search_max_size']) ? (int) $modSettings['shd_search_max_size'] : 8,
			'prefix_size' => !empty($modSettings['shd_search_prefix_size']) ? (int) $modSettings['shd_search_prefix_size'] : 0,
			'charset' => !empty($modSettings['shd_search_charset']) ? $modSettings['shd_search_charset'] : '0..9, A..Z, a..z, &, ~',
		);

		// Handle save request.
		if (isset($_POST['save']))
		{
			checkSession();

			$save_vars = array();

			$save_vars['shd_search_min_size'] = isset($_POST['min_size']) ? max(1, (int) $_POST['min_size']) : 3;
			$save_vars['shd_search_max_size'] = isset($_POST['max_size']) ? max($save_vars['shd_search_min_size'], (int) $_POST['max_size']) : 8;
			$save_vars['shd_search_prefix_size'] = isset($_POST['prefix_size']) ? max(0, (int) $_POST['prefix_size']) : 0;
			$save_vars['shd_search_charset'] = isset($_POST['charset']) ? Util::htmlspecialchars(trim($_POST['charset'])) : '0..9, A..Z, a..z, &, ~';

			updateSettings($save_vars);

			// Update the displayed values.
			$context['shd_search_settings'] = array(
				'min_size' => $save_vars['shd_search_min_size'],
				'max_size' => $save_vars['shd_search_max_size'],
				'prefix_size' => $save_vars['shd_search_prefix_size'],
				'charset' => $save_vars['shd_search_charset'],
			);

			$context['search_settings_saved'] = true;
		}

		// Handle rebuild request.
		if (isset($_POST['rebuild']) || isset($_REQUEST['rebuild']))
		{
			checkSession(isset($_POST['rebuild']) ? 'post' : 'get');

			$batch_size = 100;
			$start = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;

			// On the first pass, truncate the index tables.
			if ($start === 0)
			{
				$db->query('', '
					TRUNCATE {db_prefix}helpdesk_search_subject_words',
					array()
				);

				$db->query('', '
					TRUNCATE {db_prefix}helpdesk_search_ticket_words',
					array()
				);
			}

			// Count total tickets for progress display.
			$request = $db->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}helpdesk_tickets',
				array()
			);
			list($total_tickets) = $db->fetch_row($request);
			$db->free_result($request);

			$total_tickets = (int) $total_tickets;

			// Process tickets in this batch.
			$request = $db->query('', '
				SELECT hdt.id_ticket, hdt.subject
				FROM {db_prefix}helpdesk_tickets AS hdt
				ORDER BY hdt.id_ticket ASC
				LIMIT {int:start}, {int:limit}',
				array(
					'start' => $start,
					'limit' => $batch_size,
				)
			);

			$tickets_processed = 0;
			$ticket_ids = array();

			while ($row = $db->fetch_assoc($request))
			{
				$ticket_ids[] = (int) $row['id_ticket'];
				$tickets_processed++;

				// Tokenize the subject and insert into subject words table.
				$subject_words = shd_tokeniser($row['subject']);

				if (!empty($subject_words))
				{
					$subject_inserts = array();
					foreach ($subject_words as $word)
						$subject_inserts[] = array((int) $row['id_ticket'], $word);

					$db->insert('ignore',
						'{db_prefix}helpdesk_search_subject_words',
						array(
							'id_ticket' => 'int',
							'id_word' => 'string',
						),
						$subject_inserts,
						array('id_ticket', 'id_word')
					);
				}
			}
			$db->free_result($request);

			// Now process all messages for the tickets in this batch.
			if (!empty($ticket_ids))
			{
				$request = $db->query('', '
					SELECT hdtr.id_msg, hdtr.id_ticket, hdtr.body
					FROM {db_prefix}helpdesk_ticket_replies AS hdtr
					WHERE hdtr.id_ticket IN ({array_int:tickets})
					ORDER BY hdtr.id_msg ASC',
					array(
						'tickets' => $ticket_ids,
					)
				);

				while ($row = $db->fetch_assoc($request))
				{
					$body_words = shd_tokeniser($row['body']);

					if (!empty($body_words))
					{
						$body_inserts = array();
						foreach ($body_words as $word)
							$body_inserts[] = array((int) $row['id_msg'], $word);

						$db->insert('ignore',
							'{db_prefix}helpdesk_search_ticket_words',
							array(
								'id_msg' => 'int',
								'id_word' => 'string',
							),
							$body_inserts,
							array('id_msg', 'id_word')
						);
					}
				}
				$db->free_result($request);
			}

			// Are there more tickets to process?
			$next_start = $start + $batch_size;

			if ($next_start < $total_tickets)
			{
				// More to process -- redirect for next batch.
				$context['continue_rebuild'] = true;
				$context['rebuild_next_url'] = $scripturl . '?action=admin;area=helpdesk;sa=maintenance;do=search;rebuild;start=' . $next_start . ';' . $context['session_var'] . '=' . $context['session_id'];
				$context['rebuild_percent'] = min(100, round(($next_start / $total_tickets) * 100));
				$context['rebuild_total'] = $total_tickets;
				$context['rebuild_current'] = $next_start;
			}
			else
			{
				// All done.
				$context['search_rebuild_done'] = true;
				$context['rebuild_total'] = $total_tickets;
			}
		}
	}

	/**
	 * Recalculates the starter and updater members for a given ticket.
	 *
	 * Looks up the member who posted the first message (starter) and
	 * the member who posted the last normal message (updater) and
	 * updates the ticket record accordingly.
	 *
	 * @param int $ticket The ticket ID to update.
	 * @param int $new_member The member ID that was reattributed.
	 */
	private function shd_update_ticket_members($ticket, $new_member)
	{
		$db = database();

		// Get the first and last message for this ticket.
		$request = $db->query('', '
			SELECT id_first_msg, id_last_msg
			FROM {db_prefix}helpdesk_tickets
			WHERE id_ticket = {int:ticket}',
			array(
				'ticket' => $ticket,
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			return;
		}

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		$first_msg = (int) $row['id_first_msg'];
		$last_msg = (int) $row['id_last_msg'];

		// Get the actual starter member from the first message.
		$request = $db->query('', '
			SELECT id_member
			FROM {db_prefix}helpdesk_ticket_replies
			WHERE id_msg = {int:msg}',
			array(
				'msg' => $first_msg,
			)
		);

		$starter = 0;
		if ($db->num_rows($request) > 0)
		{
			list($starter) = $db->fetch_row($request);
			$starter = (int) $starter;
		}
		$db->free_result($request);

		// Get the actual updater member from the last message.
		$request = $db->query('', '
			SELECT id_member
			FROM {db_prefix}helpdesk_ticket_replies
			WHERE id_msg = {int:msg}',
			array(
				'msg' => $last_msg,
			)
		);

		$updater = 0;
		if ($db->num_rows($request) > 0)
		{
			list($updater) = $db->fetch_row($request);
			$updater = (int) $updater;
		}
		$db->free_result($request);

		// Update the ticket.
		$db->query('', '
			UPDATE {db_prefix}helpdesk_tickets
			SET id_member_started = {int:starter},
				id_member_updated = {int:updater}
			WHERE id_ticket = {int:ticket}',
			array(
				'starter' => $starter,
				'updater' => $updater,
				'ticket' => $ticket,
			)
		);
	}
}
