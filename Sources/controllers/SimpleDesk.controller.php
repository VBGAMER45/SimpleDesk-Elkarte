<?php
/**
 * SimpleDesk Helpdesk - Main Controller
 *
 * Entry point for ?action=helpdesk. Routes to sub-actions
 * (ticket listing, department view, etc.). Implements block-based
 * ticket listing system for staff and user views.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

class SimpleDesk_Controller extends Action_Controller
{
	/**
	 * Entry point for ?action=helpdesk.
	 * Routes to sub-actions based on the 'sa' parameter.
	 */
	public function action_index()
	{
		global $context, $modSettings, $user_info;

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		if (empty($modSettings['helpdesk_active']))
			fatal_lang_error('shd_inactive', false);

		if ($user_info['is_guest'])
			fatal_lang_error('cannot_access_helpdesk', false);

		shd_is_allowed_to('access_helpdesk', 0);

		loadLanguage('SimpleDesk');
		loadTemplate('SimpleDesk');
		loadCSSFile('helpdesk.css');

		// Determine which department we're in
		if (isset($_REQUEST['dept']))
			$context['shd_department'] = (int) $_REQUEST['dept'];
		else
			$context['shd_department'] = 0;

		$sa = isset($_REQUEST['sa']) ? $_REQUEST['sa'] : 'main';

		$subActions = array(
			'main' => 'action_main',
			'tickets' => 'action_tickets',
			'viewticket' => 'action_viewticket',
			'closedtickets' => 'action_closedtickets',
			'recyclebin' => 'action_recyclebin',
			'viewblock' => 'action_viewblock',
			'newticket' => 'action_newticket',
			'editticket' => 'action_editticket',
			'saveticket' => 'action_saveticket',
			'reply' => 'action_reply',
			'savereply' => 'action_savereply',
			'editreply' => 'action_editreply',
			'assign' => 'action_assign',
			'assign2' => 'action_assign2',
			'resolve' => 'action_resolve',
			'resolve2' => 'action_resolve2',
			'delete' => 'action_delete',
			'restore' => 'action_restore',
			'movedept' => 'action_movedept',
			'search' => 'action_search',
			'search2' => 'action_search2',
			'privacychange' => 'action_privacychange',
			'urgencychange' => 'action_urgencychange',
			'relation' => 'action_relation',
			'markunread' => 'action_markunread',
			'notify' => 'action_notify',
			'ajax' => 'action_ajax',
			'dlattach' => 'action_dlattach',
			'deleteattach' => 'action_deleteattach',
			'deleteticket' => 'action_deleteticket',
			'deletereply' => 'action_deletereply',
			'restoreticket' => 'action_restoreticket',
			'restorereply' => 'action_restorereply',
			'permadelete' => 'action_permadelete',
		);

		// Dispatch: some sub-actions go to other controllers
		$controller_dispatch = array(
			'viewticket' => array('SimpleDeskDisplay.controller.php', 'SimpleDeskDisplay_Controller', 'action_ticket'),
			'newticket' => array('SimpleDeskPost.controller.php', 'SimpleDeskPost_Controller', 'action_newticket'),
			'editticket' => array('SimpleDeskPost.controller.php', 'SimpleDeskPost_Controller', 'action_editticket'),
			'saveticket' => array('SimpleDeskPost.controller.php', 'SimpleDeskPost_Controller', 'action_saveticket'),
			'reply' => array('SimpleDeskPost.controller.php', 'SimpleDeskPost_Controller', 'action_reply'),
			'savereply' => array('SimpleDeskPost.controller.php', 'SimpleDeskPost_Controller', 'action_savereply'),
			'editreply' => array('SimpleDeskPost.controller.php', 'SimpleDeskPost_Controller', 'action_editreply'),
			'assign' => array('SimpleDeskAssign.controller.php', 'SimpleDeskAssign_Controller', 'action_assign'),
			'assign2' => array('SimpleDeskAssign.controller.php', 'SimpleDeskAssign_Controller', 'action_assign2'),
			'resolve' => array('SimpleDeskMisc.controller.php', 'SimpleDeskMisc_Controller', 'action_resolve'),
			'resolve2' => array('SimpleDeskMisc.controller.php', 'SimpleDeskMisc_Controller', 'action_resolve2'),
			'privacychange' => array('SimpleDeskMisc.controller.php', 'SimpleDeskMisc_Controller', 'action_privacychange'),
			'urgencychange' => array('SimpleDeskMisc.controller.php', 'SimpleDeskMisc_Controller', 'action_urgencychange'),
			'relation' => array('SimpleDeskMisc.controller.php', 'SimpleDeskMisc_Controller', 'action_relation'),
			'markunread' => array('SimpleDeskMisc.controller.php', 'SimpleDeskMisc_Controller', 'action_markunread'),
			'delete' => array('SimpleDeskDelete.controller.php', 'SimpleDeskDelete_Controller', 'action_delete'),
			'restore' => array('SimpleDeskDelete.controller.php', 'SimpleDeskDelete_Controller', 'action_restore'),
			'deleteticket' => array('SimpleDeskDelete.controller.php', 'SimpleDeskDelete_Controller', 'action_deleteticket'),
			'deletereply' => array('SimpleDeskDelete.controller.php', 'SimpleDeskDelete_Controller', 'action_deletereply'),
			'restoreticket' => array('SimpleDeskDelete.controller.php', 'SimpleDeskDelete_Controller', 'action_restoreticket'),
			'restorereply' => array('SimpleDeskDelete.controller.php', 'SimpleDeskDelete_Controller', 'action_restorereply'),
			'permadelete' => array('SimpleDeskDelete.controller.php', 'SimpleDeskDelete_Controller', 'action_permadelete'),
			'deleteattach' => array('SimpleDeskDelete.controller.php', 'SimpleDeskDelete_Controller', 'action_deleteattach'),
			'movedept' => array('SimpleDeskMoveDept.controller.php', 'SimpleDeskMoveDept_Controller', 'action_movedept'),
			'search' => array('SimpleDeskSearch.controller.php', 'SimpleDeskSearch_Controller', 'action_search'),
			'search2' => array('SimpleDeskSearch.controller.php', 'SimpleDeskSearch_Controller', 'action_search2'),
			'dlattach' => array('SimpleDeskMisc.controller.php', 'SimpleDeskMisc_Controller', 'action_dlattach'),
			'notify' => array('SimpleDeskNotify.controller.php', 'SimpleDeskNotify_Controller', 'action_notify'),
			'ajax' => array('SimpleDeskAjax.controller.php', 'SimpleDeskAjax_Controller', 'action_index'),
		);

		if (isset($controller_dispatch[$sa]))
		{
			list($file, $class, $method) = $controller_dispatch[$sa];
			require_once(CONTROLLERDIR . '/' . $file);
			$controller = new $class();
			$controller->$method();
		}
		elseif (isset($subActions[$sa]))
			$this->{$subActions[$sa]}();
		else
			$this->action_main();
	}

	/**
	 * Main helpdesk page - shows department list or redirects to ticket list.
	 *
	 * If the user has access to multiple departments, shows a department list
	 * with ticket counts. If only one department, redirects to action_tickets().
	 */
	public function action_main()
	{
		global $context, $scripturl, $txt;

		$db = database();

		// Get list of departments the user can access
		$depts = shd_allowed_to('access_helpdesk', false);

		if (empty($depts))
			fatal_lang_error('cannot_access_helpdesk', false);

		// If only one department, go straight to tickets
		if (count($depts) == 1)
		{
			$context['shd_department'] = $depts[0];
			$context['shd_multi_dept'] = false;
			return $this->action_tickets();
		}

		// Multiple departments: show department list
		$context['shd_multi_dept'] = true;
		$context['shd_departments'] = array();

		$request = $db->query('', '
			SELECT id_dept, dept_name, description
			FROM {db_prefix}helpdesk_depts
			WHERE id_dept IN ({array_int:depts})
			ORDER BY dept_order',
			array(
				'depts' => $depts,
			)
		);

		while ($row = $db->fetch_assoc($request))
		{
			$context['shd_departments'][$row['id_dept']] = array(
				'id' => $row['id_dept'],
				'name' => $row['dept_name'],
				'description' => $row['description'],
				'link' => $scripturl . '?action=helpdesk;sa=tickets;dept=' . $row['id_dept'],
				'ticket_count' => 0,
			);
		}
		$db->free_result($request);

		// Get ticket counts per department
		foreach (array_keys($context['shd_departments']) as $dept_id)
		{
			$old_dept = $context['shd_department'];
			$context['shd_department'] = $dept_id;

			// Reset ticket count cache for this department context
			unset($context['ticket_count']);
			$context['shd_departments'][$dept_id]['ticket_count'] = shd_count_helpdesk_tickets('open', false);

			$context['shd_department'] = $old_dept;
		}

		// Reset ticket count cache after iteration
		unset($context['ticket_count']);

		$context['page_title'] = $txt['shd_helpdesk'];
		$context['sub_template'] = 'shd_department_list';
		$context['linktree'][] = array(
			'name' => $txt['shd_helpdesk'],
			'url' => $scripturl . '?action=helpdesk;sa=main',
		);
	}

	/**
	 * Displays the ticket listing for a department using block-based views.
	 *
	 * Staff see blocks for: assigned, new, pending staff, pending user.
	 * Users see blocks for: waiting on staff, waiting on user.
	 *
	 * Each block contains tickets grouped by status with pagination and sorting.
	 */
	public function action_tickets()
	{
		global $context, $scripturl, $txt, $user_info;

		$is_staff = shd_allowed_to('shd_staff', $context['shd_department']);
		$context['shd_is_staff'] = $is_staff;

		// Get ticket counts (populates $context['ticket_count'])
		shd_count_helpdesk_tickets('', $is_staff);

		$dept_filter = !empty($context['shd_department']) ? 'AND hdt.id_dept = {int:dept}' : '';

		if ($is_staff)
		{
			// Staff view: assigned to me, new unassigned, pending staff, pending user
			$context['shd_home_view'] = 'staff';

			$context['ticket_blocks'] = array(
				'assigned' => array(
					'title' => 'shd_tickets_assigned',
					'where' => 'hdt.id_member_assigned = ' . (int) $context['user']['id']
						. ' AND hdt.status IN ('
						. TICKET_STATUS_NEW . ','
						. TICKET_STATUS_PENDING_STAFF . ','
						. TICKET_STATUS_PENDING_USER . ','
						. TICKET_STATUS_WITH_SUPERVISOR . ','
						. TICKET_STATUS_ESCALATED . ','
						. TICKET_STATUS_HOLD . ')'
						. ($dept_filter ? ' ' . str_replace('{int:dept}', (int) $context['shd_department'], $dept_filter) : ''),
					'display' => true,
					'count' => shd_count_helpdesk_tickets('assigned', true),
					'collapsed' => false,
					'tickets' => array(),
				),
				'new' => array(
					'title' => 'shd_tickets_new',
					'where' => 'hdt.status = ' . TICKET_STATUS_NEW
						. ' AND hdt.id_member_assigned != ' . (int) $context['user']['id']
						. ($dept_filter ? ' ' . str_replace('{int:dept}', (int) $context['shd_department'], $dept_filter) : ''),
					'display' => true,
					'count' => shd_count_helpdesk_tickets('new', true),
					'collapsed' => false,
					'tickets' => array(),
				),
				'staff' => array(
					'title' => 'shd_tickets_pending_staff',
					'where' => 'hdt.status IN ('
						. TICKET_STATUS_PENDING_STAFF . ','
						. TICKET_STATUS_WITH_SUPERVISOR . ','
						. TICKET_STATUS_ESCALATED . ','
						. TICKET_STATUS_HOLD . ')'
						. ' AND hdt.id_member_assigned != ' . (int) $context['user']['id']
						. ($dept_filter ? ' ' . str_replace('{int:dept}', (int) $context['shd_department'], $dept_filter) : ''),
					'display' => true,
					'count' => shd_count_helpdesk_tickets('staff', true),
					'collapsed' => false,
					'tickets' => array(),
				),
				'user' => array(
					'title' => 'shd_tickets_pending_user',
					'where' => 'hdt.status = ' . TICKET_STATUS_PENDING_USER
						. ' AND hdt.id_member_assigned != ' . (int) $context['user']['id']
						. ($dept_filter ? ' ' . str_replace('{int:dept}', (int) $context['shd_department'], $dept_filter) : ''),
					'display' => true,
					'count' => shd_count_helpdesk_tickets('with_user', true),
					'collapsed' => false,
					'tickets' => array(),
				),
			);

			$context['ticket_block_order'] = array('assigned', 'new', 'staff', 'user');
		}
		else
		{
			// User view: waiting for staff, waiting for your comment
			$context['shd_home_view'] = 'user';

			$context['ticket_blocks'] = array(
				'staff' => array(
					'title' => 'shd_tickets_waiting_staff',
					'where' => 'hdt.status IN ('
						. TICKET_STATUS_NEW . ','
						. TICKET_STATUS_PENDING_STAFF . ','
						. TICKET_STATUS_WITH_SUPERVISOR . ','
						. TICKET_STATUS_ESCALATED . ','
						. TICKET_STATUS_HOLD . ')'
						. ($dept_filter ? ' ' . str_replace('{int:dept}', (int) $context['shd_department'], $dept_filter) : ''),
					'display' => true,
					'count' => shd_count_helpdesk_tickets('staff', false),
					'collapsed' => false,
					'tickets' => array(),
				),
				'user' => array(
					'title' => 'shd_tickets_waiting_user',
					'where' => 'hdt.status = ' . TICKET_STATUS_PENDING_USER
						. ($dept_filter ? ' ' . str_replace('{int:dept}', (int) $context['shd_department'], $dept_filter) : ''),
					'display' => true,
					'count' => shd_count_helpdesk_tickets('with_user', false),
					'collapsed' => false,
					'tickets' => array(),
				),
			);

			$context['ticket_block_order'] = array('staff', 'user');
		}

		// Permission flags
		$context['can_new_ticket'] = shd_allowed_to('shd_new_ticket', $context['shd_department']);
		$context['can_proxy_ticket'] = shd_allowed_to('shd_post_proxy', $context['shd_department']);
		$context['shd_can_recyclebin'] = shd_allowed_to('shd_access_recyclebin', $context['shd_department']);
		$context['shd_ticket_sa'] = 'tickets';

		// Load tickets into blocks
		$this->helpdesk_listing();

		// Page setup
		$context['page_title'] = $txt['shd_helpdesk'] . ' - ' . $txt['shd_tickets_open'];
		$context['sub_template'] = 'shd_ticket_home';

		$context['linktree'][] = array(
			'name' => $txt['shd_helpdesk'],
			'url' => $scripturl . '?action=helpdesk;sa=main',
		);

		if (!empty($context['shd_department']))
		{
			$context['linktree'][] = array(
				'name' => $txt['shd_tickets_open'],
				'url' => $scripturl . '?action=helpdesk;sa=tickets;dept=' . $context['shd_department'],
			);
		}
	}

	/**
	 * Displays closed tickets as a single block.
	 */
	public function action_closedtickets()
	{
		global $context, $scripturl, $txt;

		shd_is_allowed_to(array('shd_view_closed_own', 'shd_view_closed_any'), $context['shd_department']);

		$is_staff = shd_allowed_to('shd_staff', $context['shd_department']);
		$context['shd_is_staff'] = $is_staff;

		// Get ticket counts
		shd_count_helpdesk_tickets('', $is_staff);

		$dept_filter = !empty($context['shd_department']) ? 'AND hdt.id_dept = {int:dept}' : '';

		$context['ticket_blocks'] = array(
			'closed' => array(
				'title' => 'shd_tickets_closed',
				'where' => 'hdt.status = ' . TICKET_STATUS_CLOSED
					. ($dept_filter ? ' ' . str_replace('{int:dept}', (int) $context['shd_department'], $dept_filter) : ''),
				'display' => true,
				'count' => shd_count_helpdesk_tickets('closed', $is_staff),
				'collapsed' => false,
				'tickets' => array(),
			),
		);
		$context['ticket_block_order'] = array('closed');
		$context['shd_home_view'] = $is_staff ? 'staff' : 'user';
		$context['can_new_ticket'] = shd_allowed_to('shd_new_ticket', $context['shd_department']);
		$context['can_proxy_ticket'] = shd_allowed_to('shd_post_proxy', $context['shd_department']);
		$context['shd_can_recyclebin'] = shd_allowed_to('shd_access_recyclebin', $context['shd_department']);
		$context['shd_ticket_sa'] = 'closedtickets';

		// Load tickets into block
		$this->helpdesk_listing();

		$context['page_title'] = $txt['shd_helpdesk'] . ' - ' . $txt['shd_tickets_closed'];
		$context['sub_template'] = 'shd_closedtickets';

		$context['linktree'][] = array(
			'name' => $txt['shd_helpdesk'],
			'url' => $scripturl . '?action=helpdesk;sa=main',
		);
		$context['linktree'][] = array(
			'name' => $txt['shd_tickets_closed'],
			'url' => $scripturl . '?action=helpdesk;sa=closedtickets' . (!empty($context['shd_department']) ? ';dept=' . $context['shd_department'] : ''),
		);
	}

	/**
	 * Displays the recycle bin with two blocks: deleted tickets and
	 * active tickets that have deleted replies.
	 */
	public function action_recyclebin()
	{
		global $context, $scripturl, $txt;

		shd_is_allowed_to('shd_access_recyclebin', $context['shd_department']);

		$is_staff = shd_allowed_to('shd_staff', $context['shd_department']);
		$context['shd_is_staff'] = $is_staff;

		// Get ticket counts
		shd_count_helpdesk_tickets('', $is_staff);

		$dept_filter = !empty($context['shd_department']) ? 'AND hdt.id_dept = {int:dept}' : '';

		$context['ticket_blocks'] = array(
			'recycle' => array(
				'title' => 'shd_tickets_deleted',
				'where' => 'hdt.status = ' . TICKET_STATUS_DELETED
					. ($dept_filter ? ' ' . str_replace('{int:dept}', (int) $context['shd_department'], $dept_filter) : ''),
				'display' => true,
				'count' => shd_count_helpdesk_tickets('recycled', $is_staff),
				'collapsed' => false,
				'tickets' => array(),
			),
			'withdeleted' => array(
				'title' => 'shd_tickets_with_deleted_replies',
				'where' => 'hdt.withdeleted > 0 AND hdt.status != ' . TICKET_STATUS_DELETED
					. ($dept_filter ? ' ' . str_replace('{int:dept}', (int) $context['shd_department'], $dept_filter) : ''),
				'display' => true,
				'count' => shd_count_helpdesk_tickets('withdeleted', $is_staff),
				'collapsed' => false,
				'tickets' => array(),
			),
		);
		$context['ticket_block_order'] = array('recycle', 'withdeleted');
		$context['shd_home_view'] = 'staff';
		$context['can_new_ticket'] = shd_allowed_to('shd_new_ticket', $context['shd_department']);
		$context['can_proxy_ticket'] = shd_allowed_to('shd_post_proxy', $context['shd_department']);
		$context['shd_can_recyclebin'] = true;
		$context['shd_ticket_sa'] = 'recyclebin';

		// Load tickets into blocks
		$this->helpdesk_listing();

		$context['page_title'] = $txt['shd_helpdesk'] . ' - ' . $txt['shd_tickets_recycled'];
		$context['sub_template'] = 'shd_recyclebin';

		$context['linktree'][] = array(
			'name' => $txt['shd_helpdesk'],
			'url' => $scripturl . '?action=helpdesk;sa=main',
		);
		$context['linktree'][] = array(
			'name' => $txt['shd_tickets_recycled'],
			'url' => $scripturl . '?action=helpdesk;sa=recyclebin' . (!empty($context['shd_department']) ? ';dept=' . $context['shd_department'] : ''),
		);
	}

	/**
	 * View a single block expanded on its own page.
	 *
	 * Gets the block name from $_REQUEST['block'], sets up the same blocks
	 * as action_tickets() but only includes the requested block, and marks
	 * it for unlimited display.
	 */
	public function action_viewblock()
	{
		global $context, $scripturl, $txt, $user_info;

		$block_name = isset($_REQUEST['block']) ? $_REQUEST['block'] : '';

		$is_staff = shd_allowed_to('shd_staff', $context['shd_department']);
		$context['shd_is_staff'] = $is_staff;

		// Get ticket counts
		shd_count_helpdesk_tickets('', $is_staff);

		$dept_filter = !empty($context['shd_department']) ? 'AND hdt.id_dept = {int:dept}' : '';

		// Build all possible blocks (same as action_tickets)
		if ($is_staff)
		{
			$context['shd_home_view'] = 'staff';

			$all_blocks = array(
				'assigned' => array(
					'title' => 'shd_tickets_assigned',
					'where' => 'hdt.id_member_assigned = ' . (int) $context['user']['id']
						. ' AND hdt.status IN ('
						. TICKET_STATUS_NEW . ','
						. TICKET_STATUS_PENDING_STAFF . ','
						. TICKET_STATUS_PENDING_USER . ','
						. TICKET_STATUS_WITH_SUPERVISOR . ','
						. TICKET_STATUS_ESCALATED . ','
						. TICKET_STATUS_HOLD . ')'
						. ($dept_filter ? ' ' . str_replace('{int:dept}', (int) $context['shd_department'], $dept_filter) : ''),
					'display' => true,
					'count' => shd_count_helpdesk_tickets('assigned', true),
					'collapsed' => false,
					'tickets' => array(),
				),
				'new' => array(
					'title' => 'shd_tickets_new',
					'where' => 'hdt.status = ' . TICKET_STATUS_NEW
						. ' AND hdt.id_member_assigned != ' . (int) $context['user']['id']
						. ($dept_filter ? ' ' . str_replace('{int:dept}', (int) $context['shd_department'], $dept_filter) : ''),
					'display' => true,
					'count' => shd_count_helpdesk_tickets('new', true),
					'collapsed' => false,
					'tickets' => array(),
				),
				'staff' => array(
					'title' => 'shd_tickets_pending_staff',
					'where' => 'hdt.status IN ('
						. TICKET_STATUS_PENDING_STAFF . ','
						. TICKET_STATUS_WITH_SUPERVISOR . ','
						. TICKET_STATUS_ESCALATED . ','
						. TICKET_STATUS_HOLD . ')'
						. ' AND hdt.id_member_assigned != ' . (int) $context['user']['id']
						. ($dept_filter ? ' ' . str_replace('{int:dept}', (int) $context['shd_department'], $dept_filter) : ''),
					'display' => true,
					'count' => shd_count_helpdesk_tickets('staff', true),
					'collapsed' => false,
					'tickets' => array(),
				),
				'user' => array(
					'title' => 'shd_tickets_pending_user',
					'where' => 'hdt.status = ' . TICKET_STATUS_PENDING_USER
						. ' AND hdt.id_member_assigned != ' . (int) $context['user']['id']
						. ($dept_filter ? ' ' . str_replace('{int:dept}', (int) $context['shd_department'], $dept_filter) : ''),
					'display' => true,
					'count' => shd_count_helpdesk_tickets('with_user', true),
					'collapsed' => false,
					'tickets' => array(),
				),
			);
		}
		else
		{
			$context['shd_home_view'] = 'user';

			$all_blocks = array(
				'staff' => array(
					'title' => 'shd_tickets_waiting_staff',
					'where' => 'hdt.status IN ('
						. TICKET_STATUS_NEW . ','
						. TICKET_STATUS_PENDING_STAFF . ','
						. TICKET_STATUS_WITH_SUPERVISOR . ','
						. TICKET_STATUS_ESCALATED . ','
						. TICKET_STATUS_HOLD . ')'
						. ($dept_filter ? ' ' . str_replace('{int:dept}', (int) $context['shd_department'], $dept_filter) : ''),
					'display' => true,
					'count' => shd_count_helpdesk_tickets('staff', false),
					'collapsed' => false,
					'tickets' => array(),
				),
				'user' => array(
					'title' => 'shd_tickets_waiting_user',
					'where' => 'hdt.status = ' . TICKET_STATUS_PENDING_USER
						. ($dept_filter ? ' ' . str_replace('{int:dept}', (int) $context['shd_department'], $dept_filter) : ''),
					'display' => true,
					'count' => shd_count_helpdesk_tickets('with_user', false),
					'collapsed' => false,
					'tickets' => array(),
				),
			);
		}

		// Only include the requested block
		if (!isset($all_blocks[$block_name]))
			fatal_lang_error('shd_no_ticket', false);

		$context['ticket_blocks'] = array(
			$block_name => $all_blocks[$block_name],
		);
		$context['ticket_blocks'][$block_name]['viewing_as_block'] = true;
		$context['ticket_block_order'] = array($block_name);

		// Permission flags
		$context['can_new_ticket'] = shd_allowed_to('shd_new_ticket', $context['shd_department']);
		$context['can_proxy_ticket'] = shd_allowed_to('shd_post_proxy', $context['shd_department']);
		$context['shd_can_recyclebin'] = shd_allowed_to('shd_access_recyclebin', $context['shd_department']);
		$context['shd_ticket_sa'] = 'viewblock';

		// Load tickets with unlimited display
		$this->helpdesk_listing();

		$block_title = isset($txt[$all_blocks[$block_name]['title']]) ? $txt[$all_blocks[$block_name]['title']] : $block_name;
		$context['page_title'] = $txt['shd_helpdesk'] . ' - ' . $block_title;
		$context['sub_template'] = 'shd_ticket_home';

		$context['linktree'][] = array(
			'name' => $txt['shd_helpdesk'],
			'url' => $scripturl . '?action=helpdesk;sa=main',
		);
		$context['linktree'][] = array(
			'name' => $block_title,
			'url' => $scripturl . '?action=helpdesk;sa=viewblock;block=' . $block_name . (!empty($context['shd_department']) ? ';dept=' . $context['shd_department'] : ''),
		);
	}

	/**
	 * Core listing logic for all block-based ticket views.
	 *
	 * Processes each block in $context['ticket_blocks'], handling pagination,
	 * sorting, querying tickets, and processing results into the block's
	 * tickets array.
	 *
	 * This is the workhorse method called by action_tickets(), action_closedtickets(),
	 * action_recyclebin(), and action_viewblock().
	 */
	private function helpdesk_listing()
	{
		global $context, $scripturl, $txt, $modSettings;

		$db = database();

		// Sort field mapping: request field name => SQL column
		$sort_columns = array(
			'ticketid' => 'hdt.id_ticket',
			'ticketname' => 'hdt.subject',
			'replies' => 'hdt.num_replies',
			'urgency' => 'hdt.urgency',
			'updated' => 'hdt.last_updated',
			'assigned' => 'assigned_name',
			'status' => 'hdt.status',
			'starter' => 'starter_name',
		);

		$per_page = 10;

		foreach ($context['ticket_block_order'] as $block)
		{
			if (empty($context['ticket_blocks'][$block]['display']))
				continue;

			$is_viewing_block = !empty($context['ticket_blocks'][$block]['viewing_as_block']);

			// Pagination: start position from st_{block}
			$start = isset($_REQUEST['st_' . $block]) ? max(0, (int) $_REQUEST['st_' . $block]) : 0;
			$block_count = (int) $context['ticket_blocks'][$block]['count'];

			// If viewing as expanded block, show all
			$block_per_page = $is_viewing_block ? max($block_count, 1) : $per_page;

			// Sorting: so_{block} = fieldname_direction (e.g. "updated_desc")
			$sort_field = 'updated';
			$sort_dir = 'desc';

			if (isset($_REQUEST['so_' . $block]))
			{
				$sort_raw = $_REQUEST['so_' . $block];

				// Parse: everything before the last underscore is field, after is direction
				$last_underscore = strrpos($sort_raw, '_');
				if ($last_underscore !== false)
				{
					$requested_field = substr($sort_raw, 0, $last_underscore);
					$requested_dir = substr($sort_raw, $last_underscore + 1);

					if (isset($sort_columns[$requested_field]))
						$sort_field = $requested_field;

					if (in_array($requested_dir, array('asc', 'desc')))
						$sort_dir = $requested_dir;
				}
			}

			$order_clause = $sort_columns[$sort_field] . ' ' . strtoupper($sort_dir);

			// Build the dept parameter for the query
			$dept_url = !empty($context['shd_department']) ? ';dept=' . $context['shd_department'] : '';

			// Build page index
			$base_url = $scripturl . '?action=helpdesk;sa=' . (isset($_REQUEST['sa']) ? $_REQUEST['sa'] : 'tickets') . $dept_url;

			// For viewblock, include the block parameter
			if (isset($_REQUEST['sa']) && $_REQUEST['sa'] === 'viewblock' && isset($_REQUEST['block']))
				$base_url .= ';block=' . $_REQUEST['block'];

			$context['ticket_blocks'][$block]['page_index'] = constructPageIndex(
				$base_url . ';st_' . $block . '=%1$d',
				$start,
				$block_count,
				$block_per_page
			);

			// Fix start if beyond count
			if ($start >= $block_count && $block_count > 0)
				$start = max(0, $block_count - $block_per_page);

			// Store current sort for template use
			$context['ticket_blocks'][$block]['sort'] = array(
				'field' => $sort_field,
				'direction' => $sort_dir,
				'link_base' => $base_url . ';so_' . $block . '=',
			);

			// Query tickets for this block
			$request = shd_db_query('', '
				SELECT hdt.id_ticket, hdt.id_dept, hdt.subject, hdt.urgency, hdt.status, hdt.private,
					hdt.num_replies, hdt.id_member_started, hdt.id_member_assigned, hdt.id_member_updated,
					hdt.last_updated,
					COALESCE(ms.real_name, {string:unknown}) AS starter_name,
					COALESCE(ma.real_name, {string:unassigned}) AS assigned_name,
					COALESCE(mu.real_name, {string:unknown}) AS updated_name,
					hdd.dept_name,
					hdlr.id_msg AS log_read
				FROM {db_prefix}helpdesk_tickets AS hdt
					LEFT JOIN {db_prefix}members AS ms ON (ms.id_member = hdt.id_member_started)
					LEFT JOIN {db_prefix}members AS ma ON (ma.id_member = hdt.id_member_assigned)
					LEFT JOIN {db_prefix}members AS mu ON (mu.id_member = hdt.id_member_updated)
					LEFT JOIN {db_prefix}helpdesk_depts AS hdd ON (hdd.id_dept = hdt.id_dept)
					LEFT JOIN {db_prefix}helpdesk_log_read AS hdlr ON (hdlr.id_ticket = hdt.id_ticket AND hdlr.id_member = {int:user_id})
				WHERE {query_see_ticket}
					AND ' . $context['ticket_blocks'][$block]['where'] . '
				ORDER BY ' . $order_clause . '
				LIMIT {int:start}, {int:per_page}',
				array(
					'user_id' => $context['user']['id'],
					'dept' => $context['shd_department'],
					'start' => $start,
					'per_page' => $block_per_page,
					'unknown' => isset($txt['shd_unknown']) ? $txt['shd_unknown'] : 'Unknown',
					'unassigned' => isset($txt['shd_unassigned']) ? $txt['shd_unassigned'] : 'Unassigned',
				)
			);

			while ($row = $db->fetch_assoc($request))
			{
				$ticket_dept = (int) $row['id_dept'];

				$context['ticket_blocks'][$block]['tickets'][$row['id_ticket']] = array(
					'id' => (int) $row['id_ticket'],
					'display_id' => str_pad($row['id_ticket'], empty($modSettings['shd_zerofill']) ? 5 : $modSettings['shd_zerofill'], '0', STR_PAD_LEFT),
					'dept_name' => $row['dept_name'],
					'dept_id' => $ticket_dept,
					'subject' => $row['subject'],
					'urgency' => (int) $row['urgency'],
					'status' => (int) $row['status'],
					'private' => !empty($row['private']),
					'num_replies' => (int) $row['num_replies'],
					'id_member_started' => (int) $row['id_member_started'],
					'id_member_assigned' => (int) $row['id_member_assigned'],
					'id_member_updated' => (int) $row['id_member_updated'],
					'starter_name' => $row['starter_name'],
					'assigned_name' => $row['assigned_name'],
					'updated_name' => $row['updated_name'],
					'last_updated' => standardTime($row['last_updated']),
					'last_updated_timestamp' => (int) $row['last_updated'],
					'link' => $scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $row['id_ticket'],
					'is_unread' => ($row['log_read'] === null),
					// Action flags based on permissions
					'can_resolve' => shd_allowed_to(array('shd_resolve_ticket_own', 'shd_resolve_ticket_any'), $ticket_dept),
					'can_unresolve' => shd_allowed_to(array('shd_unresolve_ticket_own', 'shd_unresolve_ticket_any'), $ticket_dept),
					'can_assign' => shd_allowed_to(array('shd_assign_ticket_own', 'shd_assign_ticket_any'), $ticket_dept),
					'can_delete' => shd_allowed_to(array('shd_delete_ticket_own', 'shd_delete_ticket_any'), $ticket_dept),
					'can_restore' => shd_allowed_to(array('shd_restore_ticket_own', 'shd_restore_ticket_any'), $ticket_dept),
					'can_edit' => shd_allowed_to(array('shd_edit_ticket_own', 'shd_edit_ticket_any'), $ticket_dept),
					'can_move_dept' => shd_allowed_to(array('shd_move_dept_own', 'shd_move_dept_any'), $ticket_dept),
				);
			}
			$db->free_result($request);
		}
	}
}
