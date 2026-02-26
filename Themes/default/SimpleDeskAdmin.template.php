<?php
/**
 * SimpleDesk Helpdesk - Admin Templates
 *
 * Templates for the admin information dashboard and general options page.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Admin information/dashboard page template.
 *
 * Displays SimpleDesk version, ticket statistics, department count,
 * staff member list, and credits.
 */
function template_shd_admin_info()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">';


	// Header
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['shd_admin_info_title'], '</h3>
		</div>
		<div class="information">
			', $txt['shd_admin_info_desc'], '
		</div>';

	// General information section
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['shd_admin_info_general'], '</h3>
		</div>
		<div class="content">
			<dl class="settings">
				<dt><strong>', $txt['shd_admin_info_version'], ':</strong></dt>
				<dd>', $context['shd_version'], '</dd>
				<dt><strong>', $txt['shd_admin_info_tickets'], ':</strong></dt>
				<dd>', $context['shd_total_tickets'], '</dd>
				<dt><strong>', $txt['shd_admin_info_open_tickets'], ':</strong></dt>
				<dd>', $context['shd_open_tickets'], '</dd>
				<dt><strong>', $txt['shd_admin_info_closed_tickets'], ':</strong></dt>
				<dd>', $context['shd_closed_tickets'], '</dd>
				<dt><strong>', $txt['shd_admin_info_recycled_tickets'], ':</strong></dt>
				<dd>', $context['shd_recycled_tickets'], '</dd>
				<dt><strong>', $txt['shd_admin_info_departments'], ':</strong></dt>
				<dd>', $context['shd_total_depts'], '</dd>
			</dl>
		</div>';

	// Staff list section
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['shd_admin_info_staff'], '</h3>
		</div>
		<div class="content">';

	if (!empty($context['shd_staff_list']))
	{
		echo '
			<ul class="shd_staff_list">';

		foreach ($context['shd_staff_list'] as $member)
		{
			echo '
				<li>
					<a href="', $scripturl, '?action=profile;u=', $member['id'], '">', $member['name'], '</a>
				</li>';
		}

		echo '
			</ul>';
	}
	else
	{
		echo '
			<p class="description">', $txt['shd_admin_no_staff'], '</p>';
	}

	echo '
		</div>';

	// Credits section
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['shd_admin_credits'], '</h3>
		</div>
		<div class="content">
			<p>', $txt['shd_admin_credits_desc'], '</p>
			<dl class="settings">
				<dt><strong>', $txt['shd_admin_credits_developer'], ':</strong></dt>
				<dd>SimpleDesk Team</dd>
				<dt><strong>', $txt['shd_admin_credits_website'], ':</strong></dt>
				<dd><a href="https://www.simpledesk.net/" target="_blank" rel="noopener">www.simpledesk.net</a></dd>
				<dt><strong>', $txt['shd_admin_credits_license'], ':</strong></dt>
				<dd>BSD 3-Clause License</dd>
			</dl>
		</div>';

	echo '
	</div>';
}

/**
 * Admin general options/settings page template.
 *
 * Renders a settings form with grouped sections. Each config variable is
 * either a section header (string) or a field definition (array with type).
 */
function template_shd_admin_options()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">';


	// Page header
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['shd_admin_options_title'], '</h3>
		</div>
		<div class="information">
			', $txt['shd_admin_options_desc'], '
		</div>';

	// Settings form
	echo '
		<form action="', $context['post_url'], '" method="post" accept-charset="UTF-8">';

	$in_section = false;

	foreach ($context['config_vars'] as $config_var)
	{
		// Section header (plain string)
		if (is_string($config_var))
		{
			// Close previous section if open
			if ($in_section)
			{
				echo '
				</dl>
			</div>';
			}

			echo '
			<div class="cat_bar">
				<h3 class="catbg">', $config_var, '</h3>
			</div>
			<div class="content">
				<dl class="settings">';

			$in_section = true;
			continue;
		}

		// Field definition (array)
		echo '
					<dt>
						<label for="', $config_var['name'], '"><strong>', $config_var['label'], '</strong></label>';

		if (!empty($config_var['subtext']))
		{
			echo '
						<br /><span class="smalltext">', $config_var['subtext'], '</span>';
		}

		echo '
					</dt>
					<dd>';

		switch ($config_var['type'])
		{
			case 'check':
				echo '
						<input type="checkbox" name="', $config_var['name'], '" id="', $config_var['name'], '" value="1"', (!empty($config_var['value']) ? ' checked="checked"' : ''), ' />';
				break;

			case 'int':
				echo '
						<input type="text" name="', $config_var['name'], '" id="', $config_var['name'], '" value="', $config_var['value'], '" size="5" />';
				break;

			case 'select':
				echo '
						<select name="', $config_var['name'], '" id="', $config_var['name'], '">';

				foreach ($config_var['options'] as $opt_value => $opt_label)
				{
					echo '
							<option value="', $opt_value, '"', ($config_var['value'] == $opt_value ? ' selected="selected"' : ''), '>', $opt_label, '</option>';
				}

				echo '
						</select>';
				break;

			case 'text':
			default:
				echo '
						<input type="text" name="', $config_var['name'], '" id="', $config_var['name'], '" value="', $config_var['value'], '" size="30" />';
				break;
		}

		echo '
					</dd>';
	}

	// Close the last section if open
	if ($in_section)
	{
		echo '
				</dl>
			</div>';
	}

	// Submit button and session token
	echo '
			<div class="submitbutton">
				<input type="submit" value="', $txt['save'], '" class="button_submit" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</div>
		</form>
	</div>';
}

/**
 * Display the helpdesk action log.
 */
function template_shd_action_log()
{
	global $settings, $txt, $context, $scripturl, $sort_types;

	echo '
		<h2 class="category_header">
			<span class="floatright smalltext">', $context['page_index'], '</span>
			', $txt['shd_admin_actionlog_title'], '
		</h2>
		<table class="table_grid">
			<thead>
				<tr class="table_head">
					<th style="width: 38%;" colspan="2">
						<a href="', $scripturl, '?action=admin;area=helpdesk;sa=actionlog;sort=action', $context['sort'] == $sort_types['action'] && !isset($_REQUEST['asc']) ? ';asc' : '', '">
							', $txt['shd_admin_actionlog_action'], '
						</a>
						', ($context['sort'] == $sort_types['action'] ? (isset($_REQUEST['asc']) ? '&#9650;' : '&#9660;') : ''), '
					</th>
					<th style="width: 20%;">
						<a href="', $scripturl, '?action=admin;area=helpdesk;sa=actionlog;sort=time', $context['sort'] == $sort_types['time'] && !isset($_REQUEST['asc']) ? ';asc' : '', '">
							', $txt['shd_admin_actionlog_date'], '
						</a>
						', ($context['sort'] == $sort_types['time'] ? (isset($_REQUEST['asc']) ? '&#9650;' : '&#9660;') : ''), '
					</th>
					<th style="width: 16%;">
						<a href="', $scripturl, '?action=admin;area=helpdesk;sa=actionlog;sort=member', $context['sort'] == $sort_types['member'] && !isset($_REQUEST['asc']) ? ';asc' : '', '">
							', $txt['shd_admin_actionlog_member'], '
						</a>
						', ($context['sort'] == $sort_types['member'] ? (isset($_REQUEST['asc']) ? '&#9650;' : '&#9660;') : ''), '
					</th>
					<th style="width: 16%;">
						<a href="', $scripturl, '?action=admin;area=helpdesk;sa=actionlog;sort=position', $context['sort'] == $sort_types['position'] && !isset($_REQUEST['asc']) ? ';asc' : '', '">
							', $txt['shd_admin_actionlog_position'], '
						</a>
						', ($context['sort'] == $sort_types['position'] ? (isset($_REQUEST['asc']) ? '&#9650;' : '&#9660;') : ''), '
					</th>
					<th style="width: 10%;">
						<a href="', $scripturl, '?action=admin;area=helpdesk;sa=actionlog;sort=ip', $context['sort'] == $sort_types['ip'] && !isset($_REQUEST['asc']) ? ';asc' : '', '">
							', $txt['shd_admin_actionlog_ip'], '
						</a>
						', ($context['sort'] == $sort_types['ip'] ? (isset($_REQUEST['asc']) ? '&#9650;' : '&#9660;') : ''), '
					</th>
					<th style="width: 2%;">&nbsp;</th>
				</tr>
			</thead>
			<tbody>';

	if (empty($context['actions']))
		echo '
				<tr class="windowbg">
					<td colspan="7" class="centertext">', $txt['shd_admin_actionlog_none'], '</td>
				</tr>';
	else
		foreach ($context['actions'] as $action)
			echo '
				<tr class="windowbg">
					<td style="width: 1%;"><img src="', shd_image_url($action['action_icon']), '" alt="" class="shd_smallicon" /></td>
					<td class="smalltext">', $action['action_text'], '</td>
					<td>', $action['time'], '</td>
					<td>', $action['member']['link'], '</td>
					<td>', $action['member']['group'], '</td>
					<td>', !empty($action['member']['ip']) ? $action['member']['ip'] : $txt['shd_admin_actionlog_hidden'], '</td>
					<td>', $action['can_remove'] && $context['can_delete'] ? '<a href="' . $scripturl . '?action=admin;area=helpdesk;sa=actionlog;remove=' . $action['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '"><img src="' . $settings['default_images_url'] . '/simpledesk/delete.png" class="shd_smallicon" alt="' . $txt['shd_delete_item'] . '" /></a>' : '', '</td>
				</tr>';

	echo '
			</tbody>
			<tfoot>
				<tr class="windowbg">
					<td colspan="7">
						<span class="floatright smalltext">', $context['page_index'], '</span>
						<span class="smalltext"><a href="', $scripturl, '?action=admin;area=helpdesk;sa=actionlog', $context['url_sort'], $context['url_order'], ';removeall;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(', JavaScriptEscape(sprintf($txt['shd_admin_actionlog_removeall_confirm'], $context['hoursdisable'])), ');">', $txt['shd_admin_actionlog_removeall'], '</a></span>
					</td>
				</tr>
			</tfoot>
		</table>';
}

/**
 * Display the helpdesk admin log.
 */
function template_shd_admin_log()
{
	global $settings, $txt, $context, $scripturl, $sort_types;

	echo '
		<h2 class="category_header">
			<span class="floatright smalltext">', $context['page_index'], '</span>
			', $txt['shd_admin_adminlog_title'], '
		</h2>
		<table class="table_grid">
			<thead>
				<tr class="table_head">
					<th style="width: 33%;">
						<a href="', $scripturl, '?action=admin;area=helpdesk;sa=adminlog;sort=action', $context['sort'] == $sort_types['action'] && !isset($_REQUEST['asc']) ? ';asc' : '', '">
							', $txt['shd_admin_actionlog_action'], '
						</a>
						', ($context['sort'] == $sort_types['action'] ? (isset($_REQUEST['asc']) ? '&#9650;' : '&#9660;') : ''), '
					</th>
					<th style="width: 20%;">
						<a href="', $scripturl, '?action=admin;area=helpdesk;sa=adminlog;sort=time', $context['sort'] == $sort_types['time'] && !isset($_REQUEST['asc']) ? ';asc' : '', '">
							', $txt['shd_admin_actionlog_date'], '
						</a>
						', ($context['sort'] == $sort_types['time'] ? (isset($_REQUEST['asc']) ? '&#9650;' : '&#9660;') : ''), '
					</th>
					<th style="width: 20%;">
						<a href="', $scripturl, '?action=admin;area=helpdesk;sa=adminlog;sort=member', $context['sort'] == $sort_types['member'] && !isset($_REQUEST['asc']) ? ';asc' : '', '">
							', $txt['shd_admin_actionlog_member'], '
						</a>
						', ($context['sort'] == $sort_types['member'] ? (isset($_REQUEST['asc']) ? '&#9650;' : '&#9660;') : ''), '
					</th>
					<th style="width: 10%;">
						<a href="', $scripturl, '?action=admin;area=helpdesk;sa=adminlog;sort=position', $context['sort'] == $sort_types['position'] && !isset($_REQUEST['asc']) ? ';asc' : '', '">
							', $txt['shd_admin_actionlog_position'], '
						</a>
						', ($context['sort'] == $sort_types['position'] ? (isset($_REQUEST['asc']) ? '&#9650;' : '&#9660;') : ''), '
					</th>
					<th style="width: 10%;" ', $context['can_delete'] ? 'colspan="2"' : '', '>
						<a href="', $scripturl, '?action=admin;area=helpdesk;sa=adminlog;sort=ip', $context['sort'] == $sort_types['ip'] && !isset($_REQUEST['asc']) ? ';asc' : '', '">
							', $txt['shd_admin_actionlog_ip'], '
						</a>
						', ($context['sort'] == $sort_types['ip'] ? (isset($_REQUEST['asc']) ? '&#9650;' : '&#9660;') : ''), '
					</th>
				</tr>
			</thead>
			<tbody>';

	if (empty($context['actions']))
		echo '
				<tr class="windowbg">
					<td colspan="', $context['can_delete'] ? 6 : 5, '" class="centertext">', $txt['shd_admin_actionlog_none'], '</td>
				</tr>';
	else
		foreach ($context['actions'] as $action)
			echo '
				<tr class="windowbg">
					<td class="smalltext">', $action['action_text'], '</td>
					<td>', $action['time'], '</td>
					<td>', $action['member']['link'], '</td>
					<td>', $action['member']['group'], '</td>
					<td ', $action['can_remove'] && $context['can_delete'] ? 'colspan="2"' : '', '>', !empty($action['member']['ip']) ? $action['member']['ip'] : $txt['shd_admin_actionlog_hidden'], '</td>
					', $action['can_remove'] && $context['can_delete'] ? '<td><a href="' . $scripturl . '?action=admin;area=helpdesk;sa=adminlog;remove=' . $action['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '"><img src="' . $settings['default_images_url'] . '/simpledesk/delete.png" alt="' . $txt['shd_delete_item'] . '" /></a></td>' : '', '
				</tr>';

	echo '
			</tbody>
			<tfoot>
				<tr class="windowbg">
					<td colspan="', $context['can_delete'] ? 6 : 5, '">
						<span class="floatright smalltext">', $context['page_index'], '</span>
						<span class="smalltext"><a href="', $scripturl, '?action=admin;area=helpdesk;sa=adminlog', $context['url_sort'], $context['url_order'], ';removeall;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(', JavaScriptEscape(sprintf($txt['shd_admin_actionlog_removeall_confirm'], $context['daysdisable'])), ');">', $txt['shd_admin_actionlog_removeall'], '</a></span>
					</td>
				</tr>
			</tfoot>
		</table>';
}
