<?php
/**
 * SimpleDesk Helpdesk - Integration Hooks
 *
 * All hook callback functions for ElkArte integration.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Register ?action=helpdesk with ElkArte's action dispatcher.
 * Hook: integrate_actions
 */
function simpledesk_integrate_actions(&$actionArray)
{
	$actionArray['helpdesk'] = array('SimpleDesk.controller.php', 'SimpleDesk_Controller', 'action_index');
}

/**
 * Add "Helpdesk" link to the main navigation menu.
 * Hook: integrate_menu_buttons
 */
function simpledesk_integrate_menu_buttons(&$buttons)
{
	global $scripturl, $txt, $modSettings, $user_info, $context;

	if (!empty($modSettings['shd_maintenance_mode']) && !$user_info['is_admin'])
		return;

	if ($user_info['is_guest'])
		return;

	require_once(SUBSDIR . '/SimpleDesk.subs.php');
	shd_init();

	if (!shd_allowed_to('access_helpdesk', 0))
		return;

	loadLanguage('SimpleDesk');

	$ticket_count = shd_get_active_tickets();
	$title = $txt['shd_helpdesk'];
	if (!empty($ticket_count))
		$title .= ' [' . $ticket_count . ']';

	// Insert after "Home" button.
	$insert_after = 'home';
	$new_buttons = array();

	foreach ($buttons as $key => $button)
	{
		$new_buttons[$key] = $button;

		if ($key == $insert_after)
		{
			$new_buttons['helpdesk'] = array(
				'title' => $title,
				'href' => $scripturl . '?action=helpdesk;sa=main',
				'icon' => 'i-helpdesk',
				'show' => true,
				'sub_buttons' => array(
					'newticket' => array(
						'title' => isset($txt['shd_new_ticket']) ? $txt['shd_new_ticket'] : 'New Ticket',
						'href' => $scripturl . '?action=helpdesk;sa=newticket',
						'show' => shd_allowed_to('shd_new_ticket', 0),
					),
					'closedtickets' => array(
						'title' => isset($txt['shd_tickets_closed']) ? $txt['shd_tickets_closed'] : 'Closed Tickets',
						'href' => $scripturl . '?action=helpdesk;sa=closedtickets',
						'show' => shd_allowed_to('shd_view_closed_own', 0) || shd_allowed_to('shd_view_closed_any', 0),
					),
				),
			);
		}
	}

	$buttons = $new_buttons;
}

/**
 * Initialize SimpleDesk on every page load.
 * Hook: integrate_load_theme
 */
function simpledesk_integrate_load_theme()
{
	global $modSettings, $user_info;

	if ($user_info['is_guest'])
		return;

	require_once(SUBSDIR . '/SimpleDesk.subs.php');
	shd_init();
}

/**
 * Register admin area for the helpdesk.
 * Hook: integrate_admin_areas
 */
function simpledesk_integrate_admin_areas(&$admin_areas)
{
	global $txt;

	loadLanguage('SimpleDesk');
	loadLanguage('SimpleDeskAdmin');

	$admin_areas['config']['areas']['helpdesk'] = array(
		'label' => !empty($txt['shd_admin_title']) ? $txt['shd_admin_title'] : 'Helpdesk',
		'file' => 'ManageSimpleDesk.controller.php',
		'controller' => 'ManageSimpleDesk_Controller',
		'permission' => array('admin_forum'),
		'function' => 'action_index',
		'icon' => 'transparent.png',
		'class' => 'admin_img_maintain',
		'subsections' => array(
			'info' => array(!empty($txt['shd_admin_info']) ? $txt['shd_admin_info'] : 'Information'),
			'options' => array(!empty($txt['shd_admin_options']) ? $txt['shd_admin_options'] : 'Options'),
			'departments' => array(!empty($txt['shd_admin_departments']) ? $txt['shd_admin_departments'] : 'Departments'),
			'permissions' => array(!empty($txt['shd_admin_permissions']) ? $txt['shd_admin_permissions'] : 'Permissions'),
			'custom_fields' => array(!empty($txt['shd_admin_custom_fields']) ? $txt['shd_admin_custom_fields'] : 'Custom Fields'),
			'canned_replies' => array(!empty($txt['shd_admin_cannedreplies']) ? $txt['shd_admin_cannedreplies'] : 'Canned Replies'),
			'maintenance' => array(!empty($txt['shd_admin_maint']) ? $txt['shd_admin_maint'] : 'Maintenance'),
			'plugins' => array(!empty($txt['shd_admin_plugins']) ? $txt['shd_admin_plugins'] : 'Plugins'),
			'actionlog' => array(!empty($txt['shd_admin_actionlog']) ? $txt['shd_admin_actionlog'] : 'Action Log'),
			'adminlog' => array(!empty($txt['shd_admin_adminlog']) ? $txt['shd_admin_adminlog'] : 'Admin Log'),
		),
	);
}

/**
 * Register the admin_helpdesk permission with ElkArte.
 * Hook: integrate_load_permissions
 */
function simpledesk_integrate_load_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
{
	$permissionGroups['membergroup'][] = 'simpledesk';
	$permissionList['membergroup']['admin_helpdesk'] = array(false, 'simpledesk', 'simpledesk');
	$leftPermissionGroups[] = 'simpledesk';
}

/**
 * Add mod credits.
 * Hook: integrate_credits
 */
function simpledesk_integrate_credits()
{
	global $context;

	$context['copyrights']['mods'][] = '<a href="https://www.simpledesk.net" target="_blank" rel="noopener">SimpleDesk Helpdesk</a> &copy; SimpleDesk.net';
}

/**
 * Highlight "Helpdesk" in the menu when on helpdesk pages.
 * Hook: integrate_current_action
 */
function simpledesk_integrate_current_action(&$current_action)
{
	if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'helpdesk')
		$current_action = 'helpdesk';
}

/**
 * Add helpdesk areas to user profiles.
 * Hook: integrate_pre_profile_areas
 */
function simpledesk_integrate_pre_profile_areas(&$profile_areas)
{
	global $modSettings, $context, $txt;

	if (empty($modSettings['helpdesk_active']))
		return;

	require_once(SUBSDIR . '/SimpleDesk.subs.php');
	shd_init();

	require_once(SUBSDIR . '/SimpleDeskProfile.subs.php');
	shd_profile_areas($profile_areas);
}

/**
 * Output buffer replacements (reserved for future use).
 * Hook: integrate_buffer
 */
function simpledesk_integrate_buffer($buffer)
{
	// Reserved for future use (e.g., injecting unread ticket indicators).
	return $buffer;
}
