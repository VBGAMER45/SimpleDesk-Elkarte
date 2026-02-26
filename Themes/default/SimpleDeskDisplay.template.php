<?php
/**
 * SimpleDesk Display Template
 *
 * Templates for displaying individual tickets and replies.
 * Renders the ticket view with details sidebar, body, replies,
 * quick reply form, and action log.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Main ticket display template.
 *
 * Renders a single ticket with:
 * - Header bar (ticket subject + ID)
 * - Action buttons row
 * - Two-column layout: sidebar (details) + content (body + replies)
 * - Quick reply form (if user can reply)
 * - Action log (if entries exist)
 *
 * Uses $context['ticket'] for ticket data, $context['ticket_replies'] for replies,
 * $context['ticket_buttons'] for action buttons, and $context['ticket_log'] for log entries.
 */
function template_shd_display_ticket()
{
	global $context, $scripturl, $txt;

	$ticket = $context['ticket'];

	echo '
	<div id="shd_main">';

	// 1. Header bar - ticket subject with display ID
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', $ticket['display_id'], ' - ', $ticket['subject'], '
			</h3>
		</div>';

	// 2. Action buttons row
	echo '
		<div class="shd_nav_buttons flow_auto">
			<ul class="buttonlist">';

	if (!empty($context['ticket_buttons']))
	{
		foreach ($context['ticket_buttons'] as $button)
		{
			if (!empty($button['display']))
				echo '
				<li><a href="', $button['url'], '">', (isset($txt[$button['text']]) ? $txt[$button['text']] : $button['text']), '</a></li>';
		}
	}

	// Back to helpdesk link
	echo '
				<li><a href="', $scripturl, '?action=helpdesk">', $txt['shd_back_to_helpdesk'] ?? 'Back to Helpdesk', '</a></li>
			</ul>
		</div>';

	// 3. Two-column layout: sidebar on the right, content on the left
	// Sidebar floats right, content has margin-right to avoid overlap

	// -- Left sidebar (floated right per CSS) --
	echo '
		<div class="shd_ticket_side">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['shd_ticket_details'] ?? 'Ticket Details', '
				</h3>
			</div>
			<div class="content">
				<dl class="settings">';

	// Ticket ID
	echo '
					<dt>', $txt['shd_ticket'] ?? 'Ticket', '</dt>
					<dd>', $ticket['display_id'], '</dd>';

	// Department
	echo '
					<dt>', $txt['shd_ticket_dept'] ?? 'Department', '</dt>
					<dd>', $ticket['dept_name'], '</dd>';

	// Started By
	echo '
					<dt>', $txt['shd_ticket_started_by'] ?? 'Started By', '</dt>
					<dd>', !empty($ticket['member']['link']) ? $ticket['member']['link'] : $ticket['member']['name'], '</dd>';

	// Posted On
	echo '
					<dt>', $txt['shd_ticket_date'] ?? 'Posted on', '</dt>
					<dd>', $ticket['poster_time'], '</dd>';

	// Status
	$status_level = $ticket['status']['level'];
	$status_class = '';
	$status_map = array(
		0 => 'shd_status_new',
		1 => 'shd_status_pending_staff',
		2 => 'shd_status_pending_user',
		3 => 'shd_status_closed',
		4 => 'shd_status_pending_staff',
		5 => 'shd_status_pending_staff',
		6 => 'shd_status_deleted',
		7 => 'shd_status_hold',
	);
	if (isset($status_map[$status_level]))
		$status_class = $status_map[$status_level];

	echo '
					<dt>', $txt['shd_ticket_status'] ?? 'Status', '</dt>
					<dd><span class="', $status_class, '">', $ticket['status']['label'], '</span></dd>';

	// Urgency
	$urgency_level = $ticket['urgency']['level'];
	$urgency_class = '';
	$urgency_class_map = array(
		2 => 'shd_urgency_high',
		3 => 'shd_urgency_vhigh',
		4 => 'shd_urgency_severe',
		5 => 'shd_urgency_critical',
	);
	if (isset($urgency_class_map[$urgency_level]))
		$urgency_class = ' class="' . $urgency_class_map[$urgency_level] . '"';

	echo '
					<dt>', $txt['shd_ticket_urgency'] ?? 'Urgency', '</dt>
					<dd>
						<span', $urgency_class, '>', $ticket['urgency']['label'], '</span>';

	// Urgency increase/decrease links
	if (!empty($ticket['urgency']['increase']))
		echo '
						<a href="', $scripturl, '?action=helpdesk;sa=urgencychange;ticket=', $ticket['id'], ';change=increase;', $context['session_var'], '=', $context['session_id'], '">[', $txt['shd_urgency_increase'] ?? 'Increase', ']</a>';

	if (!empty($ticket['urgency']['decrease']))
		echo '
						<a href="', $scripturl, '?action=helpdesk;sa=urgencychange;ticket=', $ticket['id'], ';change=decrease;', $context['session_var'], '=', $context['session_id'], '">[', $txt['shd_urgency_decrease'] ?? 'Decrease', ']</a>';

	echo '
					</dd>';

	// Assigned To
	echo '
					<dt>', $txt['shd_ticket_assigned'] ?? 'Assigned To', '</dt>
					<dd>';

	if (!empty($ticket['assigned']['id']))
		echo !empty($ticket['assigned']['link']) ? $ticket['assigned']['link'] : $ticket['assigned']['name'];
	else
		echo '<span style="color: #c00;">', $txt['shd_unassigned'] ?? 'Unassigned', '</span>';

	echo '</dd>';

	// Replies
	echo '
					<dt>', $txt['shd_ticket_replies'] ?? 'Replies', '</dt>
					<dd>', $ticket['num_replies'], '</dd>';

	// Privacy
	echo '
					<dt>', $txt['shd_ticket_private'] ?? 'Private', '</dt>
					<dd>', $ticket['privacy']['label'];

	if (!empty($ticket['privacy']['can_change']))
	{
		if ($ticket['privacy']['level'] == 0)
			echo ' <a href="', $scripturl, '?action=helpdesk;sa=privacychange;ticket=', $ticket['id'], ';', $context['session_var'], '=', $context['session_id'], '">[', $txt['shd_mark_private'] ?? 'Mark Private', ']</a>';
		else
			echo ' <a href="', $scripturl, '?action=helpdesk;sa=privacychange;ticket=', $ticket['id'], ';', $context['session_var'], '=', $context['session_id'], '">[', $txt['shd_mark_not_private'] ?? 'Remove Private', ']</a>';
	}

	echo '</dd>';

	// IP Address (only if set and viewable)
	if (!empty($ticket['ip_address']) && !empty($ticket['can_view_ip']))
		echo '
					<dt>IP</dt>
					<dd>', $ticket['ip_address'], '</dd>';

	echo '
				</dl>
			</div>
		</div>';

	// -- Right content area --
	echo '
		<div class="shd_ticket_content">';

	// Ticket body
	echo '
			<div class="content shd_ticket_body_container">';

	// Warning notices for deleted or closed tickets
	if (!empty($ticket['deleted']))
		echo '
				<div class="errorbox">', $txt['shd_ticket_deleted_warning'] ?? 'This ticket has been deleted.', '</div>';
	elseif (!empty($ticket['closed']))
		echo '
				<div class="information">', $txt['shd_ticket_resolved_info'] ?? 'This ticket has been resolved.', '</div>';

	echo '
				<div class="shd_ticket_body">
					', $ticket['body'], '
				</div>';

	// Ticket attachments
	if (!empty($context['ticket_attach']['ticket']))
		template_shd_display_attachments($context['ticket_attach']['ticket']);

	// Modified info if applicable
	if (!empty($ticket['modified']))
		echo '
				<div class="shd_modified smalltext">
					', sprintf($txt['shd_ticket_modified'] ?? 'Last modified by %1$s on %2$s', $ticket['modified']['name'], $ticket['modified']['time']), '
				</div>';

	echo '
			</div>';

	// 4. Replies section
	if (!empty($context['ticket_replies']) || $ticket['num_replies'] > 0)
	{
		echo '
			<div class="shd_replies_container">
				<div class="cat_bar">
					<h3 class="catbg">
						', $txt['shd_ticket_replies'] ?? 'Replies', ' (', $ticket['num_replies'], ')';

		if (!empty($context['page_index']))
			echo '
						<span class="floatright">', $context['page_index'], '</span>';

		echo '
					</h3>
				</div>';

		if (!empty($context['ticket_replies']))
		{
			foreach ($context['ticket_replies'] as $reply)
			{
				$is_deleted = (!empty($reply['message_status']) && $reply['message_status'] == 1);
				$reply_class = 'windowbg';
				if ($is_deleted)
					$reply_class .= ' shd_deleted_reply';

				echo '
				<div class="', $reply_class, '" id="msg_', $reply['id'], '">';

				// Reply header
				echo '
					<div class="shd_reply_header">';

				// Action links (floated right)
				echo '
						<span class="floatright">';

				if (!empty($reply['can_edit']) && !$is_deleted)
					echo '
							<a href="', $scripturl, '?action=helpdesk;sa=editreply;ticket=', $ticket['id'], ';msg=', $reply['id'], '">', $txt['shd_edit_reply'] ?? 'Edit', '</a>';

				if (!empty($reply['can_delete']) && !$is_deleted)
					echo '
							<a href="', $scripturl, '?action=helpdesk;sa=deletereply;ticket=', $ticket['id'], ';msg=', $reply['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['shd_delete_reply'] ?? 'Delete', '</a>';

				if (!empty($reply['can_restore']) && $is_deleted)
					echo '
							<a href="', $scripturl, '?action=helpdesk;sa=restorereply;ticket=', $ticket['id'], ';msg=', $reply['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['shd_restore_reply'] ?? 'Restore', '</a>';

				echo '
						</span>';

				// Poster name and time
				echo '
						<strong>', !empty($reply['member']['link']) ? $reply['member']['link'] : $reply['member']['name'], '</strong>';

				if (!empty($reply['is_staff']))
					echo '
						<span class="shd_staff_badge">[', $txt['shd_permrole_staff'] ?? 'Staff', ']</span>';

				echo '
						- ', $reply['time'], '
					</div>';

				// Deleted reply warning
				if ($is_deleted)
					echo '
					<div class="errorbox">', $txt['shd_reply_deleted_warning'] ?? 'This reply has been deleted.', '</div>';

				// Reply body
				echo '
					<div class="shd_reply_body">
						', $reply['body'], '
					</div>';

				// Reply attachments
				if (!empty($context['ticket_attach']['reply'][$reply['id']]))
					template_shd_display_attachments($context['ticket_attach']['reply'][$reply['id']]);

				// Modified info if applicable
				if (!empty($reply['modified']))
					echo '
					<div class="shd_modified smalltext">
						', sprintf($txt['shd_ticket_modified'] ?? 'Last modified by %1$s on %2$s', $reply['modified']['name'], $reply['modified']['time']), '
					</div>';

				// IP address if viewable
				if (!empty($reply['ip_address']) && !empty($ticket['can_view_ip']))
					echo '
					<div class="smalltext">IP: ', $reply['ip_address'], '</div>';

				echo '
				</div>';
			}
		}

		echo '
			</div>';
	}

	// 5. Quick reply form (if user can reply)
	if (!empty($context['can_reply']) || !empty($ticket['can_reply']))
	{
		echo '
			<div class="shd_quickreply">
				<div class="cat_bar">
					<h3 class="catbg">', $txt['shd_post_reply'] ?? 'Post a Reply', '</h3>
				</div>
				<div class="content">
					<form action="', $scripturl, '?action=helpdesk;sa=savereply" method="post">
						<input type="hidden" name="ticket" value="', $ticket['id'], '" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<textarea name="message" rows="8" style="width: 100%;"></textarea>
						<br /><br />
						<input type="submit" value="', $txt['shd_post_reply'] ?? 'Post Reply', '" class="button_submit" />
					</form>
				</div>
			</div>';
	}

	echo '
		</div>';

	// Clear floats after the two-column layout
	echo '
		<div style="clear: both;"></div>';

	// 6. Action log (if entries exist)
	if (!empty($context['ticket_log']))
	{
		echo '
		<div class="shd_action_log">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['shd_ticket_log'] ?? 'Ticket Log', '</h3>
			</div>
			<table class="table_grid" style="width:100%;">
				<thead>
					<tr class="title_bar">
						<th>', $txt['shd_ticket_date'] ?? 'Date', '</th>
						<th>', $txt['shd_ticket_started_by'] ?? 'Member', '</th>
						<th>', $txt['shd_action'] ?? 'Action', '</th>
					</tr>
				</thead>
				<tbody>';

		foreach ($context['ticket_log'] as $entry)
		{
			echo '
					<tr class="windowbg">
						<td>', $entry['time'], '</td>
						<td>', !empty($entry['member']['link']) ? $entry['member']['link'] : $entry['member']['name'], '</td>
						<td>', $entry['action'], '</td>
					</tr>';
		}

		echo '
				</tbody>
			</table>
		</div>';
	}

	echo '
	</div>';
}

/**
 * Renders a set of attachments (images with thumbnails, files with download links).
 *
 * @param array $attachments Array of attachment info arrays from shd_build_attachment_info().
 */
function template_shd_display_attachments($attachments)
{
	global $context, $scripturl, $txt;

	if (empty($attachments))
		return;

	echo '
				<div class="shd_ticket_attachments" style="padding: 6px 0; border-top: 1px solid #ddd; margin-top: 6px;">
					<strong>', $txt['shd_ticket_attachments'] ?? 'Attachments', '</strong> (', count($attachments), ')
					<div style="padding: 4px 0;">';

	$count = 0;
	foreach ($attachments as $attachment)
	{
		if (!empty($attachment['is_image']))
		{
			// Image attachment - show thumbnail or scaled image
			echo '
						<div style="display: inline-block; margin: 4px 8px 4px 0; text-align: center; vertical-align: top;">';

			if (!empty($attachment['thumbnail']['has_thumb']))
			{
				echo '
							<a href="', $attachment['href'], ';image">
								<img src="', $attachment['thumbnail']['href'], '" alt="', $attachment['name'], '"
									style="max-width: 150px; max-height: 150px; border: 1px solid #ccc;" />
							</a>';
			}
			else
			{
				echo '
							<a href="', $attachment['href'], ';image">
								<img src="', $attachment['href'], ';image" alt="', $attachment['name'], '"
									width="', $attachment['width'], '" height="', $attachment['height'], '"
									style="max-width: 150px; max-height: 150px; border: 1px solid #ccc;" />
							</a>';
			}

			echo '
							<br />
							<span class="smalltext">', $attachment['name'], ' (', $attachment['size'], ')</span>';

			if (!empty($attachment['can_delete']))
				echo '
							<br /><a href="', $scripturl, '?action=helpdesk;sa=deleteattach;ticket=', $context['ticket']['id'], ';attach=', $attachment['id'], ';', $context['session_var'], '=', $context['session_id'], '"
								onclick="return confirm(\'', $txt['shd_delete_attach_confirm'] ?? 'Are you sure?', '\');"
								class="smalltext" style="color: #c00;">', $txt['shd_delete_attach'] ?? 'Delete', '</a>';

			echo '
						</div>';
		}
		else
		{
			// File attachment - download link
			echo '
						<div style="padding: 2px 0;">
							', $attachment['link'], '
							<span class="smalltext">(', $attachment['size'], ')</span>';

			if (!empty($attachment['can_delete']))
				echo '
							<a href="', $scripturl, '?action=helpdesk;sa=deleteattach;ticket=', $context['ticket']['id'], ';attach=', $attachment['id'], ';', $context['session_var'], '=', $context['session_id'], '"
								onclick="return confirm(\'', $txt['shd_delete_attach_confirm'] ?? 'Are you sure?', '\');"
								class="smalltext" style="color: #c00; margin-left: 4px;">', $txt['shd_delete_attach'] ?? 'Delete', '</a>';

			echo '
						</div>';
		}

		$count++;
	}

	echo '
					</div>
				</div>';
}
