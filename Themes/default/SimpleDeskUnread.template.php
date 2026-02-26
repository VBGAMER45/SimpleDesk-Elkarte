<?php
/**
 * SimpleDesk Unread Template
 *
 * Displays helpdesk tickets with unread activity on the
 * forum's unread posts / unread replies pages.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Template layer top for unread tickets display.
 *
 * Placeholder for any content that should appear above the unread tickets block.
 */
function template_shd_unread_above()
{
	// Placeholder - template layer top.
}

/**
 * Template layer bottom for unread tickets display.
 *
 * Displays a block of helpdesk tickets with unread activity, including
 * ticket ID, subject, starter, replies, status, urgency, and last updated time.
 * Integrates into the forum's unread posts page so users see ticket updates
 * alongside regular unread posts.
 *
 * Uses $context['shd_unread_info'] for ticket data and $settings['images_url']
 * for icon paths.
 */
function template_shd_unread_below()
{
	global $context, $txt, $scripturl, $settings;

	// Only display if we have unread ticket data set up
	if (!isset($context['shd_unread_info']))
		return;

	$tickets = $context['shd_unread_info'];
	$count = count($tickets);

	echo '
	<div id="shd_unread_block">
		<div class="cat_bar">
			<h3 class="catbg">
				<img src="', $settings['images_url'], '/simpledesk/ticket.png" alt="" /> ',
				$txt['shd_helpdesk'] ?? 'Helpdesk', ' - ',
				$txt['shd_unread_tickets'] ?? 'Unread Tickets',
				' (', $count, ')
			</h3>
		</div>';

	if (!empty($tickets))
	{
		echo '
		<table class="table_grid shd_ticketlist" style="width: 100%;">
			<thead>
				<tr class="title_bar sd_unread_title">
					<th class="shd_unread_ticket_id" style="width: 6%;">', $txt['shd_ticket_id'] ?? 'ID', '</th>
					<th class="shd_unread_ticket_name" style="width: 28%;">', $txt['shd_ticket_name'] ?? 'Ticket', '</th>
					<th class="shd_unread_ticket_starter" style="width: 15%;">', $txt['shd_ticket_started_by'] ?? 'Started By', '</th>
					<th class="shd_unread_ticket_replies" style="width: 8%;">', $txt['shd_ticket_replies'] ?? 'Replies', '</th>
					<th class="shd_unread_ticket_status" style="width: 15%;">', $txt['shd_ticket_status'] ?? 'Status', '</th>
					<th class="shd_unread_ticket_urgency" style="width: 12%;">', $txt['shd_ticket_urgency'] ?? 'Urgency', '</th>
					<th class="shd_unread_ticket_updated" style="width: 16%;">', $txt['shd_ticket_updated'] ?? 'Updated', '</th>
				</tr>
			</thead>
			<tbody>';

		foreach ($tickets as $ticket)
		{
			echo '
				<tr class="content sd_unread_row">
					<td class="shd_unread_ticket_id">
						<span>', $ticket['id_ticket_display'], '</span>
					</td>
					<td class="shd_unread_ticket_name">
						<span><a href="', $ticket['href'], '">', $ticket['subject'], '</a></span>
					</td>
					<td class="shd_unread_ticket_starter">
						<span>', $ticket['ticket_starter'], '</span>
					</td>
					<td class="shd_unread_ticket_replies">
						<span>', $ticket['num_replies'], '</span>
					</td>
					<td class="shd_unread_ticket_status">
						<span>', $txt['shd_status_' . $ticket['status']] ?? $txt['shd_unknown'] ?? 'Unknown', '</span>
					</td>
					<td class="shd_unread_ticket_urgency">
						<span>', $txt['shd_urgency_' . $ticket['urgency']] ?? $txt['shd_unknown'] ?? 'Unknown', '</span>
					</td>
					<td class="shd_unread_ticket_updated">
						<span>', $ticket['updated'], '</span>
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
		<div class="information">
			', $txt['shd_no_unread_tickets'] ?? 'There are no unread helpdesk tickets.', '
		</div>';
	}

	echo '
	</div>';
}
