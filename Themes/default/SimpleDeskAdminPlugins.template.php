<?php
/**
 * SimpleDesk Admin Plugins Template
 *
 * Templates for plugin/extension administration. Provides the interface for
 * viewing installed plugins, enabling/disabling them, and viewing their
 * details (author, compatibility, language availability).
 *
 * Ported from SMF SimpleDesk-AdminPlugins.template.php for ElkArte 1.1.9.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Plugin listing template.
 *
 * Displays all installed plugins with their enable/disable toggles,
 * title, description, author, compatibility information, and language
 * availability icons.
 *
 * Features:
 * - JavaScript toggle for enabling/disabling plugins via AJAX
 * - Non-JS radio button fallback for enable/disable
 * - Compatibility warnings for non-installable versions
 * - Language availability indicators
 *
 * @since 2.0
 */
function template_shd_plugin_listing()
{
	global $context, $settings, $txt, $modSettings, $scripturl;

	echo '
	<div id="admincenter">';


	// Page header.
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<img src="', $settings['images_url'], '/simpledesk/plugins.png" alt="*" class="shd_icon_minihead" />
				', $txt['shd_admin_plugins_title'], '
			</h3>
		</div>
		<div class="information">
			', $txt['shd_admin_plugins_desc'], '
		</div>';

	// JavaScript toggle function for enabling/disabling plugins.
	echo '
		<script>
			function toggleItem(pluginId)
			{
				var img = document.getElementById("plugin_img_" + pluginId);
				var input = document.getElementById("plugin_state_" + pluginId);

				if (!img || !input)
					return;

				if (input.value == "1")
				{
					input.value = "0";
					img.src = "', $settings['images_url'], '/simpledesk/plugin_off.png";
				}
				else
				{
					input.value = "1";
					img.src = "', $settings['images_url'], '/simpledesk/plugin_on.png";
				}
			}
		</script>';

	// Plugin listing form.
	echo '
		<form action="', $scripturl, '?action=admin;area=helpdesk;sa=plugins;do=save" method="post" accept-charset="UTF-8">';

	if (empty($context['plugins']))
	{
		echo '
			<div class="content">
				<p class="description">', $txt['shd_admin_plugins_none'], '</p>
			</div>';
	}
	else
	{
		foreach ($context['plugins'] as $plugin_id => $plugin)
		{
			echo '
			<div class="content">
				<div class="floatleft" style="width: 40px; text-align: center;">';

			// Enable/disable toggle (image-based).
			if (!empty($plugin['installable']))
			{
				echo '
					<a href="#" onclick="toggleItem(', JavaScriptEscape($plugin_id), '); return false;">
						<img id="plugin_img_', $plugin_id, '" src="', $settings['images_url'], '/simpledesk/', (!empty($plugin['enabled']) ? 'plugin_on' : 'plugin_off'), '.png" alt="', (!empty($plugin['enabled']) ? $txt['shd_admin_plugins_on'] : $txt['shd_admin_plugins_off']), '" />
					</a>
					<input type="hidden" name="plugin[', $plugin_id, ']" id="plugin_state_', $plugin_id, '" value="', (!empty($plugin['enabled']) ? '1' : '0'), '" />';
			}
			else
			{
				echo '
					<img src="', $settings['images_url'], '/simpledesk/plugin_off.png" alt="', $txt['shd_admin_plugins_off'], '" />';
			}

			echo '
				</div>
				<div class="floatleft" style="margin-left: 10px; width: calc(100% - 60px);">
					<strong>';

			// Title - optionally linked to admin config page.
			if (!empty($plugin['acp_url']))
			{
				echo '
						<a href="', $plugin['acp_url'], '">', $plugin['title'], '</a>';
			}
			else
			{
				echo $plugin['title'];
			}

			echo '</strong>';

			// Version display.
			if (!empty($plugin['version']))
			{
				echo '
					<span class="smalltext">(', $txt['shd_admin_plugins_version'], ' ', $plugin['version'], ')</span>';
			}

			echo '
					<br />';

			// Description.
			if (!empty($plugin['description']))
			{
				echo '
					<span class="smalltext">', $plugin['description'], '</span><br />';
			}

			// Author.
			if (!empty($plugin['author']))
			{
				echo '
					<span class="smalltext"><strong>', $txt['shd_admin_plugins_author'], ':</strong> ';

				if (!empty($plugin['author_url']))
					echo '<a href="', $plugin['author_url'], '" target="_blank" rel="noopener">', $plugin['author'], '</a>';
				else
					echo $plugin['author'];

				echo '</span><br />';
			}

			// Compatibility information.
			if (!empty($plugin['installable']))
			{
				if (!empty($plugin['compatibility']))
				{
					echo '
					<span class="smalltext"><strong>', $txt['shd_admin_plugins_compat'], ':</strong> ', $plugin['compatibility'], '</span><br />';
				}
			}
			else
			{
				// Non-installable version warning.
				echo '
					<span class="smalltext alert"><strong>', $txt['shd_admin_plugins_compat'], ':</strong> ', $txt['shd_admin_plugins_not_installable'], '</span><br />';
			}

			// Language availability icons.
			if (!empty($plugin['languages']))
			{
				echo '
					<span class="smalltext"><strong>', $txt['shd_admin_plugins_languages'], ':</strong> ';

				$lang_list = array();
				foreach ($plugin['languages'] as $lang_code => $lang_info)
				{
					if (!empty($lang_info['icon']))
						$lang_list[] = '<img src="' . $settings['images_url'] . '/simpledesk/lang/' . $lang_info['icon'] . '" alt="' . $lang_code . '" title="' . (!empty($lang_info['name']) ? $lang_info['name'] : $lang_code) . '" class="shd_icon_minihead" />';
					else
						$lang_list[] = '<span title="' . (!empty($lang_info['name']) ? $lang_info['name'] : $lang_code) . '">' . $lang_code . '</span>';
				}

				echo implode(' ', $lang_list), '</span><br />';
			}

			// No-JS fallback: radio buttons for enable/disable.
			echo '
					<noscript>
						<br />
						<label><input type="radio" name="plugin_nojs[', $plugin_id, ']" value="1"', (!empty($plugin['enabled']) ? ' checked="checked"' : ''), (!empty($plugin['installable']) ? '' : ' disabled="disabled"'), ' /> ', $txt['shd_admin_plugins_on'], '</label>
						<label><input type="radio" name="plugin_nojs[', $plugin_id, ']" value="0"', (empty($plugin['enabled']) ? ' checked="checked"' : ''), (!empty($plugin['installable']) ? '' : ' disabled="disabled"'), ' /> ', $txt['shd_admin_plugins_off'], '</label>
					</noscript>';

			echo '
				</div>
				<div class="clear"></div>
			</div>';
		}
	}

	// Submit button.
	echo '
			<div class="submitbutton">
				<input type="submit" value="', $txt['save'], '" class="button_submit" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</div>
		</form>';

	// Bottom JavaScript for initializing the plugin state manager.
	echo '
		<script>
			var shd_plugins = {};';

	if (!empty($context['plugins']))
	{
		foreach ($context['plugins'] as $plugin_id => $plugin)
		{
			if (!empty($plugin['installable']))
			{
				echo '
			shd_plugins[', JavaScriptEscape($plugin_id), '] = {
				enabled: ', (!empty($plugin['enabled']) ? 'true' : 'false'), ',
				id: ', JavaScriptEscape($plugin_id), '
			};';
			}
		}
	}

	echo '
		</script>
	</div>';
}
