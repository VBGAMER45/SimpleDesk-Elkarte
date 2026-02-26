<?php
/**
 * SimpleDesk Front Page Plugin
 *
 * Provides a replacement front page for the helpdesk, allowing administrators
 * to display custom content (BBCode or PHP) instead of the ticket listing
 * when users first visit the helpdesk.
 *
 * Ported from SMF SimpleDesk SDPluginFrontPage.php to ElkArte.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Intercept the main helpdesk action to show the front page instead.
 *
 * Called via shd_hook_helpdesk. Modifies $subactions so that 'main'
 * points to the front page source, and adds a 'tickets' subaction
 * for the original ticket listing.
 *
 * @param array &$subactions The helpdesk subaction array.
 */
function shd_frontpage_helpdesk(&$subactions)
{
	global $context, $scripturl, $modSettings;

	// Enabled?
	if (empty($modSettings['shdp_frontpage_content']) || !in_array('front_page', $context['shd_plugins']))
		return;

	// Are we doing it this load or not?
	if (empty($modSettings['shdp_frontpage_appear']))
		$modSettings['shdp_frontpage_appear'] = 'firstdefault';

	if ($modSettings['shdp_frontpage_appear'] == 'firstload')
	{
		// Check session. If set (we've been here this session), leave.
		if (isset($_SESSION['shdp_frontpage']))
			return;
		else
			$_SESSION['shdp_frontpage'] = 1;
	}
	elseif ($modSettings['shdp_frontpage_appear'] == 'firstdefault')
	{
		if (!isset($_SESSION['shdp_frontpage']))
			$_SESSION['shdp_frontpage'] = 1;
	}

	// Fix the navigation to have a tickets button as well as the main button
	$context['shd_home'] = 'action=helpdesk;sa=tickets';
	$navigation = $context['navigation'];
	$context['navigation'] = array();

	foreach ($navigation as $area => $details)
	{
		$context['navigation'][$area] = $details;
		if ($area == 'main')
		{
			$context['navigation']['tickets'] = array(
				'text' => 'shdp_tickets',
				'lang' => true,
				'url' => $scripturl . '?action=helpdesk;sa=tickets' . $context['shd_dept_link'],
			);
		}
	}

	if (isset($context['navigation']['back']))
		$context['navigation']['back']['url'] = $scripturl . '?' . $context['shd_home'] . $context['shd_dept_link'];

	// Now, fix the actions
	$subactions['main'] = array(null, 'shd_frontpage_source');
	$subactions['tickets'] = array(null, 'shd_main_helpdesk');

	// Hide the 'back to helpdesk' button on main pages.
	if (isset($_REQUEST['sa']) && in_array($_REQUEST['sa'], array('main', 'tickets', 'viewblock', 'recyclebin', 'closedtickets')))
		unset($context['navigation']['back']);
}

/**
 * Generates the admin options form for the front page plugin.
 *
 * Called when viewing the plugin's admin settings tab.
 *
 * @param bool $return_config If true, only return config vars for search.
 * @return array Config vars array.
 */
function shd_frontpage_options($return_config)
{
	global $context, $modSettings, $txt, $scripturl;

	// Since this is potentially dangerous, real admins only.
	isAllowedTo('admin_forum');

	$config_vars = array(
		array('select', 'shdp_frontpage_appear', array(
			'always' => $txt['shdp_frontpage_appear_always'],
			'firstload' => $txt['shdp_frontpage_appear_firstload'],
			'firstdefault' => $txt['shdp_frontpage_appear_firstdefault'],
		)),
		'',
		array('select', 'shdp_frontpage_type', array(
			'php' => $txt['shdp_frontpage_type_php'],
			'bbcode' => $txt['shdp_frontpage_type_bbcode'],
		)),
		array('large_text', 'shdp_frontpage_content', 'size' => 30),
	);

	$context['settings_title'] = $txt['shdp_frontpage'];
	$context['settings_icon'] = 'frontpage.png';

	// Are we actually going to display this, or bouncing it back just for admin search?
	if (!$return_config)
	{
		loadTemplate('SDPluginFrontPage');
		$context['sub_template'] = 'shd_frontpage_admin';

		$context['shdp_frontpage_content'] = !empty($modSettings['shdp_frontpage_content']) ? $modSettings['shdp_frontpage_content'] : '';

		if (isset($_GET['save']))
		{
			checkSession();

			$_POST['shdp_frontpage_content'] = isset($_POST['shdp_frontpage_content']) ? $_POST['shdp_frontpage_content'] : '';
			if (!empty($_POST['shdp_frontpage_type']) && $_POST['shdp_frontpage_type'] == 'php')
			{
				$context['shdp_frontpage_content'] = Util::htmlspecialchars($_POST['shdp_frontpage_content'], ENT_QUOTES);
			}
			else
			{
				$_POST['shdp_frontpage_content'] = Util::htmlspecialchars($_POST['shdp_frontpage_content'], ENT_QUOTES);
				require_once(SUBSDIR . '/Post.subs.php');
				preparsecode($_POST['shdp_frontpage_content']);
				$context['shdp_frontpage_content'] = un_preparsecode($_POST['shdp_frontpage_content']);
			}

			updateSettings(array(
				'shdp_frontpage_appear' => isset($_POST['shdp_frontpage_appear']) ? $_POST['shdp_frontpage_appear'] : 'firstdefault',
				'shdp_frontpage_type' => isset($_POST['shdp_frontpage_type']) ? $_POST['shdp_frontpage_type'] : 'bbcode',
				'shdp_frontpage_content' => $_POST['shdp_frontpage_content'],
			));

			redirectexit('action=admin;area=helpdesk;sa=frontpage');
		}

		// Set up the editor.
		$modSettings['disable_wysiwyg'] = true;
		$editorOptions = array(
			'id' => 'shdp_frontpage_content',
			'value' => $context['shdp_frontpage_content'],
			'labels' => array(
				'post_button' => $txt['save'],
			),
			'preview_type' => 0,
			'width' => '70%',
			'disable_smiley_box' => false,
		);
		create_control_richedit($editorOptions);
		$context['post_box_name'] = $editorOptions['id'];
	}

	return $config_vars;
}

/**
 * Add front page admin subsection to the admin menu.
 *
 * @param array &$admin_areas The admin areas array.
 */
function shd_frontpage_adminmenu(&$admin_areas)
{
	global $context, $modSettings, $txt;

	// Enabled?
	if (!in_array('front_page', $context['shd_plugins']))
		return;

	if (allowedTo('admin_forum'))
		$admin_areas['helpdesk']['areas']['helpdesk_options']['subsections']['frontpage'] = array($txt['shdp_frontpage']);
}

/**
 * Register the front page tab in helpdesk admin options.
 */
function shd_frontpage_hdadminopts()
{
	global $context, $modSettings, $txt;

	// Enabled?
	if (!in_array('front_page', $context['shd_plugins']))
		return;

	$context[$context['admin_menu_name']]['tab_data']['tabs']['frontpage'] = array(
		'description' => $txt['shdp_frontpage_main_desc'],
		'function' => 'shd_frontpage_options',
	);
}

/**
 * Register admin search for the front page options.
 *
 * @param array &$settings_search The settings search array.
 */
function shd_frontpage_hdadminoptssrch(&$settings_search)
{
	global $context, $modSettings;

	// Enabled?
	if (!in_array('front_page', $context['shd_plugins']))
		return;

	$settings_search[] = array('shd_frontpage_options', 'area=helpdesk_options;sa=frontpage');
}

/**
 * Displays the front page content to the user.
 *
 * Evaluates PHP or parses BBCode depending on the configured content type.
 */
function shd_frontpage_source()
{
	global $context, $txt, $modSettings;

	if (!in_array('front_page', $context['shd_plugins']))
		return;

	loadTemplate('SDPluginFrontPage');
	$context['sub_template'] = 'shd_frontpage';
	$context['page_title'] = $txt['shd_helpdesk'];

	$context['shdp_frontpage_content'] = '';

	if (empty($modSettings['shdp_frontpage_type']))
		$modSettings['shdp_frontpage_type'] = 'html';

	switch ($modSettings['shdp_frontpage_type'])
	{
		case 'php':
			ob_start();
			eval($modSettings['shdp_frontpage_content']);
			$context['shdp_frontpage_content'] = ob_get_contents();
			ob_end_clean();
			break;
		case 'bbcode':
			$context['shdp_frontpage_content'] = parse_bbc($modSettings['shdp_frontpage_content'], true, 'shdp_frontpage');
			break;
	}
}

/**
 * Fix linktree entries after main helpdesk page load.
 */
function shd_frontpage_aftermain()
{
	global $context, $scripturl, $txt;

	// Enabled?
	if (!in_array('front_page', $context['shd_plugins']))
		return;

	$dest = $scripturl . '?action=helpdesk;sa=main';

	// Fix linktree: if the item says 'Tickets' it should point to the tickets page.
	foreach ($context['linktree'] as $key => $treeitem)
	{
		if (empty($treeitem['url']))
			continue;

		if ($treeitem['url'] == $dest && $treeitem['name'] == $txt['shdp_tickets'])
		{
			$context['linktree'][$key]['url'] = $scripturl . '?action=helpdesk;sa=tickets';
			break;
		}
	}

	// If we're on the tickets page, add the linktree item.
	if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'tickets')
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=helpdesk;sa=tickets',
			'name' => $txt['shdp_tickets'],
		);
}

/**
 * Modify the main menu helpdesk link to point to tickets when appropriate.
 *
 * @param array &$menu_buttons The menu buttons array.
 */
function shd_frontpage_mainmenu(&$menu_buttons)
{
	global $context, $scripturl, $modSettings;

	// Enabled?
	if (empty($modSettings['shdp_frontpage_content']) || !in_array('front_page', $context['shd_plugins']))
		return;

	if (!empty($modSettings['shdp_frontpage_appear']) && $modSettings['shdp_frontpage_appear'] == 'firstdefault' && !empty($_SESSION['shdp_frontpage']))
	{
		if (empty($modSettings['shd_helpdesk_only']) && isset($menu_buttons['helpdesk']))
			$menu_buttons['helpdesk']['href'] = $scripturl . '?action=helpdesk;sa=tickets';
		elseif (!empty($modSettings['shd_helpdesk_only']))
			$menu_buttons['home']['href'] = $scripturl . '?action=helpdesk;sa=tickets';
	}
}

/**
 * Fix the board index link for front page mode.
 */
function shd_frontpage_boardindex()
{
	global $context, $modSettings;

	if (empty($modSettings['shdp_frontpage_content']) || !in_array('front_page', $context['shd_plugins']))
		return;

	if (!empty($modSettings['shdp_frontpage_appear']))
	{
		if (($modSettings['shdp_frontpage_appear'] == 'firstlogin' && empty($_SESSION['shdp_frontpage'])) || $modSettings['shdp_frontpage_appear'] != 'firstlogin')
			$context['shd_home'] = 'action=helpdesk;sa=tickets';
	}
}
