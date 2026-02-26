<?php
/**
 * SimpleDesk Custom Field Management Controller
 *
 * Handles administration of custom ticket/reply fields: listing, creating,
 * editing, reordering, saving, and deleting custom fields. Also manages
 * field-to-department assignments, visibility/edit permissions, and field
 * type conversions.
 *
 * @package SimpleDesk
 * @version 2.1.5
 * @license BSD 3-Clause License
 *
 * Copyright (c) 2025, SimpleDesk Team
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 * 3. Neither the name of the copyright holder nor the names of its contributors
 *    may be used to endorse or promote products derived from this software
 *    without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Controller for managing helpdesk custom fields.
 *
 * Dispatched from ManageSimpleDesk_Controller when sa=custom_fields.
 * Uses the 'do' parameter for internal sub-action routing since 'sa'
 * is already consumed by the parent controller.
 *
 * URL pattern: action=admin;area=helpdesk;sa=custom_fields;do=X
 */
class ManageSimpleDeskCustomField_Controller extends Action_Controller
{
	/**
	 * Entry point for custom field management.
	 *
	 * Routes to the appropriate sub-action based on $_REQUEST['do'].
	 * Loads required language and template files, initializes the
	 * field type mapping array used across all sub-actions, and sets
	 * up the admin tab context.
	 */
	public function action_index()
	{
		global $context, $txt, $scripturl;

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		isAllowedTo('admin_forum');

		loadLanguage('SimpleDesk');
		loadLanguage('SimpleDeskAdmin');
		loadTemplate('SimpleDeskAdminCustomField');

		// Field type constant-to-label mapping. Used by list, edit, and save actions.
		$context['field_types'] = array(
			CFIELD_TYPE_TEXT => isset($txt['shd_cf_type_text']) ? $txt['shd_cf_type_text'] : 'Text',
			CFIELD_TYPE_LARGETEXT => isset($txt['shd_cf_type_largetext']) ? $txt['shd_cf_type_largetext'] : 'Large text',
			CFIELD_TYPE_INT => isset($txt['shd_cf_type_int']) ? $txt['shd_cf_type_int'] : 'Integer',
			CFIELD_TYPE_FLOAT => isset($txt['shd_cf_type_float']) ? $txt['shd_cf_type_float'] : 'Floating point',
			CFIELD_TYPE_SELECT => isset($txt['shd_cf_type_select']) ? $txt['shd_cf_type_select'] : 'Select dropdown',
			CFIELD_TYPE_CHECKBOX => isset($txt['shd_cf_type_checkbox']) ? $txt['shd_cf_type_checkbox'] : 'Checkbox',
			CFIELD_TYPE_RADIO => isset($txt['shd_cf_type_radio']) ? $txt['shd_cf_type_radio'] : 'Radio buttons',
			CFIELD_TYPE_MULTI => isset($txt['shd_cf_type_multi']) ? $txt['shd_cf_type_multi'] : 'Multi-select',
		);

		$subActions = array(
			'list' => 'action_list',
			'new' => 'action_new',
			'edit' => 'action_edit',
			'move' => 'action_move',
			'save' => 'action_save',
		);

		$do = isset($_REQUEST['do']) ? $_REQUEST['do'] : 'list';

		if (isset($subActions[$do]))
			$this->{$subActions[$do]}();
		else
			$this->action_list();
	}

	/**
	 * Lists all custom fields.
	 *
	 * Queries all fields ordered by field_order, processes each row to
	 * determine active status string, field type label, visibility/edit
	 * permissions, and parsed BBC description. Marks first/last fields
	 * for move arrow display.
	 */
	public function action_list()
	{
		global $context, $txt, $scripturl;

		$db = database();

		$context['page_title'] = isset($txt['shd_admin_custom_fields_title']) ? $txt['shd_admin_custom_fields_title'] : 'Custom Fields';
		$context['sub_template'] = 'shd_custom_field_home';

		$context['custom_fields'] = array();

		$request = shd_db_query('', '
			SELECT id_field, active, field_order, field_name, field_desc, field_loc,
				icon, field_type, field_length, field_options, bbc, default_value,
				can_see, can_edit, display_empty, placement
			FROM {db_prefix}helpdesk_custom_fields
			ORDER BY field_order ASC',
			array()
		);

		while ($row = $db->fetch_assoc($request))
		{
			$field_id = (int) $row['id_field'];

			// Active status string
			$active_string = !empty($row['active'])
				? (isset($txt['shd_cf_active']) ? $txt['shd_cf_active'] : 'Active')
				: (isset($txt['shd_cf_inactive']) ? $txt['shd_cf_inactive'] : 'Inactive');

			// Field type display string
			$field_type_key = isset($context['field_types'][(int) $row['field_type']])
				? $context['field_types'][(int) $row['field_type']]
				: (isset($txt['shd_cf_type_unknown']) ? $txt['shd_cf_type_unknown'] : 'Unknown');

			// Visibility and edit permissions
			$can_see = explode(',', $row['can_see']);
			$can_edit = explode(',', $row['can_edit']);

			// Parse BBC in the description for display
			$field_desc = !empty($row['bbc']) ? parse_bbc($row['field_desc']) : $row['field_desc'];

			// Field location label
			$field_loc_labels = array(
				CFIELD_TICKET => isset($txt['shd_cf_loc_ticket']) ? $txt['shd_cf_loc_ticket'] : 'Ticket',
				CFIELD_REPLY => isset($txt['shd_cf_loc_reply']) ? $txt['shd_cf_loc_reply'] : 'Reply',
				CFIELD_TICKETREPLY => isset($txt['shd_cf_loc_both']) ? $txt['shd_cf_loc_both'] : 'Both',
			);

			$context['custom_fields'][$field_id] = array(
				'id_field' => $field_id,
				'active' => !empty($row['active']),
				'active_string' => $active_string,
				'field_order' => (int) $row['field_order'],
				'field_name' => $row['field_name'],
				'field_desc' => $field_desc,
				'field_desc_raw' => $row['field_desc'],
				'field_loc' => (int) $row['field_loc'],
				'field_loc_string' => isset($field_loc_labels[(int) $row['field_loc']]) ? $field_loc_labels[(int) $row['field_loc']] : '',
				'icon' => $row['icon'],
				'field_type' => (int) $row['field_type'],
				'field_type_string' => $field_type_key,
				'field_length' => (int) $row['field_length'],
				'field_options' => $row['field_options'],
				'bbc' => !empty($row['bbc']),
				'default_value' => $row['default_value'],
				'can_see' => $can_see,
				'can_edit' => $can_edit,
				'display_empty' => !empty($row['display_empty']),
				'placement' => (int) $row['placement'],
				'is_first' => false,
				'is_last' => false,
			);
		}
		$db->free_result($request);

		// Mark first and last for move arrow display.
		if (!empty($context['custom_fields']))
		{
			$field_ids = array_keys($context['custom_fields']);
			$context['custom_fields'][$field_ids[0]]['is_first'] = true;
			$context['custom_fields'][$field_ids[count($field_ids) - 1]]['is_last'] = true;
		}
	}

	/**
	 * Displays the new custom field form.
	 *
	 * Sets up sensible defaults for a new field (text type, ticket scope,
	 * details placement, active). Loads departments for the department
	 * assignment checkboxes. Sets the edit sub-template (shared with
	 * action_edit).
	 */
	public function action_new()
	{
		global $context, $txt, $scripturl;

		$db = database();

		$context['page_title'] = isset($txt['shd_admin_custom_field_new']) ? $txt['shd_admin_custom_field_new'] : 'New Custom Field';
		$context['section_title'] = $context['page_title'];
		$context['section_desc'] = isset($txt['shd_admin_custom_field_new_desc']) ? $txt['shd_admin_custom_field_new_desc'] : 'Create a new custom field for helpdesk tickets.';
		$context['sub_template'] = 'shd_custom_field_edit';

		$context['custom_field'] = array(
			'id_field' => 0,
			'active' => true,
			'field_name' => '',
			'field_desc' => '',
			'field_loc' => CFIELD_TICKET,
			'icon' => '',
			'field_type' => CFIELD_TYPE_TEXT,
			'field_length' => 255,
			'field_options' => array(),
			'bbc' => false,
			'default_value' => '',
			'can_see' => array('0'),
			'can_edit' => array('0'),
			'display_empty' => false,
			'placement' => CFIELD_PLACE_DETAILS,
			'is_new' => true,
		);

		// For LARGETEXT, default dimensions
		$context['custom_field']['rows'] = 4;
		$context['custom_field']['cols'] = 30;

		// Template-facing values
		$context['field_type_value'] = CFIELD_TYPE_TEXT;
		$context['field_active'] = true;

		// Load icons
		$context['field_icons'] = $this->shd_admin_cf_icons();

		// All types are valid for a new field
		$context['valid_field_types'] = $this->shd_admin_cf_change_types(false);

		// Load departments for assignment
		$context['field_departments'] = array();
		$request = $db->query('', '
			SELECT id_dept, dept_name
			FROM {db_prefix}helpdesk_depts
			ORDER BY dept_order ASC',
			array()
		);

		while ($row = $db->fetch_assoc($request))
		{
			$context['field_departments'][$row['id_dept']] = array(
				'id_dept' => (int) $row['id_dept'],
				'dept_name' => $row['dept_name'],
				'present' => false,
				'required' => false,
			);
		}
		$db->free_result($request);

		checkSubmitOnce('register');
	}

	/**
	 * Displays the edit form for an existing custom field.
	 *
	 * Loads the field from the database, processes field_options (JSON decoded),
	 * parses can_see/can_edit comma-delimited strings, and for LARGETEXT fields
	 * splits the default_value on comma for row/column dimensions. Determines
	 * valid type conversions from the current type, and loads departments with
	 * LEFT JOIN to determine which departments currently have this field assigned.
	 */
	public function action_edit()
	{
		global $context, $txt, $scripturl;

		$db = database();

		$field_id = isset($_REQUEST['field']) ? (int) $_REQUEST['field'] : 0;
		if (empty($field_id))
			fatal_lang_error('shd_admin_cf_not_found', false);

		// Load the field
		$request = shd_db_query('', '
			SELECT id_field, active, field_order, field_name, field_desc, field_loc,
				icon, field_type, field_length, field_options, bbc, default_value,
				can_see, can_edit, display_empty, placement
			FROM {db_prefix}helpdesk_custom_fields
			WHERE id_field = {int:field}',
			array(
				'field' => $field_id,
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			fatal_lang_error('shd_admin_cf_not_found', false);
		}

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		$context['page_title'] = isset($txt['shd_admin_custom_field_edit'])
			? sprintf($txt['shd_admin_custom_field_edit'], $row['field_name'])
			: 'Edit Custom Field: ' . $row['field_name'];
		$context['section_title'] = $context['page_title'];
		$context['section_desc'] = isset($txt['shd_admin_custom_field_edit_desc']) ? $txt['shd_admin_custom_field_edit_desc'] : 'Edit the settings for this custom field.';
		$context['sub_template'] = 'shd_custom_field_edit';

		// Process field_options: stored as JSON
		$field_options = !empty($row['field_options']) ? json_decode($row['field_options'], true) : array();
		if (!is_array($field_options))
			$field_options = array();

		// Process can_see and can_edit: comma-delimited strings
		$can_see = explode(',', $row['can_see']);
		$can_edit = explode(',', $row['can_edit']);

		$context['custom_field'] = array(
			'id_field' => (int) $row['id_field'],
			'active' => !empty($row['active']),
			'field_order' => (int) $row['field_order'],
			'field_name' => $row['field_name'],
			'field_desc' => $row['field_desc'],
			'field_loc' => (int) $row['field_loc'],
			'icon' => $row['icon'],
			'field_type' => (int) $row['field_type'],
			'field_length' => (int) $row['field_length'],
			'field_options' => $field_options,
			'bbc' => !empty($row['bbc']),
			'default_value' => $row['default_value'],
			'can_see' => $can_see,
			'can_edit' => $can_edit,
			'display_empty' => !empty($row['display_empty']),
			'placement' => (int) $row['placement'],
			'is_new' => false,
		);

		// For LARGETEXT, split default_value on comma for rows,cols dimensions
		if ((int) $row['field_type'] === CFIELD_TYPE_LARGETEXT)
		{
			$dimensions = explode(',', $row['default_value']);
			$context['custom_field']['rows'] = isset($dimensions[0]) ? max(1, (int) $dimensions[0]) : 4;
			$context['custom_field']['cols'] = isset($dimensions[1]) ? max(1, (int) $dimensions[1]) : 30;
		}
		else
		{
			$context['custom_field']['rows'] = 4;
			$context['custom_field']['cols'] = 30;
		}

		// Template-facing values
		$context['field_type_value'] = (int) $row['field_type'];
		$context['field_active'] = !empty($row['active']);

		// Load icons
		$context['field_icons'] = $this->shd_admin_cf_icons();

		// Get valid type conversions from the current type
		$context['valid_field_types'] = $this->shd_admin_cf_change_types((int) $row['field_type']);

		// Load departments with LEFT JOIN to determine current assignments
		$context['field_departments'] = array();
		$request = $db->query('', '
			SELECT hd.id_dept, hd.dept_name,
				hcfd.id_field, hcfd.required
			FROM {db_prefix}helpdesk_depts AS hd
				LEFT JOIN {db_prefix}helpdesk_custom_fields_depts AS hcfd
					ON (hd.id_dept = hcfd.id_dept AND hcfd.id_field = {int:field})
			ORDER BY hd.dept_order ASC',
			array(
				'field' => $field_id,
			)
		);

		while ($row = $db->fetch_assoc($request))
		{
			$context['field_departments'][$row['id_dept']] = array(
				'id_dept' => (int) $row['id_dept'],
				'dept_name' => $row['dept_name'],
				'present' => !empty($row['id_field']),
				'required' => !empty($row['required']),
			);
		}
		$db->free_result($request);

		checkSubmitOnce('register');
	}

	/**
	 * Saves a custom field (new, update, or delete).
	 *
	 * Handles three main operations:
	 * - DELETE: Removes the field, its values, and department assignments, then
	 *   reorders remaining fields.
	 * - CANCEL: Redirects back to the list.
	 * - SAVE (new or update): Validates field name, sanitizes all inputs,
	 *   processes visibility/edit permissions from checkbox groups, processes
	 *   department assignments and required flags, handles select/radio/multi
	 *   options, and either inserts a new field or updates an existing one.
	 *
	 * For new fields, inserts with the next available field_order value.
	 * For updates, validates type changes against allowed conversions.
	 * Department mappings are always deleted and re-inserted.
	 * Logs the action via shd_log_action().
	 */
	public function action_save()
	{
		global $context, $txt, $scripturl;

		$db = database();

		checkSession('request');

		$field_id = isset($_REQUEST['field']) ? (int) $_REQUEST['field'] : 0;

		// ---- Handle DELETE ----
		if (isset($_POST['delete']) && $field_id > 0)
		{
			// Get field_order before deleting so we can reorder
			$request = shd_db_query('', '
				SELECT field_order, field_name
				FROM {db_prefix}helpdesk_custom_fields
				WHERE id_field = {int:field}',
				array(
					'field' => $field_id,
				)
			);

			if ($db->num_rows($request) == 0)
			{
				$db->free_result($request);
				fatal_lang_error('shd_admin_cf_not_found', false);
			}

			$field_info = $db->fetch_assoc($request);
			$db->free_result($request);

			// Delete the field
			shd_db_query('', '
				DELETE FROM {db_prefix}helpdesk_custom_fields
				WHERE id_field = {int:field}',
				array(
					'field' => $field_id,
				)
			);

			// Delete associated values
			shd_db_query('', '
				DELETE FROM {db_prefix}helpdesk_custom_fields_values
				WHERE id_field = {int:field}',
				array(
					'field' => $field_id,
				)
			);

			// Delete department assignments
			shd_db_query('', '
				DELETE FROM {db_prefix}helpdesk_custom_fields_depts
				WHERE id_field = {int:field}',
				array(
					'field' => $field_id,
				)
			);

			// Reorder remaining fields to ensure contiguous ordering
			$request = shd_db_query('', '
				SELECT id_field
				FROM {db_prefix}helpdesk_custom_fields
				ORDER BY field_order ASC',
				array()
			);

			$new_order = 1;
			while ($row = $db->fetch_assoc($request))
			{
				shd_db_query('', '
					UPDATE {db_prefix}helpdesk_custom_fields
					SET field_order = {int:new_order}
					WHERE id_field = {int:field}',
					array(
						'new_order' => $new_order,
						'field' => $row['id_field'],
					)
				);
				$new_order++;
			}
			$db->free_result($request);

			// Log the action
			shd_log_action('cf_delete', array(
				'field_name' => $field_info['field_name'],
			), false);

			redirectexit('action=admin;area=helpdesk;sa=custom_fields');
		}

		// ---- Handle CANCEL ----
		if (isset($_POST['cancel']))
			redirectexit('action=admin;area=helpdesk;sa=custom_fields');

		// ---- SAVE (new or update) ----

		// Validate field name
		$field_name = isset($_POST['field_name']) ? Util::htmltrim(Util::htmlspecialchars($_POST['field_name'])) : '';
		if (empty($field_name))
			fatal_lang_error('shd_admin_cf_no_name', false);

		// Field description
		$field_desc = isset($_POST['field_desc']) ? Util::htmlspecialchars($_POST['field_desc']) : '';

		// Field location: ticket, reply, or both
		$field_loc = isset($_POST['field_loc']) ? (int) $_POST['field_loc'] : CFIELD_TICKET;
		if (!in_array($field_loc, array(CFIELD_TICKET, CFIELD_REPLY, CFIELD_TICKETREPLY)))
			$field_loc = CFIELD_TICKET;

		// Icon
		$icon = isset($_POST['icon']) ? Util::htmlspecialchars($_POST['icon']) : '';

		// Field type
		$field_type = isset($_POST['field_type']) ? (int) $_POST['field_type'] : CFIELD_TYPE_TEXT;
		if (!isset($context['field_types'][$field_type]))
			$field_type = CFIELD_TYPE_TEXT;

		// Active status
		$active = !empty($_POST['active']) ? 1 : 0;

		// BBC support
		$bbc = !empty($_POST['bbc']) ? 1 : 0;

		// Display when empty
		$display_empty = !empty($_POST['display_empty']) ? 1 : 0;

		// Field length
		$field_length = isset($_POST['field_length']) ? max(0, (int) $_POST['field_length']) : 255;

		// Placement
		$placement = isset($_POST['placement']) ? (int) $_POST['placement'] : CFIELD_PLACE_DETAILS;
		if (!in_array($placement, array(CFIELD_PLACE_DETAILS, CFIELD_PLACE_INFO, CFIELD_PLACE_PREFIX, CFIELD_PLACE_PREFIXFILTER)))
			$placement = CFIELD_PLACE_DETAILS;

		// ---- Process visibility/edit permissions ----
		// The form provides checkboxes: see_users, see_staff, edit_users, edit_staff
		// These map to comma-separated values in can_see and can_edit.
		// 0 = users (non-staff), 2 = staff
		$can_see_parts = array();
		$can_edit_parts = array();

		if (!empty($_POST['see_users']))
			$can_see_parts[] = '0';
		if (!empty($_POST['see_staff']))
			$can_see_parts[] = '2';

		if (!empty($_POST['edit_users']))
			$can_edit_parts[] = '0';
		if (!empty($_POST['edit_staff']))
			$can_edit_parts[] = '2';

		// Default: at least staff can see/edit if nothing was selected
		$can_see = !empty($can_see_parts) ? implode(',', $can_see_parts) : '0';
		$can_edit = !empty($can_edit_parts) ? implode(',', $can_edit_parts) : '0';

		// ---- Process default value ----
		$default_value = '';

		switch ($field_type)
		{
			case CFIELD_TYPE_LARGETEXT:
				// Store as "rows,cols"
				$rows = isset($_POST['rows']) ? max(1, (int) $_POST['rows']) : 4;
				$cols = isset($_POST['cols']) ? max(1, (int) $_POST['cols']) : 30;
				$default_value = $rows . ',' . $cols;
				break;

			case CFIELD_TYPE_CHECKBOX:
				$default_value = !empty($_POST['default_check']) ? '1' : '0';
				break;

			case CFIELD_TYPE_SELECT:
			case CFIELD_TYPE_RADIO:
				// default_select is the index of the default option
				$default_value = isset($_POST['default_select']) ? (int) $_POST['default_select'] : 0;
				break;

			case CFIELD_TYPE_MULTI:
				// default_select_multi is an array of selected option indices
				if (isset($_POST['default_select_multi']) && is_array($_POST['default_select_multi']))
				{
					$defaults = array();
					foreach ($_POST['default_select_multi'] as $val)
						$defaults[] = (int) $val;
					$default_value = implode(',', $defaults);
				}
				else
					$default_value = '';
				break;

			default:
				// TEXT, INT, FLOAT
				$default_value = isset($_POST['default_value']) ? Util::htmlspecialchars($_POST['default_value']) : '';
				break;
		}

		// ---- Process select/radio/multi options ----
		$field_options = array();

		if (in_array($field_type, array(CFIELD_TYPE_SELECT, CFIELD_TYPE_RADIO, CFIELD_TYPE_MULTI)))
		{
			if (isset($_POST['select_option']) && is_array($_POST['select_option']))
			{
				$raw_options = $_POST['select_option'];
				$raw_order = isset($_POST['order']) && is_array($_POST['order']) ? $_POST['order'] : array();

				// If ordering info is provided, use it to sort options
				if (!empty($raw_order))
				{
					$ordered_options = array();
					foreach ($raw_order as $idx)
					{
						$idx = (int) $idx;
						if (isset($raw_options[$idx]))
						{
							$opt = Util::htmltrim(Util::htmlspecialchars($raw_options[$idx]));
							if ($opt !== '')
								$ordered_options[] = $opt;
						}
					}
					$field_options = $ordered_options;
				}
				else
				{
					// No ordering, just take them in POST order
					foreach ($raw_options as $opt)
					{
						$opt = Util::htmltrim(Util::htmlspecialchars($opt));
						if ($opt !== '')
							$field_options[] = $opt;
					}
				}
			}
		}

		// Encode field_options as JSON for storage
		$field_options_encoded = json_encode($field_options);

		// ---- Process department assignments ----
		$dept_assignments = array();

		$request = $db->query('', '
			SELECT id_dept
			FROM {db_prefix}helpdesk_depts
			ORDER BY dept_order ASC',
			array()
		);

		while ($row = $db->fetch_assoc($request))
		{
			$dept_id = (int) $row['id_dept'];

			// Check if this department is checked as "present"
			if (!empty($_POST['present_dept' . $dept_id]))
			{
				// Determine required status
				$required = 0;

				if ($field_type === CFIELD_TYPE_MULTI)
				{
					// Multi-select fields use required_dept_multi_{id}
					if (!empty($_POST['required_dept_multi_' . $dept_id]))
						$required = (int) $_POST['required_dept_multi_' . $dept_id];
				}
				else
				{
					if (!empty($_POST['required_dept' . $dept_id]))
						$required = 1;
				}

				$dept_assignments[$dept_id] = $required;
			}
		}
		$db->free_result($request);

		// ---- INSERT (new field) ----
		if (empty($field_id))
		{
			// Get next field_order value
			$request = shd_db_query('', '
				SELECT MAX(field_order)
				FROM {db_prefix}helpdesk_custom_fields',
				array()
			);
			list($max_order) = $db->fetch_row($request);
			$db->free_result($request);

			$new_order = (int) $max_order + 1;

			$db->insert('insert',
				'{db_prefix}helpdesk_custom_fields',
				array(
					'active' => 'int',
					'field_order' => 'int',
					'field_name' => 'string',
					'field_desc' => 'string',
					'field_loc' => 'int',
					'icon' => 'string',
					'field_type' => 'int',
					'field_length' => 'int',
					'field_options' => 'string',
					'bbc' => 'int',
					'default_value' => 'string',
					'can_see' => 'string',
					'can_edit' => 'string',
					'display_empty' => 'int',
					'placement' => 'int',
				),
				array(
					$active,
					$new_order,
					$field_name,
					$field_desc,
					$field_loc,
					$icon,
					$field_type,
					$field_length,
					$field_options_encoded,
					$bbc,
					$default_value,
					$can_see,
					$can_edit,
					$display_empty,
					$placement,
				),
				array('id_field')
			);

			$field_id = $db->insert_id('{db_prefix}helpdesk_custom_fields', 'id_field');

			// Insert department mappings
			foreach ($dept_assignments as $dept_id => $required)
			{
				$db->insert('insert',
					'{db_prefix}helpdesk_custom_fields_depts',
					array(
						'id_field' => 'int',
						'id_dept' => 'int',
						'required' => 'int',
					),
					array(
						$field_id,
						$dept_id,
						$required,
					),
					array('id_field', 'id_dept')
				);
			}

			// Log the action
			shd_log_action('cf_new', array(
				'field_name' => $field_name,
			), false);
		}
		// ---- UPDATE (existing field) ----
		else
		{
			// Verify the field exists and get current type for conversion validation
			$request = shd_db_query('', '
				SELECT field_type, field_name
				FROM {db_prefix}helpdesk_custom_fields
				WHERE id_field = {int:field}',
				array(
					'field' => $field_id,
				)
			);

			if ($db->num_rows($request) == 0)
			{
				$db->free_result($request);
				fatal_lang_error('shd_admin_cf_not_found', false);
			}

			$current_field = $db->fetch_assoc($request);
			$db->free_result($request);

			$current_type = (int) $current_field['field_type'];

			// Validate type change is allowed
			if ($field_type !== $current_type)
			{
				$valid_types = $this->shd_admin_cf_change_types($current_type);
				if (!in_array($field_type, $valid_types))
					$field_type = $current_type; // Revert to current type if change not allowed
			}

			// Update the field
			shd_db_query('', '
				UPDATE {db_prefix}helpdesk_custom_fields
				SET active = {int:active},
					field_name = {string:field_name},
					field_desc = {string:field_desc},
					field_loc = {int:field_loc},
					icon = {string:icon},
					field_type = {int:field_type},
					field_length = {int:field_length},
					field_options = {string:field_options},
					bbc = {int:bbc},
					default_value = {string:default_value},
					can_see = {string:can_see},
					can_edit = {string:can_edit},
					display_empty = {int:display_empty},
					placement = {int:placement}
				WHERE id_field = {int:field}',
				array(
					'active' => $active,
					'field_name' => $field_name,
					'field_desc' => $field_desc,
					'field_loc' => $field_loc,
					'icon' => $icon,
					'field_type' => $field_type,
					'field_length' => $field_length,
					'field_options' => $field_options_encoded,
					'bbc' => $bbc,
					'default_value' => $default_value,
					'can_see' => $can_see,
					'can_edit' => $can_edit,
					'display_empty' => $display_empty,
					'placement' => $placement,
					'field' => $field_id,
				)
			);

			// Delete existing department mappings and re-insert
			shd_db_query('', '
				DELETE FROM {db_prefix}helpdesk_custom_fields_depts
				WHERE id_field = {int:field}',
				array(
					'field' => $field_id,
				)
			);

			foreach ($dept_assignments as $dept_id => $required)
			{
				$db->insert('insert',
					'{db_prefix}helpdesk_custom_fields_depts',
					array(
						'id_field' => 'int',
						'id_dept' => 'int',
						'required' => 'int',
					),
					array(
						$field_id,
						$dept_id,
						$required,
					),
					array('id_field', 'id_dept')
				);
			}

			// Log the action
			shd_log_action('cf_edit', array(
				'field_name' => $field_name,
			), false);
		}

		redirectexit('action=admin;area=helpdesk;sa=custom_fields');
	}

	/**
	 * Moves a custom field up or down in the display order.
	 *
	 * Validates the session (GET), loads all field ordering values,
	 * finds the adjacent field, and swaps their field_order values.
	 * Logs the action and redirects back to the field list.
	 */
	public function action_move()
	{
		global $scripturl;

		$db = database();

		checkSession('get');

		$field_id = isset($_REQUEST['field']) ? (int) $_REQUEST['field'] : 0;
		$direction = isset($_REQUEST['direction']) ? $_REQUEST['direction'] : '';

		if (empty($field_id) || !in_array($direction, array('up', 'down')))
			fatal_lang_error('shd_admin_cf_not_found', false);

		// Load all fields in order.
		$fields = array();
		$request = shd_db_query('', '
			SELECT id_field, field_order
			FROM {db_prefix}helpdesk_custom_fields
			ORDER BY field_order ASC',
			array()
		);

		while ($row = $db->fetch_assoc($request))
		{
			$fields[] = array(
				'id_field' => (int) $row['id_field'],
				'field_order' => (int) $row['field_order'],
			);
		}
		$db->free_result($request);

		// Find the current field's position in the ordered list.
		$current_index = null;
		foreach ($fields as $index => $field)
		{
			if ($field['id_field'] == $field_id)
			{
				$current_index = $index;
				break;
			}
		}

		if ($current_index === null)
			fatal_lang_error('shd_admin_cf_not_found', false);

		// Determine the swap target.
		if ($direction === 'up' && $current_index > 0)
			$swap_index = $current_index - 1;
		elseif ($direction === 'down' && $current_index < count($fields) - 1)
			$swap_index = $current_index + 1;
		else
			redirectexit('action=admin;area=helpdesk;sa=custom_fields');

		// Swap the field_order values between current and adjacent.
		shd_db_query('', '
			UPDATE {db_prefix}helpdesk_custom_fields
			SET field_order = {int:new_order}
			WHERE id_field = {int:field}',
			array(
				'new_order' => $fields[$swap_index]['field_order'],
				'field' => $fields[$current_index]['id_field'],
			)
		);

		shd_db_query('', '
			UPDATE {db_prefix}helpdesk_custom_fields
			SET field_order = {int:new_order}
			WHERE id_field = {int:field}',
			array(
				'new_order' => $fields[$current_index]['field_order'],
				'field' => $fields[$swap_index]['id_field'],
			)
		);

		// Log the action
		shd_log_action('cf_move', array(
			'field' => $field_id,
			'direction' => $direction,
		), false);

		redirectexit('action=admin;area=helpdesk;sa=custom_fields');
	}

	/**
	 * Returns the array of allowed field type conversions from a given type.
	 *
	 * When creating a new field ($from_type is false), all types are valid.
	 * For existing fields, only certain type conversions preserve data integrity:
	 *
	 * - TEXT/LARGETEXT can convert between each other
	 * - INT can convert to FLOAT (widening)
	 * - FLOAT stays FLOAT only (narrowing to INT would lose precision)
	 * - SELECT/RADIO can convert to SELECT, RADIO, or MULTI (all list-based)
	 * - CHECKBOX stays CHECKBOX only
	 * - MULTI stays MULTI only
	 *
	 * @param int|false $from_type The current field type constant, or false for new fields.
	 * @return array Array of CFIELD_TYPE_* constants that are valid targets.
	 */
	private function shd_admin_cf_change_types($from_type)
	{
		// New field: all types available
		if ($from_type === false)
		{
			return array(
				CFIELD_TYPE_TEXT,
				CFIELD_TYPE_LARGETEXT,
				CFIELD_TYPE_INT,
				CFIELD_TYPE_FLOAT,
				CFIELD_TYPE_SELECT,
				CFIELD_TYPE_CHECKBOX,
				CFIELD_TYPE_RADIO,
				CFIELD_TYPE_MULTI,
			);
		}

		switch ($from_type)
		{
			case CFIELD_TYPE_TEXT:
			case CFIELD_TYPE_LARGETEXT:
				return array(CFIELD_TYPE_TEXT, CFIELD_TYPE_LARGETEXT);

			case CFIELD_TYPE_INT:
				return array(CFIELD_TYPE_INT, CFIELD_TYPE_FLOAT);

			case CFIELD_TYPE_FLOAT:
				return array(CFIELD_TYPE_FLOAT);

			case CFIELD_TYPE_SELECT:
			case CFIELD_TYPE_RADIO:
				return array(CFIELD_TYPE_SELECT, CFIELD_TYPE_RADIO, CFIELD_TYPE_MULTI);

			case CFIELD_TYPE_CHECKBOX:
				return array(CFIELD_TYPE_CHECKBOX);

			case CFIELD_TYPE_MULTI:
				return array(CFIELD_TYPE_MULTI);

			default:
				return array($from_type);
		}
	}

	/**
	 * Returns an array of available icon options for custom fields.
	 *
	 * Scans the Themes/default/images/simpledesk/cf/ directory for image files.
	 * Returns an array of [filename, display_name] pairs where display_name
	 * is the filename without extension. If the directory does not exist,
	 * returns an array with only a 'None' option.
	 *
	 * @return array Array of arrays, each containing [filename, display_name].
	 */
	private function shd_admin_cf_icons()
	{
		global $settings;

		$icons = array(
			array('', isset($GLOBALS['txt']['shd_cf_icon_none']) ? $GLOBALS['txt']['shd_cf_icon_none'] : 'None'),
		);

		$icon_dir = $settings['default_theme_dir'] . '/images/simpledesk/cf';

		if (!is_dir($icon_dir))
			return $icons;

		$dir = @opendir($icon_dir);
		if (!$dir)
			return $icons;

		$image_extensions = array('png', 'gif', 'jpg', 'jpeg', 'svg');

		while (($file = readdir($dir)) !== false)
		{
			if ($file === '.' || $file === '..')
				continue;

			$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
			if (in_array($ext, $image_extensions))
			{
				$display_name = pathinfo($file, PATHINFO_FILENAME);
				$icons[] = array($file, $display_name);
			}
		}
		closedir($dir);

		return $icons;
	}
}
