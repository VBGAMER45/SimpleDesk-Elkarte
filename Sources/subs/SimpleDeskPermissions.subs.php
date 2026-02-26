<?php
/**
 * SimpleDesk Helpdesk - Permission Subsystem
 *
 * Handles the role-based permission system: role templates, loading
 * user permissions, and permission checking functions.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Loads the master list of permissions and their categories.
 */
function shd_load_all_permission_sets()
{
	global $context, $modSettings;

	$context['shd_permissions']['group_display'] = array(
		array(
			'general' => 'status.png',
			'posting' => 'log_newticket.png',
			'ticketactions' => 'assign.png',
			'deletion' => 'log_delete.png',
			'attachments' => 'attachments.png',
		),
		array(
			'email' => 'log_notify.png',
			'profile' => 'profile.png',
			'relationships' => 'relationships.png',
			'moderation' => 'modification.png',
		),
	);

	$context['shd_permissions']['permission_list'] = array(
		'access_helpdesk' => array(false, 'general', ''),
		'shd_staff' => array(false, 'general', ''),
		'admin_helpdesk' => array(false, 'general', ''),
		'shd_view_ticket' => array(true, 'general', 'ticket.png'),
		'shd_view_ticket_private' => array(true, 'general', 'ticket_private.png'),
		'shd_view_closed' => array(true, 'general', 'log_resolve.png'),
		'shd_view_ip' => array(true, 'general', 'ip.png'),
		'shd_search' => array(false, 'general', 'search.png'),

		'shd_new_ticket' => array(false, 'posting', 'log_newticket.png'),
		'shd_edit_ticket' => array(true, 'posting', 'log_editticket.png'),
		'shd_reply_ticket' => array(true, 'posting', 'log_newreply.png'),
		'shd_edit_reply' => array(true, 'posting', 'log_editreply.png'),
		'shd_post_proxy' => array(false, 'posting', 'proxy.png'),
		'shd_override_cf' => array(false, 'posting', 'custom_fields.png'),

		'shd_resolve_ticket' => array(true, 'ticketactions', 'log_resolve.png'),
		'shd_unresolve_ticket' => array(true, 'ticketactions', 'log_unresolve.png'),
		'shd_view_ticket_logs' => array(true, 'ticketactions', 'log.png'),
		'shd_alter_urgency' => array(true, 'ticketactions', 'urgency.png'),
		'shd_alter_urgency_higher' => array(true, 'ticketactions', 'log_urgency_increase.png'),
		'shd_alter_privacy' => array(true, 'ticketactions', 'log_markprivate.png'),
		'shd_assign_ticket' => array(true, 'ticketactions', 'log_assign.png'),
		'shd_alter_hold' => array(false, 'ticketactions', 'ticket_hold.png'),

		'shd_access_recyclebin' => array(false, 'deletion', 'access_recyclebin.png'),
		'shd_delete_ticket' => array(true, 'deletion', 'log_delete.png'),
		'shd_delete_reply' => array(true, 'deletion', 'log_delete_reply.png'),
		'shd_restore_ticket' => array(true, 'deletion', 'log_restore.png'),
		'shd_restore_reply' => array(true, 'deletion', 'log_restore_reply.png'),
		'shd_delete_recycling' => array(false, 'deletion', 'log_permadelete.png'),

		'shd_view_attachment' => array(false, 'attachments', 'attachments.png'),
		'shd_post_attachment' => array(false, 'attachments', 'attachments_add.png'),
		'shd_delete_attachment' => array(false, 'attachments', 'attachments_delete.png'),

		'shd_monitor_ticket' => array(true, 'email', 'log_notify.png'),
		'shd_ignore_ticket' => array(true, 'email', 'perm_deny.png'),
		'shd_singleton_email' => array(false, 'email', 'live.png'),
		'shd_silent_update' => array(false, 'email', 'silent_update.png'),

		'shd_view_profile' => array(true, 'profile', 'profile.png'),
		'shd_view_profile_log' => array(true, 'profile', 'log.png'),
		'shd_view_preferences' => array(true, 'profile', 'preferences.png'),

		'shd_view_relationships' => array(false, 'relationships', 'log_rel_linked.png'),
		'shd_create_relationships' => array(false, 'relationships', 'new_relationship.png'),
		'shd_delete_relationships' => array(false, 'relationships', 'log_rel_delete.png'),

		'shd_move_dept' => array(true, 'moderation', 'log_move_dept.png'),
	);

	if (!empty($modSettings['shd_disable_tickettotopic']))
	{
		unset(
			$context['shd_permissions']['permission_list']['shd_ticket_to_topic'],
			$context['shd_permissions']['permission_list']['shd_topic_to_ticket']
		);
	}

	shd_load_role_templates();
}

/**
 * Defines the default role templates (User, Staff, Admin).
 */
function shd_load_role_templates()
{
	global $context;

	$context['shd_permissions']['roles'] = array(
		ROLE_USER => array(
			'description' => 'shd_permrole_user',
			'icon' => 'user.png',
			'permissions' => array(
				'access_helpdesk' => ROLEPERM_ALLOW,
				'shd_view_ticket_own' => ROLEPERM_ALLOW,
				'shd_view_ticket_private_own' => ROLEPERM_ALLOW,
				'shd_view_closed_own' => ROLEPERM_ALLOW,
				'shd_new_ticket' => ROLEPERM_ALLOW,
				'shd_edit_ticket_own' => ROLEPERM_ALLOW,
				'shd_reply_ticket_own' => ROLEPERM_ALLOW,
				'shd_edit_reply_own' => ROLEPERM_ALLOW,
				'shd_view_attachment' => ROLEPERM_ALLOW,
				'shd_post_attachment' => ROLEPERM_ALLOW,
				'shd_resolve_ticket_own' => ROLEPERM_ALLOW,
				'shd_unresolve_ticket_own' => ROLEPERM_ALLOW,
				'shd_view_ticket_logs_own' => ROLEPERM_ALLOW,
				'shd_view_profile_own' => ROLEPERM_ALLOW,
				'shd_view_profile_log_own' => ROLEPERM_ALLOW,
				'shd_view_preferences_own' => ROLEPERM_ALLOW,
				'shd_view_relationships' => ROLEPERM_ALLOW,
				'shd_delete_ticket_own' => ROLEPERM_ALLOW,
				'shd_delete_reply_own' => ROLEPERM_ALLOW,
			),
		),
		ROLE_STAFF => array(
			'description' => 'shd_permrole_staff',
			'icon' => 'staff.png',
			'permissions' => array(
				'access_helpdesk' => ROLEPERM_ALLOW,
				'shd_staff' => ROLEPERM_ALLOW,
				'shd_view_ticket_any' => ROLEPERM_ALLOW,
				'shd_view_ticket_private_any' => ROLEPERM_ALLOW,
				'shd_view_closed_any' => ROLEPERM_ALLOW,
				'shd_view_ip_own' => ROLEPERM_ALLOW,
				'shd_search' => ROLEPERM_ALLOW,
				'shd_new_ticket' => ROLEPERM_ALLOW,
				'shd_edit_ticket_any' => ROLEPERM_ALLOW,
				'shd_reply_ticket_any' => ROLEPERM_ALLOW,
				'shd_edit_reply_any' => ROLEPERM_ALLOW,
				'shd_post_proxy' => ROLEPERM_ALLOW,
				'shd_monitor_ticket_any' => ROLEPERM_ALLOW,
				'shd_singleton_email' => ROLEPERM_ALLOW,
				'shd_ignore_ticket_own' => ROLEPERM_ALLOW,
				'shd_silent_update' => ROLEPERM_ALLOW,
				'shd_view_attachment' => ROLEPERM_ALLOW,
				'shd_post_attachment' => ROLEPERM_ALLOW,
				'shd_resolve_ticket_any' => ROLEPERM_ALLOW,
				'shd_unresolve_ticket_any' => ROLEPERM_ALLOW,
				'shd_view_ticket_logs_any' => ROLEPERM_ALLOW,
				'shd_alter_urgency_any' => ROLEPERM_ALLOW,
				'shd_alter_privacy_any' => ROLEPERM_ALLOW,
				'shd_assign_ticket_own' => ROLEPERM_ALLOW,
				'shd_alter_hold' => ROLEPERM_ALLOW,
				'shd_view_profile_any' => ROLEPERM_ALLOW,
				'shd_view_profile_log_any' => ROLEPERM_ALLOW,
				'shd_view_preferences_own' => ROLEPERM_ALLOW,
				'shd_view_relationships' => ROLEPERM_ALLOW,
				'shd_create_relationships' => ROLEPERM_ALLOW,
				'shd_delete_relationships' => ROLEPERM_ALLOW,
				'shd_access_recyclebin' => ROLEPERM_ALLOW,
				'shd_delete_ticket_any' => ROLEPERM_ALLOW,
				'shd_delete_reply_any' => ROLEPERM_ALLOW,
				'shd_restore_ticket_any' => ROLEPERM_ALLOW,
				'shd_restore_reply_any' => ROLEPERM_ALLOW,
				'shd_move_dept_own' => ROLEPERM_ALLOW,
			),
		),
		ROLE_ADMIN => array(
			'description' => 'shd_permrole_admin',
			'icon' => 'admin.png',
			'permissions' => array(
				'access_helpdesk' => ROLEPERM_ALLOW,
				'shd_staff' => ROLEPERM_ALLOW,
				'admin_helpdesk' => ROLEPERM_ALLOW,
				'shd_view_ticket_any' => ROLEPERM_ALLOW,
				'shd_view_ticket_private_any' => ROLEPERM_ALLOW,
				'shd_view_closed_any' => ROLEPERM_ALLOW,
				'shd_view_ip_any' => ROLEPERM_ALLOW,
				'shd_search' => ROLEPERM_ALLOW,
				'shd_new_ticket' => ROLEPERM_ALLOW,
				'shd_edit_ticket_any' => ROLEPERM_ALLOW,
				'shd_reply_ticket_any' => ROLEPERM_ALLOW,
				'shd_edit_reply_any' => ROLEPERM_ALLOW,
				'shd_post_proxy' => ROLEPERM_ALLOW,
				'shd_override_cf' => ROLEPERM_ALLOW,
				'shd_monitor_ticket_any' => ROLEPERM_ALLOW,
				'shd_singleton_email' => ROLEPERM_ALLOW,
				'shd_ignore_ticket_any' => ROLEPERM_ALLOW,
				'shd_silent_update' => ROLEPERM_ALLOW,
				'shd_view_attachment' => ROLEPERM_ALLOW,
				'shd_post_attachment' => ROLEPERM_ALLOW,
				'shd_delete_attachment' => ROLEPERM_ALLOW,
				'shd_resolve_ticket_any' => ROLEPERM_ALLOW,
				'shd_unresolve_ticket_any' => ROLEPERM_ALLOW,
				'shd_view_ticket_logs_any' => ROLEPERM_ALLOW,
				'shd_alter_urgency_any' => ROLEPERM_ALLOW,
				'shd_alter_urgency_higher_any' => ROLEPERM_ALLOW,
				'shd_alter_privacy_any' => ROLEPERM_ALLOW,
				'shd_assign_ticket_any' => ROLEPERM_ALLOW,
				'shd_view_profile_any' => ROLEPERM_ALLOW,
				'shd_view_profile_log_any' => ROLEPERM_ALLOW,
				'shd_view_preferences_any' => ROLEPERM_ALLOW,
				'shd_view_relationships' => ROLEPERM_ALLOW,
				'shd_create_relationships' => ROLEPERM_ALLOW,
				'shd_delete_relationships' => ROLEPERM_ALLOW,
				'shd_access_recyclebin' => ROLEPERM_ALLOW,
				'shd_delete_ticket_any' => ROLEPERM_ALLOW,
				'shd_delete_reply_any' => ROLEPERM_ALLOW,
				'shd_restore_ticket_any' => ROLEPERM_ALLOW,
				'shd_restore_reply_any' => ROLEPERM_ALLOW,
				'shd_move_dept_any' => ROLEPERM_ALLOW,
			),
		),
	);
}

/**
 * Loads the current user's SimpleDesk permissions based on their group roles.
 * Populates $user_info['shd_permissions'] and $user_info['query_see_ticket'].
 */
function shd_load_user_perms()
{
	global $user_info, $context, $modSettings;

	$db = database();

	if (!empty($user_info['query_see_ticket']))
		return;

	shd_load_role_templates();

	// Guests have no helpdesk permissions
	if (!empty($user_info['is_guest']))
	{
		$user_info['shd_permissions'] = array();
		$user_info['query_see_ticket'] = '1=0';
		return;
	}
	elseif (empty($user_info['is_admin']))
	{
		$permissions_cache = 'shd_permissions_' . implode('-', $user_info['groups']);
		$perm_cache_time = 300;
		$temp = cache_get_data($permissions_cache, $perm_cache_time);

		if ($temp === null || (time() - $perm_cache_time > $modSettings['settings_updated']))
		{
			$role_permissions = array();

			// 1. Get all roles for this user's groups
			$request = $db->query('', '
				SELECT hdrg.id_role, hdr.template
				FROM {db_prefix}helpdesk_role_groups AS hdrg
					INNER JOIN {db_prefix}helpdesk_roles AS hdr ON (hdrg.id_role = hdr.id_role)
				WHERE hdrg.id_group IN ({array_int:groups})',
				array(
					'groups' => $user_info['groups'],
				)
			);
			$roles = array();
			while ($row = $db->fetch_assoc($request))
			{
				if (isset($context['shd_permissions']['roles'][$row['template']]))
					$role_permissions[$row['id_role']] = $context['shd_permissions']['roles'][$row['template']]['permissions'];
				$roles[$row['id_role']] = true;
			}
			$db->free_result($request);

			// 1a. Get departments for these roles
			$depts = array();
			if (!empty($roles))
			{
				$request = $db->query('', '
					SELECT id_role, id_dept
					FROM {db_prefix}helpdesk_dept_roles
					WHERE id_role IN ({array_int:roles})',
					array(
						'roles' => array_keys($roles),
					)
				);
				while ($row = $db->fetch_assoc($request))
					$depts[$row['id_role']][] = $row['id_dept'];
				$db->free_result($request);
			}

			$denied = array();

			// 2.1. Apply role-specific overrides
			if (!empty($depts))
			{
				$request = $db->query('', '
					SELECT id_role, permission, add_type
					FROM {db_prefix}helpdesk_role_permissions
					WHERE id_role IN ({array_int:roles})',
					array(
						'roles' => array_keys($roles),
					)
				);
				while ($row = $db->fetch_assoc($request))
				{
					if ($row['add_type'] == ROLEPERM_DENY)
						$denied[$row['permission']] = true;
					else
						$role_permissions[$row['id_role']][$row['permission']] = $row['add_type'];
				}
				$db->free_result($request);
			}

			// 2.2. Fuse all role permissions together
			$user_info['shd_permissions'] = array();
			if (!empty($depts) && !empty($role_permissions))
			{
				foreach ($role_permissions as $role => $perm_list)
				{
					if (empty($depts[$role]))
						continue;

					foreach ($perm_list as $perm => $value)
					{
						if ($value == ROLEPERM_ALLOW)
							$user_info['shd_permissions'][$perm] = isset($user_info['shd_permissions'][$perm]) ? array_merge($user_info['shd_permissions'][$perm], $depts[$role]) : $depts[$role];
					}
				}
			}

			// 2.3. Apply deny restrictions
			if (!empty($denied))
			{
				foreach ($denied as $perm => $value)
				{
					if (isset($user_info['shd_permissions'][$perm]))
						unset($user_info['shd_permissions'][$perm]);
				}
			}

			cache_put_data($permissions_cache, $user_info['shd_permissions'], $perm_cache_time);
		}
		else
			$user_info['shd_permissions'] = $temp;
	}
	elseif ($user_info['is_admin'])
	{
		// Admins have all permissions in all departments
		$context['shd_depts_list'] = array();
		$request = $db->query('', '
			SELECT id_dept
			FROM {db_prefix}helpdesk_depts',
			array()
		);
		while ($row = $db->fetch_assoc($request))
			$context['shd_depts_list'][] = (int) $row['id_dept'];
		$db->free_result($request);
	}

	// Build the {query_see_ticket} clause
	$tickets_any_dept = shd_allowed_to('shd_view_ticket_any', false);
	$tickets_own_dept = shd_allowed_to('shd_view_ticket_own', false);

	if (is_bool($tickets_own_dept) || is_bool($tickets_any_dept))
	{
		$user_info['query_see_ticket'] = '1=0';
		return;
	}

	if (!empty($tickets_any_dept) && !empty($tickets_own_dept))
		$tickets_own_dept = array_diff($tickets_own_dept, $tickets_any_dept);

	if ($user_info['is_admin'])
		$user_info['query_see_ticket'] = '1=1';
	elseif (!shd_allowed_to('access_helpdesk', 0))
		$user_info['query_see_ticket'] = '1=0';
	else
	{
		$tickets_private_any_dept = shd_allowed_to('shd_view_ticket_private_any', false);
		$tickets_private_own_dept = shd_allowed_to('shd_view_ticket_private_own', false);

		if (is_bool($tickets_private_any_dept) || is_bool($tickets_private_own_dept))
		{
			$user_info['query_see_ticket'] = '1=0';
			return;
		}

		$clauses = array();

		// Privacy clauses
		$tickets_any_private = array_intersect($tickets_any_dept, $tickets_private_any_dept);
		$tickets_own_private = array_intersect(array_merge($tickets_any_dept, $tickets_own_dept), $tickets_private_own_dept);
		$tickets_any_nonprivate = array_diff($tickets_any_dept, $tickets_any_private);
		$tickets_own_nonprivate = array_diff($tickets_own_dept, $tickets_own_private);

		$privacy_clauses = array();

		if (!empty($tickets_any_private))
			$privacy_clauses[] = '(hdt.id_dept IN (' . implode(',', $tickets_any_private) . '))';
		if (!empty($tickets_own_private))
			$privacy_clauses[] = '(hdt.id_dept IN (' . implode(',', $tickets_own_private) . ') AND hdt.id_member_started = {int:user_info_id})';
		if (!empty($tickets_any_nonprivate))
			$privacy_clauses[] = '(hdt.id_dept IN (' . implode(',', $tickets_any_nonprivate) . ') AND hdt.private = 0)';
		if (!empty($tickets_own_nonprivate))
			$privacy_clauses[] = '(hdt.id_dept IN (' . implode(',', $tickets_own_nonprivate) . ') AND hdt.private = 0 AND hdt.id_member_started = {int:user_info_id})';

		if (!empty($privacy_clauses))
			$clauses[] = implode(' OR ', $privacy_clauses);
		else
			$clauses[] = '1=0';

		// Closed ticket visibility
		$depts_closed_any = shd_allowed_to('shd_view_closed_any', false);
		$depts_closed_own = shd_allowed_to('shd_view_closed_own', false);

		if (is_bool($depts_closed_own) || is_bool($depts_closed_any))
		{
			$user_info['query_see_ticket'] = '1=0';
			return;
		}
		$depts_closed_own = array_diff($depts_closed_own, $depts_closed_any);

		if (empty($depts_closed_any) && empty($depts_closed_own))
			$clauses[] = 'hdt.status != ' . TICKET_STATUS_CLOSED;
		elseif (!empty($depts_closed_any) && empty($depts_closed_own))
			$clauses[] = 'hdt.status != ' . TICKET_STATUS_CLOSED . ' OR (hdt.status = ' . TICKET_STATUS_CLOSED . ' AND hdt.id_dept IN (' . implode(',', $depts_closed_any) . '))';
		elseif (!empty($depts_closed_any) && !empty($depts_closed_own))
			$clauses[] = 'hdt.status != ' . TICKET_STATUS_CLOSED . ' OR (hdt.status = ' . TICKET_STATUS_CLOSED . ' AND (hdt.id_dept IN (' . implode(',', $depts_closed_any) . ') OR (hdt.id_member_started = {int:user_info_id} AND hdt.id_dept IN (' . implode(',', $depts_closed_own) . '))))';
		elseif (empty($depts_closed_any) && !empty($depts_closed_own))
			$clauses[] = 'hdt.status != ' . TICKET_STATUS_CLOSED . ' OR (hdt.status = ' . TICKET_STATUS_CLOSED . ' AND hdt.id_dept IN (' . implode(',', $depts_closed_own) . ') AND hdt.id_member_started = {int:user_info_id})';

		// Deleted ticket visibility
		$depts_deleted = shd_allowed_to('shd_access_recyclebin', false);
		if (is_bool($depts_deleted) || empty($depts_deleted))
			$clauses[] = 'hdt.status != ' . TICKET_STATUS_DELETED;
		else
			$clauses[] = 'hdt.status != ' . TICKET_STATUS_DELETED . ' OR (hdt.status = ' . TICKET_STATUS_DELETED . ' AND hdt.id_dept IN (' . implode(',', $depts_deleted) . '))';

		if (empty($clauses))
			$user_info['query_see_ticket'] = '1=0';
		else
			$user_info['query_see_ticket'] = '((' . implode(') AND (', $clauses) . '))';
	}
}

/**
 * Checks if the current user has a given SimpleDesk permission.
 *
 * @param mixed $permission String or array of permission names.
 * @param int|bool $dept Department ID (0 = any dept), or false to return list of departments.
 * @return bool|array
 */
function shd_allowed_to($permission, $dept = 0)
{
	global $user_info, $context;

	if ($dept === false)
	{
		if (!empty($user_info['is_admin']))
			return isset($context['shd_depts_list']) ? $context['shd_depts_list'] : array();
		else
			return empty($user_info['shd_permissions'][$permission]) ? array() : $user_info['shd_permissions'][$permission];
	}
	elseif ($dept == 0)
	{
		if (!empty($user_info['is_admin']))
			return true;
		elseif (!is_array($permission) && !empty($user_info['shd_permissions'][$permission]))
			return true;
		elseif (is_array($permission))
		{
			foreach ($permission as $perm)
				if (!empty($user_info['shd_permissions'][$perm]))
					return true;
		}
		return false;
	}
	else
	{
		if (!empty($user_info['is_admin']))
			return true;
		elseif (!is_array($permission))
			return !empty($user_info['shd_permissions'][$permission]) && in_array($dept, $user_info['shd_permissions'][$permission]);
		else
		{
			foreach ($permission as $perm)
				if (!empty($user_info['shd_permissions'][$perm]) && in_array($dept, $user_info['shd_permissions'][$perm]))
					return true;
			return false;
		}
	}
}

/**
 * Enforces that the user has a given SimpleDesk permission (fatal error if not).
 *
 * @param mixed $permission String or array of permission names.
 * @param int $dept Department ID.
 */
function shd_is_allowed_to($permission, $dept = 0)
{
	if (!shd_allowed_to($permission, $dept))
		fatal_lang_error('cannot_' . (is_array($permission) ? $permission[0] : $permission), false);
}
