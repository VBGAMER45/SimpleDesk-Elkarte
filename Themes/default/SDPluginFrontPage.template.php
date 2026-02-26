<?php
/**
 * SimpleDesk Front Page Plugin - Templates
 *
 * Templates for the front page display and admin configuration.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

/**
 * Display the replacement front page content.
 */
function template_shd_frontpage()
{
	global $context, $txt, $settings, $scripturl;

	echo '
		<div class="shd_navigation clearfix">';

	if (!empty($context['navigation']))
		template_shd_button_strip($context['navigation'], 'bottom');

	echo '
		</div>
		<div id="shd_frontpage_content" class="content">
			', $context['shdp_frontpage_content'], '
		</div>';
}

/**
 * Display the admin configuration page for the front page plugin.
 */
function template_shd_frontpage_admin()
{
	global $context, $settings, $txt, $modSettings, $scripturl;

	if (empty($modSettings['shdp_frontpage_type']))
		$modSettings['shdp_frontpage_type'] = 'bbcode';
	if (empty($modSettings['shdp_frontpage_appear']))
		$modSettings['shdp_frontpage_appear'] = 'firstdefault';

	echo '
	<div id="admincenter">
		<form name="adminform" action="', $context['post_url'], '" method="post" accept-charset="UTF-8">
			<h2 class="category_header">
				', $txt['shdp_frontpage'], '
			</h2>
			<div class="windowbg">
				<div class="content">
					<dl class="settings">
						<dt>
							<label for="shdp_frontpage_appear">', $txt['shdp_frontpage_appear'], '</label>
						</dt>
						<dd>
							<select name="shdp_frontpage_appear" id="shdp_frontpage_appear">
								<option value="always"', $modSettings['shdp_frontpage_appear'] == 'always' ? ' selected="selected"' : '', '>', $txt['shdp_frontpage_appear_always'], '</option>
								<option value="firstload"', $modSettings['shdp_frontpage_appear'] == 'firstload' ? ' selected="selected"' : '', '>', $txt['shdp_frontpage_appear_firstload'], '</option>
								<option value="firstdefault"', $modSettings['shdp_frontpage_appear'] == 'firstdefault' ? ' selected="selected"' : '', '>', $txt['shdp_frontpage_appear_firstdefault'], '</option>
							</select>
						</dd>
					</dl>
					<hr />
					<dl class="settings">
						<dt>
							<label for="shdp_frontpage_type">', $txt['shdp_frontpage_type'], '</label>
						</dt>
						<dd>
							<select name="shdp_frontpage_type" id="shdp_frontpage_type" onchange="invertBBC();">
								<option value="bbcode"', ($modSettings['shdp_frontpage_type'] == 'bbcode' ? ' selected="selected"' : ''), '>', $txt['shdp_frontpage_type_bbcode'], '</option>
								<option value="php"', ($modSettings['shdp_frontpage_type'] == 'php' ? ' selected="selected"' : ''), '>', $txt['shdp_frontpage_type_php'], '</option>
							</select>
						</dd>
						<dt>
							<label for="shdp_frontpage_content">', $txt['shdp_frontpage_content'], '</label>
						</dt>
						<dd>';

	// The editor
	echo '
							<div id="shd_bbcbox"', ($modSettings['shdp_frontpage_type'] == 'php' ? ' style="display:none;"' : ''), '></div>
							<div id="shd_smileybox"', ($modSettings['shdp_frontpage_type'] == 'php' ? ' style="display:none;"' : ''), '></div>';

	template_control_richedit($context['post_box_name'], true, true);

	echo '
						</dd>
					</dl>
					<hr />
					<div class="submitbutton">
						<input type="submit" value="', $txt['save'], '" class="button_submit" />
					</div>
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>
	</div>
	<script>
		function invertBBC()
		{
			var state = document.getElementById("shdp_frontpage_type").value == "bbcode";
			document.getElementById("shd_bbcbox").style.display = state ? "" : "none";
			document.getElementById("shd_smileybox").style.display = state ? "" : "none";
		}
	</script>';
}
