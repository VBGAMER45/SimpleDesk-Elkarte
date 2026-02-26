<?php
/**
 * SimpleDesk Admin Permissions Template
 *
 * Templates for permissions and roles administration. Provides the UI for
 * listing, creating, editing, copying, and deleting helpdesk roles.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Permissions home page.
 *
 * Displays two sections:
 * 1. Role Templates - the 3 built-in templates (User, Staff, Admin) with
 *    their default permission summaries and "Create new role" links.
 * 2. User-Defined Roles - all created roles with their permissions,
 *    assigned membergroups, departments, and edit/copy links.
 */
function template_shd_permissions_home()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">';


	// Page header
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['shd_admin_permissions'], '</h3>
		</div>
		<div class="information">
			', $txt['shd_admin_permissions_desc'], '
		</div>';

	// Section 1: Role Templates
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['shd_role_templates'], '</h3>
		</div>
		<div class="content">
			<table class="table_grid" style="width: 100%;">
				<thead>
					<tr class="title_bar">
						<th class="lefttext">', $txt['shd_role_template'], '</th>
						<th class="lefttext">', $txt['shd_permissions_summary'], '</th>
						<th class="centertext">', $txt['shd_actions'], '</th>
					</tr>
				</thead>
				<tbody>';

	foreach ($context['shd_permissions']['roles'] as $template_id => $template)
	{
		echo '
					<tr class="windowbg">
						<td>
							<strong>', $txt[$template['description']], '</strong>
						</td>
						<td>';

		template_shd_display_permission_list($template['permissions']);

		echo '
						</td>
						<td class="centertext">
							<a href="', $scripturl, '?action=admin;area=helpdesk;sa=permissions;do=createrole;template=', $template_id, '">', $txt['shd_create_role_from_template'], '</a>
						</td>
					</tr>';
	}

	echo '
				</tbody>
			</table>
		</div>';

	// Section 2: User-Defined Roles
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['shd_roles'], '</h3>
		</div>
		<div class="content">';

	if (!empty($context['shd_permissions']['user_defined_roles']))
	{
		echo '
			<table class="table_grid" style="width: 100%;">
				<thead>
					<tr class="title_bar">
						<th class="lefttext">', $txt['shd_role_name'], '</th>
						<th class="lefttext">', $txt['shd_permissions_summary'], '</th>
						<th class="lefttext">', $txt['shd_membergroups'], '</th>
						<th class="lefttext">', $txt['shd_departments'], '</th>
						<th class="centertext">', $txt['shd_actions'], '</th>
					</tr>
				</thead>
				<tbody>';

		foreach ($context['shd_permissions']['user_defined_roles'] as $role_id => $role)
		{
			echo '
					<tr class="windowbg">
						<td>
							<strong>', $role['name'], '</strong><br />
							<span class="smalltext">', $txt['shd_based_on'], ': ',
								isset($context['shd_permissions']['roles'][$role['template']]) ? $txt[$context['shd_permissions']['roles'][$role['template']]['description']] : $txt['shd_unknown_template_label'], '</span>
						</td>
						<td>';

			template_shd_display_permission_list($role['permissions']);

			echo '
						</td>
						<td>';

			if (!empty($role['groups']))
			{
				$group_names = array();
				foreach ($role['groups'] as $group)
				{
					if (!empty($group['online_color']))
						$group_names[] = '<span style="color: ' . $group['online_color'] . ';">' . $group['name'] . '</span>';
					else
						$group_names[] = $group['name'];
				}
				echo implode(', ', $group_names);
			}
			else
				echo '<em>', $txt['shd_no_groups_assigned'], '</em>';

			echo '
						</td>
						<td>';

			if (!empty($context['role_depts'][$role_id]))
				echo implode(', ', $context['role_depts'][$role_id]);
			else
				echo '<em>', $txt['shd_no_depts_assigned'], '</em>';

			echo '
						</td>
						<td class="centertext">
							<a href="', $scripturl, '?action=admin;area=helpdesk;sa=permissions;do=editrole;role=', $role_id, '">', $txt['shd_edit_role'], '</a>
							| <a href="', $scripturl, '?action=admin;area=helpdesk;sa=permissions;do=copyrole;role=', $role_id, '">', $txt['shd_copy_role'], '</a>
						</td>
					</tr>';
		}

		echo '
				</tbody>
			</table>';
	}
	else
	{
		echo '
			<p class="description">', $txt['shd_no_roles_defined'], '</p>';
	}

	echo '
		</div>
	</div>';
}

/**
 * Display a compact permission summary.
 *
 * Groups permissions by their category and shows which permissions are
 * allowed. Uses text labels and CSS classes instead of image icons.
 *
 * @param array $permissions Permission set to display (permission_name => value).
 */
function template_shd_display_permission_list($permissions)
{
	global $context, $txt;

	if (empty($permissions))
	{
		echo '<em>', $txt['shd_no_permissions'], '</em>';
		return;
	}

	// Group permissions by their category using the permission_list definitions
	$by_group = array();
	foreach ($permissions as $perm => $value)
	{
		if ($value != ROLEPERM_ALLOW)
			continue;

		// For own/any permissions, strip the suffix to find the base permission
		$base_perm = $perm;
		$suffix = '';
		if (substr($perm, -4) === '_own')
		{
			$base_perm = substr($perm, 0, -4);
			$suffix = '_own';
		}
		elseif (substr($perm, -4) === '_any')
		{
			$base_perm = substr($perm, 0, -4);
			$suffix = '_any';
		}

		// Find the category this permission belongs to
		if (isset($context['shd_permissions']['permission_list'][$base_perm]))
		{
			$group = $context['shd_permissions']['permission_list'][$base_perm][1];
			$by_group[$group][$perm] = true;
		}
		elseif (isset($context['shd_permissions']['permission_list'][$perm]))
		{
			$group = $context['shd_permissions']['permission_list'][$perm][1];
			$by_group[$group][$perm] = true;
		}
	}

	if (empty($by_group))
	{
		echo '<em>', $txt['shd_no_permissions'], '</em>';
		return;
	}

	$first = true;
	foreach ($by_group as $group => $perms)
	{
		if (!$first)
			echo '<br />';
		$first = false;

		$group_label = isset($txt['shd_permgroup_' . $group]) ? $txt['shd_permgroup_' . $group] : $group;
		echo '<span class="smalltext"><strong>', $group_label, ':</strong> ';

		$perm_labels = array();
		foreach (array_keys($perms) as $perm)
		{
			$label = isset($txt['permissionname_' . $perm]) ? $txt['permissionname_' . $perm] : $perm;
			$perm_labels[] = $label;
		}
		echo implode(', ', $perm_labels);
		echo '</span>';
	}
}

/**
 * Create role form.
 *
 * Displays a form to create a new role based on a selected template.
 * Includes the template name, a text input for the role name, and
 * hidden fields for the template id and session token.
 */
function template_shd_create_role()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">';


	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['shd_create_role'], '</h3>
		</div>
		<div class="information">
			', $txt['shd_create_role_desc'], '
		</div>';

	$template_id = $context['role_template_id'];
	$template = $context['shd_permissions']['roles'][$template_id];

	echo '
		<form action="', $scripturl, '?action=admin;area=helpdesk;sa=permissions;do=createrole;part=2" method="post" accept-charset="UTF-8">
			<div class="content">
				<dl class="settings">
					<dt>
						<strong>', $txt['shd_role_template'], ':</strong>
					</dt>
					<dd>
						', $txt[$template['description']], '
					</dd>
					<dt>
						<label for="rolename"><strong>', $txt['shd_role_name'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="rolename" id="rolename" value="" size="40" class="input_text" />
					</dd>
				</dl>
			</div>
			<div class="submitbutton">
				<input type="submit" value="', $txt['shd_create_role'], '" class="button_submit" />
				<input type="hidden" name="template" value="', $template_id, '" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />
			</div>
		</form>
	</div>';
}

/**
 * Edit role form.
 *
 * Displays the full role editing interface with sections for:
 * 1. Role name and template info
 * 2. Permission groups with select dropdowns
 * 3. Membergroup checkboxes
 * 4. Department checkboxes
 * 5. Delete and Save buttons
 */
function template_shd_edit_role()
{
	global $context, $scripturl, $txt;

	$role = $context['shd_role'];

	echo '
	<div id="admincenter">';


	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['shd_edit_role'], ': ', $role['name'], '</h3>
		</div>';

	echo '
		<form action="', $scripturl, '?action=admin;area=helpdesk;sa=permissions;do=saverole" method="post" accept-charset="UTF-8">
			<input type="hidden" name="role" value="', $role['id'], '" />';

	// Section 1: Role name and template info
	echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['shd_role_details'], '</h3>
			</div>
			<div class="content">
				<dl class="settings">
					<dt>
						<label for="rolename"><strong>', $txt['shd_role_name'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="rolename" id="rolename" value="', $role['name'], '" size="40" class="input_text" />
					</dd>
					<dt>
						<strong>', $txt['shd_role_template'], ':</strong>
					</dt>
					<dd>',
						isset($context['shd_permissions']['roles'][$role['template']]) ? $txt[$context['shd_permissions']['roles'][$role['template']]['description']] : $txt['shd_unknown_template_label'], '
					</dd>
				</dl>
			</div>';

	// Section 2: Permission groups
	// Build the permission display grouped by category
	$permission_groups = array();
	foreach ($context['shd_permissions']['permission_list'] as $perm => $details)
	{
		list($is_own_any, $group, $icon) = $details;
		$permission_groups[$group][$perm] = array(
			'is_own_any' => $is_own_any,
		);
	}

	foreach ($permission_groups as $group => $perms)
	{
		$group_label = isset($txt['shd_permgroup_' . $group]) ? $txt['shd_permgroup_' . $group] : $group;

		echo '
			<div class="cat_bar">
				<h3 class="catbg">', $group_label, '</h3>
			</div>
			<div class="content">
				<dl class="settings">';

		foreach ($perms as $perm => $perm_info)
		{
			$perm_label = isset($txt['permissionname_' . $perm]) ? $txt['permissionname_' . $perm] : $perm;
			$perm_desc = isset($txt['permissionhelp_' . $perm]) ? $txt['permissionhelp_' . $perm] : '';

			echo '
					<dt>
						<strong>', $perm_label, '</strong>';

			if (!empty($perm_desc))
			{
				echo '
						<br /><span class="smalltext">', $perm_desc, '</span>';
			}

			echo '
					</dt>
					<dd>
						<select name="perm_', $perm, '">';

			if ($perm_info['is_own_any'])
			{
				// Determine current value from the role's permissions
				$has_any = !empty($role['permissions'][$perm . '_any']) && $role['permissions'][$perm . '_any'] == ROLEPERM_ALLOW;
				$has_own = !empty($role['permissions'][$perm . '_own']) && $role['permissions'][$perm . '_own'] == ROLEPERM_ALLOW;
				$is_deny = (!empty($role['permissions'][$perm . '_any']) && $role['permissions'][$perm . '_any'] == ROLEPERM_DENY)
					|| (!empty($role['permissions'][$perm . '_own']) && $role['permissions'][$perm . '_own'] == ROLEPERM_DENY);

				if ($is_deny)
					$current = 'deny';
				elseif ($has_any)
					$current = 'allow_any';
				elseif ($has_own)
					$current = 'allow_own';
				else
					$current = 'disallow';

				echo '
							<option value="disallow"', ($current === 'disallow' ? ' selected="selected"' : ''), '>', $txt['shd_perm_disallow'], '</option>
							<option value="allow_own"', ($current === 'allow_own' ? ' selected="selected"' : ''), '>', $txt['shd_perm_allow_own'], '</option>
							<option value="allow_any"', ($current === 'allow_any' ? ' selected="selected"' : ''), '>', $txt['shd_perm_allow_any'], '</option>
							<option value="deny"', ($current === 'deny' ? ' selected="selected"' : ''), '>', $txt['shd_perm_deny'], '</option>';
			}
			else
			{
				// Simple permission
				$is_allow = !empty($role['permissions'][$perm]) && $role['permissions'][$perm] == ROLEPERM_ALLOW;
				$is_deny = !empty($role['permissions'][$perm]) && $role['permissions'][$perm] == ROLEPERM_DENY;

				if ($is_deny)
					$current = 'deny';
				elseif ($is_allow)
					$current = 'allow';
				else
					$current = 'disallow';

				echo '
							<option value="disallow"', ($current === 'disallow' ? ' selected="selected"' : ''), '>', $txt['shd_perm_disallow'], '</option>
							<option value="allow"', ($current === 'allow' ? ' selected="selected"' : ''), '>', $txt['shd_perm_allow'], '</option>
							<option value="deny"', ($current === 'deny' ? ' selected="selected"' : ''), '>', $txt['shd_perm_deny'], '</option>';
			}

			echo '
						</select>
					</dd>';
		}

		echo '
				</dl>
			</div>';
	}

	// Section 3: Membergroups
	echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['shd_membergroups'], '</h3>
			</div>
			<div class="content">
				<p class="description">', $txt['shd_membergroups_desc'], '</p>
				<table class="table_grid" style="width: 100%;">
					<thead>
						<tr class="title_bar">
							<th class="centertext" style="width: 30px;"></th>
							<th class="lefttext">', $txt['shd_group_name'], '</th>
						</tr>
					</thead>
					<tbody>';

	foreach ($context['shd_membergroups'] as $group)
	{
		$checked = isset($context['shd_role_groups'][$group['id']]);
		$group_display = $group['name'];
		if (!empty($group['online_color']))
			$group_display = '<span style="color: ' . $group['online_color'] . ';">' . $group['name'] . '</span>';

		echo '
						<tr class="windowbg">
							<td class="centertext">
								<input type="checkbox" name="group[]" value="', $group['id'], '"', ($checked ? ' checked="checked"' : ''), ' />
							</td>
							<td>', $group_display, '</td>
						</tr>';
	}

	echo '
					</tbody>
				</table>
			</div>';

	// Section 4: Departments
	echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['shd_departments'], '</h3>
			</div>
			<div class="content">
				<p class="description">', $txt['shd_departments_desc'], '</p>';

	if (!empty($context['shd_departments']))
	{
		echo '
				<table class="table_grid" style="width: 100%;">
					<thead>
						<tr class="title_bar">
							<th class="centertext" style="width: 30px;"></th>
							<th class="lefttext">', $txt['shd_dept_name'], '</th>
						</tr>
					</thead>
					<tbody>';

		foreach ($context['shd_departments'] as $dept)
		{
			echo '
						<tr class="windowbg">
							<td class="centertext">
								<input type="checkbox" name="dept[]" value="', $dept['id'], '"', ($dept['assigned'] ? ' checked="checked"' : ''), ' />
							</td>
							<td>', $dept['name'], '</td>
						</tr>';
		}

		echo '
					</tbody>
				</table>';
	}
	else
	{
		echo '
				<p class="description">', $txt['shd_no_departments'], '</p>';
	}

	echo '
			</div>';

	// Section 5: Delete and Save buttons
	echo '
			<div class="submitbutton">
				<input type="submit" name="save" value="', $txt['save'], '" class="button_submit" />
				<input type="submit" name="delete" value="', $txt['shd_delete_role'], '" class="button_submit" onclick="return confirm(\'', $txt['shd_delete_role_confirm'], '\');" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</div>
		</form>
	</div>';
}

/**
 * Copy role form.
 *
 * Displays a form to copy an existing role, with an option to also
 * copy its membergroup and department associations.
 */
function template_shd_copy_role()
{
	global $context, $scripturl, $txt;

	$source = $context['shd_source_role'];

	echo '
	<div id="admincenter">';


	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['shd_copy_role'], '</h3>
		</div>
		<div class="information">
			', $txt['shd_copy_role_desc'], '
		</div>';

	echo '
		<form action="', $scripturl, '?action=admin;area=helpdesk;sa=permissions;do=copyrole;part=2" method="post" accept-charset="UTF-8">
			<div class="content">
				<dl class="settings">
					<dt>
						<strong>', $txt['shd_copy_source'], ':</strong>
					</dt>
					<dd>
						', $source['name'], '
						<span class="smalltext">(', isset($context['shd_permissions']['roles'][$source['template']]) ? $txt[$context['shd_permissions']['roles'][$source['template']]['description']] : $txt['shd_unknown_template_label'], ')</span>
					</dd>
					<dt>
						<label for="rolename"><strong>', $txt['shd_new_role_name'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="rolename" id="rolename" value="" size="40" class="input_text" />
					</dd>
					<dt>
						<label for="copy_groups"><strong>', $txt['shd_copy_groups_depts'], ':</strong></label>
						<br /><span class="smalltext">', $txt['shd_copy_groups_depts_desc'], '</span>
					</dt>
					<dd>
						<input type="checkbox" name="copy_groups" id="copy_groups" value="1" />
					</dd>
				</dl>
			</div>
			<div class="submitbutton">
				<input type="submit" value="', $txt['shd_copy_role'], '" class="button_submit" />
				<input type="hidden" name="role" value="', $source['id'], '" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />
			</div>
		</form>
	</div>';
}
