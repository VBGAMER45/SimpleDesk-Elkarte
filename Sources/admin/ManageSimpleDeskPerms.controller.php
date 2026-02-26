<?php
/**
 * SimpleDesk Permissions Management Controller
 *
 * Handles administration of helpdesk permissions and roles.
 * Manages creating, editing, copying, and deleting roles, along with
 * their permission sets, membergroup associations, and department assignments.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Controller for managing helpdesk permissions.
 */
class ManageSimpleDeskPerms_Controller extends Action_Controller
{
	/**
	 * Permissions management entry point.
	 *
	 * Loads permission sets, language, and template, then routes
	 * to the appropriate sub-action based on the 'do' parameter.
	 * Uses 'do' because 'sa' is already consumed by the parent controller.
	 */
	public function action_index()
	{
		global $context, $txt, $scripturl;

		require_once(SUBSDIR . '/SimpleDeskPermissions.subs.php');
		shd_load_all_permission_sets();

		loadLanguage('SimpleDeskPermissions');
		loadTemplate('SimpleDeskAdminPerms');

		// Add linktree entry for permissions
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=admin;area=helpdesk;sa=permissions',
			'name' => $txt['shd_admin_permissions'],
		);

		// Route based on 'do' parameter since 'sa' is used by the parent controller
		$do = isset($_REQUEST['do']) ? $_REQUEST['do'] : 'list';

		$subActions = array(
			'list' => 'action_list',
			'createrole' => 'action_create',
			'editrole' => 'action_edit',
			'saverole' => 'action_save',
			'copyrole' => 'action_copy',
		);

		if (isset($subActions[$do]))
			$this->{$subActions[$do]}();
		else
			$this->action_list();
	}

	/**
	 * List all roles and role templates.
	 *
	 * Shows the permissions home page with role templates and user-defined roles,
	 * including their department associations.
	 */
	public function action_list()
	{
		global $context, $txt, $scripturl;

		$db = database();

		$context['page_title'] = $txt['shd_admin_permissions'];
		$context['sub_template'] = 'shd_permissions_home';

		// Load all roles with their permissions
		$this->shd_load_role();

		// Get department associations for each role
		$context['role_depts'] = array();

		if (!empty($context['shd_permissions']['user_defined_roles']))
		{
			$role_ids = array_keys($context['shd_permissions']['user_defined_roles']);

			$request = $db->query('', '
				SELECT hddr.id_role, hdd.id_dept, hdd.dept_name
				FROM {db_prefix}helpdesk_dept_roles AS hddr
					INNER JOIN {db_prefix}helpdesk_depts AS hdd ON (hddr.id_dept = hdd.id_dept)
				WHERE hddr.id_role IN ({array_int:roles})
				ORDER BY hdd.dept_name ASC',
				array(
					'roles' => $role_ids,
				)
			);
			while ($row = $db->fetch_assoc($request))
			{
				$context['role_depts'][$row['id_role']][$row['id_dept']] = $row['dept_name'];
			}
			$db->free_result($request);
		}
	}

	/**
	 * Create a new role from a template.
	 *
	 * Two-phase operation:
	 * - Phase 1: Display the creation form (no 'part' parameter)
	 * - Phase 2: Process the form submission (part=2)
	 */
	public function action_create()
	{
		global $context, $txt, $scripturl;

		$db = database();

		// Phase 2: Process the submitted form
		if (isset($_REQUEST['part']) && $_REQUEST['part'] == 2)
		{
			checkSession();

			// Validate template
			$template = isset($_REQUEST['template']) ? (int) $_REQUEST['template'] : 0;
			if (!isset($context['shd_permissions']['roles'][$template]))
				fatal_lang_error('shd_unknown_template', false);

			// Validate role name
			$rolename = isset($_POST['rolename']) ? Util::htmlspecialchars(Util::htmltrim($_POST['rolename'])) : '';
			if (empty($rolename))
				fatal_lang_error('shd_no_role_name', false);

			// Insert the new role
			$db->insert('',
				'{db_prefix}helpdesk_roles',
				array(
					'template' => 'int',
					'role_name' => 'string',
				),
				array(
					$template,
					$rolename,
				),
				array('id_role')
			);

			// Get the new role ID
			$request = $db->query('', '
				SELECT MAX(id_role)
				FROM {db_prefix}helpdesk_roles',
				array()
			);
			list($new_role) = $db->fetch_row($request);
			$db->free_result($request);

			redirectexit('action=admin;area=helpdesk;sa=permissions;do=editrole;role=' . $new_role);
		}

		// Phase 1: Display the creation form
		$context['page_title'] = $txt['shd_create_role'];
		$context['sub_template'] = 'shd_create_role';

		// Validate template parameter
		$template = isset($_REQUEST['template']) ? (int) $_REQUEST['template'] : 0;
		if (!isset($context['shd_permissions']['roles'][$template]))
			fatal_lang_error('shd_unknown_template', false);

		$context['role_template_id'] = $template;

		checkSubmitOnce('register');
	}

	/**
	 * Edit an existing role.
	 *
	 * Displays the role editing form with permission dropdowns, membergroup
	 * checkboxes, and department checkboxes.
	 */
	public function action_edit()
	{
		global $context, $txt, $scripturl;

		$db = database();

		$role = isset($_REQUEST['role']) ? (int) $_REQUEST['role'] : 0;
		if (empty($role))
			fatal_lang_error('shd_unknown_role', false);

		// Load the specific role
		$this->shd_load_role($role);

		if (empty($context['shd_permissions']['user_defined_roles'][$role]))
			fatal_lang_error('shd_unknown_role', false);

		$context['shd_role'] = $context['shd_permissions']['user_defined_roles'][$role];
		$context['shd_role']['id'] = $role;

		$context['page_title'] = $txt['shd_edit_role'];
		$context['sub_template'] = 'shd_edit_role';

		// Load membergroups (excluding admin group 1, moderator group 3, and post-count groups)
		$context['shd_membergroups'] = array();

		// Include "Regular Members" as group 0
		$context['shd_membergroups'][0] = array(
			'id' => 0,
			'name' => $txt['membergroups_members'],
			'online_color' => '',
			'icons' => '',
		);

		$request = $db->query('', '
			SELECT id_group, group_name, online_color, icons
			FROM {db_prefix}membergroups
			WHERE id_group NOT IN ({array_int:excluded})
				AND min_posts = {int:min_posts}
			ORDER BY group_name ASC',
			array(
				'excluded' => array(1, 3),
				'min_posts' => -1,
			)
		);
		while ($row = $db->fetch_assoc($request))
		{
			$context['shd_membergroups'][$row['id_group']] = array(
				'id' => $row['id_group'],
				'name' => $row['group_name'],
				'online_color' => $row['online_color'],
				'icons' => $row['icons'],
			);
		}
		$db->free_result($request);

		// Load current role-group associations
		$context['shd_role_groups'] = array();
		$request = $db->query('', '
			SELECT id_group
			FROM {db_prefix}helpdesk_role_groups
			WHERE id_role = {int:role}',
			array(
				'role' => $role,
			)
		);
		while ($row = $db->fetch_assoc($request))
		{
			$context['shd_role_groups'][$row['id_group']] = true;
		}
		$db->free_result($request);

		// Load departments with check for whether this role is assigned
		$context['shd_departments'] = array();
		$request = $db->query('', '
			SELECT hdd.id_dept, hdd.dept_name,
				COALESCE(hddr.id_role, 0) AS role_present
			FROM {db_prefix}helpdesk_depts AS hdd
				LEFT JOIN {db_prefix}helpdesk_dept_roles AS hddr ON (hdd.id_dept = hddr.id_dept AND hddr.id_role = {int:role})
			ORDER BY hdd.dept_name ASC',
			array(
				'role' => $role,
			)
		);
		while ($row = $db->fetch_assoc($request))
		{
			$context['shd_departments'][$row['id_dept']] = array(
				'id' => $row['id_dept'],
				'name' => $row['dept_name'],
				'assigned' => !empty($row['role_present']),
			);
		}
		$db->free_result($request);
	}

	/**
	 * Save changes to a role.
	 *
	 * Handles name changes, permission changes, group changes,
	 * department changes, and role deletion.
	 */
	public function action_save()
	{
		global $context, $txt, $scripturl;

		$db = database();

		checkSession();

		$role = isset($_REQUEST['role']) ? (int) $_REQUEST['role'] : 0;
		if (empty($role))
			fatal_lang_error('shd_unknown_role', false);

		// Load the role to validate it exists
		$this->shd_load_role($role);

		if (empty($context['shd_permissions']['user_defined_roles'][$role]))
			fatal_lang_error('shd_unknown_role', false);

		$role_data = $context['shd_permissions']['user_defined_roles'][$role];

		// Handle deletion
		if (isset($_POST['delete']))
		{
			$db->query('', '
				DELETE FROM {db_prefix}helpdesk_roles
				WHERE id_role = {int:role}',
				array(
					'role' => $role,
				)
			);
			$db->query('', '
				DELETE FROM {db_prefix}helpdesk_role_groups
				WHERE id_role = {int:role}',
				array(
					'role' => $role,
				)
			);
			$db->query('', '
				DELETE FROM {db_prefix}helpdesk_role_permissions
				WHERE id_role = {int:role}',
				array(
					'role' => $role,
				)
			);
			$db->query('', '
				DELETE FROM {db_prefix}helpdesk_dept_roles
				WHERE id_role = {int:role}',
				array(
					'role' => $role,
				)
			);

			redirectexit('action=admin;area=helpdesk;sa=permissions');
		}

		// Handle name change
		if (isset($_POST['rolename']))
		{
			$rolename = Util::htmlspecialchars(Util::htmltrim($_POST['rolename']));
			if (!empty($rolename))
			{
				$db->query('', '
					UPDATE {db_prefix}helpdesk_roles
					SET role_name = {string:name}
					WHERE id_role = {int:role}',
					array(
						'role' => $role,
						'name' => $rolename,
					)
				);
			}
		}

		// Handle permission changes
		$add_permissions = array();
		$remove_permissions = array();

		foreach ($context['shd_permissions']['permission_list'] as $perm => $perm_details)
		{
			list($is_own_any, $group, $icon) = $perm_details;

			if ($is_own_any)
			{
				// Own/any permission - values: allow_any, allow_own, disallow, deny
				$value = isset($_POST['perm_' . $perm]) ? $_POST['perm_' . $perm] : 'disallow';

				switch ($value)
				{
					case 'allow_any':
						$add_permissions[] = array($role, $perm . '_any', ROLEPERM_ALLOW);
						$remove_permissions[] = $perm . '_own';
						break;
					case 'allow_own':
						$add_permissions[] = array($role, $perm . '_own', ROLEPERM_ALLOW);
						// Explicitly disallow _any to override template if needed
						$add_permissions[] = array($role, $perm . '_any', ROLEPERM_DISALLOW);
						break;
					case 'deny':
						$add_permissions[] = array($role, $perm . '_any', ROLEPERM_DENY);
						$add_permissions[] = array($role, $perm . '_own', ROLEPERM_DENY);
						break;
					case 'disallow':
					default:
						// Explicitly disallow to override template defaults
						$add_permissions[] = array($role, $perm . '_any', ROLEPERM_DISALLOW);
						$add_permissions[] = array($role, $perm . '_own', ROLEPERM_DISALLOW);
						break;
				}
			}
			else
			{
				// Simple permission - values: allow, disallow, deny
				$value = isset($_POST['perm_' . $perm]) ? $_POST['perm_' . $perm] : 'disallow';

				switch ($value)
				{
					case 'allow':
						$add_permissions[] = array($role, $perm, ROLEPERM_ALLOW);
						break;
					case 'deny':
						$add_permissions[] = array($role, $perm, ROLEPERM_DENY);
						break;
					case 'disallow':
					default:
						$remove_permissions[] = $perm;
						break;
				}
			}
		}

		// Insert/replace changed permissions
		if (!empty($add_permissions))
		{
			$db->insert('replace',
				'{db_prefix}helpdesk_role_permissions',
				array(
					'id_role' => 'int',
					'permission' => 'string',
					'add_type' => 'int',
				),
				$add_permissions,
				array('id_role', 'permission')
			);
		}

		// Remove disallowed permissions
		if (!empty($remove_permissions))
		{
			$db->query('', '
				DELETE FROM {db_prefix}helpdesk_role_permissions
				WHERE id_role = {int:role}
					AND permission IN ({array_string:permissions})',
				array(
					'role' => $role,
					'permissions' => $remove_permissions,
				)
			);
		}

		// Handle group changes
		// Load current groups
		$current_groups = array();
		$request = $db->query('', '
			SELECT id_group
			FROM {db_prefix}helpdesk_role_groups
			WHERE id_role = {int:role}',
			array(
				'role' => $role,
			)
		);
		while ($row = $db->fetch_assoc($request))
			$current_groups[$row['id_group']] = true;
		$db->free_result($request);

		// Get posted groups
		$posted_groups = array();
		if (isset($_POST['group']))
		{
			foreach ($_POST['group'] as $group_id)
				$posted_groups[(int) $group_id] = true;
		}

		// Add new group associations
		$groups_to_add = array_diff_key($posted_groups, $current_groups);
		if (!empty($groups_to_add))
		{
			$insert_groups = array();
			foreach (array_keys($groups_to_add) as $group_id)
				$insert_groups[] = array($role, $group_id);

			$db->insert('',
				'{db_prefix}helpdesk_role_groups',
				array(
					'id_role' => 'int',
					'id_group' => 'int',
				),
				$insert_groups,
				array('id_role', 'id_group')
			);
		}

		// Remove old group associations
		$groups_to_remove = array_diff_key($current_groups, $posted_groups);
		if (!empty($groups_to_remove))
		{
			$db->query('', '
				DELETE FROM {db_prefix}helpdesk_role_groups
				WHERE id_role = {int:role}
					AND id_group IN ({array_int:groups})',
				array(
					'role' => $role,
					'groups' => array_keys($groups_to_remove),
				)
			);
		}

		// Handle department changes - delete all, re-add checked ones
		$db->query('', '
			DELETE FROM {db_prefix}helpdesk_dept_roles
			WHERE id_role = {int:role}',
			array(
				'role' => $role,
			)
		);

		if (isset($_POST['dept']))
		{
			$insert_depts = array();
			foreach ($_POST['dept'] as $dept_id)
				$insert_depts[] = array($role, (int) $dept_id);

			if (!empty($insert_depts))
			{
				$db->insert('',
					'{db_prefix}helpdesk_dept_roles',
					array(
						'id_role' => 'int',
						'id_dept' => 'int',
					),
					$insert_depts,
					array('id_role', 'id_dept')
				);
			}
		}

		redirectexit('action=admin;area=helpdesk;sa=permissions');
	}

	/**
	 * Copy an existing role.
	 *
	 * Two-phase operation:
	 * - Phase 1: Display the copy form
	 * - Phase 2: Process the form, creating a new role with copied permissions
	 */
	public function action_copy()
	{
		global $context, $txt, $scripturl;

		$db = database();

		$role = isset($_REQUEST['role']) ? (int) $_REQUEST['role'] : 0;
		if (empty($role))
			fatal_lang_error('shd_unknown_role', false);

		// Load the source role
		$this->shd_load_role($role);

		if (empty($context['shd_permissions']['user_defined_roles'][$role]))
			fatal_lang_error('shd_unknown_role', false);

		$context['shd_source_role'] = $context['shd_permissions']['user_defined_roles'][$role];
		$context['shd_source_role']['id'] = $role;

		// Phase 2: Process the copy
		if (isset($_REQUEST['part']) && $_REQUEST['part'] == 2)
		{
			checkSession();

			// Validate new name
			$rolename = isset($_POST['rolename']) ? Util::htmlspecialchars(Util::htmltrim($_POST['rolename'])) : '';
			if (empty($rolename))
				fatal_lang_error('shd_no_role_name', false);

			// Create the new role with the same template
			$db->insert('',
				'{db_prefix}helpdesk_roles',
				array(
					'template' => 'int',
					'role_name' => 'string',
				),
				array(
					$context['shd_source_role']['template'],
					$rolename,
				),
				array('id_role')
			);

			// Get the new role ID
			$request = $db->query('', '
				SELECT MAX(id_role)
				FROM {db_prefix}helpdesk_roles',
				array()
			);
			list($new_role) = $db->fetch_row($request);
			$db->free_result($request);

			// Copy permissions from the source role
			$request = $db->query('', '
				SELECT permission, add_type
				FROM {db_prefix}helpdesk_role_permissions
				WHERE id_role = {int:source}',
				array(
					'source' => $role,
				)
			);

			$copy_perms = array();
			while ($row = $db->fetch_assoc($request))
				$copy_perms[] = array($new_role, $row['permission'], $row['add_type']);
			$db->free_result($request);

			if (!empty($copy_perms))
			{
				$db->insert('',
					'{db_prefix}helpdesk_role_permissions',
					array(
						'id_role' => 'int',
						'permission' => 'string',
						'add_type' => 'int',
					),
					$copy_perms,
					array('id_role', 'permission')
				);
			}

			// Optionally copy group and department associations
			if (!empty($_POST['copy_groups']))
			{
				$request = $db->query('', '
					SELECT id_group
					FROM {db_prefix}helpdesk_role_groups
					WHERE id_role = {int:source}',
					array(
						'source' => $role,
					)
				);

				$copy_groups = array();
				while ($row = $db->fetch_assoc($request))
					$copy_groups[] = array($new_role, $row['id_group']);
				$db->free_result($request);

				if (!empty($copy_groups))
				{
					$db->insert('',
						'{db_prefix}helpdesk_role_groups',
						array(
							'id_role' => 'int',
							'id_group' => 'int',
						),
						$copy_groups,
						array('id_role', 'id_group')
					);
				}

				$request = $db->query('', '
					SELECT id_dept
					FROM {db_prefix}helpdesk_dept_roles
					WHERE id_role = {int:source}',
					array(
						'source' => $role,
					)
				);

				$copy_depts = array();
				while ($row = $db->fetch_assoc($request))
					$copy_depts[] = array($new_role, $row['id_dept']);
				$db->free_result($request);

				if (!empty($copy_depts))
				{
					$db->insert('',
						'{db_prefix}helpdesk_dept_roles',
						array(
							'id_role' => 'int',
							'id_dept' => 'int',
						),
						$copy_depts,
						array('id_role', 'id_dept')
					);
				}
			}

			redirectexit('action=admin;area=helpdesk;sa=permissions;do=editrole;role=' . $new_role);
		}

		// Phase 1: Display the copy form
		$context['page_title'] = $txt['shd_copy_role'];
		$context['sub_template'] = 'shd_copy_role';

		checkSubmitOnce('register');
	}

	/**
	 * Loads roles from the database with their permissions.
	 *
	 * If $loadrole is 0, loads all roles. If a specific role ID is given,
	 * loads only that role. Applies template defaults first, then overrides
	 * from stored role permissions.
	 *
	 * @param int $loadrole Role ID to load, or 0 for all roles.
	 */
	private function shd_load_role($loadrole = 0)
	{
		global $context;

		$db = database();

		$context['shd_permissions']['user_defined_roles'] = array();

		// Load roles from the database
		if (empty($loadrole))
		{
			$request = $db->query('', '
				SELECT id_role, template, role_name
				FROM {db_prefix}helpdesk_roles
				ORDER BY id_role ASC',
				array()
			);
		}
		else
		{
			$request = $db->query('', '
				SELECT id_role, template, role_name
				FROM {db_prefix}helpdesk_roles
				WHERE id_role = {int:role}',
				array(
					'role' => $loadrole,
				)
			);
		}

		$roles = array();
		while ($row = $db->fetch_assoc($request))
		{
			$roles[$row['id_role']] = $row;

			// Start with template permissions as the base
			$template_perms = array();
			if (isset($context['shd_permissions']['roles'][$row['template']]))
				$template_perms = $context['shd_permissions']['roles'][$row['template']]['permissions'];

			$context['shd_permissions']['user_defined_roles'][$row['id_role']] = array(
				'name' => $row['role_name'],
				'template' => $row['template'],
				'permissions' => $template_perms,
				'groups' => array(),
			);
		}
		$db->free_result($request);

		if (empty($roles))
			return;

		$role_ids = array_keys($roles);

		// Load role-group associations with membergroup details
		$request = $db->query('', '
			SELECT hdrg.id_role, hdrg.id_group, mg.group_name, mg.online_color
			FROM {db_prefix}helpdesk_role_groups AS hdrg
				LEFT JOIN {db_prefix}membergroups AS mg ON (hdrg.id_group = mg.id_group)
			WHERE hdrg.id_role IN ({array_int:roles})',
			array(
				'roles' => $role_ids,
			)
		);
		while ($row = $db->fetch_assoc($request))
		{
			$context['shd_permissions']['user_defined_roles'][$row['id_role']]['groups'][$row['id_group']] = array(
				'id' => $row['id_group'],
				'name' => $row['id_group'] == 0 ? (isset($GLOBALS['txt']['membergroups_members']) ? $GLOBALS['txt']['membergroups_members'] : 'Regular Members') : $row['group_name'],
				'online_color' => !empty($row['online_color']) ? $row['online_color'] : '',
			);
		}
		$db->free_result($request);

		// Load role-specific permission overrides
		$request = $db->query('', '
			SELECT id_role, permission, add_type
			FROM {db_prefix}helpdesk_role_permissions
			WHERE id_role IN ({array_int:roles})',
			array(
				'roles' => $role_ids,
			)
		);
		while ($row = $db->fetch_assoc($request))
		{
			// Override template permissions with stored values
			if ($row['add_type'] == ROLEPERM_ALLOW)
				$context['shd_permissions']['user_defined_roles'][$row['id_role']]['permissions'][$row['permission']] = ROLEPERM_ALLOW;
			elseif ($row['add_type'] == ROLEPERM_DENY)
				$context['shd_permissions']['user_defined_roles'][$row['id_role']]['permissions'][$row['permission']] = ROLEPERM_DENY;
			else
			{
				// ROLEPERM_DISALLOW - remove it from the active permissions
				unset($context['shd_permissions']['user_defined_roles'][$row['id_role']]['permissions'][$row['permission']]);
			}
		}
		$db->free_result($request);
	}
}
