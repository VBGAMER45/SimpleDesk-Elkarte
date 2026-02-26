<?php
/**
 * SimpleDesk Stats Plugin - Templates
 *
 * Templates for displaying helpdesk statistics in the admin panel.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

/**
 * Display the helpdesk statistics page.
 */
function template_shd_stats()
{
	global $context, $txt, $settings, $scripturl;

	// General Statistics
	echo '
		<h2 class="category_header">
			', $txt['shdp_general_stats'], '
		</h2>
		<div class="flow_hidden">
			<div id="stats_left" class="half_content">
				<div class="windowbg">
					<div class="content">
						<dl class="stats">
							<dt>', $txt['shdp_new_tickets_today'], '</dt>
							<dd>', $context['shd_stats']['today'][TICKET_STATUS_NEW], '</dd>
							<dt>', $txt['shdp_closed_tickets_today'], '</dt>
							<dd>', $context['shd_stats']['today'][TICKET_STATUS_CLOSED], '</dd>
							<dt>', $txt['shdp_total_open_tickets'], '</dt>
							<dd>', $context['shd_stats']['status']['total_open'], '</dd>
							<dt>', $txt['shdp_total_closed_tickets'], '</dt>
							<dd>', $context['shd_stats']['status']['total_closed'], '</dd>
							<dt>', $txt['shdp_total_total_tickets'], '</dt>
							<dd>', $context['shd_stats']['status']['total_total'], '</dd>
							<dt>', $txt['shdp_most_open_tickets'], '</dt>
							<dd>', $context['shd_stats']['most'][TICKET_STATUS_NEW][0];

	if (!empty($context['shd_stats']['most'][TICKET_STATUS_NEW][1]))
		echo ' &mdash; ', date('F d, Y', $context['shd_stats']['most'][TICKET_STATUS_NEW][1]);

	echo '</dd>
							<dt>', $txt['shdp_most_closed_tickets'], '</dt>
							<dd>', $context['shd_stats']['most'][TICKET_STATUS_CLOSED][0];

	if (!empty($context['shd_stats']['most'][TICKET_STATUS_CLOSED][1]))
		echo ' &mdash; ', date('F d, Y', $context['shd_stats']['most'][TICKET_STATUS_CLOSED][1]);

	echo '</dd>
						</dl>
					</div>
				</div>
			</div>
			<div id="stats_right" class="half_content">
				<div class="windowbg">
					<div class="content">
						<dl class="stats">
							<dt>', $txt['shdp_average_new_tickets'], '</dt>
							<dd>', round($context['shd_stats']['average'][TICKET_STATUS_NEW], 1), '</dd>
							<dt>', $txt['shdp_average_closed_tickets'], '</dt>
							<dd>', round($context['shd_stats']['average'][TICKET_STATUS_CLOSED], 1), '</dd>
							<dt>', $txt['shdp_average_assign_tickets'], '</dt>
							<dd>', round($context['shd_stats']['average'][TICKET_STATUS_PENDING_STAFF], 1), '</dd>
							<dt>', $txt['shdp_total_users'], '</dt>
							<dd>', $context['shd_stats']['totals'][ROLE_USER], '</dd>
							<dt>', $txt['shdp_total_staff'], '</dt>
							<dd>', $context['shd_stats']['totals'][ROLE_STAFF], '</dd>
							<dt>', $txt['shdp_total_admins'], '</dt>
							<dd>', $context['shd_stats']['totals'][ROLE_ADMIN], '</dd>
							<dt>', $txt['shdp_open_closed_ratio'], '</dt>
							<dd>', $context['shd_stats']['status']['ratio'], '</dd>
						</dl>
					</div>
				</div>
			</div>
		</div>';

	// Urgency Statistics
	echo '
		<h2 class="category_header">
			', $txt['shdp_urgency_stats'], '
		</h2>
		<div class="flow_hidden">
			<div id="urgency_left" class="half_content">
				<div class="windowbg">
					<div class="content">
						<dl class="stats">';

	foreach ($context['shd_stats']['urgency']['open'] as $type => $count)
		echo '
							<dt>', $txt['shdp_urgency_type_' . $type], ' (', $txt['shdp_urgency_open'], ')</dt>
							<dd>', $count, '</dd>';

	echo '
						</dl>
					</div>
				</div>
			</div>
			<div id="urgency_right" class="half_content">
				<div class="windowbg">
					<div class="content">
						<dl class="stats">';

	foreach ($context['shd_stats']['urgency']['closed'] as $type => $count)
		echo '
							<dt>', $txt['shdp_urgency_type_' . $type], ' (', $txt['shdp_urgency_closed'], ')</dt>
							<dd>', $count, '</dd>';

	echo '
						</dl>
					</div>
				</div>
			</div>
		</div>';

	// Ticket History
	echo '
		<h2 class="category_header">
			', $txt['shdp_ticket_history'], '
		</h2>
		<table class="table_grid" id="shd_stats_history">
			<thead>
				<tr class="table_head">
					<th class="lefttext" style="width: 25%;">', $txt['shdp_yearly_summary'], '</th>
					<th style="width: 15%;">', $txt['shdp_new_tickets'], '</th>
					<th style="width: 15%;">', $txt['shdp_assigned_tickets'], '</th>
					<th style="width: 15%;">', $txt['shdp_reopen_tickets'], '</th>
					<th style="width: 15%;">', $txt['shdp_closed_tickets'], '</th>
				</tr>
			</thead>
			<tbody>';

	if (empty($context['shd_stats']['history']))
	{
		echo '
				<tr class="windowbg">
					<td colspan="5" class="centertext">', $txt['shdp_no_history'], '</td>
				</tr>';
	}
	else
	{
		foreach ($context['shd_stats']['history'] as $year_id => $year)
		{
			echo '
				<tr class="windowbg" id="year_', $year_id, '">
					<th class="lefttext"><strong>', $year_id, '</strong></th>
					<td class="centertext">', $year['open'], '</td>
					<td class="centertext">', $year['assigned'], '</td>
					<td class="centertext">', $year['reopen'], '</td>
					<td class="centertext">', $year['resolved'], '</td>
				</tr>';

			if (!empty($year['child']))
			{
				foreach ($year['child'] as $month_id => $month)
				{
					$month_names = array(1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
					$month_name = isset($month_names[(int) $month_id]) ? $month_names[(int) $month_id] : $month_id;

					echo '
				<tr class="windowbg2" id="tr_month_', $year_id, '_', $month_id, '">
					<td class="lefttext" style="padding-left: 2em;">', $month_name, ' ', $year_id, '</td>
					<td class="centertext">', $month['open'], '</td>
					<td class="centertext">', $month['assigned'], '</td>
					<td class="centertext">', $month['reopen'], '</td>
					<td class="centertext">', $month['resolved'], '</td>
				</tr>';

					if (!empty($month['child']))
					{
						foreach ($month['child'] as $day_id => $day)
						{
							echo '
				<tr class="windowbg" id="tr_day_', $year_id, '_', $month_id, '_', $day_id, '">
					<td class="lefttext" style="padding-left: 4em;">', $year_id, '-', $month_id, '-', $day_id, '</td>
					<td class="centertext">', $day['open'], '</td>
					<td class="centertext">', $day['assigned'], '</td>
					<td class="centertext">', $day['reopen'], '</td>
					<td class="centertext">', $day['resolved'], '</td>
				</tr>';
						}
					}
				}
			}
		}
	}

	echo '
			</tbody>
		</table>';
}
