<?php
/**
 * SimpleDesk Post Subs
 *
 * Helper functions for ticket and reply posting: creating/modifying
 * tickets and replies, custom field handling, urgency options,
 * and department helpers.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Creates a new ticket or reply in the database.
 *
 * For a new ticket, this creates both the ticket record and its first message.
 * For a reply, this creates a new message and updates the ticket's counters.
 *
 * @param array &$msgOptions Message options (body, smileys_enabled, etc.)
 * @param array &$ticketOptions Ticket options (id, dept, subject, urgency, status, private, etc.)
 * @param array &$posterOptions Poster info (id, name, email, ip)
 * @return bool True on success.
 */
function shd_create_ticket_post(&$msgOptions, &$ticketOptions, &$posterOptions)
{
	global $user_info, $modSettings;

	$db = database();
	$is_new_ticket = empty($ticketOptions['id']);

	// Insert the message
	$db->insert('',
		'{db_prefix}helpdesk_ticket_replies',
		array(
			'id_ticket' => 'int',
			'body' => 'string',
			'id_member' => 'int',
			'poster_time' => 'int',
			'poster_name' => 'string',
			'poster_email' => 'string',
			'poster_ip' => 'string',
			'smileys_enabled' => 'int',
			'modified_time' => 'int',
			'modified_member' => 'int',
			'modified_name' => 'string',
			'message_status' => 'int',
		),
		array(
			$is_new_ticket ? 0 : $ticketOptions['id'],
			$msgOptions['body'],
			$posterOptions['id'],
			time(),
			$posterOptions['name'],
			!empty($posterOptions['email']) ? $posterOptions['email'] : '',
			!empty($posterOptions['ip']) ? $posterOptions['ip'] : '',
			!empty($msgOptions['smileys_enabled']) ? 1 : 0,
			0,
			0,
			'',
			MSG_STATUS_NORMAL,
		),
		array('id_msg')
	);
	$msgOptions['id'] = $db->insert_id('{db_prefix}helpdesk_ticket_replies');

	if (empty($msgOptions['id']))
		return false;

	if ($is_new_ticket)
	{
		// Create the ticket record
		$db->insert('',
			'{db_prefix}helpdesk_tickets',
			array(
				'id_dept' => 'int',
				'id_first_msg' => 'int',
				'id_member_started' => 'int',
				'id_last_msg' => 'int',
				'id_member_updated' => 'int',
				'id_member_assigned' => 'int',
				'num_replies' => 'int',
				'deleted_replies' => 'int',
				'subject' => 'string',
				'urgency' => 'int',
				'status' => 'int',
				'private' => 'int',
				'withdeleted' => 'int',
				'last_updated' => 'int',
			),
			array(
				!empty($ticketOptions['dept']) ? $ticketOptions['dept'] : 0,
				$msgOptions['id'],
				$posterOptions['id'],
				$msgOptions['id'],
				$posterOptions['id'],
				!empty($ticketOptions['assigned']) ? $ticketOptions['assigned'] : 0,
				0,
				0,
				$ticketOptions['subject'],
				!empty($ticketOptions['urgency']) ? $ticketOptions['urgency'] : TICKET_URGENCY_LOW,
				!empty($ticketOptions['status']) ? $ticketOptions['status'] : TICKET_STATUS_NEW,
				!empty($ticketOptions['private']) ? 1 : 0,
				0,
				time(),
			),
			array('id_ticket')
		);
		$ticketOptions['id'] = $db->insert_id('{db_prefix}helpdesk_tickets');

		if (empty($ticketOptions['id']))
			return false;

		// Update the message with the ticket ID
		$db->query('', '
			UPDATE {db_prefix}helpdesk_ticket_replies
			SET id_ticket = {int:ticket}
			WHERE id_msg = {int:msg}',
			array(
				'ticket' => $ticketOptions['id'],
				'msg' => $msgOptions['id'],
			)
		);
	}
	else
	{
		// This is a reply to an existing ticket - update ticket counters
		$db->query('', '
			UPDATE {db_prefix}helpdesk_tickets
			SET id_last_msg = {int:msg},
				id_member_updated = {int:member},
				num_replies = num_replies + 1,
				status = {int:status},
				last_updated = {int:time}
			WHERE id_ticket = {int:ticket}',
			array(
				'msg' => $msgOptions['id'],
				'member' => $posterOptions['id'],
				'status' => $ticketOptions['status'],
				'time' => time(),
				'ticket' => $ticketOptions['id'],
			)
		);
	}

	// Mark as read for the poster
	if (!empty($posterOptions['id']))
	{
		$db->insert('replace',
			'{db_prefix}helpdesk_log_read',
			array(
				'id_ticket' => 'int',
				'id_member' => 'int',
				'id_msg' => 'int',
			),
			array(
				$ticketOptions['id'],
				$posterOptions['id'],
				$msgOptions['id'],
			),
			array('id_ticket', 'id_member')
		);
	}

	// If there is a proxy user (staff posting on behalf of user), mark read for staff too
	if (!empty($ticketOptions['mark_as_read_proxy']))
	{
		$db->insert('replace',
			'{db_prefix}helpdesk_log_read',
			array(
				'id_ticket' => 'int',
				'id_member' => 'int',
				'id_msg' => 'int',
			),
			array(
				$ticketOptions['id'],
				$ticketOptions['mark_as_read_proxy'],
				$msgOptions['id'],
			),
			array('id_ticket', 'id_member')
		);
	}

	// Save custom field values if provided
	if (!empty($ticketOptions['custom_fields']))
	{
		foreach ($ticketOptions['custom_fields'] as $field_id => $value)
		{
			$post_id = $is_new_ticket ? $ticketOptions['id'] : $msgOptions['id'];
			$post_type = $is_new_ticket ? CFIELD_TICKET : CFIELD_REPLY;

			$db->insert('replace',
				'{db_prefix}helpdesk_custom_fields_values',
				array(
					'id_post' => 'int',
					'id_field' => 'int',
					'value' => 'string',
					'post_type' => 'int',
				),
				array(
					$post_id,
					$field_id,
					$value,
					$post_type,
				),
				array('id_post', 'id_field')
			);
		}
	}

	// Link attachments if provided
	if (!empty($msgOptions['attachments']))
	{
		$attach_rows = array();
		foreach ($msgOptions['attachments'] as $attach_id)
			$attach_rows[] = array((int) $attach_id, (int) $msgOptions['id'], (int) $ticketOptions['id']);

		if (!empty($attach_rows))
		{
			$db->insert('replace',
				'{db_prefix}helpdesk_attachments',
				array('id_attach' => 'int', 'id_msg' => 'int', 'id_ticket' => 'int'),
				$attach_rows,
				array('id_attach')
			);
		}
	}

	// Clear the active tickets cache
	shd_clear_active_tickets(!empty($ticketOptions['dept']) ? $ticketOptions['dept'] : 0);

	return true;
}

/**
 * Modifies an existing ticket or reply in the database.
 *
 * @param array &$msgOptions Message options (id, body, etc.)
 * @param array &$ticketOptions Ticket options (id, subject, urgency, etc.)
 * @param array &$posterOptions Poster/modifier info.
 * @return bool True on success.
 */
function shd_modify_ticket_post(&$msgOptions, &$ticketOptions, &$posterOptions)
{
	global $user_info;

	$db = database();

	// Update the message body if provided
	if (!empty($msgOptions['id']) && isset($msgOptions['body']))
	{
		$db->query('', '
			UPDATE {db_prefix}helpdesk_ticket_replies
			SET body = {string:body},
				modified_time = {int:modified_time},
				modified_member = {int:modified_member},
				modified_name = {string:modified_name}' . (isset($msgOptions['smileys_enabled']) ? ',
				smileys_enabled = {int:smileys}' : '') . '
			WHERE id_msg = {int:msg}',
			array(
				'body' => $msgOptions['body'],
				'modified_time' => time(),
				'modified_member' => $user_info['id'],
				'modified_name' => $user_info['name'],
				'smileys' => !empty($msgOptions['smileys_enabled']) ? 1 : 0,
				'msg' => $msgOptions['id'],
			)
		);
	}

	// Update ticket-level fields if this is a ticket edit (not just a reply edit)
	if (!empty($ticketOptions['id']))
	{
		$set_clauses = array();
		$params = array('ticket' => $ticketOptions['id']);

		if (isset($ticketOptions['subject']))
		{
			$set_clauses[] = 'subject = {string:subject}';
			$params['subject'] = $ticketOptions['subject'];
		}

		if (isset($ticketOptions['urgency']))
		{
			$set_clauses[] = 'urgency = {int:urgency}';
			$params['urgency'] = $ticketOptions['urgency'];
		}

		if (isset($ticketOptions['private']))
		{
			$set_clauses[] = 'private = {int:private}';
			$params['private'] = !empty($ticketOptions['private']) ? 1 : 0;
		}

		if (isset($ticketOptions['status']))
		{
			$set_clauses[] = 'status = {int:status}';
			$params['status'] = $ticketOptions['status'];
		}

		$set_clauses[] = 'last_updated = {int:time}';
		$params['time'] = time();

		if (!empty($set_clauses))
		{
			$db->query('', '
				UPDATE {db_prefix}helpdesk_tickets
				SET ' . implode(', ', $set_clauses) . '
				WHERE id_ticket = {int:ticket}',
				$params
			);
		}
	}

	// Update custom field values if provided
	if (!empty($ticketOptions['custom_fields']))
	{
		$post_type = !empty($ticketOptions['is_ticket_edit']) ? CFIELD_TICKET : CFIELD_REPLY;
		$post_id = ($post_type == CFIELD_TICKET) ? $ticketOptions['id'] : $msgOptions['id'];

		foreach ($ticketOptions['custom_fields'] as $field_id => $value)
		{
			$db->insert('replace',
				'{db_prefix}helpdesk_custom_fields_values',
				array(
					'id_post' => 'int',
					'id_field' => 'int',
					'value' => 'string',
					'post_type' => 'int',
				),
				array(
					$post_id,
					$field_id,
					$value,
					$post_type,
				),
				array('id_post', 'id_field')
			);
		}
	}

	// Link attachments if provided
	if (!empty($msgOptions['attachments']))
	{
		$attach_rows = array();
		foreach ($msgOptions['attachments'] as $attach_id)
			$attach_rows[] = array((int) $attach_id, (int) $msgOptions['id'], (int) $ticketOptions['id']);

		if (!empty($attach_rows))
		{
			$db->insert('replace',
				'{db_prefix}helpdesk_attachments',
				array('id_attach' => 'int', 'id_msg' => 'int', 'id_ticket' => 'int'),
				$attach_rows,
				array('id_attach')
			);
		}
	}

	// Clear the active tickets cache
	shd_clear_active_tickets(!empty($ticketOptions['dept']) ? $ticketOptions['dept'] : 0);

	return true;
}

/**
 * Sets up urgency options in context based on the user's permissions.
 *
 * Populates $context['ticket_form']['urgency']['options'] with the urgency
 * levels the user is allowed to select.
 *
 * @param bool $is_own Whether the ticket belongs to the current user.
 * @param int $dept Department ID.
 */
function shd_get_urgency_options($is_own, $dept)
{
	global $context, $txt;

	$options = array();
	$can_change = false;

	// Everyone can select Low and Medium
	$options[TICKET_URGENCY_LOW] = isset($txt['shd_urgency_0']) ? $txt['shd_urgency_0'] : 'Low';
	$options[TICKET_URGENCY_MEDIUM] = isset($txt['shd_urgency_1']) ? $txt['shd_urgency_1'] : 'Medium';

	// High is available if user can alter urgency (own or any)
	if (shd_allowed_to('shd_alter_urgency_any', $dept) || ($is_own && shd_allowed_to('shd_alter_urgency_own', $dept)))
	{
		$can_change = true;
		$options[TICKET_URGENCY_HIGH] = isset($txt['shd_urgency_2']) ? $txt['shd_urgency_2'] : 'High';

		// Very High and above require higher urgency permissions
		if (shd_allowed_to('shd_alter_urgency_higher_any', $dept) || ($is_own && shd_allowed_to('shd_alter_urgency_higher_own', $dept)))
		{
			$options[TICKET_URGENCY_VHIGH] = isset($txt['shd_urgency_3']) ? $txt['shd_urgency_3'] : 'Very High';
			$options[TICKET_URGENCY_SEVERE] = isset($txt['shd_urgency_4']) ? $txt['shd_urgency_4'] : 'Severe';
			$options[TICKET_URGENCY_CRITICAL] = isset($txt['shd_urgency_5']) ? $txt['shd_urgency_5'] : 'Critical';
		}
	}

	$context['ticket_form']['urgency']['options'] = $options;
	$context['ticket_form']['urgency']['can_change'] = $can_change;
}

/**
 * Loads custom fields for the posting form.
 *
 * Queries the custom field definitions for the given scope (ticket or reply)
 * and department, along with any existing values if editing.
 *
 * @param bool $is_ticket True for ticket fields (CFIELD_TICKET), false for reply fields (CFIELD_REPLY).
 * @param int $ticketContext Ticket ID or message ID for loading existing values (0 for new).
 * @param int $dept Department ID.
 */
function shd_load_custom_fields($is_ticket, $ticketContext, $dept)
{
	global $context, $user_info;

	$db = database();

	$field_loc = $is_ticket ? CFIELD_TICKET : CFIELD_REPLY;

	$context['ticket_form']['custom_fields'] = array();
	$context['ticket_form']['custom_fields_context'] = array();

	// Can this user override required fields?
	$can_override = shd_allowed_to('shd_override_cf', $dept);

	// Load field definitions for this department and scope
	$request = $db->query('', '
		SELECT hcf.id_field, hcf.field_name, hcf.field_desc, hcf.field_type, hcf.field_length,
			hcf.field_options, hcf.default_value, hcf.bbc, hcf.can_see, hcf.can_edit,
			hcf.display_empty, hcf.placement, hcf.active, hcf.icon,
			hcfd.required
		FROM {db_prefix}helpdesk_custom_fields AS hcf
			INNER JOIN {db_prefix}helpdesk_custom_fields_depts AS hcfd ON (hcf.id_field = hcfd.id_field)
		WHERE hcfd.id_dept = {int:dept}
			AND hcf.field_loc IN ({int:field_loc}, {int:field_loc_both})
			AND hcf.active = {int:active}
		ORDER BY hcf.field_order',
		array(
			'dept' => $dept,
			'field_loc' => $field_loc,
			'field_loc_both' => 3, // Both ticket and reply
			'active' => 1,
		)
	);

	$field_ids = array();
	while ($row = $db->fetch_assoc($request))
	{
		$field_id = (int) $row['id_field'];
		$field_ids[] = $field_id;

		// Determine visibility: can_see is stored as a comma-separated string of role types
		// 0 = everyone, or specific role types (1=user, 2=staff, 4=admin)
		$is_staff = shd_allowed_to('shd_staff', $dept);
		$can_see = explode(',', $row['can_see']);
		$can_edit = explode(',', $row['can_edit']);

		$visible = in_array('0', $can_see) || ($is_staff && in_array('2', $can_see)) || (!$is_staff && in_array('1', $can_see)) || ($user_info['is_admin'] && in_array('4', $can_see));
		$editable = in_array('0', $can_edit) || ($is_staff && in_array('2', $can_edit)) || (!$is_staff && in_array('1', $can_edit)) || ($user_info['is_admin'] && in_array('4', $can_edit));

		if (!$visible)
			continue;

		$field_options = !empty($row['field_options']) ? explode(',', $row['field_options']) : array();

		$context['ticket_form']['custom_fields'][$field_id] = array(
			'id' => $field_id,
			'name' => $row['field_name'],
			'desc' => $row['field_desc'],
			'type' => (int) $row['field_type'],
			'length' => (int) $row['field_length'],
			'options' => $field_options,
			'default' => $row['default_value'],
			'bbc' => !empty($row['bbc']),
			'required' => !empty($row['required']) && !$can_override,
			'editable' => $editable,
			'icon' => $row['icon'],
			'placement' => (int) $row['placement'],
			'value' => $row['default_value'],
		);
	}
	$db->free_result($request);

	// Load existing values if editing
	if (!empty($ticketContext) && !empty($field_ids))
	{
		$request = $db->query('', '
			SELECT id_field, value
			FROM {db_prefix}helpdesk_custom_fields_values
			WHERE id_post = {int:post}
				AND id_field IN ({array_int:fields})',
			array(
				'post' => $ticketContext,
				'fields' => $field_ids,
			)
		);

		while ($row = $db->fetch_assoc($request))
		{
			if (isset($context['ticket_form']['custom_fields'][$row['id_field']]))
				$context['ticket_form']['custom_fields'][$row['id_field']]['value'] = $row['value'];
		}
		$db->free_result($request);
	}
}

/**
 * Validates custom fields submitted via POST.
 *
 * Checks required fields are filled, type constraints are met, and returns
 * an array of validated field values or errors.
 *
 * @param int $scope CFIELD_TICKET or CFIELD_REPLY.
 * @param int $dept Department ID.
 * @return array Array with 'fields' => validated values, 'errors' => error strings.
 */
function shd_validate_custom_fields($scope, $dept)
{
	global $context, $txt;

	$result = array(
		'fields' => array(),
		'errors' => array(),
		'invalid' => array(),
		'missing' => array(),
	);

	if (empty($context['ticket_form']['custom_fields']))
		return $result;

	foreach ($context['ticket_form']['custom_fields'] as $field_id => $field)
	{
		if (!$field['editable'])
			continue;

		$value = '';
		$post_key = 'custom_field_' . $field_id;

		if (isset($_POST[$post_key]))
			$value = $_POST[$post_key];

		// Handle multi-select/checkbox arrays
		if (is_array($value))
			$value = implode(',', $value);

		$value = Util::htmlspecialchars($value);

		// Validate based on type
		switch ($field['type'])
		{
			case CFIELD_TYPE_INT:
				if ($value !== '' && !ctype_digit(str_replace('-', '', $value)))
					$result['invalid'][] = $field['name'];
				break;

			case CFIELD_TYPE_FLOAT:
				if ($value !== '' && !is_numeric($value))
					$result['invalid'][] = $field['name'];
				break;

			case CFIELD_TYPE_TEXT:
			case CFIELD_TYPE_LARGETEXT:
				if (!empty($field['length']) && Util::strlen($value) > $field['length'])
					$value = Util::substr($value, 0, $field['length']);
				break;

			case CFIELD_TYPE_SELECT:
			case CFIELD_TYPE_RADIO:
				if ($value !== '' && !in_array($value, $field['options']))
					$result['invalid'][] = $field['name'];
				break;

			case CFIELD_TYPE_CHECKBOX:
				$value = !empty($value) ? '1' : '0';
				break;
		}

		// Check required
		if ($field['required'] && ($value === '' || $value === null))
			$result['missing'][] = $field['name'];

		$result['fields'][$field_id] = $value;

		// Update the context so the form can show the posted values
		if (isset($context['ticket_form']['custom_fields'][$field_id]))
			$context['ticket_form']['custom_fields'][$field_id]['value'] = $value;
	}

	// Build error messages
	if (!empty($result['invalid']))
		$result['errors'][] = sprintf(
			isset($txt['error_invalid_fields']) ? $txt['error_invalid_fields'] : 'Invalid fields: %1$s',
			implode(', ', $result['invalid'])
		);

	if (!empty($result['missing']))
		$result['errors'][] = sprintf(
			isset($txt['error_missing_fields']) ? $txt['error_missing_fields'] : 'Missing fields: %1$s',
			implode(', ', $result['missing'])
		);

	return $result;
}

/**
 * Returns a list of departments the current user can post new tickets in.
 *
 * @return array Array of department data (id, name).
 */
function shd_get_postable_depts()
{
	global $user_info;

	$db = database();

	$depts = shd_allowed_to('shd_new_ticket', false);

	if (empty($depts) || $depts === false)
		return array();

	$result = array();

	$request = $db->query('', '
		SELECT id_dept, dept_name
		FROM {db_prefix}helpdesk_depts
		WHERE id_dept IN ({array_int:depts})
		ORDER BY dept_order',
		array(
			'depts' => $depts,
		)
	);

	while ($row = $db->fetch_assoc($request))
	{
		$result[$row['id_dept']] = array(
			'id' => (int) $row['id_dept'],
			'name' => $row['dept_name'],
		);
	}
	$db->free_result($request);

	return $result;
}

/**
 * Processes uploaded attachments on form submission.
 *
 * Handles new file uploads via ElkArte's createAttachment(), deletion of
 * unchecked existing attachments, and returns an array of attachment IDs
 * to be linked to the ticket/reply via shd_create_ticket_post().
 *
 * @param int $dept Department ID for permission checks.
 * @return array Array of attachment IDs (may be empty).
 */
function shd_handle_attachments($dept = 0)
{
	global $modSettings, $context, $user_info, $txt;

	if (!shd_allowed_to('shd_post_attachment', $dept))
		return array();

	$db = database();
	$attachIDs = array();

	require_once(SUBSDIR . '/Attachments.subs.php');

	$ticket_id = !empty($context['ticket_form']['ticket']) ? (int) $context['ticket_form']['ticket'] : 0;
	$msg_id = !empty($context['ticket_form']['msg']) ? (int) $context['ticket_form']['msg'] : 0;
	$attach_mode = !empty($modSettings['shd_attachments_mode']) ? $modSettings['shd_attachments_mode'] : 'ticket';

	// Handle deletion of existing attachments (unchecked ones)
	if (isset($_POST['attach_del']))
	{
		$keep_ids = array();
		foreach ($_POST['attach_del'] as $id)
			$keep_ids[] = (int) $id;

		// Get all current attachments for this ticket/msg
		$request = shd_db_query('', '
			SELECT a.id_attach
			FROM {db_prefix}attachments AS a
				INNER JOIN {db_prefix}helpdesk_attachments AS hda ON (hda.id_attach = a.id_attach)
			WHERE ' . ($attach_mode == 'ticket' ? 'hda.id_ticket = {int:ticket}' : 'hda.id_msg = {int:msg}') . '
				AND a.attachment_type = {int:attach_type}',
			array(
				'msg' => $msg_id,
				'ticket' => $ticket_id,
				'attach_type' => 0,
			)
		);

		$existing = array();
		while ($row = $db->fetch_assoc($request))
			$existing[] = (int) $row['id_attach'];
		$db->free_result($request);

		// Delete those NOT in the keep list
		$to_delete = array_diff($existing, $keep_ids);
		if (!empty($to_delete))
		{
			require_once(SUBSDIR . '/ManageAttachments.subs.php');
			removeAttachments(array('id_attach' => $to_delete));
		}
	}

	// Process new file uploads
	if (!empty($_FILES))
	{
		foreach ($_FILES as $key => $uplfile)
		{
			if (empty($uplfile['name']) || empty($uplfile['tmp_name']))
				continue;

			if (!is_uploaded_file($uplfile['tmp_name']))
				continue;

			// Validate file size
			if (!empty($modSettings['attachmentSizeLimit']) && $uplfile['size'] > $modSettings['attachmentSizeLimit'] * 1024)
				continue;

			// Validate extension
			if (!empty($modSettings['attachmentCheckExtensions']))
			{
				$ext = strtolower(substr(strrchr($uplfile['name'], '.'), 1));
				$allowed = explode(',', strtolower($modSettings['attachmentExtensions']));
				$allowed = array_map('trim', $allowed);

				if (!in_array($ext, $allowed))
					continue;
			}

			// Determine upload folder
			$id_folder = !empty($modSettings['currentAttachmentUploadDir']) ? (int) $modSettings['currentAttachmentUploadDir'] : 1;

			// Detect MIME type
			$mime_type = !empty($uplfile['type']) ? $uplfile['type'] : '';
			if (empty($mime_type) && function_exists('mime_content_type'))
				$mime_type = mime_content_type($uplfile['tmp_name']);

			$attachmentOptions = array(
				'post' => 0,
				'poster' => $user_info['id'],
				'name' => $uplfile['name'],
				'tmp_name' => $uplfile['tmp_name'],
				'size' => $uplfile['size'],
				'id_folder' => $id_folder,
				'mime_type' => $mime_type,
				'approved' => 1,
			);

			if (createAttachment($attachmentOptions))
			{
				$attachIDs[] = $attachmentOptions['id'];

				if (!empty($attachmentOptions['thumb']))
					$attachIDs[] = $attachmentOptions['thumb'];
			}
		}
	}

	return $attachIDs;
}

/**
 * Loads existing attachments for display on the post edit form.
 *
 * Populates $context['current_attachments'] with the list of attachments
 * already on this ticket or message, for rendering checkboxes to keep/remove.
 *
 * @param int $ticket_id Ticket ID.
 * @param int $msg_id Message ID.
 */
function shd_load_attachments($ticket_id, $msg_id)
{
	global $context, $modSettings;

	$db = database();
	$context['current_attachments'] = array();

	$attach_mode = !empty($modSettings['shd_attachments_mode']) ? $modSettings['shd_attachments_mode'] : 'ticket';
	$deleteable = shd_allowed_to('shd_delete_attachment', !empty($context['shd_department']) ? $context['shd_department'] : 0);

	$request = shd_db_query('', '
		SELECT a.id_attach, a.filename
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}helpdesk_attachments AS hda ON (a.id_attach = hda.id_attach)
		WHERE ' . ($attach_mode == 'ticket' ? 'hda.id_ticket = {int:ticket}' : 'hda.id_msg = {int:msg}') . '
			AND a.attachment_type = {int:attach_type}
		ORDER BY hda.id_attach',
		array(
			'msg' => $msg_id,
			'ticket' => $ticket_id,
			'attach_type' => 0,
		)
	);

	while ($row = $db->fetch_assoc($request))
	{
		$context['current_attachments'][] = array(
			'id' => (int) $row['id_attach'],
			'name' => htmlspecialchars($row['filename']),
			'can_delete' => $deleteable,
		);
	}
	$db->free_result($request);
}

/**
 * Loads attachments for display on the ticket view page.
 *
 * Builds $context['ticket_attach'] with full attachment info for rendering
 * in the display template (images, thumbnails, download links, sizes).
 *
 * @param int $ticket_id Ticket ID.
 * @param array $message_ids Array of message IDs on this page.
 */
function shd_display_load_attachments($ticket_id, $message_ids = array())
{
	global $context, $modSettings, $scripturl, $txt;

	$db = database();
	$attach_mode = !empty($modSettings['shd_attachments_mode']) ? $modSettings['shd_attachments_mode'] : 'ticket';
	$can_view = shd_allowed_to('shd_view_attachment', !empty($context['shd_department']) ? $context['shd_department'] : 0);
	$can_delete = shd_allowed_to('shd_delete_attachment', !empty($context['shd_department']) ? $context['shd_department'] : 0);

	$context['ticket_attach'] = array(
		'ticket' => array(),
		'reply' => array(),
	);

	if (!$can_view)
		return;

	$use_thumbs = !empty($modSettings['attachmentShowImages']) && !empty($modSettings['attachmentThumbnails']);

	if ($attach_mode == 'ticket')
	{
		$request = shd_db_query('', '
			SELECT hda.id_attach, hda.id_msg, hda.id_ticket,
				a.filename, a.id_folder, a.file_hash, COALESCE(a.size, 0) AS filesize,
				a.width, a.height' . ($use_thumbs ? ',
				COALESCE(thumb.id_attach, 0) AS id_thumb, thumb.width AS thumb_width, thumb.height AS thumb_height' : '') . '
			FROM {db_prefix}helpdesk_attachments AS hda
				INNER JOIN {db_prefix}attachments AS a ON (hda.id_attach = a.id_attach)' . ($use_thumbs ? '
				LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)' : '') . '
			WHERE hda.id_ticket = {int:ticket}
				AND a.attachment_type = {int:attach_type}
			ORDER BY hda.id_attach',
			array(
				'ticket' => $ticket_id,
				'attach_type' => 0,
			)
		);

		while ($row = $db->fetch_assoc($request))
			$context['ticket_attach']['ticket'][$row['id_attach']] = shd_build_attachment_info($row, $ticket_id, $can_delete);

		$db->free_result($request);
	}
	else
	{
		if (empty($message_ids))
			return;

		$request = shd_db_query('', '
			SELECT hda.id_attach, hda.id_msg, hda.id_ticket,
				a.filename, a.id_folder, a.file_hash, COALESCE(a.size, 0) AS filesize,
				a.width, a.height' . ($use_thumbs ? ',
				COALESCE(thumb.id_attach, 0) AS id_thumb, thumb.width AS thumb_width, thumb.height AS thumb_height' : '') . '
			FROM {db_prefix}helpdesk_attachments AS hda
				INNER JOIN {db_prefix}attachments AS a ON (hda.id_attach = a.id_attach)' . ($use_thumbs ? '
				LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)' : '') . '
			WHERE hda.id_msg IN ({array_int:msgs})
				AND a.attachment_type = {int:attach_type}
			ORDER BY hda.id_attach',
			array(
				'msgs' => $message_ids,
				'attach_type' => 0,
			)
		);

		while ($row = $db->fetch_assoc($request))
			$context['ticket_attach']['reply'][$row['id_msg']][$row['id_attach']] = shd_build_attachment_info($row, $ticket_id, $can_delete);

		$db->free_result($request);
	}
}

/**
 * Builds a single attachment info array for template rendering.
 *
 * @param array $row Database row with attachment data.
 * @param int $ticket_id Ticket ID for download URLs.
 * @param bool $can_delete Whether user can delete attachments.
 * @return array Attachment info suitable for template use.
 */
function shd_build_attachment_info($row, $ticket_id, $can_delete)
{
	global $scripturl, $modSettings, $txt;

	$filename = htmlspecialchars($row['filename']);

	$attach = array(
		'id' => (int) $row['id_attach'],
		'name' => $filename,
		'size' => round($row['filesize'] / 1024, 2) . ' ' . ($txt['kilobyte'] ?? 'KB'),
		'byte_size' => (int) $row['filesize'],
		'href' => $scripturl . '?action=helpdesk;sa=dlattach;ticket=' . $ticket_id . ';attach=' . $row['id_attach'],
		'link' => '<a href="' . $scripturl . '?action=helpdesk;sa=dlattach;ticket=' . $ticket_id . ';attach=' . $row['id_attach'] . '">' . $filename . '</a>',
		'is_image' => !empty($modSettings['attachmentShowImages']) && !empty($row['width']) && !empty($row['height']),
		'can_delete' => $can_delete,
	);

	if ($attach['is_image'])
	{
		$attach['real_width'] = (int) $row['width'];
		$attach['real_height'] = (int) $row['height'];
		$attach['width'] = (int) $row['width'];
		$attach['height'] = (int) $row['height'];

		$has_thumb = !empty($row['id_thumb']);

		if ($has_thumb)
		{
			$attach['width'] = (int) $row['thumb_width'];
			$attach['height'] = (int) $row['thumb_height'];
			$attach['thumbnail'] = array(
				'id' => (int) $row['id_thumb'],
				'href' => $scripturl . '?action=helpdesk;sa=dlattach;ticket=' . $ticket_id . ';attach=' . $row['id_thumb'] . ';image',
				'has_thumb' => true,
			);
		}
		else
		{
			// Scale down if too large
			if ((!empty($modSettings['max_image_width']) && $row['width'] > $modSettings['max_image_width']) || (!empty($modSettings['max_image_height']) && $row['height'] > $modSettings['max_image_height']))
			{
				if (!empty($modSettings['max_image_width']) && (empty($modSettings['max_image_height']) || $row['height'] * $modSettings['max_image_width'] / $row['width'] <= $modSettings['max_image_height']))
				{
					$attach['width'] = $modSettings['max_image_width'];
					$attach['height'] = (int) floor($row['height'] * $modSettings['max_image_width'] / $row['width']);
				}
				elseif (!empty($modSettings['max_image_height']))
				{
					$attach['width'] = (int) floor($row['width'] * $modSettings['max_image_height'] / $row['height']);
					$attach['height'] = $modSettings['max_image_height'];
				}
			}

			$attach['thumbnail'] = array(
				'has_thumb' => false,
			);
		}
	}

	return $attach;
}
