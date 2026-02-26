<?php
/**
 * SimpleDesk Helpdesk - Main Templates
 *
 * Templates for department list, ticket home, ticket blocks,
 * closed tickets, and recycle bin pages.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

/**
 * Department list template.
 *
 * Shows all departments the user can access, with ticket counts per department.
 * Uses $context['shd_departments'] (array with keys: id, name, description, link, ticket_count).
 */
function template_shd_department_list()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="shd_main">
		<div class="cat_bar">
			<h3 class="catbg">
				', $txt['shd_helpdesk'] ?? 'Helpdesk', ' - ', $txt['shd_departments'] ?? 'Departments', '
			</h3>
		</div>';

	if (!empty($context['shd_departments']))
	{
		echo '
		<div class="information">
			', sprintf($txt['shd_welcome'] ?? 'Welcome, %s!', $context['user']['name']), '
		</div>
		<div class="shd_departments">';

		foreach ($context['shd_departments'] as $dept)
		{
			echo '
			<div class="content">
				<h4>
					<a href="', $dept['link'], '">', $dept['name'], '</a>&nbsp;&nbsp;';

			if (isset($dept['ticket_count']))
				echo '
					<span class="floatright">', $dept['ticket_count'], ' ', ($dept['ticket_count'] == 1 ? ($txt['shd_count_ticket_1'] ?? 'ticket') : ($txt['shd_count_tickets'] ?? 'tickets')), '</span>';

			echo '
				</h4>';

			if (!empty($dept['description']))
				echo '
				<p>', $dept['description'], '</p>';

			echo '
			</div>';
		}

		echo '
		</div>';
	}
	else
	{
		echo '
		<div class="information">
			', $txt['shd_error_no_tickets'] ?? 'No departments available.', '
		</div>';
	}

	echo '
	</div>';
}

/**
 * Main helpdesk ticket home page.
 *
 * Shows the primary ticket listing with status-grouped blocks.
 * Layout: header, nav buttons, greeting, go-to-ticket form, ticket blocks.
 */
function template_shd_ticket_home()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="shd_main">';

	// Header bar
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', $context['page_title'] ?? ($txt['shd_helpdesk'] ?? 'Helpdesk'), '
			</h3>
		</div>';

	// Navigation / action buttons row
	echo '
		<div class="shd_nav_buttons flow_auto">
			<ul class="buttonlist">';

	if (!empty($context['can_new_ticket']))
		echo '
				<li><a href="', $scripturl, '?action=helpdesk;sa=newticket', (!empty($context['shd_department']) ? ';dept=' . $context['shd_department'] : ''), '" class="active">', $txt['shd_new_ticket'] ?? 'Post New Ticket', '</a></li>';

	if (!empty($context['can_proxy_ticket']))
		echo '
				<li><a href="', $scripturl, '?action=helpdesk;sa=newticket;proxy', (!empty($context['shd_department']) ? ';dept=' . $context['shd_department'] : ''), '">', $txt['shd_new_ticket_proxy'] ?? 'Post Proxy Ticket', '</a></li>';

	echo '
				<li><a href="', $scripturl, '?action=helpdesk;sa=closedtickets', (!empty($context['shd_department']) ? ';dept=' . $context['shd_department'] : ''), '">', $txt['shd_tickets_closed'] ?? 'Closed Tickets', '</a></li>';

	if (!empty($context['shd_can_recyclebin']))
		echo '
				<li><a href="', $scripturl, '?action=helpdesk;sa=recyclebin', (!empty($context['shd_department']) ? ';dept=' . $context['shd_department'] : ''), '">', $txt['shd_tickets_recycled'] ?? 'Recycled Tickets', '</a></li>';

	echo '
			</ul>
		</div>';

	// Greeting message
	echo '
		<div class="information">';

	if (isset($context['shd_home_view']) && $context['shd_home_view'] === 'staff')
		echo $txt['shd_staff_greeting'] ?? 'Here are all the tickets that require attention.';
	else
		echo $txt['shd_user_greeting'] ?? 'Here you can file new tickets for the site staff to action, and check on current tickets already underway.';

	echo '
		</div>';

	// "Go to Ticket #" form
	echo '
		<div class="shd_go_ticket flow_auto">
			<form action="', $scripturl, '?action=helpdesk;sa=viewticket" method="get">
				<input type="hidden" name="action" value="helpdesk" />
				<input type="hidden" name="sa" value="viewticket" />
				', $txt['shd_go_to_ticket'] ?? 'Go to ticket', '
				<input type="text" name="ticket" size="5" />
				<input type="submit" value="', $txt['shd_go'] ?? 'Go!', '" class="button_submit" />
			</form>
		</div>';

	// Ticket blocks - loop through the ordered blocks
	if (!empty($context['ticket_block_order']))
	{
		foreach ($context['ticket_block_order'] as $block)
		{
			template_ticket_block($block);
		}
	}

	echo '
	</div>';
}

/**
 * Renders a single ticket block (e.g., new tickets, pending staff, assigned to me).
 *
 * @param string $block The block key to render from $context['ticket_blocks'].
 */
function template_ticket_block($block)
{
	global $context, $scripturl, $txt;

	// Block data must exist
	if (!isset($context['ticket_blocks'][$block]))
		return;

	$block_data = $context['ticket_blocks'][$block];

	// Skip non-required blocks with zero tickets
	if (empty($block_data['required']) && empty($block_data['count']))
		return;

	echo '
		<div class="shd_ticket_block" id="block_', $block, '">';

	// Block header
	echo '
			<div class="cat_bar">
				<h3 class="catbg">';

	// Block title from language string, with count
	$block_title = '';
	if (!empty($block_data['title']))
		$block_title = $txt[$block_data['title']] ?? $block_data['title'];
	else
		$block_title = ucfirst($block);

	echo $block_title, ' - ', $block_data['count'] ?? 0;

	// Pagination links if available
	if (!empty($block_data['page_index']))
		echo '
					<span class="floatright">', $block_data['page_index'], '</span>';

	echo '
				</h3>
			</div>';

	// Ticket table or empty message
	if (!empty($block_data['tickets']))
	{
		echo '
			<table class="table_grid" style="width: 100%;">
				<thead>
					<tr class="title_bar">
						<th class="shd_ticketid" style="width: 7%;">';
		template_shd_sort_link($block, 'ticketid', $txt['shd_ticket'] ?? 'Ticket');
		echo '</th>
						<th class="shd_subject">';
		template_shd_sort_link($block, 'ticketname', $txt['shd_ticket_name'] ?? 'Subject');
		echo '</th>
						<th class="shd_starter" style="width: 14%;">';
		template_shd_sort_link($block, 'starter', $txt['shd_ticket_started_by'] ?? 'Started By');
		echo '</th>
						<th class="shd_replies" style="width: 7%;">';
		template_shd_sort_link($block, 'replies', $txt['shd_ticket_replies'] ?? 'Replies');
		echo '</th>
						<th class="shd_status" style="width: 12%;">';
		template_shd_sort_link($block, 'status', $txt['shd_ticket_status'] ?? 'Status');
		echo '</th>
						<th class="shd_urgency" style="width: 8%;">';
		template_shd_sort_link($block, 'urgency', $txt['shd_ticket_urgency'] ?? 'Urgency');
		echo '</th>
						<th class="shd_assigned" style="width: 14%;">';
		template_shd_sort_link($block, 'assigned', $txt['shd_ticket_assigned'] ?? 'Assigned To');
		echo '</th>
						<th class="shd_updated" style="width: 18%;">';
		template_shd_sort_link($block, 'updated', $txt['shd_ticket_updated'] ?? 'Last Updated');
		echo '</th>
					</tr>
				</thead>
				<tbody>';

		foreach ($block_data['tickets'] as $ticket)
		{
			echo '
					<tr class="windowbg', (!empty($ticket['is_unread']) ? ' shd_unread' : ''), '">
						<td class="shd_ticketid">', $ticket['display_id'], '</td>
						<td class="shd_subject">
							<a href="', $ticket['link'], '">', $ticket['subject'], '</a>';

			if (!empty($ticket['private']))
				echo ' <span class="shd_private">[', $txt['shd_ticket_private'] ?? 'Private', ']</span>';

			echo '
						</td>
						<td class="shd_starter">', $ticket['starter_name'] ?? '', '</td>
						<td class="shd_replies centertext">', $ticket['num_replies'] ?? 0, '</td>
						<td class="shd_status">', $txt['shd_status_' . $ticket['status']] ?? '', '</td>';

			// Urgency - bold if high (>= 2)
			$urgency_class = '';
			if (isset($ticket['urgency']) && $ticket['urgency'] >= 2)
				$urgency_class = ' shd_urgency_high';

			echo '
						<td class="shd_urgency', $urgency_class, '">', $txt['shd_urgency_' . $ticket['urgency']] ?? '', '</td>
						<td class="shd_assigned">', $ticket['assigned_name'] ?? '', '</td>
						<td class="shd_updated">', $ticket['last_updated'] ?? '', '</td>
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
				', $txt['shd_error_no_tickets'] ?? 'No tickets were found.', '
			</div>';
	}

	echo '
		</div>';
}

/**
 * Generates a sortable column header link for ticket block tables.
 *
 * @param string $block The block key.
 * @param string $field The sort field name.
 * @param string $label The display label for the column header.
 */
function template_shd_sort_link($block, $field, $label)
{
	global $context, $scripturl;

	// If sort data is not available, just output the label
	if (!isset($context['ticket_blocks'][$block]['sort']))
	{
		echo $label;
		return;
	}

	$current = $context['ticket_blocks'][$block]['sort'];
	$is_current = ($current['field'] == $field);
	$new_dir = ($is_current && $current['direction'] == 'asc') ? 'desc' : 'asc';

	// Build URL preserving other block positions
	$url = $scripturl . '?action=helpdesk;sa=' . ($context['shd_ticket_sa'] ?? 'tickets');

	if (!empty($context['shd_department']))
		$url .= ';dept=' . $context['shd_department'];

	// Preserve start positions for all blocks
	if (!empty($context['ticket_blocks']))
	{
		foreach ($context['ticket_blocks'] as $bk => $bdata)
		{
			if (!empty($bdata['start']))
				$url .= ';st_' . $bk . '=' . $bdata['start'];
		}
	}

	$url .= ';so_' . $block . '=' . $field . '_' . $new_dir;

	echo '<a href="', $url, '">', $label;

	if ($is_current)
		echo ' <span class="sort_arrow">', ($current['direction'] == 'asc' ? '&#9650;' : '&#9660;'), '</span>';

	echo '</a>';
}

/**
 * Closed tickets page template.
 *
 * Similar to home but displays a single block of closed tickets.
 */
function template_shd_closedtickets()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="shd_main">';

	// Header bar
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', $context['page_title'] ?? ($txt['shd_tickets_closed'] ?? 'Closed Tickets'), '
			</h3>
		</div>';

	// Navigation / action buttons row
	echo '
		<div class="shd_nav_buttons flow_auto">
			<ul class="buttonlist">';

	if (!empty($context['can_new_ticket']))
		echo '
				<li><a href="', $scripturl, '?action=helpdesk;sa=newticket', (!empty($context['shd_department']) ? ';dept=' . $context['shd_department'] : ''), '" class="active">', $txt['shd_new_ticket'] ?? 'Post New Ticket', '</a></li>';

	if (!empty($context['can_proxy_ticket']))
		echo '
				<li><a href="', $scripturl, '?action=helpdesk;sa=newticket;proxy', (!empty($context['shd_department']) ? ';dept=' . $context['shd_department'] : ''), '">', $txt['shd_new_ticket_proxy'] ?? 'Post Proxy Ticket', '</a></li>';

	echo '
				<li><a href="', $scripturl, '?action=helpdesk;sa=tickets', (!empty($context['shd_department']) ? ';dept=' . $context['shd_department'] : ''), '">', $txt['shd_tickets_open'] ?? 'Open Tickets', '</a></li>';

	if (!empty($context['shd_can_recyclebin']))
		echo '
				<li><a href="', $scripturl, '?action=helpdesk;sa=recyclebin', (!empty($context['shd_department']) ? ';dept=' . $context['shd_department'] : ''), '">', $txt['shd_tickets_recycled'] ?? 'Recycled Tickets', '</a></li>';

	echo '
			</ul>
		</div>';

	// "Go to Ticket #" form
	echo '
		<div class="shd_go_ticket flow_auto">
			<form action="', $scripturl, '?action=helpdesk;sa=viewticket" method="get">
				<input type="hidden" name="action" value="helpdesk" />
				<input type="hidden" name="sa" value="viewticket" />
				', $txt['shd_go_to_ticket'] ?? 'Go to ticket', '
				<input type="text" name="ticket" size="5" />
				<input type="submit" value="', $txt['shd_go'] ?? 'Go!', '" class="button_submit" />
			</form>
		</div>';

	// Single closed block
	template_ticket_block('closed');

	echo '
	</div>';
}

/**
 * Recycle bin page template.
 *
 * Shows two blocks: deleted tickets and tickets with deleted replies.
 */
function template_shd_recyclebin()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="shd_main">';

	// Header bar
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', $context['page_title'] ?? ($txt['shd_tickets_recycled'] ?? 'Recycled Tickets'), '
			</h3>
		</div>';

	// Navigation / action buttons row
	echo '
		<div class="shd_nav_buttons flow_auto">
			<ul class="buttonlist">';

	if (!empty($context['can_new_ticket']))
		echo '
				<li><a href="', $scripturl, '?action=helpdesk;sa=newticket', (!empty($context['shd_department']) ? ';dept=' . $context['shd_department'] : ''), '" class="active">', $txt['shd_new_ticket'] ?? 'Post New Ticket', '</a></li>';

	if (!empty($context['can_proxy_ticket']))
		echo '
				<li><a href="', $scripturl, '?action=helpdesk;sa=newticket;proxy', (!empty($context['shd_department']) ? ';dept=' . $context['shd_department'] : ''), '">', $txt['shd_new_ticket_proxy'] ?? 'Post Proxy Ticket', '</a></li>';

	echo '
				<li><a href="', $scripturl, '?action=helpdesk;sa=tickets', (!empty($context['shd_department']) ? ';dept=' . $context['shd_department'] : ''), '">', $txt['shd_tickets_open'] ?? 'Open Tickets', '</a></li>
				<li><a href="', $scripturl, '?action=helpdesk;sa=closedtickets', (!empty($context['shd_department']) ? ';dept=' . $context['shd_department'] : ''), '">', $txt['shd_tickets_closed'] ?? 'Closed Tickets', '</a></li>';

	echo '
			</ul>
		</div>';

	// "Go to Ticket #" form
	echo '
		<div class="shd_go_ticket flow_auto">
			<form action="', $scripturl, '?action=helpdesk;sa=viewticket" method="get">
				<input type="hidden" name="action" value="helpdesk" />
				<input type="hidden" name="sa" value="viewticket" />
				', $txt['shd_go_to_ticket'] ?? 'Go to ticket', '
				<input type="text" name="ticket" size="5" />
				<input type="submit" value="', $txt['shd_go'] ?? 'Go!', '" class="button_submit" />
			</form>
		</div>';

	// Two blocks: deleted tickets and tickets with deleted replies
	template_ticket_block('recycle');
	template_ticket_block('withdeleted');

	echo '
	</div>';
}

/**
 * Simple ticket list template (backward compatibility).
 *
 * Used by viewblock and legacy sub-actions that populate $context['shd_tickets']
 * directly rather than using the block system.
 */
function template_shd_ticket_list()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<div id="shd_main">
		<div class="cat_bar">
			<h3 class="catbg">
				', $context['page_title'] ?? ($txt['shd_helpdesk'] ?? 'Helpdesk'), '
			</h3>
		</div>';

	// Navigation buttons
	echo '
		<div class="shd_nav_buttons flow_auto">
			<ul class="buttonlist">';

	if (!empty($context['can_new_ticket']) || (function_exists('shd_allowed_to') && shd_allowed_to('shd_new_ticket', 0)))
		echo '
				<li><a href="', $scripturl, '?action=helpdesk;sa=newticket', (!empty($context['shd_department']) ? ';dept=' . $context['shd_department'] : ''), '" class="active">', $txt['shd_new_ticket'] ?? 'Post New Ticket', '</a></li>';

	echo '
			</ul>
		</div>';

	if (!empty($context['shd_tickets']))
	{
		echo '
		<table class="table_grid" style="width: 100%;">
			<thead>
				<tr class="title_bar">
					<th class="shd_ticketid" style="width: 7%;">', $txt['shd_ticket'] ?? 'Ticket', '</th>
					<th class="shd_subject">', $txt['shd_ticket_name'] ?? 'Subject', '</th>
					<th class="shd_starter" style="width: 14%;">', $txt['shd_ticket_started_by'] ?? 'Started By', '</th>
					<th class="shd_replies" style="width: 7%;">', $txt['shd_ticket_replies'] ?? 'Replies', '</th>
					<th class="shd_status" style="width: 12%;">', $txt['shd_ticket_status'] ?? 'Status', '</th>
					<th class="shd_urgency" style="width: 8%;">', $txt['shd_ticket_urgency'] ?? 'Urgency', '</th>
					<th class="shd_assigned" style="width: 14%;">', $txt['shd_ticket_assigned'] ?? 'Assigned To', '</th>
					<th class="shd_updated" style="width: 18%;">', $txt['shd_ticket_updated'] ?? 'Last Updated', '</th>
				</tr>
			</thead>
			<tbody>';

		foreach ($context['shd_tickets'] as $ticket)
		{
			echo '
				<tr class="windowbg', (!empty($ticket['is_unread']) ? ' shd_unread' : ''), '">
					<td class="shd_ticketid">', $ticket['display_id'], '</td>
					<td class="shd_subject">
						<a href="', $ticket['link'], '">', $ticket['subject'], '</a>';

			if (!empty($ticket['private']))
				echo ' <span class="shd_private">[', $txt['shd_ticket_private'] ?? 'Private', ']</span>';

			echo '
					</td>
					<td class="shd_starter">', $ticket['starter_name'] ?? '', '</td>
					<td class="shd_replies centertext">', $ticket['num_replies'] ?? 0, '</td>
					<td class="shd_status">', $txt['shd_status_' . $ticket['status']] ?? '', '</td>
					<td class="shd_urgency">', $txt['shd_urgency_' . $ticket['urgency']] ?? '', '</td>
					<td class="shd_assigned">', $ticket['assigned_name'] ?? '', '</td>
					<td class="shd_updated">', $ticket['last_updated'] ?? '', '</td>
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
			', $txt['shd_error_no_tickets'] ?? 'No tickets were found.', '
		</div>';
	}

	echo '
	</div>';
}
