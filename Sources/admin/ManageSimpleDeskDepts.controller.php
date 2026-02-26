<?php
/**
 * SimpleDesk Department Management Controller
 *
 * Handles administration of helpdesk departments: listing, creating,
 * editing, reordering, and deleting departments. Also manages the
 * assignment of permission roles to departments.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Controller for managing helpdesk departments.
 *
 * Dispatched from ManageSimpleDesk_Controller when sa=departments.
 * Uses the 'do' parameter for internal sub-action routing since 'sa'
 * is already consumed by the parent controller.
 *
 * URL pattern: action=admin;area=helpdesk;sa=departments;do=X
 */
class ManageSimpleDeskDepts_Controller extends Action_Controller
{
	/**
	 * Entry point for department management.
	 *
	 * Routes to the appropriate sub-action based on $_REQUEST['do'].
	 * Loads required language and template files.
	 */
	public function action_index()
	{
		global $context, $txt;

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		require_once(SUBSDIR . '/SimpleDeskPermissions.subs.php');
		shd_init();

		isAllowedTo('admin_forum');

		loadLanguage('SimpleDesk');
		loadLanguage('SimpleDeskAdmin');
		loadLanguage('SimpleDeskPermissions');
		loadTemplate('SimpleDeskAdminDepts');

		$subActions = array(
			'list' => 'action_list',
			'move' => 'action_move',
			'createdept' => 'action_create',
			'editdept' => 'action_edit',
			'savedept' => 'action_save',
		);

		$do = isset($_REQUEST['do']) ? $_REQUEST['do'] : 'list';

		if (isset($subActions[$do]))
			$this->{$subActions[$do]}();
		else
			$this->action_list();
	}

	/**
	 * Lists all departments with their associated category and role information.
	 *
	 * Fetches departments joined with board categories, marks first/last for
	 * move arrows, loads roles assigned to each department, and loads permission
	 * set data for role template icons.
	 */
	public function action_list()
	{
		global $context, $txt, $scripturl;

		$db = database();

		$context['page_title'] = $txt['shd_admin_departments_title'];
		$context['sub_template'] = 'shd_departments_home';

		// Get all departments with their board category names.
		$context['shd_departments'] = array();

		$request = $db->query('', '
			SELECT hd.id_dept, hd.dept_name, hd.description, hd.board_cat,
				hd.before_after, hd.dept_order, hd.dept_theme, hd.autoclose_days,
				c.name AS cat_name
			FROM {db_prefix}helpdesk_depts AS hd
				LEFT JOIN {db_prefix}categories AS c ON (hd.board_cat = c.id_cat)
			ORDER BY hd.dept_order ASC',
			array()
		);

		while ($row = $db->fetch_assoc($request))
		{
			$context['shd_departments'][$row['id_dept']] = array(
				'id_dept' => $row['id_dept'],
				'dept_name' => $row['dept_name'],
				'description' => $row['description'],
				'board_cat' => $row['board_cat'],
				'before_after' => $row['before_after'],
				'dept_order' => $row['dept_order'],
				'dept_theme' => $row['dept_theme'],
				'autoclose_days' => $row['autoclose_days'],
				'cat_name' => !empty($row['cat_name']) ? $row['cat_name'] : $txt['shd_dept_no_cat'],
				'is_first' => false,
				'is_last' => false,
				'roles' => array(),
			);
		}
		$db->free_result($request);

		// Mark first and last for move arrow display.
		if (!empty($context['shd_departments']))
		{
			$dept_ids = array_keys($context['shd_departments']);
			$context['shd_departments'][$dept_ids[0]]['is_first'] = true;
			$context['shd_departments'][$dept_ids[count($dept_ids) - 1]]['is_last'] = true;
		}

		// Get roles attached to each department.
		if (!empty($context['shd_departments']))
		{
			$request = $db->query('', '
				SELECT hddr.id_dept, hddr.id_role, hdr.role_name, hdr.template
				FROM {db_prefix}helpdesk_dept_roles AS hddr
					INNER JOIN {db_prefix}helpdesk_roles AS hdr ON (hddr.id_role = hdr.id_role)
				ORDER BY hdr.role_name ASC',
				array()
			);

			while ($row = $db->fetch_assoc($request))
			{
				if (isset($context['shd_departments'][$row['id_dept']]))
				{
					$context['shd_departments'][$row['id_dept']]['roles'][$row['id_role']] = array(
						'id_role' => $row['id_role'],
						'role_name' => $row['role_name'],
						'template' => $row['template'],
					);
				}
			}
			$db->free_result($request);
		}

		// Load permission sets so the template can access role template info.
		shd_load_all_permission_sets();
	}

	/**
	 * Moves a department up or down in the display order.
	 *
	 * Validates the session (GET), loads all department ordering values,
	 * finds the adjacent department, and swaps their dept_order values.
	 * Redirects back to the department list afterward.
	 */
	public function action_move()
	{
		global $scripturl;

		$db = database();

		checkSession('get');

		$dept_id = isset($_REQUEST['dept']) ? (int) $_REQUEST['dept'] : 0;
		$direction = isset($_REQUEST['direction']) ? $_REQUEST['direction'] : '';

		if (empty($dept_id) || !in_array($direction, array('up', 'down')))
			fatal_lang_error('shd_unknown_dept', false);

		// Load all departments in order.
		$depts = array();
		$request = $db->query('', '
			SELECT id_dept, dept_order
			FROM {db_prefix}helpdesk_depts
			ORDER BY dept_order ASC',
			array()
		);

		while ($row = $db->fetch_assoc($request))
			$depts[] = array(
				'id_dept' => (int) $row['id_dept'],
				'dept_order' => (int) $row['dept_order'],
			);
		$db->free_result($request);

		// Find the current department's position in the ordered list.
		$current_index = null;
		foreach ($depts as $index => $dept)
		{
			if ($dept['id_dept'] == $dept_id)
			{
				$current_index = $index;
				break;
			}
		}

		if ($current_index === null)
			fatal_lang_error('shd_unknown_dept', false);

		// Determine the swap target.
		if ($direction === 'up' && $current_index > 0)
			$swap_index = $current_index - 1;
		elseif ($direction === 'down' && $current_index < count($depts) - 1)
			$swap_index = $current_index + 1;
		else
			redirectexit('action=admin;area=helpdesk;sa=departments');

		// Swap the dept_order values between current and adjacent.
		$db->query('', '
			UPDATE {db_prefix}helpdesk_depts
			SET dept_order = {int:new_order}
			WHERE id_dept = {int:dept}',
			array(
				'new_order' => $depts[$swap_index]['dept_order'],
				'dept' => $depts[$current_index]['id_dept'],
			)
		);

		$db->query('', '
			UPDATE {db_prefix}helpdesk_depts
			SET dept_order = {int:new_order}
			WHERE id_dept = {int:dept}',
			array(
				'new_order' => $depts[$current_index]['dept_order'],
				'dept' => $depts[$swap_index]['id_dept'],
			)
		);

		redirectexit('action=admin;area=helpdesk;sa=departments');
	}

	/**
	 * Creates a new department.
	 *
	 * Two-phase operation:
	 * - Phase 1 (no 'part' parameter): Displays the creation form with category
	 *   selection. Loads the shd_create_dept sub-template.
	 * - Phase 2 ('part' parameter present): Processes the POST data, validates
	 *   the department name and category, inserts the new record, and redirects
	 *   to the edit page for further configuration (role assignment, etc.).
	 */
	public function action_create()
	{
		global $context, $txt, $scripturl;

		$db = database();

		// Phase 2: Process form submission.
		if (isset($_REQUEST['part']))
		{
			checkSubmitOnce('check');
			checkSession();

			// Validate department name.
			$dept_name = isset($_POST['dept_name']) ? Util::htmltrim(Util::htmlspecialchars($_POST['dept_name'])) : '';
			if (empty($dept_name))
				fatal_lang_error('shd_no_dept_name', false);

			// Validate category.
			$dept_cat = isset($_POST['dept_cat']) ? (int) $_POST['dept_cat'] : 0;

			// Sanitize description.
			$dept_desc = isset($_POST['dept_desc']) ? Util::htmlspecialchars($_POST['dept_desc']) : '';

			// Before/after boards in category.
			$before_after = isset($_POST['dept_beforeafter']) ? (int) $_POST['dept_beforeafter'] : 0;
			if (!in_array($before_after, array(0, 1)))
				$before_after = 0;

			// Get the next dept_order value.
			$request = $db->query('', '
				SELECT MAX(dept_order)
				FROM {db_prefix}helpdesk_depts',
				array()
			);
			list($max_order) = $db->fetch_row($request);
			$db->free_result($request);

			$new_order = (int) $max_order + 1;

			// Insert the new department.
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
					$dept_name,
					$dept_desc,
					$dept_cat,
					$before_after,
					$new_order,
					0,
					0,
				),
				array('id_dept')
			);

			// Get the ID of the newly created department.
			$new_dept_id = $db->insert_id('{db_prefix}helpdesk_depts', 'id_dept');

			// Redirect to editdept so the admin can assign roles, etc.
			redirectexit('action=admin;area=helpdesk;sa=departments;do=editdept;dept=' . $new_dept_id);
		}

		// Phase 1: Display the creation form.
		$context['page_title'] = $txt['shd_admin_dept_create_title'];
		$context['sub_template'] = 'shd_create_dept';

		// Load category list for the dropdown.
		$context['shd_cat_list'] = array();
		$context['shd_cat_list'][0] = $txt['shd_dept_cat_none'];

		$request = $db->query('', '
			SELECT id_cat, name
			FROM {db_prefix}categories
			ORDER BY cat_order ASC',
			array()
		);

		while ($row = $db->fetch_assoc($request))
			$context['shd_cat_list'][$row['id_cat']] = $row['name'];
		$db->free_result($request);

		checkSubmitOnce('register');
	}

	/**
	 * Displays the edit form for an existing department.
	 *
	 * Loads the department data, category list, all roles, and which roles
	 * are currently assigned to this department. Also loads permission set
	 * data for role template display. Sets the shd_edit_dept sub-template.
	 */
	public function action_edit()
	{
		global $context, $txt, $scripturl;

		$db = database();

		$dept_id = isset($_REQUEST['dept']) ? (int) $_REQUEST['dept'] : 0;
		if (empty($dept_id))
			fatal_lang_error('shd_unknown_dept', false);

		// Load department data.
		$request = $db->query('', '
			SELECT id_dept, dept_name, description, board_cat, before_after,
				dept_order, dept_theme, autoclose_days
			FROM {db_prefix}helpdesk_depts
			WHERE id_dept = {int:dept}',
			array(
				'dept' => $dept_id,
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			fatal_lang_error('shd_unknown_dept', false);
		}

		$context['shd_dept'] = $db->fetch_assoc($request);
		$db->free_result($request);

		$context['page_title'] = sprintf($txt['shd_admin_dept_edit_title'], $context['shd_dept']['dept_name']);
		$context['sub_template'] = 'shd_edit_dept';

		// Load category list for the dropdown.
		$context['shd_cat_list'] = array();
		$context['shd_cat_list'][0] = $txt['shd_dept_cat_none'];

		$request = $db->query('', '
			SELECT id_cat, name
			FROM {db_prefix}categories
			ORDER BY cat_order ASC',
			array()
		);

		while ($row = $db->fetch_assoc($request))
			$context['shd_cat_list'][$row['id_cat']] = $row['name'];
		$db->free_result($request);

		// Load all roles.
		$context['shd_roles'] = array();
		$request = $db->query('', '
			SELECT id_role, template, role_name
			FROM {db_prefix}helpdesk_roles
			ORDER BY id_role ASC',
			array()
		);

		while ($row = $db->fetch_assoc($request))
		{
			$context['shd_roles'][$row['id_role']] = array(
				'id_role' => $row['id_role'],
				'template' => $row['template'],
				'role_name' => $row['role_name'],
				'in_dept' => false,
			);
		}
		$db->free_result($request);

		// Which roles are currently in this department?
		$request = $db->query('', '
			SELECT id_role
			FROM {db_prefix}helpdesk_dept_roles
			WHERE id_dept = {int:dept}',
			array(
				'dept' => $dept_id,
			)
		);

		while ($row = $db->fetch_assoc($request))
		{
			if (isset($context['shd_roles'][$row['id_role']]))
				$context['shd_roles'][$row['id_role']]['in_dept'] = true;
		}
		$db->free_result($request);

		// Load permission sets for role template icon display.
		shd_load_all_permission_sets();
	}

	/**
	 * Saves changes to an existing department, or deletes it.
	 *
	 * Handles three scenarios:
	 * - DELETE: Verifies at least 2 departments exist and no tickets are in this
	 *   department, then removes the department and its role assignments, and
	 *   reorders remaining departments.
	 * - UPDATE: Validates department name and category, updates the record.
	 * - ROLE ASSIGNMENTS: Compares POST role checkboxes against existing
	 *   assignments, adding and removing as needed.
	 *
	 * Redirects to the department list on completion.
	 */
	public function action_save()
	{
		global $context, $txt, $scripturl;

		$db = database();

		checkSession();

		$dept_id = isset($_POST['dept']) ? (int) $_POST['dept'] : 0;
		if (empty($dept_id))
			fatal_lang_error('shd_unknown_dept', false);

		// Verify the department exists.
		$request = $db->query('', '
			SELECT id_dept, dept_name, dept_order
			FROM {db_prefix}helpdesk_depts
			WHERE id_dept = {int:dept}',
			array(
				'dept' => $dept_id,
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			fatal_lang_error('shd_unknown_dept', false);
		}

		$dept_info = $db->fetch_assoc($request);
		$db->free_result($request);

		// ---- Handle DELETE ----
		if (isset($_POST['delete']))
		{
			// Must have at least 2 departments to allow deletion.
			$request = $db->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}helpdesk_depts',
				array()
			);
			list($dept_count) = $db->fetch_row($request);
			$db->free_result($request);

			if ((int) $dept_count < 2)
				fatal_lang_error('shd_must_have_dept', false);

			// Check no tickets exist in this department.
			$request = $db->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}helpdesk_tickets
				WHERE id_dept = {int:dept}',
				array(
					'dept' => $dept_id,
				)
			);
			list($ticket_count) = $db->fetch_row($request);
			$db->free_result($request);

			if ((int) $ticket_count > 0)
				fatal_lang_error('shd_dept_not_empty', false);

			// Delete the department.
			$db->query('', '
				DELETE FROM {db_prefix}helpdesk_depts
				WHERE id_dept = {int:dept}',
				array(
					'dept' => $dept_id,
				)
			);

			// Delete role assignments for this department.
			$db->query('', '
				DELETE FROM {db_prefix}helpdesk_dept_roles
				WHERE id_dept = {int:dept}',
				array(
					'dept' => $dept_id,
				)
			);

			// Reorder remaining departments to ensure contiguous ordering.
			$request = $db->query('', '
				SELECT id_dept
				FROM {db_prefix}helpdesk_depts
				ORDER BY dept_order ASC',
				array()
			);

			$new_order = 1;
			while ($row = $db->fetch_assoc($request))
			{
				$db->query('', '
					UPDATE {db_prefix}helpdesk_depts
					SET dept_order = {int:new_order}
					WHERE id_dept = {int:dept}',
					array(
						'new_order' => $new_order,
						'dept' => $row['id_dept'],
					)
				);
				$new_order++;
			}
			$db->free_result($request);

			redirectexit('action=admin;area=helpdesk;sa=departments');
		}

		// ---- Handle UPDATE ----

		// Validate department name.
		$dept_name = isset($_POST['dept_name']) ? Util::htmltrim(Util::htmlspecialchars($_POST['dept_name'])) : '';
		if (empty($dept_name))
			fatal_lang_error('shd_no_dept_name', false);

		// Validate category.
		$dept_cat = isset($_POST['dept_cat']) ? (int) $_POST['dept_cat'] : 0;

		// Sanitize description.
		$dept_desc = isset($_POST['dept_desc']) ? Util::htmlspecialchars($_POST['dept_desc']) : '';

		// Before/after boards in category.
		$before_after = isset($_POST['dept_beforeafter']) ? (int) $_POST['dept_beforeafter'] : 0;
		if (!in_array($before_after, array(0, 1)))
			$before_after = 0;

		// Autoclose days.
		$autoclose_days = isset($_POST['autoclose_days']) ? max(0, (int) $_POST['autoclose_days']) : 0;

		// Update the department record.
		$db->query('', '
			UPDATE {db_prefix}helpdesk_depts
			SET dept_name = {string:dept_name},
				description = {string:description},
				board_cat = {int:board_cat},
				before_after = {int:before_after},
				dept_theme = {int:dept_theme},
				autoclose_days = {int:autoclose_days}
			WHERE id_dept = {int:dept}',
			array(
				'dept_name' => $dept_name,
				'description' => $dept_desc,
				'board_cat' => $dept_cat,
				'before_after' => $before_after,
				'dept_theme' => 0,
				'autoclose_days' => $autoclose_days,
				'dept' => $dept_id,
			)
		);

		// ---- Handle ROLE ASSIGNMENTS ----

		// Get existing role assignments for this department.
		$existing_roles = array();
		$request = $db->query('', '
			SELECT id_role
			FROM {db_prefix}helpdesk_dept_roles
			WHERE id_dept = {int:dept}',
			array(
				'dept' => $dept_id,
			)
		);

		while ($row = $db->fetch_assoc($request))
			$existing_roles[] = (int) $row['id_role'];
		$db->free_result($request);

		// Determine which roles were checked in the POST.
		$posted_roles = array();
		if (!empty($_POST['role']))
		{
			foreach ($_POST['role'] as $role_id => $val)
				$posted_roles[] = (int) $role_id;
		}

		// Roles to add: in POST but not in existing.
		$to_add = array_diff($posted_roles, $existing_roles);

		// Roles to remove: in existing but not in POST.
		$to_remove = array_diff($existing_roles, $posted_roles);

		// Add new role assignments.
		foreach ($to_add as $role_id)
		{
			$db->insert('insert',
				'{db_prefix}helpdesk_dept_roles',
				array(
					'id_role' => 'int',
					'id_dept' => 'int',
				),
				array(
					$role_id,
					$dept_id,
				),
				array('id_role', 'id_dept')
			);
		}

		// Remove old role assignments.
		if (!empty($to_remove))
		{
			$db->query('', '
				DELETE FROM {db_prefix}helpdesk_dept_roles
				WHERE id_dept = {int:dept}
					AND id_role IN ({array_int:roles})',
				array(
					'dept' => $dept_id,
					'roles' => $to_remove,
				)
			);
		}

		redirectexit('action=admin;area=helpdesk;sa=departments');
	}
}
