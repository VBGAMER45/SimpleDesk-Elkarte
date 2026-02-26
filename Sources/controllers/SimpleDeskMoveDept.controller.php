<?php
/**
 * SimpleDesk Move Department Controller
 *
 * Handles moving tickets between departments. Provides the department
 * selection form and processes the move, updating the ticket's department,
 * logging the action, and clearing relevant caches.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Controller for moving tickets between departments.
 */
class SimpleDeskMoveDept_Controller extends Action_Controller
{
	/**
	 * Default entry point - redirects to main helpdesk.
	 */
	public function action_index()
	{
		redirectexit('action=helpdesk');
	}

	/**
	 * Move a ticket to another department.
	 *
	 * Handles both the display of the move form (GET) and the processing
	 * of the move submission (POST). On GET, shows a form with available
	 * destination departments. On POST, validates the destination,
	 * updates the ticket, logs the action, and redirects.
	 */
	public function action_movedept()
	{
		global $context, $scripturl, $txt, $modSettings, $user_info;

		$db = database();

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		loadLanguage('SimpleDesk');

		$ticket_id = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;

		if (empty($ticket_id))
			fatal_lang_error('shd_no_ticket', false);

		$context['shd_return_to'] = isset($_REQUEST['home']) ? 'home' : '';
		$context['ticket_id'] = $ticket_id;

		// Handle POST submission (move the ticket)
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['to_dept']))
		{
			checkSession();

			// If the cancel button was pressed, redirect back
			if (isset($_POST['cancel']))
			{
				if (!empty($context['shd_return_to']) && $context['shd_return_to'] == 'home')
					redirectexit('action=helpdesk;sa=main');
				else
					redirectexit('action=helpdesk;sa=viewticket;ticket=' . $ticket_id);
			}

			$to_dept = (int) $_POST['to_dept'];

			// Load the ticket
			$request = shd_db_query('', '
				SELECT hdt.id_ticket, hdt.id_member_started, hdt.subject,
					hdt.id_dept, hdt.status,
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

			$current_dept = (int) $row['id_dept'];
			$starter_id = (int) $row['id_member_started'];

			$context['shd_department'] = $current_dept;

			// Permission check: shd_move_dept_any OR (shd_move_dept_own AND is the starter)
			$can_move_any = shd_allowed_to('shd_move_dept_any', $current_dept);
			$can_move_own = shd_allowed_to('shd_move_dept_own', $current_dept) && ($starter_id == $user_info['id']);

			if (!$can_move_any && !$can_move_own)
				fatal_lang_error('shd_no_perm_move_dept', false);

			// Destination must differ from current
			if ($to_dept == $current_dept)
				fatal_lang_error('shd_no_ticket', false);

			// Validate the destination department exists and is visible to the user
			$visible_depts = $this->shd_get_visible_departments($current_dept);

			if (!isset($visible_depts[$to_dept]))
				fatal_lang_error('shd_no_ticket', false);

			$new_dept_name = $visible_depts[$to_dept];

			// Log the action
			shd_log_action('move_dept', array(
				'ticket' => $ticket_id,
				'subject' => $row['subject'],
				'from_dept' => $current_dept,
				'from_dept_name' => $row['dept_name'],
				'to_dept' => $to_dept,
				'to_dept_name' => $new_dept_name,
			));

			// Move the ticket
			$db->query('', '
				UPDATE {db_prefix}helpdesk_tickets
				SET id_dept = {int:new_dept},
					last_updated = {int:time}
				WHERE id_ticket = {int:ticket}',
				array(
					'new_dept' => $to_dept,
					'time' => time(),
					'ticket' => $ticket_id,
				)
			);

			// Clear cache for both the source and destination departments
			shd_clear_active_tickets($current_dept);
			shd_clear_active_tickets($to_dept);

			// Redirect
			if (!empty($context['shd_return_to']) && $context['shd_return_to'] == 'home')
				redirectexit('action=helpdesk;sa=main');
			else
				redirectexit('action=helpdesk;sa=viewticket;ticket=' . $ticket_id);
		}

		// GET request: display the move form

		// Multi-department must be active for moving to make sense
		$all_depts = shd_allowed_to('access_helpdesk', false);

		if (empty($all_depts) || count($all_depts) < 2)
			fatal_lang_error('shd_no_ticket', false);

		// Load the ticket
		$request = shd_db_query('', '
			SELECT hdt.id_ticket, hdt.id_member_started, hdt.subject,
				hdt.id_dept, hdt.status,
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

		$current_dept = (int) $row['id_dept'];
		$starter_id = (int) $row['id_member_started'];

		$context['shd_department'] = $current_dept;

		// Permission check: shd_move_dept_any OR (shd_move_dept_own AND is the starter)
		$can_move_any = shd_allowed_to('shd_move_dept_any', $current_dept);
		$can_move_own = shd_allowed_to('shd_move_dept_own', $current_dept) && ($starter_id == $user_info['id']);

		if (!$can_move_any && !$can_move_own)
			fatal_lang_error('shd_no_perm_move_dept', false);

		// Get available destination departments (excluding current)
		$visible_depts = $this->shd_get_visible_departments($current_dept);

		if (empty($visible_depts))
			fatal_lang_error('shd_no_ticket', false);

		// Set up context for the template
		$context['dept_list'] = $visible_depts;
		$context['current_dept'] = $current_dept;
		$context['current_dept_name'] = $row['dept_name'];
		$context['ticket_subject'] = $row['subject'];

		// Load template
		loadTemplate('SimpleDeskMoveDept');
		loadCSSFile('helpdesk.css');

		$context['page_title'] = (isset($txt['shd_ticket_move_dept']) ? $txt['shd_ticket_move_dept'] : 'Move Department') . ' - ' . $row['subject'];
		$context['sub_template'] = 'shd_movedept';

		$context['linktree'][] = array(
			'name' => $txt['shd_helpdesk'],
			'url' => $scripturl . '?action=helpdesk;sa=main',
		);
		$context['linktree'][] = array(
			'name' => $row['subject'],
			'url' => $scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $ticket_id,
		);
		$context['linktree'][] = array(
			'name' => isset($txt['shd_ticket_move_dept']) ? $txt['shd_ticket_move_dept'] : 'Move Department',
		);
	}

	/**
	 * Returns a list of visible departments excluding the current one.
	 *
	 * Queries the departments the user has helpdesk access to and returns
	 * them as an id => name array, filtering out the ticket's current
	 * department.
	 *
	 * @param int $current_dept The department ID to exclude.
	 * @return array Associative array of department ID => department name.
	 */
	private function shd_get_visible_departments($current_dept)
	{
		$db = database();

		$accessible_depts = shd_allowed_to('access_helpdesk', false);

		if (empty($accessible_depts))
			return array();

		// Remove the current department
		$accessible_depts = array_diff($accessible_depts, array($current_dept));

		if (empty($accessible_depts))
			return array();

		$depts = array();

		$request = $db->query('', '
			SELECT id_dept, dept_name
			FROM {db_prefix}helpdesk_depts
			WHERE id_dept IN ({array_int:depts})
			ORDER BY dept_order',
			array(
				'depts' => $accessible_depts,
			)
		);

		while ($row = $db->fetch_assoc($request))
			$depts[(int) $row['id_dept']] = $row['dept_name'];

		$db->free_result($request);

		return $depts;
	}
}
