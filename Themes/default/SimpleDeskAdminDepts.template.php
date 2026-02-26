<?php
/**
 * SimpleDesk Admin Departments Template
 *
 * Templates for department administration: listing, creating, and
 * editing departments with role assignment.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Department listing template.
 *
 * Displays a table of all departments with their description, board index
 * category, assigned roles, move up/down arrows, and an edit button.
 * Includes a "Create new department" link at the bottom.
 */
function template_shd_departments_home()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">';


	// Page header.
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['shd_admin_departments_title'], '</h3>
		</div>
		<div class="information">
			', $txt['shd_admin_departments_desc'], '
		</div>';

	// Department table.
	echo '
		<table class="table_grid" style="width: 100%;">
			<thead>
				<tr class="table_head">
					<th scope="col">', $txt['shd_admin_dept_name'], '</th>
					<th scope="col">', $txt['shd_admin_dept_board_cat'], '</th>
					<th scope="col">', $txt['shd_admin_dept_roles'], '</th>
					<th scope="col" style="width: 8%;">', $txt['shd_admin_dept_move'], '</th>
					<th scope="col" style="width: 8%;">', $txt['shd_admin_dept_actions'], '</th>
				</tr>
			</thead>
			<tbody>';

	if (empty($context['shd_departments']))
	{
		echo '
				<tr class="windowbg">
					<td colspan="5" class="centertext">', $txt['shd_admin_dept_none'], '</td>
				</tr>';
	}
	else
	{
		foreach ($context['shd_departments'] as $dept)
		{
			echo '
				<tr class="windowbg">
					<td>
						<strong>', $dept['dept_name'], '</strong>';

			if (!empty($dept['description']))
			{
				echo '
						<br /><span class="smalltext">', $dept['description'], '</span>';
			}

			echo '
					</td>
					<td>', $dept['cat_name'], '</td>
					<td>';

			// Display roles assigned to this department.
			if (!empty($dept['roles']))
			{
				$role_links = array();
				foreach ($dept['roles'] as $role)
				{
					// Build a role label with the template type indicator.
					$role_type = '';
					if (isset($context['shd_permissions']['roles'][$role['template']]))
					{
						$role_desc_key = $context['shd_permissions']['roles'][$role['template']]['description'];
						$role_type = isset($txt[$role_desc_key]) ? $txt[$role_desc_key] : '';
					}

					$role_label = $role['role_name'];
					if (!empty($role_type))
						$role_label .= ' <span class="smalltext">(' . $role_type . ')</span>';

					$role_links[] = '<a href="' . $scripturl . '?action=admin;area=helpdesk;sa=permissions;do=editrole;role=' . $role['id_role'] . '">' . $role_label . '</a>';
				}
				echo implode(', ', $role_links);
			}
			else
			{
				echo '<em>', $txt['shd_admin_dept_no_roles'], '</em>';
			}

			echo '
					</td>
					<td class="centertext">';

			// Move up arrow (not for first department).
			if (!$dept['is_first'])
			{
				echo '
						<a href="', $scripturl, '?action=admin;area=helpdesk;sa=departments;do=move;dept=', $dept['id_dept'], ';direction=up;', $context['session_var'], '=', $context['session_id'], '">', $txt['shd_admin_move_up'], '</a>';
			}

			// Separator between arrows.
			if (!$dept['is_first'] && !$dept['is_last'])
				echo ' / ';

			// Move down arrow (not for last department).
			if (!$dept['is_last'])
			{
				echo '
						<a href="', $scripturl, '?action=admin;area=helpdesk;sa=departments;do=move;dept=', $dept['id_dept'], ';direction=down;', $context['session_var'], '=', $context['session_id'], '">', $txt['shd_admin_move_down'], '</a>';
			}

			echo '
					</td>
					<td class="centertext">
						<a href="', $scripturl, '?action=admin;area=helpdesk;sa=departments;do=editdept;dept=', $dept['id_dept'], '">', $txt['shd_admin_dept_edit'], '</a>
					</td>
				</tr>';
		}
	}

	echo '
			</tbody>
		</table>';

	// Create new department link.
	echo '
		<div class="flow_auto" style="padding: 8px 0;">
			<a href="', $scripturl, '?action=admin;area=helpdesk;sa=departments;do=createdept" class="button_submit">[', $txt['shd_admin_dept_create'], ']</a>
		</div>
	</div>';
}

/**
 * Department creation form template.
 *
 * Displays a form with department name, description, board category
 * selection, and board position (before/after) dropdown. Submits to
 * the createdept action with part=2 for processing.
 */
function template_shd_create_dept()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">';


	// Page header.
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['shd_admin_dept_create_title'], '</h3>
		</div>
		<div class="information">
			', $txt['shd_admin_dept_create_desc'], '
		</div>';

	// Creation form.
	echo '
		<form action="', $scripturl, '?action=admin;area=helpdesk;sa=departments;do=createdept;part=2" method="post" accept-charset="UTF-8">
			<div class="content">
				<dl class="settings">
					<dt>
						<label for="dept_name"><strong>', $txt['shd_admin_dept_name'], '</strong></label>
						<br /><span class="smalltext">', $txt['shd_admin_dept_name_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="dept_name" id="dept_name" value="" size="40" maxlength="50" />
					</dd>
					<dt>
						<label for="dept_desc"><strong>', $txt['shd_admin_dept_description'], '</strong></label>
						<br /><span class="smalltext">', $txt['shd_admin_dept_description_desc'], '</span>
					</dt>
					<dd>
						<textarea name="dept_desc" id="dept_desc" rows="3" cols="40"></textarea>
					</dd>
					<dt>
						<label for="dept_cat"><strong>', $txt['shd_admin_dept_board_cat'], '</strong></label>
						<br /><span class="smalltext">', $txt['shd_admin_dept_board_cat_desc'], '</span>
					</dt>
					<dd>
						<select name="dept_cat" id="dept_cat">';

	foreach ($context['shd_cat_list'] as $cat_id => $cat_name)
	{
		echo '
							<option value="', $cat_id, '">', $cat_name, '</option>';
	}

	echo '
						</select>
					</dd>
					<dt>
						<label for="dept_beforeafter"><strong>', $txt['shd_admin_dept_beforeafter'], '</strong></label>
						<br /><span class="smalltext">', $txt['shd_admin_dept_beforeafter_desc'], '</span>
					</dt>
					<dd>
						<select name="dept_beforeafter" id="dept_beforeafter">
							<option value="0">', $txt['shd_admin_dept_before_boards'], '</option>
							<option value="1">', $txt['shd_admin_dept_after_boards'], '</option>
						</select>
					</dd>
				</dl>
			</div>
			<div class="submitbutton">
				<input type="submit" value="', $txt['shd_admin_dept_create_btn'], '" class="button_submit" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />
			</div>
		</form>
	</div>';
}

/**
 * Department edit form template.
 *
 * Displays a form to edit the department name, description, category,
 * board position, and autoclose days. Includes a checklist table of all
 * available roles with checkboxes indicating which roles are assigned
 * to this department. Features Save and Delete buttons.
 */
function template_shd_edit_dept()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">';


	// Page header.
	$page_title = sprintf($txt['shd_admin_dept_edit_title'], $context['shd_dept']['dept_name']);
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $page_title, '</h3>
		</div>';

	// Edit form.
	echo '
		<form action="', $scripturl, '?action=admin;area=helpdesk;sa=departments;do=savedept" method="post" accept-charset="UTF-8">
			<input type="hidden" name="dept" value="', $context['shd_dept']['id_dept'], '" />';

	// ---- Department settings section ----
	echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['shd_admin_dept_settings'], '</h3>
			</div>
			<div class="content">
				<dl class="settings">
					<dt>
						<label for="dept_name"><strong>', $txt['shd_admin_dept_name'], '</strong></label>
						<br /><span class="smalltext">', $txt['shd_admin_dept_name_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="dept_name" id="dept_name" value="', $context['shd_dept']['dept_name'], '" size="40" maxlength="50" />
					</dd>
					<dt>
						<label for="dept_desc"><strong>', $txt['shd_admin_dept_description'], '</strong></label>
						<br /><span class="smalltext">', $txt['shd_admin_dept_description_desc'], '</span>
					</dt>
					<dd>
						<textarea name="dept_desc" id="dept_desc" rows="3" cols="40">', $context['shd_dept']['description'], '</textarea>
					</dd>
					<dt>
						<label for="dept_cat"><strong>', $txt['shd_admin_dept_board_cat'], '</strong></label>
						<br /><span class="smalltext">', $txt['shd_admin_dept_board_cat_desc'], '</span>
					</dt>
					<dd>
						<select name="dept_cat" id="dept_cat">';

	foreach ($context['shd_cat_list'] as $cat_id => $cat_name)
	{
		echo '
							<option value="', $cat_id, '"', ($context['shd_dept']['board_cat'] == $cat_id ? ' selected="selected"' : ''), '>', $cat_name, '</option>';
	}

	echo '
						</select>
					</dd>
					<dt>
						<label for="dept_beforeafter"><strong>', $txt['shd_admin_dept_beforeafter'], '</strong></label>
						<br /><span class="smalltext">', $txt['shd_admin_dept_beforeafter_desc'], '</span>
					</dt>
					<dd>
						<select name="dept_beforeafter" id="dept_beforeafter">
							<option value="0"', ($context['shd_dept']['before_after'] == 0 ? ' selected="selected"' : ''), '>', $txt['shd_admin_dept_before_boards'], '</option>
							<option value="1"', ($context['shd_dept']['before_after'] == 1 ? ' selected="selected"' : ''), '>', $txt['shd_admin_dept_after_boards'], '</option>
						</select>
					</dd>
					<dt>
						<label for="autoclose_days"><strong>', $txt['shd_admin_dept_autoclose'], '</strong></label>
						<br /><span class="smalltext">', $txt['shd_admin_dept_autoclose_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="autoclose_days" id="autoclose_days" value="', $context['shd_dept']['autoclose_days'], '" size="5" />
					</dd>
				</dl>
			</div>';

	// ---- Role assignment section ----
	echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['shd_admin_dept_roles_section'], '</h3>
			</div>
			<div class="information">
				', $txt['shd_admin_dept_roles_desc'], '
			</div>';

	if (empty($context['shd_roles']))
	{
		echo '
			<div class="content">
				<p class="description">', $txt['shd_admin_dept_no_roles_available'], '</p>
			</div>';
	}
	else
	{
		echo '
			<table class="table_grid" style="width: 100%;">
				<thead>
					<tr class="table_head">
						<th scope="col" style="width: 5%;">&nbsp;</th>
						<th scope="col">', $txt['shd_admin_dept_role_name'], '</th>
						<th scope="col">', $txt['shd_admin_dept_role_type'], '</th>
					</tr>
				</thead>
				<tbody>';

		foreach ($context['shd_roles'] as $role)
		{
			// Get the role type description based on template.
			$role_type = '';
			if (isset($context['shd_permissions']['roles'][$role['template']]))
			{
				$role_desc_key = $context['shd_permissions']['roles'][$role['template']]['description'];
				$role_type = isset($txt[$role_desc_key]) ? $txt[$role_desc_key] : '';
			}

			echo '
					<tr class="windowbg">
						<td class="centertext">
							<input type="checkbox" name="role[', $role['id_role'], ']" value="1"', ($role['in_dept'] ? ' checked="checked"' : ''), ' />
						</td>
						<td>', $role['role_name'], '</td>
						<td>', !empty($role_type) ? $role_type : $txt['shd_unknown'], '</td>
					</tr>';
		}

		echo '
				</tbody>
			</table>';
	}

	// ---- Submit / Delete buttons ----
	echo '
			<div class="submitbutton">
				<input type="submit" value="', $txt['shd_admin_dept_save'], '" class="button_submit" />
				<input type="submit" name="delete" value="', $txt['shd_admin_dept_delete'], '" class="button_submit" onclick="return confirm(\'', $txt['shd_admin_dept_delete_confirm'], '\');" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</div>
		</form>
	</div>';
}
