<?php
/**
 * SimpleDesk Move Department Template
 *
 * Templates for moving tickets between departments. Provides the form
 * for selecting a destination department.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Move department form template.
 *
 * Renders a form with:
 * - Current department display
 * - Dropdown of available destination departments
 * - Submit and cancel buttons
 *
 * Uses $context['dept_list'] for the destination options,
 * $context['current_dept_name'] for the current department name,
 * and $context['ticket_id'] for the ticket being moved.
 */
function template_shd_movedept()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="shd_main">';

	// Header bar
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', $txt['shd_ticket_move_dept'] ?? 'Move Department', '
			</h3>
		</div>';

	// Ticket subject reference
	if (!empty($context['ticket_subject']))
	{
		echo '
		<div class="information">
			<strong>', $txt['shd_ticket'] ?? 'Ticket', ':</strong> ', $context['ticket_subject'], '
		</div>';
	}

	// Move form
	echo '
		<div class="content">
			<form action="', $scripturl, '?action=helpdesk;sa=movedept;ticket=', $context['ticket_id'], '" method="post">
				<dl class="settings">';

	// Current department
	echo '
					<dt>
						<strong>', $txt['shd_current_dept'] ?? 'Current Department', '</strong>
					</dt>
					<dd>', $context['current_dept_name'], '</dd>';

	// Destination dropdown
	echo '
					<dt>
						<strong>', $txt['shd_ticket_move_to'] ?? 'Move To', '</strong>
					</dt>
					<dd>
						<select name="to_dept">';

	foreach ($context['dept_list'] as $id => $name)
		echo '
							<option value="', $id, '">', $name, '</option>';

	echo '
						</select>
					</dd>
				</dl>';

	// Buttons
	echo '
				<div class="shd_nav_buttons flow_auto" style="padding: 8px;">
					<input type="submit" value="', $txt['shd_ticket_move'] ?? 'Move Ticket', '" class="button_submit" />
					<input type="submit" name="cancel" value="', $txt['shd_cancel_ticket'] ?? 'Cancel', '" class="button" />
				</div>';

	// Hidden fields
	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />';

	if (!empty($context['shd_return_to']) && $context['shd_return_to'] == 'home')
		echo '
				<input type="hidden" name="home" value="1" />';

	echo '
			</form>
		</div>';

	// Back to ticket link
	echo '
		<div class="shd_nav_buttons flow_auto" style="padding: 4px 0;">
			<a href="', $scripturl, '?action=helpdesk;sa=viewticket;ticket=', $context['ticket_id'], '">', $txt['shd_back_to_ticket'] ?? 'Back to Ticket', '</a>
		</div>';

	echo '
	</div>';
}
