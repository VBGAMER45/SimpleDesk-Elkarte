<?php
/**
 * SimpleDesk Admin Maintenance Template
 *
 * Templates for helpdesk maintenance operations including find/repair,
 * reattribution, mass department moves, and search index management.
 *
 * Ported from SMF SimpleDesk-AdminMaint.template.php for ElkArte 1.1.9.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Main maintenance page template.
 *
 * Displays sections for:
 * 1. Find and Repair Errors - runs integrity checks on helpdesk data
 * 2. Reattribute Posts - reassign helpdesk content between members
 * 3. Mass Department Move - bulk move tickets between departments
 *
 * @since 2.0
 */
function template_shd_admin_maint_home()
{
	global $context, $settings, $txt, $modSettings, $scripturl;

	echo '
	<div id="admincenter">';


	// ------------------------------------------------------------------
	// Section 1: Find and Repair Errors
	// ------------------------------------------------------------------
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<img src="', $settings['images_url'], '/simpledesk/maintenance.png" alt="*" class="shd_icon_minihead" />
				', $txt['shd_maint_findrepair'], '
			</h3>
		</div>
		<div class="information">
			', $txt['shd_maint_findrepair_desc'], '
		</div>
		<form action="', $scripturl, '?action=admin;area=helpdesk;sa=maintenance;do=findrepair" method="post" accept-charset="UTF-8">
			<div class="content">
				<div class="submitbutton">
					<input type="submit" value="', $txt['shd_maint_findrepair_go'], '" class="button_submit" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</div>
			</div>
		</form>';

	// ------------------------------------------------------------------
	// Section 2: Reattribute Posts
	// ------------------------------------------------------------------
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<img src="', $settings['images_url'], '/simpledesk/reattribute.png" alt="*" class="shd_icon_minihead" />
				', $txt['shd_maint_reattribute'], '
			</h3>
		</div>
		<div class="information">
			', $txt['shd_maint_reattribute_desc'], '
		</div>
		<form action="', $scripturl, '?action=admin;area=helpdesk;sa=maintenance;do=reattribute" method="post" accept-charset="UTF-8">
			<div class="content">
				<dl class="settings">
					<dt>
						<strong>', $txt['shd_maint_reattribute_type'], '</strong>
					</dt>
					<dd>
						<label><input type="radio" name="type" value="email" checked="checked" /> ', $txt['shd_maint_reattribute_email'], '</label><br />
						<label><input type="radio" name="type" value="name" /> ', $txt['shd_maint_reattribute_name'], '</label><br />
						<label><input type="radio" name="type" value="starter" /> ', $txt['shd_maint_reattribute_starter'], '</label>
					</dd>
					<dt>
						<strong>', $txt['shd_maint_reattribute_from'], '</strong>
					</dt>
					<dd>
						<label>', $txt['shd_maint_reattribute_from_email'], ': <input type="text" name="from_email" value="" size="30" /></label><br />
						<label>', $txt['shd_maint_reattribute_from_name'], ': <input type="text" name="from_name" value="" size="30" /></label><br />
						<label>', $txt['shd_maint_reattribute_from_starter'], ': <input type="text" name="from_starter" id="from_starter" value="" size="30" /></label>
						<div id="from_starter_container"></div>
					</dd>
					<dt>
						<strong>', $txt['shd_maint_reattribute_to'], '</strong>
					</dt>
					<dd>
						<input type="text" name="to" id="to" value="" size="30" />
						<div id="to_container"></div>
					</dd>
				</dl>
				<div class="submitbutton">
					<input type="submit" value="', $txt['shd_maint_reattribute_go'], '" class="button_submit" onclick="return confirm(\'', JavaScriptEscape($txt['shd_maint_reattribute_confirm']), '\');" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</div>
			</div>
		</form>
		<script>
			var oAttributeMemberSuggest = new elk_AutoSuggest({
				sSelf: \'oAttributeMemberSuggest\',
				sSessionId: \'', $context['session_id'], '\',
				sSessionVar: \'', $context['session_var'], '\',
				sControlId: \'to\',
				sSearchType: \'member\',
				bItemList: false
			});
			var oStarterMemberSuggest = new elk_AutoSuggest({
				sSelf: \'oStarterMemberSuggest\',
				sSessionId: \'', $context['session_id'], '\',
				sSessionVar: \'', $context['session_var'], '\',
				sControlId: \'from_starter\',
				sSearchType: \'member\',
				bItemList: false
			});
		</script>';

	// ------------------------------------------------------------------
	// Section 3: Mass Department Move (only if departments exist)
	// ------------------------------------------------------------------
	if (!empty($context['dept_list']))
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<img src="', $settings['images_url'], '/simpledesk/movedept.png" alt="*" class="shd_icon_minihead" />
				', $txt['shd_maint_massdeptmove'], '
			</h3>
		</div>
		<div class="information">
			', $txt['shd_maint_massdeptmove_desc'], '
		</div>
		<form action="', $scripturl, '?action=admin;area=helpdesk;sa=maintenance;do=massdeptmove" method="post" accept-charset="UTF-8">
			<div class="content">
				<dl class="settings">
					<dt>
						<strong>', $txt['shd_maint_massdeptmove_from'], '</strong>
					</dt>
					<dd>
						<select name="from">';

		foreach ($context['dept_list'] as $dept_id => $dept_name)
		{
			echo '
							<option value="', $dept_id, '">', $dept_name, '</option>';
		}

		echo '
						</select>
					</dd>
					<dt>
						<strong>', $txt['shd_maint_massdeptmove_to'], '</strong>
					</dt>
					<dd>
						<select name="to_dept">';

		foreach ($context['dept_list'] as $dept_id => $dept_name)
		{
			echo '
							<option value="', $dept_id, '">', $dept_name, '</option>';
		}

		echo '
						</select>
					</dd>
					<dt>
						<strong>', $txt['shd_maint_massdeptmove_status'], '</strong>
					</dt>
					<dd>
						<label><input type="checkbox" name="moveopen" value="1" checked="checked" /> ', $txt['shd_maint_massdeptmove_open'], '</label><br />
						<label><input type="checkbox" name="moveclosed" value="1" checked="checked" /> ', $txt['shd_maint_massdeptmove_closed'], '</label><br />
						<label><input type="checkbox" name="movedeleted" value="1" /> ', $txt['shd_maint_massdeptmove_deleted'], '</label>
					</dd>
					<dt>
						<strong>', $txt['shd_maint_massdeptmove_date'], '</strong>
						<br /><span class="smalltext">', $txt['shd_maint_massdeptmove_date_desc'], '</span>
					</dt>
					<dd>
						<label>', $txt['shd_maint_massdeptmove_date_from'], ': <input type="text" name="date_from" value="" size="20" /></label><br />
						<label>', $txt['shd_maint_massdeptmove_date_to'], ': <input type="text" name="date_to" value="" size="20" /></label>
					</dd>
				</dl>
				<div class="submitbutton">
					<input type="submit" value="', $txt['shd_maint_massdeptmove_go'], '" class="button_submit" onclick="return confirm(\'', JavaScriptEscape($txt['shd_maint_massdeptmove_confirm']), '\');" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</div>
			</div>
		</form>';
	}

	echo '
	</div>';
}

/**
 * Find and repair results template.
 *
 * Shows the results of the find-and-repair integrity check. If no errors
 * were found, displays a success message. Otherwise, lists each error type
 * with a count of occurrences found/fixed.
 *
 * Error types: zero_tickets, zero_msgs, deleted, first_last, status,
 * starter_updater, invalid_dept.
 *
 * @since 2.0
 */
function template_shd_admin_maint_findrepairdone()
{
	global $context, $settings, $txt, $scripturl;

	echo '
	<div id="admincenter">';


	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<img src="', $settings['images_url'], '/simpledesk/maintenance.png" alt="*" class="shd_icon_minihead" />
				', $txt['shd_maint_findrepair'], '
			</h3>
		</div>';

	if (empty($context['maintenance_result']))
	{
		echo '
		<div class="information">
			', $txt['shd_maint_findrepair_none'], '
		</div>';
	}
	else
	{
		echo '
		<div class="content">
			<ul>';

		$error_types = array(
			'zero_tickets' => 'shd_maint_zero_tickets',
			'zero_msgs' => 'shd_maint_zero_msgs',
			'deleted' => 'shd_maint_deleted',
			'first_last' => 'shd_maint_first_last',
			'status' => 'shd_maint_status',
			'starter_updater' => 'shd_maint_starter_updater',
			'invalid_dept' => 'shd_maint_invalid_dept',
		);

		foreach ($error_types as $type => $lang_key)
		{
			if (!empty($context['maintenance_result'][$type]))
			{
				echo '
				<li>', sprintf($txt[$lang_key], $context['maintenance_result'][$type]), '</li>';
			}
		}

		echo '
			</ul>
		</div>';
	}

	echo '
		<div class="content">
			<a href="', $scripturl, '?action=admin;area=helpdesk;sa=maintenance">', $txt['shd_maint_back'], '</a>
		</div>
	</div>';
}

/**
 * Reattribution success template.
 *
 * Shows a simple success message after posts have been reattributed,
 * with a back link to the maintenance page.
 *
 * @since 2.0
 */
function template_shd_admin_maint_reattributedone()
{
	global $context, $settings, $txt, $scripturl;

	echo '
	<div id="admincenter">';


	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<img src="', $settings['images_url'], '/simpledesk/reattribute.png" alt="*" class="shd_icon_minihead" />
				', $txt['shd_maint_reattribute'], '
			</h3>
		</div>
		<div class="information">
			', $txt['shd_maint_reattribute_success'], '
		</div>
		<div class="content">
			<a href="', $scripturl, '?action=admin;area=helpdesk;sa=maintenance">', $txt['shd_maint_back'], '</a>
		</div>
	</div>';
}

/**
 * Mass department move success template.
 *
 * Shows a simple success message after tickets have been moved between
 * departments, with a back link to the maintenance page.
 *
 * @since 2.0
 */
function template_shd_admin_maint_massdeptmovedone()
{
	global $context, $settings, $txt, $scripturl;

	echo '
	<div id="admincenter">';


	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<img src="', $settings['images_url'], '/simpledesk/movedept.png" alt="*" class="shd_icon_minihead" />
				', $txt['shd_maint_massdeptmove'], '
			</h3>
		</div>
		<div class="information">
			', $txt['shd_maint_massdeptmove_success'], '
		</div>
		<div class="content">
			<a href="', $scripturl, '?action=admin;area=helpdesk;sa=maintenance">', $txt['shd_maint_back'], '</a>
		</div>
	</div>';
}

/**
 * Search index management template.
 *
 * Displays sections for:
 * 1. Rebuild Index - button to rebuild the helpdesk search index
 * 2. Search Settings - configuration for search index parameters
 *    (min/max word size, prefix size, character set)
 *
 * Shows a success message when the ?rebuilddone GET parameter is present.
 *
 * @since 2.0
 */
function template_shd_admin_maint_search()
{
	global $context, $settings, $txt, $modSettings, $scripturl;

	echo '
	<div id="admincenter">';


	// Show rebuild success message if applicable.
	if (isset($_GET['rebuilddone']))
	{
		echo '
		<div class="infobox">
			', $txt['shd_maint_search_rebuilt'], '
		</div>';
	}

	// ------------------------------------------------------------------
	// Section 1: Rebuild Search Index
	// ------------------------------------------------------------------
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<img src="', $settings['images_url'], '/simpledesk/search.png" alt="*" class="shd_icon_minihead" />
				', $txt['shd_maint_search_rebuild'], '
			</h3>
		</div>
		<div class="information">
			', $txt['shd_maint_search_rebuild_desc'], '
		</div>
		<form action="', $scripturl, '?action=admin;area=helpdesk;sa=maintenance;do=searchrebuild" method="post" accept-charset="UTF-8">
			<div class="content">
				<div class="submitbutton">
					<input type="submit" value="', $txt['shd_maint_search_rebuild_go'], '" class="button_submit" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</div>
			</div>
		</form>';

	// ------------------------------------------------------------------
	// Section 2: Search Settings
	// ------------------------------------------------------------------
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<img src="', $settings['images_url'], '/simpledesk/search.png" alt="*" class="shd_icon_minihead" />
				', $txt['shd_maint_search_settings'], '
			</h3>
		</div>
		<form action="', $scripturl, '?action=admin;area=helpdesk;sa=maintenance;do=searchsettings" method="post" accept-charset="UTF-8">
			<div class="content">
				<dl class="settings">
					<dt>
						<label for="shd_search_min_size"><strong>', $txt['shd_maint_search_min_size'], '</strong></label>
						<br /><span class="smalltext">', $txt['shd_maint_search_min_size_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="shd_search_min_size" id="shd_search_min_size" value="', !empty($modSettings['shd_search_min_size']) ? $modSettings['shd_search_min_size'] : '3', '" size="5" />
					</dd>
					<dt>
						<label for="shd_search_max_size"><strong>', $txt['shd_maint_search_max_size'], '</strong></label>
						<br /><span class="smalltext">', $txt['shd_maint_search_max_size_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="shd_search_max_size" id="shd_search_max_size" value="', !empty($modSettings['shd_search_max_size']) ? $modSettings['shd_search_max_size'] : '20', '" size="5" />
					</dd>
					<dt>
						<label for="shd_search_prefix_size"><strong>', $txt['shd_maint_search_prefix_size'], '</strong></label>
						<br /><span class="smalltext">', $txt['shd_maint_search_prefix_size_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="shd_search_prefix_size" id="shd_search_prefix_size" value="', !empty($modSettings['shd_search_prefix_size']) ? $modSettings['shd_search_prefix_size'] : '0', '" size="5" />
					</dd>
					<dt>
						<label for="shd_search_charset"><strong>', $txt['shd_maint_search_charset'], '</strong></label>
						<br /><span class="smalltext">', $txt['shd_maint_search_charset_desc'], '</span>
					</dt>
					<dd>
						<textarea name="shd_search_charset" id="shd_search_charset" rows="3" cols="40">', !empty($modSettings['shd_search_charset']) ? $modSettings['shd_search_charset'] : '', '</textarea>
					</dd>
				</dl>
				<div class="errorbox">
					', $txt['shd_maint_search_rebuild_warning'], '
				</div>
				<div class="submitbutton">
					<input type="submit" value="', $txt['save'], '" class="button_submit" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</div>
			</div>
		</form>
	</div>';
}
