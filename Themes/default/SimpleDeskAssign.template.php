<?php
/**
 * SimpleDesk Assign Template
 *
 * Templates for the ticket assignment interface. Provides the form
 * for selecting a staff member to assign a ticket to.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Assignment form template.
 *
 * Renders a form with:
 * - Current assignment display
 * - Dropdown of possible assignees (including "Unassigned")
 * - Submit and cancel buttons
 *
 * Uses $context['member_list'] for the assignee options,
 * $context['ticket_assigned'] for the currently assigned member,
 * and $context['ticket_id'] for the ticket being assigned.
 */
function template_shd_assign()
{
	global $context, $txt, $scripturl, $settings;

	echo '
	<div id="shd_main">';

	// Header bar
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', $txt['shd_ticket_assign_ticket'] ?? 'Assign Ticket', '
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

	// Assignment form
	echo '
		<div class="content">
			<form action="', $scripturl, '?action=helpdesk;sa=assign2;ticket=', $context['ticket_id'], '" method="post">
				<dl class="settings">';

	// Current assignment
	echo '
					<dt>
						<strong>', $txt['shd_ticket_assignedto'] ?? 'Currently Assigned To', '</strong>
					</dt>
					<dd>';

	if (isset($context['member_list'][$context['ticket_assigned']]))
		echo $context['member_list'][$context['ticket_assigned']];
	else
		echo $txt['shd_unassigned'] ?? 'Unassigned';

	echo '</dd>';

	// New assignment dropdown
	echo '
					<dt>
						<strong>', $txt['shd_ticket_assign_to'] ?? 'Assign To', '</strong>
					</dt>
					<dd>
						<select name="to_user">';

	foreach ($context['member_list'] as $id => $name)
		echo '
							<option value="', $id, '"', ($id == $context['ticket_assigned'] ? ' selected="selected"' : ''), '>', $name, '</option>';

	echo '
						</select>
					</dd>
				</dl>';

	// Buttons
	echo '
				<div class="shd_nav_buttons flow_auto" style="padding: 8px;">
					<input type="submit" value="', $txt['shd_ticket_assign_ticket'] ?? 'Assign Ticket', '" class="button_submit" />
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
