<?php
/**
 * SimpleDesk Plugins Management Controller
 *
 * Handles administration of helpdesk plugins/extensions: listing installed
 * plugins, enabling/disabling them, and registering/unregistering their
 * hooks. Plugins are discovered from JSON manifests in the plugins directory.
 *
 * Ported from SMF SimpleDesk-AdminPlugins.php to ElkArte.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Controller for managing helpdesk plugins.
 *
 * Dispatched from ManageSimpleDesk_Controller when sa=plugins.
 */
class ManageSimpleDeskPlugins_Controller extends Action_Controller
{
	/**
	 * Plugin listing and enable/disable management page.
	 *
	 * Scans the plugins directory for plugin manifests, builds the plugin
	 * list for display, and handles save requests to toggle plugins on/off.
	 * When enabling a plugin, its declared hooks are registered; when
	 * disabling, they are unregistered.
	 */
	public function action_index()
	{
		global $context, $txt, $scripturl, $modSettings;

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		isAllowedTo('admin_forum');

		loadLanguage('SimpleDesk');
		loadLanguage('SimpleDeskAdmin');
		loadTemplate('SimpleDeskAdminPlugins');

		$context['page_title'] = isset($txt['shd_admin_plugins_title']) ? $txt['shd_admin_plugins_title'] : 'SimpleDesk Plugins';
		$context['sub_template'] = 'shd_plugin_listing';

		// Currently enabled plugins stored as comma-separated list.
		$enabled_plugins = !empty($modSettings['shd_enabled_plugins']) ? explode(',', $modSettings['shd_enabled_plugins']) : array();

		// Get all available hook points in SimpleDesk.
		$context['shd_hook_list'] = shd_list_hooks();

		// Discover plugins from the plugins directory.
		$context['plugins'] = array();
		$plugins_dir = $this->_get_plugins_dir();

		if (!empty($plugins_dir) && is_dir($plugins_dir))
		{
			$dir_handle = opendir($plugins_dir);
			if ($dir_handle !== false)
			{
				while (($entry = readdir($dir_handle)) !== false)
				{
					// Skip dot entries and non-directories.
					if ($entry === '.' || $entry === '..')
						continue;

					$plugin_path = $plugins_dir . '/' . $entry;

					// Each plugin lives in its own subdirectory.
					if (!is_dir($plugin_path))
						continue;

					// Look for an info.json manifest.
					$manifest_file = $plugin_path . '/info.json';
					if (!file_exists($manifest_file))
						continue;

					$manifest_raw = file_get_contents($manifest_file);
					if (empty($manifest_raw))
						continue;

					$manifest = json_decode($manifest_raw, true);
					if (empty($manifest) || empty($manifest['id']))
						continue;

					$plugin_id = $manifest['id'];

					// Check version compatibility if specified.
					$compatible = true;
					if (!empty($manifest['compatibility']))
					{
						$compatible = $this->_check_compatibility($manifest['compatibility']);
					}

					$context['plugins'][$plugin_id] = array(
						'id' => $plugin_id,
						'dir' => $entry,
						'title' => !empty($manifest['title']) ? $manifest['title'] : $plugin_id,
						'author' => !empty($manifest['author']) ? $manifest['author'] : '',
						'website' => !empty($manifest['website']) ? $manifest['website'] : '',
						'description' => !empty($manifest['description']) ? $manifest['description'] : '',
						'version' => !empty($manifest['version']) ? $manifest['version'] : '1.0',
						'compatibility' => !empty($manifest['compatibility']) ? $manifest['compatibility'] : array(),
						'hooks' => !empty($manifest['hooks']) && is_array($manifest['hooks']) ? $manifest['hooks'] : array(),
						'enabled' => in_array($plugin_id, $enabled_plugins),
						'compatible' => $compatible,
						'path' => $plugin_path,
					);
				}
				closedir($dir_handle);
			}
		}

		// Sort plugins alphabetically by title.
		uasort($context['plugins'], function ($a, $b) {
			return strcasecmp($a['title'], $b['title']);
		});

		// Handle save: toggling plugins on/off.
		if (isset($_GET['save']) || isset($_REQUEST['save']))
		{
			checkSession();

			$new_enabled = array();

			foreach ($context['plugins'] as $plugin_id => $plugin)
			{
				$was_enabled = $plugin['enabled'];
				$now_enabled = !empty($_POST['plugin_' . $plugin_id]);

				// Only allow enabling compatible plugins.
				if ($now_enabled && !$plugin['compatible'])
					$now_enabled = false;

				if ($now_enabled)
					$new_enabled[] = $plugin_id;

				// Handle hook registration changes.
				if ($was_enabled && !$now_enabled)
				{
					// Disabling: unregister hooks.
					$this->_unregister_plugin_hooks($plugin);
				}
				elseif (!$was_enabled && $now_enabled)
				{
					// Enabling: register hooks.
					$this->_register_plugin_hooks($plugin);
				}

				// Update the displayed state.
				$context['plugins'][$plugin_id]['enabled'] = $now_enabled;
			}

			$save_value = implode(',', $new_enabled);
			updateSettings(array('shd_enabled_plugins' => $save_value));

			// Refresh the enabled list in modSettings.
			$modSettings['shd_enabled_plugins'] = $save_value;

			redirectexit('action=admin;area=helpdesk;sa=plugins');
		}

		// Post URL for the form.
		$context['post_url'] = $scripturl . '?action=admin;area=helpdesk;sa=plugins;save';
	}

	/**
	 * Returns the path to the SimpleDesk plugins directory.
	 *
	 * Looks for a 'plugins' subdirectory under the SimpleDesk sources area.
	 * Falls back to checking SOURCEDIR and SUBSDIR.
	 *
	 * @return string|false Path to the plugins directory, or false if not found.
	 */
	private function _get_plugins_dir()
	{
		// Check for a plugins directory under the subs area first.
		$candidates = array(
			SUBSDIR . '/simpledesk/plugins',
			SOURCEDIR . '/simpledesk/plugins',
			BOARDDIR . '/simpledesk/plugins',
		);

		foreach ($candidates as $path)
		{
			if (is_dir($path))
				return $path;
		}

		// If none found, return the default expected location.
		return SUBSDIR . '/simpledesk/plugins';
	}

	/**
	 * Checks plugin compatibility against the current SimpleDesk version.
	 *
	 * The compatibility field in the manifest can specify version constraints
	 * such as min_version and max_version.
	 *
	 * @param array $compatibility Array with min_version and/or max_version keys.
	 * @return bool True if compatible, false otherwise.
	 */
	private function _check_compatibility($compatibility)
	{
		// Extract the numeric version from SHD_VERSION (e.g. "SimpleDesk 2.1.5" -> "2.1.5")
		$current_version = defined('SHD_VERSION') ? preg_replace('~^[^0-9]*~', '', SHD_VERSION) : '2.1.5';

		if (!empty($compatibility['min_version']))
		{
			if (version_compare($current_version, $compatibility['min_version'], '<'))
				return false;
		}

		if (!empty($compatibility['max_version']))
		{
			if (version_compare($current_version, $compatibility['max_version'], '>'))
				return false;
		}

		return true;
	}

	/**
	 * Registers a plugin's declared hooks with ElkArte.
	 *
	 * Each hook entry in the manifest maps a SimpleDesk hook point to a
	 * callable (function name or class::method).
	 *
	 * @param array $plugin Plugin data array with 'hooks' and 'path' keys.
	 */
	private function _register_plugin_hooks($plugin)
	{
		if (empty($plugin['hooks']) || !is_array($plugin['hooks']))
			return;

		foreach ($plugin['hooks'] as $hook_point => $callable)
		{
			// Validate hook point exists.
			$valid_hooks = shd_list_hooks();
			if (!in_array($hook_point, $valid_hooks))
				continue;

			// If a file needs to be included, add the path.
			if (!empty($plugin['path']))
			{
				$hook_file = $plugin['path'] . '/plugin.php';
				if (file_exists($hook_file))
				{
					add_integration_function($hook_point, $callable, $hook_file, true);
					continue;
				}
			}

			add_integration_function($hook_point, $callable, '', true);
		}
	}

	/**
	 * Unregisters a plugin's declared hooks from ElkArte.
	 *
	 * @param array $plugin Plugin data array with 'hooks' and 'path' keys.
	 */
	private function _unregister_plugin_hooks($plugin)
	{
		if (empty($plugin['hooks']) || !is_array($plugin['hooks']))
			return;

		foreach ($plugin['hooks'] as $hook_point => $callable)
		{
			$valid_hooks = shd_list_hooks();
			if (!in_array($hook_point, $valid_hooks))
				continue;

			if (!empty($plugin['path']))
			{
				$hook_file = $plugin['path'] . '/plugin.php';
				if (file_exists($hook_file))
				{
					remove_integration_function($hook_point, $callable, $hook_file);
					continue;
				}
			}

			remove_integration_function($hook_point, $callable);
		}
	}
}

/**
 * Returns the master list of all SimpleDesk internal hook points.
 *
 * These are extension points where plugins can inject behavior into the
 * helpdesk workflow. Each hook point name follows the shd_hook_* convention.
 *
 * Ported from the SMF SimpleDesk plugin hook list.
 *
 * @return array Array of hook point name strings.
 */
function shd_list_hooks()
{
	return array(
		// Core helpdesk hooks
		'shd_hook_helpdesk',
		'shd_hook_hdmain',
		'shd_hook_hdprofile',

		// Ticket lifecycle hooks
		'shd_hook_newticket',
		'shd_hook_replyticket',
		'shd_hook_editticket',
		'shd_hook_viewticket',
		'shd_hook_deleteticket',
		'shd_hook_restoreticket',
		'shd_hook_permadelete',
		'shd_hook_resolveticket',
		'shd_hook_unresolveticket',

		// Reply lifecycle hooks
		'shd_hook_deletereply',
		'shd_hook_restorereply',
		'shd_hook_editreply',

		// Assignment hooks
		'shd_hook_assign',
		'shd_hook_unassign',

		// Urgency hooks
		'shd_hook_urgencychange',

		// Privacy hooks
		'shd_hook_privacychange',

		// Relationship hooks
		'shd_hook_relationships',
		'shd_hook_relations_save',

		// Department hooks
		'shd_hook_movedept',

		// Notification hooks
		'shd_hook_notify',
		'shd_hook_notification_prefs',

		// Search hooks
		'shd_hook_search',

		// Posting hooks
		'shd_hook_beforepost',
		'shd_hook_afterpost',
		'shd_hook_beforeedit',
		'shd_hook_afteredit',

		// Custom field hooks
		'shd_hook_customfields',
		'shd_hook_customfield_save',

		// Admin hooks
		'shd_hook_admin_options',
		'shd_hook_admin_departments',
		'shd_hook_admin_permissions',
		'shd_hook_admin_maintenance',
		'shd_hook_admin_plugins',

		// Display hooks
		'shd_hook_display_ticket',
		'shd_hook_display_reply',
		'shd_hook_display_sidebar',
		'shd_hook_ticketlist',

		// Canned reply hooks
		'shd_hook_cannedreply',

		// Attachment hooks
		'shd_hook_attachment',
		'shd_hook_attachment_upload',
		'shd_hook_attachment_delete',

		// Board index integration hooks
		'shd_hook_boardindex',
		'shd_hook_boardindex_after',

		// Action log hooks
		'shd_hook_actionlog',

		// Buffer/output hooks
		'shd_hook_buffer',

		// Menu/navigation hooks
		'shd_hook_menu',
		'shd_hook_navigation',

		// Profile hooks
		'shd_hook_profile_summary',
		'shd_hook_profile_prefs',

		// Permission hooks
		'shd_hook_perms',
	);
}
