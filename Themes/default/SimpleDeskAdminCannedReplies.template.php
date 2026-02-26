<?php
/**
 * SimpleDesk Admin Canned Replies Template
 *
 * Templates for canned replies administration: listing categories
 * and replies, creating/editing categories, creating/editing replies,
 * and moving replies between categories.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Canned replies home listing template.
 *
 * Displays all canned reply categories with their replies in a table
 * format. Each category shows its replies with title, active status,
 * visibility, departments, move controls, and action links.
 */
function template_shd_cannedreplies_home()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">';


	// Page header.
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['shd_admin_cannedreplies_home'], '</h3>
		</div>
		<div class="information">
			', $txt['shd_admin_cannedreplies_desc'], '
		</div>';

	// Create new category link at top.
	echo '
		<div class="flow_auto" style="padding: 8px 0;">
			<a href="', $scripturl, '?action=admin;area=helpdesk;sa=canned_replies;do=createcat" class="button_submit">[', $txt['shd_admin_cannedreplies_createcat'], ']</a>
		</div>';

	// No categories at all.
	if (empty($context['canned_replies']))
	{
		echo '
		<div class="content">
			<p class="description">', $txt['shd_admin_cannedreplies_no_cats'], '</p>
		</div>';
	}
	else
	{
		// Loop through each category.
		foreach ($context['canned_replies'] as $cat)
		{
			// Category header bar.
			echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', $txt['shd_admin_cannedreplies_cat'], ': ', $cat['name'];

			// Move category up/down.
			if (!empty($cat['move_up']))
			{
				echo '
				<a href="', $scripturl, '?action=admin;area=helpdesk;sa=canned_replies;do=movecat;cat=', $cat['id'], ';direction=up;', $context['session_var'], '=', $context['session_id'], '" style="margin-left: 8px;">', $txt['shd_admin_move_up'], '</a>';
			}
			if (!empty($cat['move_down']))
			{
				echo '
				<a href="', $scripturl, '?action=admin;area=helpdesk;sa=canned_replies;do=movecat;cat=', $cat['id'], ';direction=down;', $context['session_var'], '=', $context['session_id'], '">', $txt['shd_admin_move_down'], '</a>';
			}

			// Edit category link.
			echo '
				<a href="', $scripturl, '?action=admin;area=helpdesk;sa=canned_replies;do=editcat;cat=', $cat['id'], '" style="margin-left: 8px;">[', $txt['shd_admin_cannedreplies_editcat'], ']</a>';

			// Add reply link.
			echo '
				<a href="', $scripturl, '?action=admin;area=helpdesk;sa=canned_replies;do=createreply;cat=', $cat['id'], '" style="margin-left: 8px;">[', $txt['shd_admin_cannedreplies_addreply'], ']</a>';

			echo '
			</h3>
		</div>';

			// Replies table for this category.
			echo '
		<table class="table_grid" style="width: 100%;">
			<thead>
				<tr class="table_head">
					<th scope="col">', $txt['shd_admin_cannedreplies_title'], '</th>
					<th scope="col" style="width: 8%;">', $txt['shd_admin_cannedreplies_active'], '</th>
					<th scope="col" style="width: 12%;">', $txt['shd_admin_cannedreplies_vis_user'], ' / ', $txt['shd_admin_cannedreplies_vis_staff'], '</th>
					<th scope="col" style="width: 15%;">', $txt['shd_admin_cannedreplies_depts'], '</th>
					<th scope="col" style="width: 12%;">', $txt['shd_admin_cannedreplies_move'], '</th>
					<th scope="col" style="width: 10%;">', $txt['shd_admin_cannedreplies_actions'], '</th>
				</tr>
			</thead>
			<tbody>';

			if (empty($cat['replies']))
			{
				echo '
				<tr class="windowbg">
					<td colspan="6" class="centertext">', $txt['shd_admin_cannedreplies_no_replies'], '</td>
				</tr>';
			}
			else
			{
				foreach ($cat['replies'] as $reply)
				{
					echo '
				<tr class="windowbg">
					<td>', $reply['title'], '</td>
					<td class="centertext">', $reply['active_string'], '</td>
					<td class="centertext">';

					// Visibility indicators.
					$vis = array();
					if (!empty($reply['vis_user']))
						$vis[] = $txt['shd_admin_cannedreplies_vis_user'];
					if (!empty($reply['vis_staff']))
						$vis[] = $txt['shd_admin_cannedreplies_vis_staff'];

					echo !empty($vis) ? implode(', ', $vis) : '&mdash;';

					echo '</td>
					<td class="centertext">', !empty($reply['depts']) ? $reply['depts'] : $txt['shd_none'], '</td>
					<td class="centertext">';

					// Move up within category.
					if (!empty($reply['move_up']))
					{
						echo '
						<a href="', $scripturl, '?action=admin;area=helpdesk;sa=canned_replies;do=movereply;reply=', $reply['id'], ';direction=up;', $context['session_var'], '=', $context['session_id'], '">', $txt['shd_admin_move_up'], '</a>';
					}

					if (!empty($reply['move_up']) && !empty($reply['move_down']))
						echo ' / ';

					// Move down within category.
					if (!empty($reply['move_down']))
					{
						echo '
						<a href="', $scripturl, '?action=admin;area=helpdesk;sa=canned_replies;do=movereply;reply=', $reply['id'], ';direction=down;', $context['session_var'], '=', $context['session_id'], '">', $txt['shd_admin_move_down'], '</a>';
					}

					// Move to another category (between categories).
					if (!empty($context['move_between_cats']))
					{
						echo '
						<br /><a href="', $scripturl, '?action=admin;area=helpdesk;sa=canned_replies;do=movereplycat;reply=', $reply['id'], '">[', $txt['shd_admin_cannedreplies_move_between_cat'], ']</a>';
					}

					echo '
					</td>
					<td class="centertext">
						<a href="', $scripturl, '?action=admin;area=helpdesk;sa=canned_replies;do=editreply;reply=', $reply['id'], '">', $txt['shd_admin_cannedreplies_edit'], '</a>
					</td>
				</tr>';
				}
			}

			echo '
			</tbody>
		</table>';
		}
	}

	echo '
	</div>';
}

/**
 * Create/edit canned reply category form template.
 *
 * Displays a form for creating a new category or editing an existing one.
 * Includes category name input, save button, and delete button (for existing).
 */
function template_shd_edit_canned_category()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">';


	// Page header.
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $context['canned_category'] === 'new' ? $txt['shd_admin_cannedreplies_createcat'] : $txt['shd_admin_cannedreplies_editcat'], '</h3>
		</div>';

	// Category form.
	echo '
		<form action="', $scripturl, '?action=admin;area=helpdesk;sa=canned_replies;do=savecat" method="post" accept-charset="UTF-8">
			<input type="hidden" name="cat" value="', $context['canned_category'], '" />
			<div class="content">
				<dl class="settings">
					<dt>
						<label for="catname"><strong>', $txt['shd_admin_cannedreplies_catname'], '</strong></label>
					</dt>
					<dd>
						<input type="text" name="catname" id="catname" value="', $context['category_name'], '" size="40" maxlength="100" />
					</dd>
				</dl>
			</div>
			<div class="submitbutton">
				<input type="submit" value="', $txt['shd_admin_cannedreplies_save'], '" class="button_submit" />';

	// Delete button only for existing categories.
	if ($context['canned_category'] !== 'new')
	{
		echo '
				<input type="submit" name="delete" value="', $txt['shd_admin_cannedreplies_deletecat'], '" class="button_submit" onclick="return confirm(\'', $txt['shd_admin_cannedreplies_deletecat_confirm'], '\');" />';
	}

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />
			</div>
		</form>
	</div>';
}

/**
 * Create/edit canned reply form template.
 *
 * Displays a form for creating a new reply or editing an existing one.
 * Includes title, rich text editor body, active checkbox, visibility
 * checkboxes, category select, department checkboxes, save and delete buttons.
 */
function template_shd_edit_canned_reply()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">';


	// Page header.
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $context['canned_reply']['id'] === 'new' ? $txt['shd_admin_cannedreplies_addreply'] : $txt['shd_admin_cannedreplies_editreply'], '</h3>
		</div>';

	// Reply form.
	echo '
		<form action="', $scripturl, '?action=admin;area=helpdesk;sa=canned_replies;do=savereply" method="post" accept-charset="UTF-8" id="cannedreply">
			<input type="hidden" name="reply" value="', $context['canned_reply']['id'], '" />
			<input type="hidden" name="cat" value="', $context['canned_reply']['cat'], '" />
			<div class="content">
				<dl class="settings">';

	// Title field.
	echo '
					<dt>
						<label for="title"><strong>', $txt['shd_admin_cannedreplies_title'], '</strong></label>
					</dt>
					<dd>
						<input type="text" name="title" id="title" value="', $context['canned_reply']['title'], '" size="60" maxlength="255" />
					</dd>';

	// Body - rich text editor.
	echo '
					<dt>
						<label><strong>', $txt['shd_admin_cannedreplies_content'], '</strong></label>
					</dt>
					<dd>';

	template_control_richedit($context['post_box_name'], 'smileyBox_message', 'bbcBox_message');

	echo '
					</dd>';

	// Active checkbox.
	echo '
					<dt>
						<label for="active"><strong>', $txt['shd_admin_cannedreplies_active'], '</strong></label>
					</dt>
					<dd>
						<input type="checkbox" name="active" id="active" value="1"', !empty($context['canned_reply']['active']) ? ' checked="checked"' : '', ' />
					</dd>';

	// Visible to Users checkbox.
	echo '
					<dt>
						<label for="vis_user"><strong>', $txt['shd_admin_cannedreplies_vis_user'], '</strong></label>
					</dt>
					<dd>
						<input type="checkbox" name="vis_user" id="vis_user" value="1"', !empty($context['canned_reply']['vis_user']) ? ' checked="checked"' : '', ' />
					</dd>';

	// Visible to Staff checkbox.
	echo '
					<dt>
						<label for="vis_staff"><strong>', $txt['shd_admin_cannedreplies_vis_staff'], '</strong></label>
					</dt>
					<dd>
						<input type="checkbox" name="vis_staff" id="vis_staff" value="1"', !empty($context['canned_reply']['vis_staff']) ? ' checked="checked"' : '', ' />
					</dd>';

	// Category select (for changing category when editing).
	if ($context['canned_reply']['id'] !== 'new' && !empty($context['cannedreply_cats']))
	{
		echo '
					<dt>
						<label for="cat"><strong>', $txt['shd_admin_cannedreplies_selectcat'], '</strong></label>
					</dt>
					<dd>
						<select name="cat" id="cat">';

		foreach ($context['cannedreply_cats'] as $cat_id => $cat_name)
		{
			echo '
							<option value="', $cat_id, '"', ($context['canned_reply']['cat'] == $cat_id ? ' selected="selected"' : ''), '>', $cat_name, '</option>';
		}

		echo '
						</select>
					</dd>';
	}

	echo '
				</dl>';

	// Departments section.
	if (!empty($context['canned_reply']['departments']))
	{
		echo '
				<fieldset>
					<legend><strong>', $txt['shd_admin_cannedreplies_depts'], '</strong></legend>';

		foreach ($context['canned_reply']['departments'] as $dept)
		{
			echo '
					<label style="display: block; padding: 2px 0;">
						<input type="checkbox" name="dept_', $dept['id'], '" value="1"', !empty($dept['selected']) ? ' checked="checked"' : '', ' />
						', $dept['name'], '
					</label>';
		}

		echo '
				</fieldset>';
	}

	echo '
			</div>';

	// Submit / Delete buttons.
	echo '
			<div class="submitbutton">
				<input type="submit" value="', $txt['shd_admin_cannedreplies_savereply'], '" class="button_submit" />';

	// Delete button only for existing replies.
	if ($context['canned_reply']['id'] !== 'new')
	{
		echo '
				<input type="submit" name="delete" value="', $txt['shd_admin_cannedreplies_deletereply'], '" class="button_submit" onclick="return confirm(\'', $txt['shd_admin_cannedreplies_deletereply_confirm'], '\');" />';
	}

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />
			</div>
		</form>
	</div>';
}

/**
 * Move reply between categories form template.
 *
 * Displays a simple form with a category select dropdown to move
 * a canned reply from its current category to another one.
 */
function template_shd_move_reply_cat()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">';


	// Page header.
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['shd_admin_cannedreplies_move_between_cat'], '</h3>
		</div>';

	// Move form.
	echo '
		<form action="', $scripturl, '?action=admin;area=helpdesk;sa=canned_replies;do=movereplycat" method="post" accept-charset="UTF-8">
			<input type="hidden" name="reply" value="', $context['canned_reply']['id'], '" />
			<div class="content">
				<dl class="settings">
					<dt>
						<label for="tocat"><strong>', $txt['shd_admin_cannedreplies_selectcat'], '</strong></label>
					</dt>
					<dd>
						<select name="tocat" id="tocat">';

	foreach ($context['cannedreply_cats'] as $cat_id => $cat_name)
	{
		echo '
							<option value="', $cat_id, '">', $cat_name, '</option>';
	}

	echo '
						</select>
					</dd>
				</dl>
			</div>
			<div class="submitbutton">
				<input type="submit" value="', $txt['shd_admin_cannedreplies_save'], '" class="button_submit" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />
			</div>
		</form>
	</div>';
}
