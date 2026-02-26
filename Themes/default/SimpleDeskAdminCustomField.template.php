<?php
/**
 * SimpleDesk Admin Custom Field Template
 *
 * Templates for custom field administration: listing all fields,
 * creating new fields, and editing existing field definitions.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Custom field listing template.
 *
 * Displays a table of all custom fields with their type, location,
 * active status, visibility, move up/down arrows, and an edit link.
 * Includes a "Create New Custom Field" link at the bottom.
 */
function template_shd_custom_field_home()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">';


	// Page header.
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['shd_admin_custom_fields'], '</h3>
		</div>
		<div class="information">
			', $txt['shd_admin_custom_fields_desc'], '
		</div>';

	// Custom fields table.
	echo '
		<table class="table_grid" style="width: 100%;">
			<thead>
				<tr class="table_head">
					<th scope="col">', $txt['shd_admin_custom_field_name'], '</th>
					<th scope="col">', $txt['shd_admin_custom_field_type'], '</th>
					<th scope="col">', $txt['shd_admin_custom_field_location'], '</th>
					<th scope="col" style="width: 6%;">', $txt['shd_admin_custom_field_active'], '</th>
					<th scope="col">', $txt['shd_admin_custom_field_visibility'], '</th>
					<th scope="col" style="width: 8%;">', $txt['shd_admin_dept_move'], '</th>
					<th scope="col" style="width: 8%;">', $txt['shd_admin_dept_actions'], '</th>
				</tr>
			</thead>
			<tbody>';

	if (empty($context['custom_fields']))
	{
		echo '
				<tr class="windowbg">
					<td colspan="7" class="centertext">', $txt['shd_admin_custom_field_none'], '</td>
				</tr>';
	}
	else
	{
		foreach ($context['custom_fields'] as $field)
		{
			echo '
				<tr class="windowbg">
					<td>
						<strong>', $field['field_name'], '</strong>';

			if (!empty($field['field_desc']))
			{
				echo '
						<br /><span class="smalltext">', $field['field_desc'], '</span>';
			}

			echo '
					</td>
					<td>';

			// Display the field type label from the types array.
			if (isset($context['field_types'][$field['field_type']]))
				echo $context['field_types'][$field['field_type']];
			else
				echo $field['field_type'];

			echo '</td>
					<td>';

			// Location: Ticket/Reply/Both based on field_loc constant.
			if ($field['field_loc'] == CFIELD_TICKET)
				echo $txt['shd_admin_custom_field_loc_ticket'];
			elseif ($field['field_loc'] == CFIELD_REPLY)
				echo $txt['shd_admin_custom_field_loc_reply'];
			elseif ($field['field_loc'] == CFIELD_TICKETREPLY)
				echo $txt['shd_admin_custom_field_loc_both'];

			echo '</td>
					<td class="centertext">';

			// Active/Inactive status indicator.
			if (!empty($field['active']))
				echo '<span style="color: green;">', $txt['shd_admin_custom_field_active'], '</span>';
			else
				echo '<span style="color: red;">', $txt['shd_admin_custom_field_inactive'], '</span>';

			echo '</td>
					<td>';

			// Visibility: who can see this field.
			$visible_to = array();
			if (!empty($field['can_see'][0]))
				$visible_to[] = $txt['shd_admin_custom_field_can_see_users'];
			if (!empty($field['can_see'][1]))
				$visible_to[] = $txt['shd_admin_custom_field_can_see_staff'];

			echo !empty($visible_to) ? implode(', ', $visible_to) : '<em>' . $txt['shd_admin_custom_field_nobody'] . '</em>';

			echo '</td>
					<td class="centertext">';

			// Move up arrow (not for first field).
			if (!$field['is_first'])
			{
				echo '
						<a href="', $scripturl, '?action=admin;area=helpdesk;sa=custom_fields;do=move;field=', $field['id_field'], ';direction=up;', $context['session_var'], '=', $context['session_id'], '">', $txt['shd_admin_move_up'], '</a>';
			}

			// Separator between arrows.
			if (!$field['is_first'] && !$field['is_last'])
				echo ' / ';

			// Move down arrow (not for last field).
			if (!$field['is_last'])
			{
				echo '
						<a href="', $scripturl, '?action=admin;area=helpdesk;sa=custom_fields;do=move;field=', $field['id_field'], ';direction=down;', $context['session_var'], '=', $context['session_id'], '">', $txt['shd_admin_move_down'], '</a>';
			}

			echo '
					</td>
					<td class="centertext">
						<a href="', $scripturl, '?action=admin;area=helpdesk;sa=custom_fields;do=edit;field=', $field['id_field'], '">', $txt['shd_admin_dept_edit'], '</a>
					</td>
				</tr>';
		}
	}

	echo '
			</tbody>
		</table>';

	// Create new custom field link.
	echo '
		<div class="flow_auto" style="padding: 8px 0;">
			<a href="', $scripturl, '?action=admin;area=helpdesk;sa=custom_fields;do=new" class="button_submit">[', $txt['shd_admin_new_custom_field'], ']</a>
		</div>
	</div>';
}

/**
 * Custom field create/edit form template.
 *
 * Displays a comprehensive form for creating or editing a custom field
 * definition, including field properties, select/radio options,
 * textarea dimensions, department assignments, and visibility settings.
 */
function template_shd_custom_field_edit()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">';


	// Page header.
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $context['section_title'], '</h3>
		</div>
		<div class="information">
			', $context['section_desc'], '
		</div>';

	// Determine if this is editing an existing field or creating a new one.
	$is_edit = !empty($context['custom_field']['id_field']);

	// Edit/create form.
	echo '
		<form action="', $scripturl, '?action=admin;area=helpdesk;sa=custom_fields;do=save" method="post" accept-charset="UTF-8">';

	// Hidden fields.
	if ($is_edit)
	{
		echo '
			<input type="hidden" name="field" value="', $context['custom_field']['id_field'], '" />';
	}
	else
	{
		echo '
			<input type="hidden" name="new" value="1" />';
	}

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />';

	// ---- Field Properties section ----
	echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['shd_admin_custom_field_name'], '</h3>
			</div>
			<div class="content">
				<dl class="settings">
					<dt>
						<label for="field_name"><strong>', $txt['shd_admin_custom_field_name'], '</strong></label>
					</dt>
					<dd>
						<input type="text" name="field_name" id="field_name" value="', !empty($context['custom_field']['field_name']) ? $context['custom_field']['field_name'] : '', '" size="40" />
					</dd>
					<dt>
						<label for="description"><strong>', $txt['shd_admin_custom_field_desc'], '</strong></label>
					</dt>
					<dd>
						<textarea name="description" id="description" rows="3" cols="40">', !empty($context['custom_field']['field_desc']) ? $context['custom_field']['field_desc'] : '', '</textarea>
					</dd>
					<dt>
						<label for="active"><strong>', $txt['shd_admin_custom_field_active'], '</strong></label>
					</dt>
					<dd>
						<input type="checkbox" name="active" id="active" value="1"', !empty($context['field_active']) ? ' checked="checked"' : '', ' />
					</dd>
					<dt>
						<label for="field_type"><strong>', $txt['shd_admin_custom_field_type'], '</strong></label>
					</dt>
					<dd>
						<select name="field_type" id="field_type" onchange="shd_update_field_type();">';

	foreach ($context['field_types'] as $type_key => $type_label)
	{
		echo '
							<option value="', $type_key, '"', (isset($context['field_type_value']) && $context['field_type_value'] == $type_key ? ' selected="selected"' : ''), '>', $type_label, '</option>';
	}

	echo '
						</select>
					</dd>
					<dt>
						<label for="field_visible"><strong>', $txt['shd_admin_custom_field_location'], '</strong></label>
					</dt>
					<dd>
						<select name="field_visible" id="field_visible">
							<option value="', CFIELD_TICKET, '"', (isset($context['field_loc']) && $context['field_loc'] == CFIELD_TICKET ? ' selected="selected"' : ''), '>', $txt['shd_admin_custom_field_loc_ticket'], '</option>
							<option value="', CFIELD_REPLY, '"', (isset($context['field_loc']) && $context['field_loc'] == CFIELD_REPLY ? ' selected="selected"' : ''), '>', $txt['shd_admin_custom_field_loc_reply'], '</option>
							<option value="', CFIELD_TICKETREPLY, '"', (isset($context['field_loc']) && $context['field_loc'] == CFIELD_TICKETREPLY ? ' selected="selected"' : ''), '>', $txt['shd_admin_custom_field_loc_both'], '</option>
						</select>
					</dd>
					<dt>
						<label for="field_length"><strong>', $txt['shd_admin_custom_field_maxlength'], '</strong></label>
					</dt>
					<dd>
						<input type="text" name="field_length" id="field_length" value="', !empty($context['custom_field']['field_length']) ? $context['custom_field']['field_length'] : '255', '" size="5" />
					</dd>
					<dt>
						<label for="bbc"><strong>', $txt['shd_admin_custom_field_bbc'], '</strong></label>
					</dt>
					<dd>
						<input type="checkbox" name="bbc" id="bbc" value="1"', !empty($context['custom_field']['bbc']) ? ' checked="checked"' : '', ' />
					</dd>
					<dt>
						<label for="display_empty"><strong>', $txt['shd_admin_custom_field_display_empty'], '</strong></label>
					</dt>
					<dd>
						<input type="checkbox" name="display_empty" id="display_empty" value="1"', !empty($context['custom_field']['display_empty']) ? ' checked="checked"' : '', ' />
					</dd>
					<dt>
						<label for="placement"><strong>', $txt['shd_admin_custom_field_placement'], '</strong></label>
					</dt>
					<dd>
						<select name="placement" id="placement">
							<option value="1"', (isset($context['custom_field']['placement']) && $context['custom_field']['placement'] == 1 ? ' selected="selected"' : ''), '>', $txt['shd_admin_custom_field_place_details'], '</option>
							<option value="2"', (isset($context['custom_field']['placement']) && $context['custom_field']['placement'] == 2 ? ' selected="selected"' : ''), '>', $txt['shd_admin_custom_field_place_info'], '</option>
							<option value="3"', (isset($context['custom_field']['placement']) && $context['custom_field']['placement'] == 3 ? ' selected="selected"' : ''), '>', $txt['shd_admin_custom_field_place_prefix'], '</option>
							<option value="4"', (isset($context['custom_field']['placement']) && $context['custom_field']['placement'] == 4 ? ' selected="selected"' : ''), '>', $txt['shd_admin_custom_field_place_prefixfilter'], '</option>
						</select>
					</dd>
				</dl>
			</div>';

	// ---- Visibility section ----
	echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['shd_admin_custom_field_visibility'], '</h3>
			</div>
			<div class="content">
				<dl class="settings">
					<dt>
						<label for="see_users"><strong>', $txt['shd_admin_custom_field_can_see_users'], '</strong></label>
					</dt>
					<dd>
						<input type="checkbox" name="see_users" id="see_users" value="1"', !empty($context['custom_field']['can_see'][0]) ? ' checked="checked"' : '', ' />
					</dd>
					<dt>
						<label for="edit_users"><strong>', $txt['shd_admin_custom_field_can_edit_users'], '</strong></label>
					</dt>
					<dd>
						<input type="checkbox" name="edit_users" id="edit_users" value="1"', !empty($context['custom_field']['can_edit'][0]) ? ' checked="checked"' : '', ' />
					</dd>
					<dt>
						<label for="see_staff"><strong>', $txt['shd_admin_custom_field_can_see_staff'], '</strong></label>
					</dt>
					<dd>
						<input type="checkbox" name="see_staff" id="see_staff" value="1"', !empty($context['custom_field']['can_see'][1]) ? ' checked="checked"' : '', ' />
					</dd>
					<dt>
						<label for="edit_staff"><strong>', $txt['shd_admin_custom_field_can_edit_staff'], '</strong></label>
					</dt>
					<dd>
						<input type="checkbox" name="edit_staff" id="edit_staff" value="1"', !empty($context['custom_field']['can_edit'][1]) ? ' checked="checked"' : '', ' />
					</dd>
				</dl>
			</div>';

	// ---- Select/Radio/Multi options section ----
	echo '
			<div id="field_options_section">
				<div class="cat_bar">
					<h3 class="catbg">', $txt['shd_admin_custom_field_options'], '</h3>
				</div>
				<div class="content">';

	if (!empty($context['custom_field']['options']))
	{
		echo '
					<table class="table_grid" style="width: 100%;">
						<thead>
							<tr class="table_head">
								<th scope="col">', $txt['shd_admin_custom_field_options'], '</th>
								<th scope="col" style="width: 10%;">', $txt['shd_admin_custom_field_default'], '</th>
								<th scope="col" style="width: 10%;">', $txt['shd_admin_dept_move'], '</th>
							</tr>
						</thead>
						<tbody>';

		foreach ($context['custom_field']['options'] as $key => $option)
		{
			echo '
							<tr class="windowbg">
								<td>
									<input type="text" name="select_option[', $key, ']" value="', $option, '" size="40" />
								</td>
								<td class="centertext">
									<input type="radio" name="default_select" value="', $key, '"', (isset($context['custom_field']['default_value']) && $context['custom_field']['default_value'] == $key ? ' checked="checked"' : ''), ' />
								</td>
								<td class="centertext">';

			// Move up/down for options.
			if ($key > 0)
				echo '<a href="#" onclick="shd_move_option(', $key, ', \'up\'); return false;">', $txt['shd_admin_move_up'], '</a>';

			if ($key > 0 && $key < count($context['custom_field']['options']) - 1)
				echo ' / ';

			if ($key < count($context['custom_field']['options']) - 1)
				echo '<a href="#" onclick="shd_move_option(', $key, ', \'down\'); return false;">', $txt['shd_admin_move_down'], '</a>';

			echo '
								</td>
							</tr>';
		}

		echo '
						</tbody>
					</table>';
	}

	echo '
					<div style="padding: 8px 0;">
						<a href="#" onclick="shd_add_option(); return false;">[', $txt['shd_admin_custom_field_add_option'], ']</a>
					</div>
				</div>
			</div>';

	// ---- Textarea dimensions section ----
	echo '
			<div id="field_dimensions_section">
				<div class="cat_bar">
					<h3 class="catbg">', $txt['shd_admin_custom_field_dimensions'], '</h3>
				</div>
				<div class="content">
					<dl class="settings">
						<dt>
							<label for="rows"><strong>', $txt['shd_admin_custom_field_rows'], '</strong></label>
						</dt>
						<dd>
							<input type="text" name="rows" id="rows" value="', !empty($context['custom_field']['rows']) ? $context['custom_field']['rows'] : '4', '" size="5" />
						</dd>
						<dt>
							<label for="cols"><strong>', $txt['shd_admin_custom_field_cols'], '</strong></label>
						</dt>
						<dd>
							<input type="text" name="cols" id="cols" value="', !empty($context['custom_field']['cols']) ? $context['custom_field']['cols'] : '30', '" size="5" />
						</dd>
					</dl>
				</div>
			</div>';

	// ---- Department assignment section ----
	echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['shd_admin_custom_field_dept_assignment'], '</h3>
			</div>
			<div class="content">';

	if (!empty($context['dept_fields']))
	{
		echo '
				<table class="table_grid" style="width: 100%;">
					<thead>
						<tr class="table_head">
							<th scope="col" style="width: 5%;">&nbsp;</th>
							<th scope="col">', $txt['shd_admin_dept_name'], '</th>
							<th scope="col" style="width: 15%;">', $txt['shd_admin_custom_field_dept_required'], '</th>
						</tr>
					</thead>
					<tbody>';

		foreach ($context['dept_fields'] as $dept)
		{
			echo '
						<tr class="windowbg">
							<td class="centertext">
								<input type="checkbox" name="present_dept', $dept['id_dept'], '" value="1"', !empty($dept['present']) ? ' checked="checked"' : '', ' />
							</td>
							<td>', $dept['dept_name'], '</td>
							<td class="centertext">';

			// If the field type supports multi-value required counts, use a number input.
			if (!empty($context['field_is_multi']))
			{
				echo '
								<input type="number" name="required_dept_multi_', $dept['id_dept'], '" value="', !empty($dept['required']) ? $dept['required'] : '0', '" size="3" min="0" />';
			}
			else
			{
				echo '
								<input type="checkbox" name="required_dept', $dept['id_dept'], '" value="1"', !empty($dept['required']) ? ' checked="checked"' : '', ' />';
			}

			echo '
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
				<p class="description">', $txt['shd_admin_dept_none'], '</p>';
	}

	echo '
			</div>';

	// ---- Submit / Delete / Cancel buttons ----
	echo '
			<div class="submitbutton">
				<input type="submit" name="save" value="', $txt['shd_admin_custom_field_save'], '" class="button_submit" />';

	// Delete button only for existing fields.
	if ($is_edit)
	{
		echo '
				<input type="submit" name="delete" value="', $txt['shd_admin_custom_field_delete'], '" class="button_submit" onclick="return confirm(\'', $txt['shd_admin_custom_field_delete_confirm'], '\');" />';
	}

	echo '
				<input type="submit" name="cancel" value="', $txt['shd_admin_custom_field_cancel'], '" class="button_submit" />
			</div>
		</form>';

	// JavaScript to toggle field-type-specific sections.
	echo '
		<script>
			function shd_update_field_type()
			{
				var fieldType = document.getElementById("field_type").value;
				var optionsSection = document.getElementById("field_options_section");
				var dimensionsSection = document.getElementById("field_dimensions_section");

				// Show options section for select, radio, and multi types.
				if (fieldType === "select" || fieldType === "radio" || fieldType === "multi")
				{
					optionsSection.style.display = "";
				}
				else
				{
					optionsSection.style.display = "none";
				}

				// Show dimensions section for largetext type.
				if (fieldType === "largetext")
				{
					dimensionsSection.style.display = "";
				}
				else
				{
					dimensionsSection.style.display = "none";
				}
			}

			function shd_add_option()
			{
				var tbody = document.querySelector("#field_options_section tbody");
				if (!tbody)
					return;

				var rows = tbody.querySelectorAll("tr");
				var newIndex = rows.length;
				var newRow = document.createElement("tr");
				newRow.className = "windowbg";
				newRow.innerHTML = \'<td><input type="text" name="select_option[\' + newIndex + \']" value="" size="40" /></td>\' +
					\'<td class="centertext"><input type="radio" name="default_select" value="\' + newIndex + \'" /></td>\' +
					\'<td class="centertext"></td>\';
				tbody.appendChild(newRow);
			}

			function shd_move_option(index, direction)
			{
				var tbody = document.querySelector("#field_options_section tbody");
				if (!tbody)
					return;

				var rows = tbody.querySelectorAll("tr");
				if (direction === "up" && index > 0)
				{
					tbody.insertBefore(rows[index], rows[index - 1]);
				}
				else if (direction === "down" && index < rows.length - 1)
				{
					tbody.insertBefore(rows[index + 1], rows[index]);
				}
			}

			// Initialize visibility on page load.
			shd_update_field_type();
		</script>
	</div>';
}
