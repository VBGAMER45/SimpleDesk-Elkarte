<?php
/**
 * SimpleDesk Helpdesk - Admin Controller
 *
 * Main admin panel: information dashboard and general options.
 * Routes complex sub-actions (departments, permissions, etc.) to
 * their own dedicated admin controllers.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

class ManageSimpleDesk_Controller extends Action_Controller
{
	/**
	 * Entry point for the admin helpdesk area.
	 *
	 * Initializes SimpleDesk, verifies admin permission, loads language
	 * and template files, then routes to the appropriate sub-action.
	 */
	public function action_index()
	{
		global $context, $txt, $scripturl, $modSettings;

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		isAllowedTo('admin_forum');

		loadLanguage('SimpleDesk');
		loadLanguage('SimpleDeskAdmin');
		loadTemplate('SimpleDeskAdmin');

		loadCSSFile('helpdesk.css');
		loadCSSFile('helpdesk_admin.css');

		$sa = isset($_REQUEST['sa']) ? $_REQUEST['sa'] : 'info';

		// Sub-actions handled locally within this controller.
		$subActions = array(
			'info' => 'action_info',
			'options' => 'action_options',
			'actionlog' => 'action_actionlog',
			'adminlog' => 'action_adminlog',
		);

		// Complex sub-actions dispatched to dedicated admin controllers.
		$controller_dispatch = array(
			'departments' => array('ManageSimpleDeskDepts.controller.php', 'ManageSimpleDeskDepts_Controller', 'action_index'),
			'permissions' => array('ManageSimpleDeskPerms.controller.php', 'ManageSimpleDeskPerms_Controller', 'action_index'),
			'custom_fields' => array('ManageSimpleDeskCustomField.controller.php', 'ManageSimpleDeskCustomField_Controller', 'action_index'),
			'canned_replies' => array('ManageSimpleDeskCannedReplies.controller.php', 'ManageSimpleDeskCannedReplies_Controller', 'action_index'),
			'maintenance' => array('ManageSimpleDeskMaint.controller.php', 'ManageSimpleDeskMaint_Controller', 'action_index'),
			'plugins' => array('ManageSimpleDeskPlugins.controller.php', 'ManageSimpleDeskPlugins_Controller', 'action_index'),
		);

		// Set up ElkArte's native admin submenu tabs via tab_data.
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['shd_admin_title'],
			'description' => isset($txt['shd_admin_info_desc']) ? $txt['shd_admin_info_desc'] : '',
			'tabs' => array(
				'info' => array(),
				'options' => array(),
				'departments' => array(),
				'permissions' => array(),
				'custom_fields' => array(),
				'canned_replies' => array(),
				'maintenance' => array(),
				'plugins' => array(),
				'actionlog' => array(
					'disabled' => !empty($modSettings['shd_disable_action_log']),
				),
				'adminlog' => array(
					'disabled' => !allowedTo('admin_forum'),
				),
			),
		);

		// Build the linktree.
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=admin;area=helpdesk',
			'name' => $txt['shd_admin_title'],
		);

		// Subsection label lookup for linktree.
		$sa_labels = array(
			'options' => $txt['shd_admin_options'],
			'departments' => $txt['shd_admin_departments'],
			'permissions' => $txt['shd_admin_permissions'],
			'custom_fields' => $txt['shd_admin_custom_fields'],
			'canned_replies' => $txt['shd_admin_cannedreplies'],
			'maintenance' => $txt['shd_admin_maint'],
			'plugins' => $txt['shd_admin_plugins'],
			'actionlog' => $txt['shd_admin_actionlog'],
			'adminlog' => $txt['shd_admin_adminlog'],
		);

		if ($sa !== 'info' && isset($sa_labels[$sa]))
		{
			$context['linktree'][] = array(
				'url' => $scripturl . '?action=admin;area=helpdesk;sa=' . $sa,
				'name' => $sa_labels[$sa],
			);
		}

		// Dispatch to the appropriate handler.
		if (isset($controller_dispatch[$sa]))
		{
			list($file, $class, $method) = $controller_dispatch[$sa];
			require_once(ADMINDIR . '/' . $file);
			$controller = new $class();
			$controller->$method();
		}
		elseif (isset($subActions[$sa]))
		{
			$this->{$subActions[$sa]}();
		}
		else
		{
			$this->action_info();
		}
	}

	/**
	 * Helpdesk information/dashboard page.
	 *
	 * Displays version info, ticket statistics, department count,
	 * and a list of current staff members.
	 */
	public function action_info()
	{
		global $context, $txt;

		$db = database();

		$context['page_title'] = $txt['shd_admin_info_title'];
		$context['sub_template'] = 'shd_admin_info';

		// -- Ticket statistics --

		// Total tickets (all statuses)
		$request = $db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}helpdesk_tickets',
			array()
		);
		list($context['shd_total_tickets']) = $db->fetch_row($request);
		$db->free_result($request);

		// Open tickets (everything except closed and deleted)
		$request = $db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}helpdesk_tickets
			WHERE status NOT IN ({array_int:closed})',
			array(
				'closed' => array(TICKET_STATUS_CLOSED, TICKET_STATUS_DELETED),
			)
		);
		list($context['shd_open_tickets']) = $db->fetch_row($request);
		$db->free_result($request);

		// Closed tickets
		$request = $db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}helpdesk_tickets
			WHERE status = {int:closed}',
			array(
				'closed' => TICKET_STATUS_CLOSED,
			)
		);
		list($context['shd_closed_tickets']) = $db->fetch_row($request);
		$db->free_result($request);

		// Recycled (deleted) tickets
		$request = $db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}helpdesk_tickets
			WHERE status = {int:deleted}',
			array(
				'deleted' => TICKET_STATUS_DELETED,
			)
		);
		list($context['shd_recycled_tickets']) = $db->fetch_row($request);
		$db->free_result($request);

		// Department count
		$request = $db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}helpdesk_depts',
			array()
		);
		list($context['shd_total_depts']) = $db->fetch_row($request);
		$db->free_result($request);

		// -- Staff member list --
		$context['shd_staff_list'] = array();

		// Get member IDs with staff permission across all departments.
		$staff_members = shd_members_allowed_to('shd_staff', 0);

		if (!empty($staff_members))
		{
			$request = $db->query('', '
				SELECT id_member, real_name
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:staff})
				ORDER BY real_name ASC',
				array(
					'staff' => $staff_members,
				)
			);

			while ($row = $db->fetch_assoc($request))
			{
				$context['shd_staff_list'][$row['id_member']] = array(
					'id' => $row['id_member'],
					'name' => $row['real_name'],
				);
			}
			$db->free_result($request);
		}

		// Version
		$context['shd_version'] = SHD_VERSION;
	}

	/**
	 * Helpdesk general options/settings page.
	 *
	 * Builds an array of config variables grouped by section and handles
	 * saving via updateSettings(). Uses manual form handling rather than
	 * SMF/ElkArte saveDBSettings since SimpleDesk has its own settings flow.
	 */
	public function action_options()
	{
		global $context, $txt, $scripturl, $modSettings;

		$context['page_title'] = $txt['shd_admin_options_title'];
		$context['sub_template'] = 'shd_admin_options';
		$context['post_url'] = $scripturl . '?action=admin;area=helpdesk;sa=options;save';

		// If saving, process the form submission.
		if (isset($_GET['save']))
		{
			checkSession();

			$save_vars = array();

			// Checkbox settings: present in POST = 1, absent = 0
			$checkboxes = array(
				'shd_display_avatar',
				'shd_hidemenuitem',
				'shd_thank_you_post',
				'shd_allow_wikilinks',
				'shd_allow_ticket_bbc',
				'shd_allow_ticket_smileys',
				'shd_maintenance_mode',
				'shd_staff_ticket_self',
				'shd_admins_not_assignable',
				'shd_disable_relationships',
				'shd_disable_action_log',
				'shd_logopt_newposts',
				'shd_logopt_editposts',
				'shd_logopt_resolve',
				'shd_logopt_assign',
				'shd_logopt_privacy',
				'shd_logopt_urgency',
				'shd_logopt_delete',
				'shd_logopt_restore',
				'shd_logopt_permadelete',
				'shd_logopt_relationships',
				'shd_logopt_move_dept',
			);

			foreach ($checkboxes as $cb)
				$save_vars[$cb] = !empty($_POST[$cb]) ? 1 : 0;

			// Integer settings
			$integers = array(
				'shd_zerofill',
			);

			foreach ($integers as $int_var)
				$save_vars[$int_var] = isset($_POST[$int_var]) ? max(0, (int) $_POST[$int_var]) : 0;

			// Select / text settings
			$selects = array(
				'shd_staff_badge' => array('nobadge', 'staffbadge', 'userbadge', 'bothbadge'),
				'shd_ticketnav_style' => array('sd', 'sdcompact', 'smf'),
				'shd_attachments_mode' => array('ticket', 'reply'),
				'shd_privacy_display' => array('smart', 'always'),
			);

			foreach ($selects as $sel_var => $valid_options)
			{
				if (isset($_POST[$sel_var]) && in_array($_POST[$sel_var], $valid_options))
					$save_vars[$sel_var] = $_POST[$sel_var];
				else
					$save_vars[$sel_var] = $valid_options[0];
			}

			updateSettings($save_vars);

			redirectexit('action=admin;area=helpdesk;sa=options');
		}

		// Build the config_vars array for the template to render.
		$context['config_vars'] = array();

		// ---- Display Options ----
		$context['config_vars'][] = $txt['shd_admin_options_display'];

		$context['config_vars'][] = array(
			'name' => 'shd_staff_badge',
			'label' => $txt['shd_staff_badge'],
			'subtext' => isset($txt['shd_staff_badge_desc']) ? $txt['shd_staff_badge_desc'] : '',
			'type' => 'select',
			'value' => isset($modSettings['shd_staff_badge']) ? $modSettings['shd_staff_badge'] : 'nobadge',
			'options' => array(
				'nobadge' => $txt['shd_staff_badge_nobadge'],
				'staffbadge' => $txt['shd_staff_badge_staffbadge'],
				'userbadge' => $txt['shd_staff_badge_userbadge'],
				'bothbadge' => $txt['shd_staff_badge_bothbadge'],
			),
		);

		$context['config_vars'][] = array(
			'name' => 'shd_display_avatar',
			'label' => $txt['shd_display_avatar'],
			'subtext' => isset($txt['shd_display_avatar_desc']) ? $txt['shd_display_avatar_desc'] : '',
			'type' => 'check',
			'value' => !empty($modSettings['shd_display_avatar']),
		);

		$context['config_vars'][] = array(
			'name' => 'shd_ticketnav_style',
			'label' => $txt['shd_ticketnav_style'],
			'subtext' => isset($txt['shd_ticketnav_style_desc']) ? $txt['shd_ticketnav_style_desc'] : '',
			'type' => 'select',
			'value' => isset($modSettings['shd_ticketnav_style']) ? $modSettings['shd_ticketnav_style'] : 'sd',
			'options' => array(
				'sd' => $txt['shd_ticketnav_style_sd'],
				'sdcompact' => $txt['shd_ticketnav_style_sdcompact'],
				'smf' => $txt['shd_ticketnav_style_smf'],
			),
		);

		$context['config_vars'][] = array(
			'name' => 'shd_zerofill',
			'label' => $txt['shd_zerofill'],
			'subtext' => isset($txt['shd_zerofill_desc']) ? $txt['shd_zerofill_desc'] : '',
			'type' => 'int',
			'value' => isset($modSettings['shd_zerofill']) ? (int) $modSettings['shd_zerofill'] : 0,
		);

		$context['config_vars'][] = array(
			'name' => 'shd_hidemenuitem',
			'label' => $txt['shd_hidemenuitem'],
			'subtext' => isset($txt['shd_hidemenuitem_desc']) ? $txt['shd_hidemenuitem_desc'] : '',
			'type' => 'check',
			'value' => !empty($modSettings['shd_hidemenuitem']),
		);

		// ---- Posting Options ----
		$context['config_vars'][] = $txt['shd_admin_options_posting'];

		$context['config_vars'][] = array(
			'name' => 'shd_thank_you_post',
			'label' => $txt['shd_thank_you_post'],
			'subtext' => isset($txt['shd_thank_you_post_desc']) ? $txt['shd_thank_you_post_desc'] : '',
			'type' => 'check',
			'value' => !empty($modSettings['shd_thank_you_post']),
		);

		$context['config_vars'][] = array(
			'name' => 'shd_allow_wikilinks',
			'label' => $txt['shd_allow_wikilinks'],
			'subtext' => isset($txt['shd_allow_wikilinks_desc']) ? $txt['shd_allow_wikilinks_desc'] : '',
			'type' => 'check',
			'value' => !empty($modSettings['shd_allow_wikilinks']),
		);

		$context['config_vars'][] = array(
			'name' => 'shd_allow_ticket_bbc',
			'label' => $txt['shd_allow_ticket_bbc'],
			'subtext' => isset($txt['shd_allow_ticket_bbc_desc']) ? $txt['shd_allow_ticket_bbc_desc'] : '',
			'type' => 'check',
			'value' => !empty($modSettings['shd_allow_ticket_bbc']),
		);

		$context['config_vars'][] = array(
			'name' => 'shd_allow_ticket_smileys',
			'label' => $txt['shd_allow_ticket_smileys'],
			'subtext' => isset($txt['shd_allow_ticket_smileys_desc']) ? $txt['shd_allow_ticket_smileys_desc'] : '',
			'type' => 'check',
			'value' => !empty($modSettings['shd_allow_ticket_smileys']),
		);

		$context['config_vars'][] = array(
			'name' => 'shd_attachments_mode',
			'label' => $txt['shd_attachments_mode'],
			'subtext' => isset($txt['shd_attachments_mode_desc']) ? $txt['shd_attachments_mode_desc'] : '',
			'type' => 'select',
			'value' => isset($modSettings['shd_attachments_mode']) ? $modSettings['shd_attachments_mode'] : 'ticket',
			'options' => array(
				'ticket' => $txt['shd_attachments_mode_ticket'],
				'reply' => $txt['shd_attachments_mode_reply'],
			),
		);

		// ---- Admin Options ----
		$context['config_vars'][] = $txt['shd_admin_options_admin'];

		$context['config_vars'][] = array(
			'name' => 'shd_maintenance_mode',
			'label' => $txt['shd_maintenance_mode'],
			'subtext' => isset($txt['shd_maintenance_mode_desc']) ? $txt['shd_maintenance_mode_desc'] : '',
			'type' => 'check',
			'value' => !empty($modSettings['shd_maintenance_mode']),
		);

		$context['config_vars'][] = array(
			'name' => 'shd_staff_ticket_self',
			'label' => $txt['shd_staff_ticket_self'],
			'subtext' => isset($txt['shd_staff_ticket_self_desc']) ? $txt['shd_staff_ticket_self_desc'] : '',
			'type' => 'check',
			'value' => !empty($modSettings['shd_staff_ticket_self']),
		);

		$context['config_vars'][] = array(
			'name' => 'shd_admins_not_assignable',
			'label' => $txt['shd_admins_not_assignable'],
			'subtext' => isset($txt['shd_admins_not_assignable_desc']) ? $txt['shd_admins_not_assignable_desc'] : '',
			'type' => 'check',
			'value' => !empty($modSettings['shd_admins_not_assignable']),
		);

		$context['config_vars'][] = array(
			'name' => 'shd_privacy_display',
			'label' => $txt['shd_privacy_display'],
			'subtext' => isset($txt['shd_privacy_display_desc']) ? $txt['shd_privacy_display_desc'] : '',
			'type' => 'select',
			'value' => isset($modSettings['shd_privacy_display']) ? $modSettings['shd_privacy_display'] : 'smart',
			'options' => array(
				'smart' => $txt['shd_privacy_display_smart'],
				'always' => $txt['shd_privacy_display_always'],
			),
		);

		$context['config_vars'][] = array(
			'name' => 'shd_disable_relationships',
			'label' => $txt['shd_disable_relationships'],
			'subtext' => isset($txt['shd_disable_relationships_desc']) ? $txt['shd_disable_relationships_desc'] : '',
			'type' => 'check',
			'value' => !empty($modSettings['shd_disable_relationships']),
		);

		// ---- Action Log Options ----
		$context['config_vars'][] = $txt['shd_admin_options_log'];

		$context['config_vars'][] = array(
			'name' => 'shd_disable_action_log',
			'label' => $txt['shd_disable_action_log'],
			'subtext' => isset($txt['shd_disable_action_log_desc']) ? $txt['shd_disable_action_log_desc'] : '',
			'type' => 'check',
			'value' => !empty($modSettings['shd_disable_action_log']),
		);

		$log_options = array(
			'shd_logopt_newposts',
			'shd_logopt_editposts',
			'shd_logopt_resolve',
			'shd_logopt_assign',
			'shd_logopt_privacy',
			'shd_logopt_urgency',
			'shd_logopt_delete',
			'shd_logopt_restore',
			'shd_logopt_permadelete',
			'shd_logopt_relationships',
			'shd_logopt_move_dept',
		);

		foreach ($log_options as $logopt)
		{
			$context['config_vars'][] = array(
				'name' => $logopt,
				'label' => $txt[$logopt],
				'subtext' => isset($txt[$logopt . '_desc']) ? $txt[$logopt . '_desc'] : '',
				'type' => 'check',
				// These default to true (enabled) when not explicitly set.
				'value' => isset($modSettings[$logopt]) ? !empty($modSettings[$logopt]) : true,
			);
		}
	}

	/**
	 * Display the helpdesk action log.
	 *
	 * Shows all ticket-level actions with sorting, pagination, and deletion.
	 */
	public function action_actionlog()
	{
		global $context, $settings, $scripturl, $txt, $modSettings, $sort_types;

		require_once(SUBSDIR . '/SimpleDeskAdmin.subs.php');
		loadLanguage('SimpleDeskLogAction');

		$context['can_delete'] = allowedTo('admin_forum');
		$context['displaypage'] = 30;
		$context['hoursdisable'] = 24;
		$context['waittime'] = time() - $context['hoursdisable'] * 3600;

		// Handle deletion
		if (isset($_REQUEST['removeall']) && $context['can_delete'])
		{
			checkSession('get');
			shd_db_query('', '
				DELETE FROM {db_prefix}helpdesk_log_action
				WHERE log_time < {int:twenty_four_hours_wait}
					AND id_ticket != {int:no_ticket}',
				array(
					'twenty_four_hours_wait' => $context['waittime'],
					'no_ticket' => 0,
				)
			);
		}
		elseif (!empty($_REQUEST['remove']) && $context['can_delete'])
		{
			checkSession('get');
			shd_db_query('', '
				DELETE FROM {db_prefix}helpdesk_log_action
				WHERE id_action = {int:gtfo}
					AND log_time < {int:twenty_four_hours_wait}',
				array(
					'twenty_four_hours_wait' => $context['waittime'],
					'gtfo' => (int) $_REQUEST['remove'],
				)
			);
		}

		// Sorting columns
		$sort_types = array(
			'action' => 'la.action',
			'time' => 'la.log_time',
			'member' => 'mem.real_name',
			'position' => 'mg.group_name',
			'ip' => 'la.ip',
		);

		$context['sort'] = isset($_REQUEST['sort']) && isset($sort_types[$_REQUEST['sort']]) ? $sort_types[$_REQUEST['sort']] : $sort_types['time'];
		$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
		$context['order'] = isset($_REQUEST['asc']) ? 'ASC' : 'DESC';
		$context['url_sort'] = isset($_REQUEST['sort']) ? ';sort=' . $_REQUEST['sort'] : '';
		$context['url_order'] = isset($_REQUEST['asc']) ? ';asc' : '';

		$context['actions'] = shd_load_action_log_entries($context['start'], $context['displaypage'], $context['sort'], $context['order']);
		$context['page_index'] = shd_no_expand_pageindex($scripturl . '?action=admin;area=helpdesk;sa=actionlog' . $context['url_sort'] . $context['url_order'], $context['start'], shd_count_action_log_entries(), $context['displaypage']);

		$context['sub_template'] = 'shd_action_log';
	}

	/**
	 * Display the helpdesk admin log.
	 *
	 * Shows admin-level actions (settings changes, dept/role management, etc.)
	 * with sorting, pagination, and deletion. Full admin only.
	 */
	public function action_adminlog()
	{
		global $context, $settings, $scripturl, $txt, $modSettings, $sort_types;

		isAllowedTo('admin_forum');

		require_once(SUBSDIR . '/SimpleDeskAdmin.subs.php');
		loadLanguage('SimpleDeskLogAction');

		$context['can_delete'] = true;
		$context['displaypage'] = 30;
		$context['daysdisable'] = 28;
		$context['waittime'] = time() - $context['daysdisable'] * 24 * 3600;

		// Handle deletion
		if (isset($_REQUEST['removeall']))
		{
			checkSession('get');
			shd_db_query('', '
				DELETE FROM {db_prefix}helpdesk_log_action
				WHERE log_time < {int:twenty_four_hours_wait}
					AND id_ticket = {int:no_ticket}',
				array(
					'twenty_four_hours_wait' => $context['waittime'],
					'no_ticket' => 0,
				)
			);
		}
		elseif (!empty($_REQUEST['remove']) && $context['can_delete'])
		{
			checkSession('get');
			shd_db_query('', '
				DELETE FROM {db_prefix}helpdesk_log_action
				WHERE id_action = {int:gtfo}
					AND log_time < {int:twenty_four_hours_wait}',
				array(
					'twenty_four_hours_wait' => $context['waittime'],
					'gtfo' => (int) $_REQUEST['remove'],
				)
			);
		}

		// Sorting columns
		$sort_types = array(
			'action' => 'la.action',
			'time' => 'la.log_time',
			'member' => 'mem.real_name',
			'position' => 'mg.group_name',
			'ip' => 'la.ip',
		);

		$context['sort'] = isset($_REQUEST['sort']) && isset($sort_types[$_REQUEST['sort']]) ? $sort_types[$_REQUEST['sort']] : $sort_types['time'];
		$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
		$context['order'] = isset($_REQUEST['asc']) ? 'ASC' : 'DESC';
		$context['url_sort'] = isset($_REQUEST['sort']) ? ';sort=' . $_REQUEST['sort'] : '';
		$context['url_order'] = isset($_REQUEST['asc']) ? ';asc' : '';

		$context['actions'] = shd_load_admin_log_entries($context['start'], $context['displaypage'], $context['sort'], $context['order']);
		$context['page_index'] = shd_no_expand_pageindex($scripturl . '?action=admin;area=helpdesk;sa=adminlog' . $context['url_sort'] . $context['url_order'], $context['start'], shd_count_admin_log_entries(), $context['displaypage']);

		$context['sub_template'] = 'shd_admin_log';
	}
}
