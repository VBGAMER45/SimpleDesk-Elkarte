<?php
/**
 * SimpleDesk Post Template
 *
 * Templates for creating and editing tickets and replies.
 * Provides two-column layout with sidebar ticket details, rich text editor,
 * collapsible custom fields and additional options sections.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Main new/edit ticket form template.
 *
 * Two-column layout with:
 * - Right sidebar: Ticket Details (user, urgency, status, privacy, assigned)
 * - Main area: Subject, department, rich editor, custom fields, additional options
 */
function template_shd_post_ticket()
{
	global $context, $scripturl, $txt, $modSettings;

	$ticket_form = $context['ticket_form'];

	echo '
	<div id="shd_main">';

	// Header bar
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', $ticket_form['form_title'], '
			</h3>
		</div>';

	// Error display
	if (!empty($ticket_form['errors']))
	{
		echo '
		<div class="errorbox">
			<strong>', $txt['shd_errors_occurred'], '</strong>
			<ul>';

		foreach ($ticket_form['errors'] as $error)
		{
			echo '
				<li>', isset($txt[$error]) ? $txt[$error] : $error, '</li>';
		}

		echo '
			</ul>
		</div>';
	}

	// Form
	echo '
		<form action="', $ticket_form['form_action'], '" method="post" accept-charset="UTF-8" name="postmodify" id="postmodify" enctype="multipart/form-data"
			onsubmit="submitonce(this);smc_saveEntities(\'postmodify\', [\'subject\', \'', $context['post_box_name'], '\'], \'options\');">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />
			<input type="hidden" name="ticket" value="', $ticket_form['ticket'], '" />
			<input type="hidden" name="msg" value="', $ticket_form['msg'], '" />';

	// Hidden dept when not selecting
	if (empty($ticket_form['selecting_dept']))
		echo '
			<input type="hidden" name="dept" value="', $ticket_form['dept'], '" />';

	echo '
			<div class="content shd_ticket">';

	// === SIDEBAR: Ticket Details ===
	echo '
				<div class="shd_ticket_side_column">
					<div class="shd_ticketdetails">
						<strong>', $txt['shd_ticket_details'], '</strong>
						<hr />
						<ul class="reset">';

	// Ticket ID (edit only)
	if (!empty($ticket_form['ticket']))
		echo '
							<li><strong>', $txt['shd_ticket_display_id'], ':</strong> ', $ticket_form['ticket'], '</li>';

	// User
	if (!empty($ticket_form['member']['link']))
		echo '
							<li><strong>', $txt['shd_ticket_user'], ':</strong> ', $ticket_form['member']['link'], '</li>';

	// Urgency
	if (!empty($ticket_form['urgency']['can_change']) && !empty($ticket_form['urgency']['options']))
	{
		echo '
							<li>
								<strong>', $txt['shd_ticket_urgency'], ':</strong><br />
								<select name="urgency">';

		foreach ($ticket_form['urgency']['options'] as $urgency_value => $urgency_key)
		{
			echo '
									<option value="', $urgency_value, '"', ($urgency_value == $ticket_form['urgency']['setting'] ? ' selected="selected"' : ''), '>', isset($txt[$urgency_key]) ? $txt[$urgency_key] : $urgency_key, '</option>';
		}

		echo '
								</select>
							</li>';
	}
	elseif (isset($ticket_form['urgency']['setting']))
	{
		echo '
							<li><strong>', $txt['shd_ticket_urgency'], ':</strong> ', isset($txt['shd_urgency_' . $ticket_form['urgency']['setting']]) ? $txt['shd_urgency_' . $ticket_form['urgency']['setting']] : '', '</li>';
	}

	// Status
	echo '
							<li><strong>', $txt['shd_ticket_status'], ':</strong> ', isset($txt['shd_status_' . $ticket_form['status']]) ? $txt['shd_status_' . $ticket_form['status']] : '', '</li>';

	// Privacy
	if (!empty($context['display_private']) && !empty($ticket_form['private']['can_change']))
	{
		echo '
							<li>
								<strong>', $txt['shd_ticket_private'], ':</strong><br />
								<select name="private">';

		if (!empty($ticket_form['private']['options']))
		{
			foreach ($ticket_form['private']['options'] as $priv_value => $priv_key)
			{
				echo '
									<option value="', $priv_value, '"', ($priv_value == $ticket_form['private']['setting'] ? ' selected="selected"' : ''), '>', $priv_key, '</option>';
			}
		}

		echo '
								</select>
							</li>';
	}
	elseif (!empty($context['display_private']))
	{
		echo '
							<li><strong>', $txt['shd_ticket_private'], ':</strong> ', !empty($ticket_form['private']['setting']) ? $txt['shd_ticket_private'] : ($txt['shd_ticket_is_not_private'] ?? 'Not Private'), '</li>';
	}

	// Assigned to (edit only)
	if (!empty($ticket_form['assigned']))
	{
		echo '
							<li><strong>', $txt['shd_ticket_assigned_to'], ':</strong> ',
								!empty($ticket_form['assigned']['link']) ? $ticket_form['assigned']['link'] : $txt['shd_unassigned'],
							'</li>';
	}

	echo '
						</ul>
					</div>
				</div>';

	// === MAIN CONTENT AREA ===
	echo '
				<div class="shd_ticket_description">
					<div class="shd_post_form">';

	// Department selector
	if (!empty($ticket_form['selecting_dept']) && !empty($context['postable_dept_list']))
	{
		echo '
						<dl class="settings">
							<dt>
								<label for="shd_dept">', $txt['shd_ticket_department'], '</label>
							</dt>
							<dd>
								<select name="dept" id="shd_dept">';

		foreach ($context['postable_dept_list'] as $dept_id => $dept_name)
		{
			echo '
									<option value="', $dept_id, '"', ($dept_id == $ticket_form['dept'] ? ' selected="selected"' : ''), '>', $dept_name, '</option>';
		}

		echo '
								</select>
							</dd>
						</dl>';
	}

	// Subject field
	echo '
						<dl class="settings">
							<dt>
								<label for="shd_subject"><strong>', $txt['shd_ticket_subject'], '</strong></label>
							</dt>
							<dd>
								<input type="text" name="subject" id="shd_subject" value="', $ticket_form['subject'], '" size="80" maxlength="100" class="input_text" style="width: 100%; box-sizing: border-box;" />
							</dd>
						</dl>';

	// Proxy field (before editor)
	if (!empty($context['can_post_proxy']))
	{
		echo '
						<dl class="settings">
							<dt>
								<label for="shd_proxy">', $txt['shd_proxy_post_for'], '</label>
								<br /><span class="smalltext">', $txt['shd_proxy_post_for_desc'], '</span>
							</dt>
							<dd>
								<input type="text" name="proxy" id="shd_proxy" value="', !empty($ticket_form['proxy']['name']) ? $ticket_form['proxy']['name'] : '', '" size="40" class="input_text" />
							</dd>
						</dl>';
	}

	// BBC editor
	echo '
						<div id="shd_bbcbox"></div>
						<div id="shd_smileybox"></div>';

	template_control_richedit($context['post_box_name'], 'shd_smileybox', 'shd_bbcbox');

	// === Custom Fields Section (collapsible) ===
	$custom_fields = !empty($ticket_form['custom_fields']) ? $ticket_form['custom_fields'] : array();
	$has_editable_fields = false;

	if (!empty($custom_fields))
	{
		foreach ($custom_fields as $field)
		{
			if (is_array($field) && !empty($field['id']) && !empty($field['editable']))
			{
				$has_editable_fields = true;
				break;
			}
		}
	}

	if ($has_editable_fields)
	{
		echo '
						<div class="title_bar" style="margin-top: 8px;">
							<h3 class="titlebg">
								<a href="#" onclick="document.getElementById(\'shd_customfields\').style.display = document.getElementById(\'shd_customfields\').style.display === \'none\' ? \'block\' : \'none\'; return false;">',
									$txt['shd_additional_details'],
								'</a>
							</h3>
						</div>
						<div class="shd_customfields" id="shd_customfields">';

		foreach ($custom_fields as $field)
		{
			if (!is_array($field) || empty($field['id']) || empty($field['editable']))
				continue;

			echo '
							<dl class="settings">
								<dt>
									<label for="field-', $field['id'], '">', $field['name'];

			if (!empty($field['is_required']))
				echo ' <span class="shd_required">*</span>';

			echo '</label>';

			if (!empty($field['desc']))
				echo '
									<br /><span class="smalltext">', $field['desc'], '</span>';

			echo '
								</dt>
								<dd>';

			template_shd_custom_field($field);

			echo '
								</dd>
							</dl>';
		}

		echo '
						</div>';
	}

	// === Additional Options Section (collapsible) ===
	$show_additional = !empty($ticket_form['do_attach']) || !empty($context['can_solve']) || true; // Always show for smileys toggle

	if ($show_additional)
	{
		echo '
						<div class="title_bar" style="margin-top: 8px;">
							<h3 class="titlebg">
								<a href="#" onclick="document.getElementById(\'shd_additional_options\').style.display = document.getElementById(\'shd_additional_options\').style.display === \'none\' ? \'block\' : \'none\'; return false;">',
									$txt['shd_additional_information'],
								'</a>
							</h3>
						</div>
						<div class="shd_additional_options" id="shd_additional_options">';

		// Option checkboxes
		echo '
							<ul class="post_options">';

		// Disable smileys
		echo '
								<li>
									<label for="shd_ns">
										<input type="checkbox" name="ns" id="shd_ns" value="1"', !empty($ticket_form['smileys_enabled']) ? '' : ' checked="checked"', ' />
										', $txt['shd_disable_smileys_post'], '
									</label>
								</li>';

		// Resolve ticket (edit only, if permitted)
		if (!empty($context['can_solve']) && empty($ticket_form['is_new']))
		{
			echo '
								<li>
									<label for="shd_resolve">
										<input type="checkbox" name="resolve" id="shd_resolve" value="1" />
										', $txt['shd_resolve_this_ticket'], '
									</label>
								</li>';
		}

		echo '
							</ul>';

		// Existing attachments (when editing)
		if (!empty($ticket_form['do_attach']) && !empty($context['current_attachments']))
		{
			echo '
							<div class="shd_attachments">
								<dl>
									<dt>', $txt['shd_attachments_existing'], ':</dt>
									<dd>
										<span class="smalltext">', $txt['shd_attachments_existing_uncheck'], '</span><br />';

			foreach ($context['current_attachments'] as $attach)
			{
				echo '
										<label>
											<input type="checkbox" name="attach_del[]" value="', $attach['id'], '" checked="checked" /> ', $attach['name'], '
										</label><br />';
			}

			echo '
									</dd>
								</dl>
							</div>';
		}

		// Attachment upload
		if (!empty($ticket_form['do_attach']))
		{
			echo '
							<div class="shd_attachments">
								<dl>
									<dt>', $txt['shd_attach_add_file'], ':</dt>
									<dd>
										<input type="file" name="attachment" id="shd_attach" class="input_file" size="60" />
									</dd>
								</dl>
							</div>';
		}

		echo '
						</div>';
	}

	echo '
					</div>';

	// Submit button and back link
	echo '
					<div class="shd_nav_buttons flow_auto" style="padding: 8px;">
						<input type="submit" name="submit" value="', !empty($ticket_form['is_new']) ? $txt['shd_post_ticket'] : $txt['shd_save'], '" class="button_submit" />
						<a href="', $scripturl, '?action=helpdesk', (!empty($ticket_form['dept']) ? ';dept=' . $ticket_form['dept'] : ''), '">', $txt['shd_back_to_helpdesk'], '</a>
					</div>';

	echo '
				</div>';

	// Close the two-column container
	echo '
			</div>
		</form>';

	echo '
	</div>';
}

/**
 * Reply/edit reply form template.
 *
 * Two-column layout with:
 * - Right sidebar: Ticket Details (user, urgency, status, privacy, assigned)
 * - Main area: Ticket body reference, reply editor, custom fields, additional options
 */
function template_shd_post_reply()
{
	global $context, $scripturl, $txt, $modSettings;

	$ticket_form = $context['ticket_form'];

	echo '
	<div id="shd_main">';

	// Header bar
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', $ticket_form['form_title'], '
			</h3>
		</div>';

	// Error display
	if (!empty($ticket_form['errors']))
	{
		echo '
		<div class="errorbox">
			<strong>', $txt['shd_errors_occurred'], '</strong>
			<ul>';

		foreach ($ticket_form['errors'] as $error)
		{
			echo '
				<li>', isset($txt[$error]) ? $txt[$error] : $error, '</li>';
		}

		echo '
			</ul>
		</div>';
	}

	// Ticket body for reference (read-only, outside the form)
	if (!empty($ticket_form['ticket_body']))
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', !empty($ticket_form['ticket_subject']) ? $ticket_form['ticket_subject'] : ($txt['shd_ticket_body'] ?? 'Message'), '
			</h3>
		</div>
		<div class="windowbg">
			<div class="shd_ticket_body" style="padding: 8px;">
				', $ticket_form['ticket_body'], '
			</div>
		</div>';
	}

	// Reply form header
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', !empty($ticket_form['is_new']) ? ($txt['shd_post_reply']) : ($txt['shd_edit_reply']), '
			</h3>
		</div>';

	// Form
	echo '
		<form action="', $ticket_form['form_action'], '" method="post" accept-charset="UTF-8" name="postmodify" id="postmodify" enctype="multipart/form-data"
			onsubmit="submitonce(this);smc_saveEntities(\'postmodify\', [\'', $context['post_box_name'], '\'], \'options\');">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />
			<input type="hidden" name="ticket" value="', $ticket_form['ticket'], '" />';

	if (!empty($ticket_form['msg']))
		echo '
			<input type="hidden" name="msg" value="', $ticket_form['msg'], '" />';

	echo '
			<div class="content shd_ticket">';

	// === SIDEBAR: Ticket Details ===
	echo '
				<div class="shd_ticket_side_column">
					<div class="shd_ticketdetails">
						<strong>', $txt['shd_ticket_details'], '</strong>
						<hr />
						<ul class="reset">';

	// Ticket ID
	if (!empty($ticket_form['ticket']))
		echo '
							<li><strong>', $txt['shd_ticket_display_id'], ':</strong> ', $ticket_form['ticket'], '</li>';

	// User (ticket starter)
	if (!empty($ticket_form['member']['link']))
		echo '
							<li><strong>', $txt['shd_ticket_user'], ':</strong> ', $ticket_form['member']['link'], '</li>';

	// Urgency (read-only in reply forms)
	if (isset($ticket_form['urgency']['setting']))
		echo '
							<li><strong>', $txt['shd_ticket_urgency'], ':</strong> ', isset($txt['shd_urgency_' . $ticket_form['urgency']['setting']]) ? $txt['shd_urgency_' . $ticket_form['urgency']['setting']] : '', '</li>';

	// Status
	echo '
							<li><strong>', $txt['shd_ticket_status'], ':</strong> ', isset($txt['shd_status_' . $ticket_form['status']]) ? $txt['shd_status_' . $ticket_form['status']] : '', '</li>';

	// Privacy (read-only in reply forms)
	if (isset($ticket_form['private']['setting']))
		echo '
							<li><strong>', $txt['shd_ticket_private'], ':</strong> ', !empty($ticket_form['private']['setting']) ? $txt['shd_ticket_private'] : ($txt['shd_ticket_is_not_private'] ?? 'Not Private'), '</li>';

	// Assigned to
	if (!empty($ticket_form['assigned']))
	{
		echo '
							<li><strong>', $txt['shd_ticket_assigned_to'], ':</strong> ',
								!empty($ticket_form['assigned']['link']) ? $ticket_form['assigned']['link'] : $txt['shd_unassigned'],
							'</li>';
	}

	echo '
						</ul>
					</div>
				</div>';

	// === MAIN CONTENT AREA ===
	echo '
				<div class="shd_ticket_description">
					<div class="shd_post_form">';

	// BBC editor
	echo '
						<div id="shd_bbcbox"></div>
						<div id="shd_smileybox"></div>';

	template_control_richedit($context['post_box_name'], 'shd_smileybox', 'shd_bbcbox');

	// === Custom Fields Section (reply scope, collapsible) ===
	$reply_fields = array();
	if (!empty($ticket_form['custom_fields']))
	{
		foreach ($ticket_form['custom_fields'] as $scope => $fields)
		{
			if ($scope === 'ticket')
				continue;

			if (!empty($fields))
				$reply_fields = array_merge($reply_fields, $fields);
		}
	}

	$has_editable_reply_fields = false;
	if (!empty($reply_fields))
	{
		foreach ($reply_fields as $field)
		{
			if (!empty($field['editable']))
			{
				$has_editable_reply_fields = true;
				break;
			}
		}
	}

	if ($has_editable_reply_fields)
	{
		echo '
						<div class="title_bar" style="margin-top: 8px;">
							<h3 class="titlebg">
								<a href="#" onclick="document.getElementById(\'shd_customfields\').style.display = document.getElementById(\'shd_customfields\').style.display === \'none\' ? \'block\' : \'none\'; return false;">',
									$txt['shd_additional_details'],
								'</a>
							</h3>
						</div>
						<div class="shd_customfields" id="shd_customfields">';

		foreach ($reply_fields as $field)
		{
			if (empty($field['editable']))
				continue;

			echo '
							<dl class="settings">
								<dt>
									<label for="field-', $field['id'], '">', $field['name'];

			if (!empty($field['is_required']))
				echo ' <span class="shd_required">*</span>';

			echo '</label>';

			if (!empty($field['desc']))
				echo '
									<br /><span class="smalltext">', $field['desc'], '</span>';

			echo '
								</dt>
								<dd>';

			template_shd_custom_field($field);

			echo '
								</dd>
							</dl>';
		}

		echo '
						</div>';
	}

	// === Additional Options Section (collapsible) ===
	echo '
						<div class="title_bar" style="margin-top: 8px;">
							<h3 class="titlebg">
								<a href="#" onclick="document.getElementById(\'shd_additional_options\').style.display = document.getElementById(\'shd_additional_options\').style.display === \'none\' ? \'block\' : \'none\'; return false;">',
									$txt['shd_additional_information'],
								'</a>
							</h3>
						</div>
						<div class="shd_additional_options" id="shd_additional_options">';

	// Option checkboxes
	echo '
							<ul class="post_options">';

	// Disable smileys
	echo '
								<li>
									<label for="shd_ns">
										<input type="checkbox" name="ns" id="shd_ns" value="1"', !empty($ticket_form['smileys_enabled']) ? '' : ' checked="checked"', ' />
										', $txt['shd_disable_smileys_post'], '
									</label>
								</li>';

	// Resolve ticket (new reply only, if permitted)
	if (!empty($context['can_solve']) && !empty($ticket_form['is_new']))
	{
		echo '
								<li>
									<label for="shd_resolve">
										<input type="checkbox" name="resolve" id="shd_resolve" value="1" />
										', $txt['shd_resolve_this_ticket'], '
									</label>
								</li>';
	}

	echo '
							</ul>';

	// Existing attachments (when editing a reply)
	if (!empty($ticket_form['do_attach']) && !empty($context['current_attachments']))
	{
		echo '
							<div class="shd_attachments">
								<dl>
									<dt>', $txt['shd_attachments_existing'], ':</dt>
									<dd>
										<span class="smalltext">', $txt['shd_attachments_existing_uncheck'], '</span><br />';

		foreach ($context['current_attachments'] as $attach)
		{
			echo '
										<label>
											<input type="checkbox" name="attach_del[]" value="', $attach['id'], '" checked="checked" /> ', $attach['name'], '
										</label><br />';
		}

		echo '
									</dd>
								</dl>
							</div>';
	}

	// Attachment upload
	if (!empty($ticket_form['do_attach']))
	{
		echo '
							<div class="shd_attachments">
								<dl>
									<dt>', $txt['shd_attach_add_file'], ':</dt>
									<dd>
										<input type="file" name="attachment" id="shd_attach" class="input_file" size="60" />
									</dd>
								</dl>
							</div>';
	}

	echo '
						</div>';

	echo '
					</div>';

	// Submit button and back link
	echo '
					<div class="shd_nav_buttons flow_auto" style="padding: 8px;">
						<input type="submit" name="submit" value="', !empty($ticket_form['is_new']) ? $txt['shd_post_reply'] : $txt['shd_save'], '" class="button_submit" />
						<a href="', $scripturl, '?action=helpdesk;sa=viewticket;ticket=', $ticket_form['ticket'], '">', $txt['shd_back_to_ticket'], '</a>
					</div>';

	echo '
				</div>';

	// Close the two-column container
	echo '
			</div>
		</form>';

	echo '
	</div>';
}

/**
 * Renders a single custom field based on its type.
 *
 * Supports all SimpleDesk custom field types:
 * - CFIELD_TYPE_TEXT (1): Single-line text input
 * - CFIELD_TYPE_LARGETEXT (2): Multi-line textarea
 * - CFIELD_TYPE_INT (3): Integer input
 * - CFIELD_TYPE_FLOAT (4): Floating-point input
 * - CFIELD_TYPE_SELECT (5): Select dropdown
 * - CFIELD_TYPE_CHECKBOX (6): Single checkbox
 * - CFIELD_TYPE_RADIO (7): Radio button group
 * - CFIELD_TYPE_MULTI (8): Multiple checkbox group
 *
 * @param array $field The custom field data array containing id, name, type,
 *                     options, value, default_value, length, etc.
 */
function template_shd_custom_field($field)
{
	// Determine the field type using constants if defined, otherwise raw integers
	$type_text = defined('CFIELD_TYPE_TEXT') ? CFIELD_TYPE_TEXT : 1;
	$type_largetext = defined('CFIELD_TYPE_LARGETEXT') ? CFIELD_TYPE_LARGETEXT : 2;
	$type_int = defined('CFIELD_TYPE_INT') ? CFIELD_TYPE_INT : 3;
	$type_float = defined('CFIELD_TYPE_FLOAT') ? CFIELD_TYPE_FLOAT : 4;
	$type_select = defined('CFIELD_TYPE_SELECT') ? CFIELD_TYPE_SELECT : 5;
	$type_checkbox = defined('CFIELD_TYPE_CHECKBOX') ? CFIELD_TYPE_CHECKBOX : 6;
	$type_radio = defined('CFIELD_TYPE_RADIO') ? CFIELD_TYPE_RADIO : 7;
	$type_multi = defined('CFIELD_TYPE_MULTI') ? CFIELD_TYPE_MULTI : 8;

	$field_name = 'custom_field_' . $field['id'];
	$value = isset($field['value']) ? $field['value'] : (isset($field['default_value']) ? $field['default_value'] : '');

	switch ($field['type'])
	{
		// Single-line text input
		case $type_text:
			$maxlength = !empty($field['length']) ? $field['length'] : 255;
			echo '
						<input type="text" name="', $field_name, '" id="field-', $field['id'], '" value="', $value, '" maxlength="', $maxlength, '" class="input_text" style="width: 100%; box-sizing: border-box;" />';
			break;

		// Multi-line textarea
		case $type_largetext:
			// Rows can be specified in default_value[1] for largetext fields
			$rows = 4;
			if (is_array($field['default_value']) && isset($field['default_value'][1]))
				$rows = (int) $field['default_value'][1];

			// For largetext, the value is the text content
			$text_value = is_array($value) ? (isset($value[0]) ? $value[0] : '') : $value;

			echo '
						<textarea name="', $field_name, '" id="field-', $field['id'], '" rows="', $rows, '" cols="50" style="width: 100%; box-sizing: border-box;">', $text_value, '</textarea>';
			break;

		// Integer input
		case $type_int:
			echo '
						<input type="text" name="', $field_name, '" id="field-', $field['id'], '" value="', $value, '" size="10" class="input_text" />';
			break;

		// Float input
		case $type_float:
			echo '
						<input type="text" name="', $field_name, '" id="field-', $field['id'], '" value="', $value, '" size="10" class="input_text" />';
			break;

		// Select dropdown
		case $type_select:
			echo '
						<select name="', $field_name, '" id="field-', $field['id'], '">';

			if (!empty($field['options']))
			{
				foreach ($field['options'] as $opt_key => $opt_label)
				{
					echo '
							<option value="', $opt_key, '"', ($value == $opt_key ? ' selected="selected"' : ''), '>', $opt_label, '</option>';
				}
			}

			echo '
						</select>';
			break;

		// Single checkbox
		case $type_checkbox:
			echo '
						<input type="checkbox" name="', $field_name, '" id="field-', $field['id'], '" value="1"', (!empty($value) ? ' checked="checked"' : ''), ' />';
			break;

		// Radio button group
		case $type_radio:
			if (!empty($field['options']))
			{
				foreach ($field['options'] as $opt_key => $opt_label)
				{
					echo '
						<label>
							<input type="radio" name="', $field_name, '" value="', $opt_key, '"', ($value == $opt_key ? ' checked="checked"' : ''), ' />
							', $opt_label, '
						</label><br />';
				}
			}
			break;

		// Multiple checkbox group
		case $type_multi:
			if (!empty($field['options']))
			{
				// Value for multi fields may be an array of selected keys
				$selected = is_array($value) ? $value : array();

				foreach ($field['options'] as $opt_key => $opt_label)
				{
					echo '
						<label>
							<input type="checkbox" name="custom_field_', $field['id'], '[]" value="', $opt_key, '"', (in_array($opt_key, $selected) ? ' checked="checked"' : ''), ' />
							', $opt_label, '
						</label><br />';
				}
			}
			break;
	}
}
