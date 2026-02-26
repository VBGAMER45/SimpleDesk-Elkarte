<?php
/**
 * SimpleDesk Admin Subs
 *
 * Helper functions for SimpleDesk administration: action log loading,
 * admin log loading, attachment cleanup, config change tracking.
 *
 * Ported from SMF SimpleDesk Subs-SimpleDeskAdmin.php to ElkArte.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Load the items from the helpdesk action log.
 *
 * Subject to given parameters (start, number of items, order/sorting), parses the language
 * strings and adds the parameter information provided.
 *
 * @param int $start Number of items into the log to start (for pagination). -1 = return all.
 * @param int $items_per_page How many items to load. Default 10.
 * @param string $sort SQL clause for ordering. Default 'la.log_time'.
 * @param string $order 'DESC' or 'ASC'. Default 'DESC'.
 * @param string $clause SQL WHERE fragment to limit log items.
 * @return array Hash array of log items keyed by id_action.
 */
function shd_load_action_log_entries($start = 0, $items_per_page = 10, $sort = 'la.log_time', $order = 'DESC', $clause = '')
{
	global $txt, $scripturl, $context, $user_info, $user_profile;

	$db = database();

	// Load languages in case they aren't there (Read: ticket-specific logs)
	loadLanguage('SimpleDeskAdmin');
	loadLanguage('SimpleDeskLogAction');
	loadLanguage('SimpleDeskNotifications');

	// We may have to exclude some items depending on who the user is.
	$exclude = shd_action_log_exclusions();

	if (!empty($exclude))
	{
		if (empty($clause))
			$clause = 'la.action NOT IN ({array_string:exclude})';
		else
			$clause .= ' AND la.action NOT IN ({array_string:exclude})';
	}

	// Fetch the actions.
	$request = shd_db_query('', '
		SELECT la.id_action, la.log_time, la.ip, la.action, la.id_ticket, la.id_msg, la.extra,
		COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, {string:blank}) AS real_name, COALESCE(mg.group_name, {string:na}) AS group_name
		FROM {db_prefix}helpdesk_log_action AS la
			LEFT JOIN {db_prefix}members AS mem ON(mem.id_member = la.id_member)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_group_id} THEN mem.id_post_group ELSE mem.id_group END)
		WHERE la.id_ticket != {int:no_ticket}' . (empty($clause) ? '' : '
			AND ' . $clause) . '
		ORDER BY ' . ($sort != '' ? '{raw:sort} {raw:order}' : 'la.log_time DESC') . '
		' . ($start != -1 ? 'LIMIT {int:start}, {int:items_per_page}' : ''),
		array(
			'no_ticket' => 0,
			'reg_group_id' => 0,
			'sort' => $sort,
			'start' => $start,
			'items_per_page' => $items_per_page,
			'order' => $order,
			'na' => $txt['not_applicable'],
			'blank' => '',
			'exclude' => $exclude,
		)
	);

	$actions = array();
	$notify_members = array();
	while ($row = $db->fetch_assoc($request))
	{
		$row['extra'] = json_decode($row['extra'], true);
		$row['extra'] = is_array($row['extra']) ? $row['extra'] : array();

		// Unknown member? Check if it's automatically by the system.
		if (empty($row['id_member']))
		{
			if (isset($row['extra']['auto']) && $row['extra']['auto'] === true)
				$row['real_name'] = $txt['shd_helpdesk'];
			else
				$row['real_name'] = $txt['shd_admin_actionlog_unknown'];
		}

		$actions[$row['id_action']] = array(
			'id' => $row['id_action'],
			'time' => standardTime($row['log_time']),
			'member' => array(
				'id' => $row['id_member'],
				'name' => $row['real_name'],
				'link' => shd_profile_link($row['real_name'], $row['id_member']),
				'group' => $row['group_name'],
			),
			'action' => $row['action'],
			'id_ticket' => $row['id_ticket'],
			'id_msg' => $row['id_msg'],
			'extra' => $row['extra'],
			'action_text' => '',
			'action_icon' => 'log_' . $row['action'] . '.png',
			'can_remove' => empty($context['waittime']) ? false : ($row['log_time'] < $context['waittime']),
		);

		// Use a generic icon for custom fields changes.
		if (strpos($row['action'], 'cf_') === 0)
			$actions[$row['id_action']]['action_icon'] = 'log_cfchange.png';

		if (shd_allowed_to('shd_view_ip_any', 0) || ($row['id_member'] == $user_info['id'] && shd_allowed_to('shd_view_ip_own', 0)))
			$actions[$row['id_action']]['member']['ip'] = !empty($row['ip']) ? $row['ip'] : $txt['shd_admin_actionlog_unknown'];

		// Notifications require us to collate all the user ids.
		if ($row['action'] == 'notify' && !empty($row['extra']['emails']))
			foreach ($row['extra']['emails'] as $email_type => $recipients)
				if (!empty($recipients['u']))
					$notify_members = array_merge($notify_members, explode(',', $recipients['u']));
	}
	$db->free_result($request);

	if (!empty($notify_members))
		loadMemberData(array_unique($notify_members));

	// Format the action strings.
	foreach ($actions as $k => $action)
	{
		if (empty($actions[$k]['action_text']))
			$actions[$k]['action_text'] = isset($txt['shd_log_' . $action['action']]) ? $txt['shd_log_' . $action['action']] : $action['action'];

		$actions[$k]['action_text'] = str_replace('{scripturl}', $scripturl, $actions[$k]['action_text']);
		$actions[$k]['action_text'] = str_replace('{shd_home}', $context['shd_home'], $actions[$k]['action_text']);

		if (isset($action['extra']['subject']))
		{
			$actions[$k]['action_text'] = str_replace('{ticket}', $actions[$k]['id_ticket'], $actions[$k]['action_text']);
			$actions[$k]['action_text'] = str_replace('{msg}', $actions[$k]['id_msg'], $actions[$k]['action_text']);

			if (isset($actions[$k]['extra']['subject']))
				$actions[$k]['action_text'] = str_replace('{subject}', $actions[$k]['extra']['subject'], $actions[$k]['action_text']);

			if (isset($actions[$k]['extra']['urgency']))
				$actions[$k]['action_text'] = str_replace('{urgency}', $txt['shd_urgency_' . $actions[$k]['extra']['urgency']], $actions[$k]['action_text']);
		}

		// Notifications are complex - handle them separately.
		if ($action['action'] == 'notify' && isset($action['extra']['emails']))
		{
			$content = '';
			foreach ($action['extra']['emails'] as $email_type => $recipients)
			{
				$this_content = '<br><a href="' . $scripturl . '?action=helpdesk;sa=emaillog;log=' . $action['id'] . ';template=' . $email_type . '" onclick="return reqWin(this.href);">' . $txt['template_log_notify_' . $email_type] . '</a> - ';

				$new_content = '';
				if (!empty($recipients['u']))
				{
					$first = true;
					$users = array_map('intval', explode(',', $recipients['u']));
					$unknown_users = 0;
					foreach ($users as $user)
					{
						if (empty($user_profile[$user]))
						{
							$unknown_users++;
							continue;
						}

						$new_content .= ($first ? $txt['shd_log_notify_users'] . ': ' : ', ') . shd_profile_link($user_profile[$user]['real_name'], $user);
						$first = false;
					}
					if ($unknown_users > 0)
						$new_content .= ($first ? $txt['shd_log_notify_users'] . ': ' : ', ') . ($unknown_users == 1 ? $txt['shd_log_unknown_user_1'] : sprintf($txt['shd_log_unknown_user_n'], $unknown_users));
				}

				if (!empty($new_content))
					$content .= $this_content . $new_content;
			}
			if (!empty($content))
				$actions[$k]['action_text'] .= $txt['shd_log_notify_to'] . $content;
			continue;
		}

		if (isset($action['extra']['user_name']))
		{
			$actions[$k]['action_text'] = str_replace('{profile_link}', shd_profile_link($actions[$k]['extra']['user_name'], (isset($actions[$k]['extra']['user_id']) ? $actions[$k]['extra']['user_id'] : 0)), $actions[$k]['action_text']);
			$actions[$k]['action_text'] = str_replace('{user_name}', $actions[$k]['extra']['user_name'], $actions[$k]['action_text']);
		}
		if (isset($action['extra']['user_id']))
			$actions[$k]['action_text'] = str_replace('{user_id}', $actions[$k]['extra']['user_id'], $actions[$k]['action_text']);
		if (isset($actions[$k]['extra']['board_name']))
			$actions[$k]['action_text'] = str_replace('{board_name}', $actions[$k]['extra']['board_name'], $actions[$k]['action_text']);
		if (isset($actions[$k]['extra']['board_id']))
			$actions[$k]['action_text'] = str_replace('{board_id}', $actions[$k]['extra']['board_id'], $actions[$k]['action_text']);
		if (isset($action['extra']['othersubject']))
		{
			$actions[$k]['action_text'] = str_replace('{othersubject}', $actions[$k]['extra']['othersubject'], $actions[$k]['action_text']);
			$actions[$k]['action_text'] = str_replace('{otherticket}', $actions[$k]['extra']['otherticket'], $actions[$k]['action_text']);
		}

		if (isset($action['extra']['old_dept_id']))
		{
			$replace = array(
				'{old_dept_id}' => $action['extra']['old_dept_id'],
				'{old_dept_name}' => $action['extra']['old_dept_name'],
				'{new_dept_id}' => $action['extra']['new_dept_id'],
				'{new_dept_name}' => $action['extra']['new_dept_name'],
			);
			$actions[$k]['action_text'] = str_replace(array_keys($replace), array_values($replace), $actions[$k]['action_text']);
		}

		// Custom fields
		if (isset($action['extra']['fieldname']))
		{
			if ($action['extra']['fieldtype'] == CFIELD_TYPE_CHECKBOX)
			{
				$action['extra']['oldvalue'] = !empty($action['extra']['oldvalue']) ? $txt['yes'] : $txt['no'];
				$action['extra']['newvalue'] = !empty($action['extra']['newvalue']) ? $txt['yes'] : $txt['no'];
			}
			elseif ($action['extra']['fieldtype'] == CFIELD_TYPE_RADIO || $action['extra']['fieldtype'] == CFIELD_TYPE_SELECT || $action['extra']['fieldtype'] == CFIELD_TYPE_MULTI)
			{
				if (empty($action['extra']['oldvalue']))
					$action['extra']['oldvalue'] = $txt['shd_none_selected'];
				if (empty($action['extra']['newvalue']))
					$action['extra']['newvalue'] = $txt['shd_none_selected'];
			}
			else
			{
				if (empty($action['extra']['oldvalue']))
					$action['extra']['oldvalue'] = $txt['shd_empty_item'];
				if (empty($action['extra']['newvalue']))
					$action['extra']['newvalue'] = $txt['shd_empty_item'];
			}
			$actions[$k]['action_text'] = str_replace('{fieldname}', $action['extra']['fieldname'], $actions[$k]['action_text']);
			$actions[$k]['action_text'] = str_replace('{oldvalue}', $action['extra']['oldvalue'], $actions[$k]['action_text']);
			$actions[$k]['action_text'] = str_replace('{newvalue}', $action['extra']['newvalue'], $actions[$k]['action_text']);
		}

		// Attachments - always last.
		if (isset($action['extra']['att_added']))
			$actions[$k]['action_text'] .= ' ' . $txt['shd_logpart_att_added'] . ': ' . implode(', ', $action['extra']['att_added']);
		if (isset($action['extra']['att_removed']))
			$actions[$k]['action_text'] .= ' ' . $txt['shd_logpart_att_removed'] . ': ' . implode(', ', $action['extra']['att_removed']);
	}

	return $actions;
}

/**
 * Returns the total number of items in the helpdesk action log.
 *
 * @param string $clause SQL WHERE fragment to limit log items.
 * @return int Number of entries.
 */
function shd_count_action_log_entries($clause = '')
{
	$db = database();

	$exclude = shd_action_log_exclusions();

	if (!empty($exclude))
	{
		if (empty($clause))
			$clause = 'la.action NOT IN ({array_string:exclude})';
		else
			$clause .= ' AND la.action NOT IN ({array_string:exclude})';
	}

	$request = shd_db_query('', '
		SELECT COUNT(*)
		FROM {db_prefix}helpdesk_log_action AS la
		LEFT JOIN {db_prefix}members AS mem ON(mem.id_member = la.id_member)
		LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_group_id} THEN mem.id_post_group ELSE mem.id_group END)
		WHERE la.id_ticket != {int:no_ticket}' . (empty($clause) ? '' : '
			AND ' . $clause),
		array(
			'no_ticket' => 0,
			'reg_group_id' => 0,
			'exclude' => $exclude,
		)
	);

	list ($entry_count) = $db->fetch_row($request);
	$db->free_result($request);

	return $entry_count;
}

/**
 * Determines which action log entries should be excluded based on user permissions.
 *
 * @return array Array of action names to exclude.
 */
function shd_action_log_exclusions()
{
	global $user_info;

	$exclude = array();

	if (!$user_info['is_admin'] && !shd_allowed_to('admin_helpdesk', 0))
	{
		// Custom field changes only available to admins.
		$exclude = array('cf_tktchange_admin', 'cf_rplchange_admin', 'cf_tktchgdef_admin', 'cf_rplchgdef_admin');

		// Staff only things
		if (!shd_allowed_to('shd_staff', 0))
		{
			$exclude[] = 'cf_tktchange_staffadmin';
			$exclude[] = 'cf_rplchange_staffadmin';
			$exclude[] = 'cf_tktchgdef_staffadmin';
			$exclude[] = 'cf_rplchgdef_staffadmin';
		}
		else
		{
			// User only things (that staff can't see)
			$exclude[] = 'cf_tktchange_useradmin';
			$exclude[] = 'cf_rplchange_useradmin';
			$exclude[] = 'cf_tktchgdef_useradmin';
			$exclude[] = 'cf_rplchgdef_useradmin';
		}

		// Can they see multiple departments? If not, exclude dept move notices.
		$dept = shd_allowed_to('access_helpdesk', false);
		if (!is_bool($dept) && count($dept) == 1)
			$exclude[] = 'move_dept';
	}

	return $exclude;
}

/**
 * Removes helpdesk attachment references when attachments are deleted.
 *
 * @param array $attach Array of attachment IDs being removed.
 */
function shd_remove_attachments($attach)
{
	$db = database();

	if (!empty($attach))
		$db->query('', '
			DELETE FROM {db_prefix}helpdesk_attachments
			WHERE id_attach IN ({array_int:attachment_list})',
			array(
				'attachment_list' => $attach,
			)
		);
}

/**
 * Converts helpdesk body column type to match forum setting.
 *
 * @param string $body_type 'text' or other (for mediumtext).
 */
function shd_convert_msgbody($body_type = 'null')
{
	$db_table = db_table();

	if ($body_type == 'text')
		$db_table->db_change_column('{db_prefix}helpdesk_ticket_replies', 'body', array('type' => 'text'));
	else
		$db_table->db_change_column('{db_prefix}helpdesk_ticket_replies', 'body', array('type' => 'mediumtext'));
}

/**
 * Tracks a change to helpdesk options/config vars for admin logging.
 *
 * @param array $save_vars The config vars array passed before updateSettings.
 */
function shd_admin_log_configvar($save_vars)
{
	global $modSettings;

	foreach ($save_vars as $var)
	{
		if (!isset($var[1]) || (!isset($_POST[$var[1]]) && $var[0] != 'check' && $var[0] != 'permissions' && ($var[0] != 'bbc' || !isset($_POST[$var[1] . '_enabledTags']))))
			continue;

		// Fix some data for proper testing.
		$newValue = isset($_POST[$var[1]]) ? $_POST[$var[1]] : null;

		// Checks are either on or off.
		if ($var[0] == 'check')
			$newValue = !empty($_POST[$var[1]]) ? '1' : '0';

		// Skip it if nothing was changed.
		if (isset($modSettings[$var[1]]) && $modSettings[$var[1]] == $newValue)
			continue;
		// Or if nothing exists.
		elseif (!isset($modSettings[$var[1]]) && empty($newValue))
			continue;

		// Log this.
		shd_admin_log('admin_change_option', array(
			'action' => 'update',
			'setting' => $var[1],
			'type' => $var[0],
			'from' => isset($modSettings[$var[1]]) ? $modSettings[$var[1]] : null,
			'to' => $newValue,
		));
	}
}

/**
 * Logs an action in the helpdesk admin log.
 *
 * @param string $action The area this was from.
 * @param array $extra Extra elements for the log entry.
 */
function shd_admin_log($action, $extra)
{
	global $user_info;

	$db = database();

	$db->insert('',
		'{db_prefix}helpdesk_log_action',
		array('log_time' => 'int', 'id_member' => 'int', 'ip' => 'string-16', 'action' => 'string', 'id_ticket' => 'int', 'id_msg' => 'int', 'extra' => 'string-65534'),
		array(time(), $user_info['id'], $user_info['ip'], $action, 0, 0, json_encode($extra)),
		array('id_action')
	);
}

/**
 * Load the items from the helpdesk admin log (id_ticket = 0 entries).
 *
 * @param int $start Pagination start. -1 = return all.
 * @param int $items_per_page Items per page. Default 10.
 * @param string $sort SQL order column. Default 'la.log_time'.
 * @param string $order 'DESC' or 'ASC'. Default 'DESC'.
 * @return array Hash array of admin log items keyed by id_action.
 */
function shd_load_admin_log_entries($start = 0, $items_per_page = 10, $sort = 'la.log_time', $order = 'DESC')
{
	global $txt, $scripturl, $context, $user_info, $user_profile;

	$db = database();

	// Load languages in case they aren't there.
	loadLanguage('SimpleDeskAdmin');
	loadLanguage('SimpleDeskLogAction');
	loadLanguage('SimpleDeskNotifications');

	$request = shd_db_query('', '
		SELECT la.id_action, la.log_time, la.ip, la.action, la.extra,
		COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, {string:blank}) AS real_name, COALESCE(mg.group_name, {string:na}) AS group_name
		FROM {db_prefix}helpdesk_log_action AS la
			LEFT JOIN {db_prefix}members AS mem ON(mem.id_member = la.id_member)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_group_id} THEN mem.id_post_group ELSE mem.id_group END)
		WHERE la.id_ticket = {int:no_ticket}
		ORDER BY ' . ($sort != '' ? '{raw:sort} {raw:order}' : 'la.log_time DESC') . '
		' . ($start != -1 ? 'LIMIT {int:start}, {int:items_per_page}' : ''),
		array(
			'no_ticket' => 0,
			'reg_group_id' => 0,
			'sort' => $sort,
			'start' => $start,
			'items_per_page' => $items_per_page,
			'order' => $order,
			'na' => $txt['not_applicable'],
			'blank' => '',
		)
	);

	$actions = array();
	$ids = array(
		'canned_cat' => array(),
		'depts' => array(),
		'canned_reply' => array(),
		'custom_field' => array(),
		'members' => array(),
		'permissions' => array(),
	);

	while ($row = $db->fetch_assoc($request))
	{
		$row['extra'] = json_decode($row['extra'], true);
		$row['extra'] = is_array($row['extra']) ? $row['extra'] : array();

		// Unknown member? Check if it's automatically by the system.
		if (empty($row['id_member']))
		{
			if (isset($row['extra']['auto']) && $row['extra']['auto'] === true)
				$row['real_name'] = $txt['shd_helpdesk'];
			else
				$row['real_name'] = $txt['shd_admin_actionlog_unknown'];
		}

		$actions[$row['id_action']] = array(
			'id' => $row['id_action'],
			'time' => standardTime($row['log_time']),
			'member' => array(
				'id' => $row['id_member'],
				'name' => $row['real_name'],
				'link' => shd_profile_link($row['real_name'], $row['id_member']),
				'group' => $row['group_name'],
				'ip' => !empty($row['ip']) ? $row['ip'] : $txt['shd_admin_actionlog_unknown'],
			),
			'action' => $row['action'],
			'type' => isset($row['extra']['type']) ? $row['extra']['type'] : '',
			'extra' => $row['extra'],
			'action_text' => '',
			'can_remove' => empty($context['waittime']) ? false : ($row['log_time'] < $context['waittime']),
		);

		// Collect IDs for later name lookups.
		if ($row['action'] == 'admin_canned' && !empty($row['extra']['id']) && isset($row['extra']['type']) && in_array($row['extra']['type'], array('cat_move', 'cat_delete', 'cat_add', 'cat_update')))
		{
			if (!isset($ids['canned_cat'][$row['extra']['id']]))
				$ids['canned_cat'][$row['extra']['id']] = array();
			$ids['canned_cat'][$row['extra']['id']][$row['id_action']] = 'id';
		}
		elseif ($row['action'] == 'admin_canned' && !empty($row['extra']['id']) && isset($row['extra']['type']) && in_array($row['extra']['type'], array('reply_move', 'reply_delete', 'reply_add', 'reply_update')))
		{
			if (!isset($ids['canned_reply'][$row['extra']['id']]))
				$ids['canned_reply'][$row['extra']['id']] = array();
			$ids['canned_reply'][$row['extra']['id']][$row['id_action']] = 'id';
		}
		elseif ($row['action'] == 'admin_dept')
		{
			if (!isset($ids['depts'][$row['extra']['id']]))
				$ids['depts'][$row['extra']['id']] = array();
			$ids['depts'][$row['extra']['id']][$row['id_action']] = 'id';
		}
		elseif ($row['action'] == 'admin_customfield')
		{
			if (!isset($row['extra']['id']))
				continue;

			if (!isset($ids['custom_field'][$row['extra']['id']]))
				$ids['custom_field'][$row['extra']['id']] = array();
			$ids['custom_field'][$row['extra']['id']][$row['id_action']] = 'id';
		}
		elseif ($row['action'] == 'admin_maint')
		{
			if (isset($row['extra']['action']) && $row['extra']['action'] == 'move_dept')
			{
				if (!empty($row['extra']['to']))
				{
					if (!isset($ids['depts'][$row['extra']['to']]))
						$ids['depts'][$row['extra']['to']] = array();
					$ids['depts'][$row['extra']['to']][$row['id_action']] = 'to';
				}
				if (!empty($row['extra']['from']))
				{
					if (!isset($ids['depts'][$row['extra']['from']]))
						$ids['depts'][$row['extra']['from']] = array();
					$ids['depts'][$row['extra']['from']][$row['id_action']] = 'from';
				}
			}
			else
			{
				if (!empty($row['extra']['to']))
				{
					if (!isset($ids['members'][$row['extra']['to']]))
						$ids['members'][$row['extra']['to']] = array();
					$ids['members'][$row['extra']['to']][$row['id_action']] = 'to';
				}
				if (!empty($row['extra']['from']))
				{
					if (!isset($ids['members'][$row['extra']['from']]))
						$ids['members'][$row['extra']['from']] = array();
					$ids['members'][$row['extra']['from']][$row['id_action']] = 'from';
				}
			}
		}
		elseif ($row['action'] == 'admin_permissions')
		{
			if (!empty($row['extra']['id']))
			{
				if (!isset($ids['permissions'][$row['extra']['id']]))
					$ids['permissions'][$row['extra']['id']] = array();
				$ids['permissions'][$row['extra']['id']][$row['id_action']] = 'id';
			}
			if (!empty($row['extra']['to']))
			{
				if (!isset($ids['permissions'][$row['extra']['to']]))
					$ids['permissions'][$row['extra']['to']] = array();
				$ids['permissions'][$row['extra']['to']][$row['id_action']] = 'to';
			}
			if (!empty($row['extra']['from']))
			{
				if (!isset($ids['permissions'][$row['extra']['from']]))
					$ids['permissions'][$row['extra']['from']] = array();
				$ids['permissions'][$row['extra']['from']][$row['id_action']] = 'from';
			}
		}
	}
	$db->free_result($request);

	// Now look up names for collected IDs.
	if (!empty($ids['canned_cat']))
	{
		$request = shd_db_query('', '
			SELECT id_cat AS id, cat_name AS name
			FROM {db_prefix}helpdesk_cannedreplies_cats
			WHERE id_cat IN ({array_int:cats})',
			array(
				'cats' => array_keys($ids['canned_cat']),
			)
		);
		while ($row = $db->fetch_assoc($request))
			foreach ($ids['canned_cat'][$row['id']] as $id_action => $type)
				if ($type == 'id')
					$actions[$id_action]['id_name'] = $row['name'];
		$db->free_result($request);
	}

	if (!empty($ids['canned_reply']))
	{
		$request = shd_db_query('', '
			SELECT id_reply AS id, title AS name
			FROM {db_prefix}helpdesk_cannedreplies
			WHERE id_reply IN ({array_int:replys})',
			array(
				'replys' => array_keys($ids['canned_reply']),
			)
		);
		while ($row = $db->fetch_assoc($request))
			foreach ($ids['canned_reply'][$row['id']] as $id_action => $type)
				if ($type == 'id')
					$actions[$id_action]['id_name'] = $row['name'];
		$db->free_result($request);
	}

	if (!empty($ids['custom_field']))
	{
		$request = shd_db_query('', '
			SELECT id_field AS id, field_name AS name
			FROM {db_prefix}helpdesk_custom_fields
			WHERE id_field IN ({array_int:custom_fields})',
			array(
				'custom_fields' => array_keys($ids['custom_field']),
			)
		);
		while ($row = $db->fetch_assoc($request))
			foreach ($ids['custom_field'][$row['id']] as $id_action => $type)
				if ($type == 'id')
					$actions[$id_action]['id_name'] = $row['name'];
		$db->free_result($request);
	}

	if (!empty($ids['depts']))
	{
		$request = shd_db_query('', '
			SELECT id_dept AS id, dept_name AS name
			FROM {db_prefix}helpdesk_depts
			WHERE id_dept IN ({array_int:depts})',
			array(
				'depts' => array_keys($ids['depts']),
			)
		);
		while ($row = $db->fetch_assoc($request))
			foreach ($ids['depts'][$row['id']] as $id_action => $type)
				$actions[$id_action][$type == 'id' ? 'id_name' : ($type == 'to' ? 'to_name' : 'from_name')] = $row['name'];
		$db->free_result($request);
	}

	if (!empty($ids['members']))
	{
		$request = shd_db_query('', '
			SELECT id_member AS id, IFNULL(real_name, {string:blank}) AS name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:members})',
			array(
				'members' => array_keys($ids['members']),
				'blank' => '',
			)
		);
		while ($row = $db->fetch_assoc($request))
			if (isset($ids['members'][$row['id']]))
				foreach ($ids['members'][$row['id']] as $id_action => $type)
					$actions[$id_action][$type == 'to' ? 'to_name' : 'from_name'] = $row['name'];
		$db->free_result($request);
	}

	if (!empty($ids['permissions']))
	{
		$request = shd_db_query('', '
			SELECT id_role AS id, role_name AS name
			FROM {db_prefix}helpdesk_roles
			WHERE id_role IN ({array_int:roles})',
			array(
				'roles' => array_keys($ids['permissions']),
				'blank' => '',
			)
		);
		while ($row = $db->fetch_assoc($request))
			foreach ($ids['permissions'][$row['id']] as $id_action => $type)
				$actions[$id_action][$type == 'id' ? 'id_name' : ($type == 'to' ? 'to_name' : 'from_name')] = $row['name'];
		$db->free_result($request);
	}

	// Format the action strings.
	foreach ($actions as $k => $action)
	{
		if (empty($actions[$k]['action_text']))
			$actions[$k]['action_text'] = isset($txt['shd_log_' . $action['action']]) ? $txt['shd_log_' . $action['action']] : $action['action'];

		if (!empty($action['extra']['setting']) && isset($txt[$action['extra']['setting']]))
			$actions[$k]['action_text'] .= '<br>' . $txt['shd_admin_adminlog_setting'] . ': <span title="' . $action['extra']['setting'] . '">' . $txt[$action['extra']['setting']] . '</span>';
		elseif (!empty($action['extra']['setting']))
			$actions[$k]['action_text'] .= '<br>' . $txt['shd_admin_adminlog_setting'] . ': ' . $action['extra']['setting'];
		elseif (!empty($action['type']) && isset($txt['shd_log_' . $action['action'] . '_' . $action['type']]))
			$actions[$k]['action_text'] .= '<br>' . $txt['shd_admin_adminlog_action'] . ': ' . $txt['shd_log_' . $action['action'] . '_' . $action['type']];
		elseif (!empty($action['type']))
			$actions[$k]['action_text'] .= '<br>' . $txt['shd_admin_adminlog_action'] . ': ' . $action['type'];
		elseif (!empty($action['extra']['action']) && isset($txt['shd_log_' . $action['action'] . '_' . $action['extra']['action']]))
			$actions[$k]['action_text'] .= '<br>' . $txt['shd_admin_adminlog_action'] . ': ' . $txt['shd_log_' . $action['action'] . '_' . $action['extra']['action']];
		elseif (!empty($action['extra']['action']))
			$actions[$k]['action_text'] .= '<br>' . $txt['shd_admin_adminlog_action'] . ': shd_log_' . $action['action'] . '_' . $action['extra']['action'];

		if (isset($action['id_name']))
			$actions[$k]['action_text'] .= '<br>' . $txt['shd_admin_adminlog_name'] . ': <span title="' . $action['extra']['id'] . '">' . $action['id_name'] . '</span>';
		elseif (isset($action['extra']['id']))
			$actions[$k]['action_text'] .= '<br>' . $txt['shd_admin_adminlog_name'] . ': ' . $action['extra']['id'];

		if (isset($action['from_name']))
			$actions[$k]['action_text'] .= '<br>' . $txt['shd_admin_adminlog_from'] . ': <span title="' . $action['extra']['from'] . '">' . $action['from_name'] . '</span>';
		elseif (isset($action['extra']['from']) && $action['type'] == 'check')
			$actions[$k]['action_text'] .= '<br>' . $txt['shd_admin_adminlog_from'] . ': <span title="' . $action['extra']['from'] . '">' . (empty($action['extra']['from']) ? $txt['shd_admin_default_state_off'] : $txt['shd_admin_default_state_on']) . '</span>';
		elseif (isset($action['extra']['from']))
			$actions[$k]['action_text'] .= '<br>' . $txt['shd_admin_adminlog_from'] . ': ' . $action['extra']['from'];

		if (isset($action['to_name']))
			$actions[$k]['action_text'] .= '<br>' . $txt['shd_admin_adminlog_to'] . ': <span title="' . $action['extra']['to'] . '">' . $action['to_name'] . '</span>';
		elseif (isset($action['extra']['to']) && $action['type'] == 'check')
			$actions[$k]['action_text'] .= '<br>' . $txt['shd_admin_adminlog_to'] . ': <span title="' . $action['extra']['to'] . '">' . (empty($action['extra']['to']) ? $txt['shd_admin_default_state_off'] : $txt['shd_admin_default_state_on']) . '</span>';
		elseif (isset($action['extra']['to']))
			$actions[$k]['action_text'] .= '<br>' . $txt['shd_admin_adminlog_to'] . ': ' . $action['extra']['to'];
	}

	return $actions;
}

/**
 * Returns the total number of items in the helpdesk admin log.
 *
 * @return int Number of entries in the admin log (id_ticket = 0).
 */
function shd_count_admin_log_entries()
{
	$db = database();

	$request = shd_db_query('', '
		SELECT COUNT(*)
		FROM {db_prefix}helpdesk_log_action AS la
		WHERE id_ticket = {int:no_ticket}',
		array(
			'no_ticket' => 0,
		)
	);

	list ($entry_count) = $db->fetch_row($request);
	$db->free_result($request);

	return $entry_count;
}
