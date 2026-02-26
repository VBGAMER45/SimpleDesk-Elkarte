<?php
/**
 * SimpleDesk Helpdesk - Database Cleanup
 *
 * Drops all helpdesk tables and removes settings.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

$db = database();
$db_table = db_table();

// =========================================================================
// 1. Drop database tables
// =========================================================================

$db_table->db_drop_table('{db_prefix}helpdesk_tickets');
$db_table->db_drop_table('{db_prefix}helpdesk_ticket_replies');
$db_table->db_drop_table('{db_prefix}helpdesk_log_action');
$db_table->db_drop_table('{db_prefix}helpdesk_log_read');
$db_table->db_drop_table('{db_prefix}helpdesk_attachments');
$db_table->db_drop_table('{db_prefix}helpdesk_relationships');
$db_table->db_drop_table('{db_prefix}helpdesk_custom_fields');
$db_table->db_drop_table('{db_prefix}helpdesk_custom_fields_values');
$db_table->db_drop_table('{db_prefix}helpdesk_custom_fields_depts');
$db_table->db_drop_table('{db_prefix}helpdesk_roles');
$db_table->db_drop_table('{db_prefix}helpdesk_role_groups');
$db_table->db_drop_table('{db_prefix}helpdesk_role_permissions');
$db_table->db_drop_table('{db_prefix}helpdesk_preferences');
$db_table->db_drop_table('{db_prefix}helpdesk_depts');
$db_table->db_drop_table('{db_prefix}helpdesk_dept_roles');
$db_table->db_drop_table('{db_prefix}helpdesk_cannedreplies');
$db_table->db_drop_table('{db_prefix}helpdesk_cannedreplies_cats');
$db_table->db_drop_table('{db_prefix}helpdesk_cannedreplies_depts');
$db_table->db_drop_table('{db_prefix}helpdesk_notify_override');
$db_table->db_drop_table('{db_prefix}helpdesk_search_ticket_words');
$db_table->db_drop_table('{db_prefix}helpdesk_search_subject_words');

// =========================================================================
// 2. Remove settings
// =========================================================================

$settings_to_remove = array(
	'shd_attachments_mode',
	'shd_staff_badge',
	'shd_ticketnav_style',
	'shd_privacy_display',
	'shd_allow_wikilinks',
	'shd_display_ticket_logs',
	'shd_logopt_resolve',
	'shd_logopt_autoclose',
	'shd_logopt_assign',
	'shd_logopt_privacy',
	'shd_logopt_urgency',
	'shd_logopt_tickettopicmove',
	'shd_logopt_cfchanges',
	'shd_logopt_delete',
	'shd_logopt_restore',
	'shd_logopt_permadelete',
	'shd_logopt_move_dept',
	'shd_logopt_relationships',
	'shd_logopt_newposts',
	'shd_logopt_editposts',
	'shd_thank_you_post',
	'shd_zerofill',
	'shd_notify_log',
	'shd_notify_with_body',
	'shd_notify_new_ticket',
	'shd_notify_new_reply_own',
	'shd_notify_new_reply_assigned',
	'shd_notify_new_reply_previous',
	'shd_notify_new_reply_any',
	'shd_notify_assign_me',
	'shd_notify_assign_own',
	'shd_maintenance_mode',
	'shd_hidemenuitem',
	'shd_disable_action_log',
	'shd_enabled_plugins',
	'shd_enabled_bbc',
	'shd_helpdesk_only',
	'shd_theme',
	'shd_disable_tickettotopic',
	'shd_new_search_index',
	'shd_developer_mode',
);

$db->query('', '
	DELETE FROM {db_prefix}settings
	WHERE variable IN ({array_string:settings})',
	array(
		'settings' => $settings_to_remove,
	)
);

// Refresh settings cache.
updateSettings(array('shd_removed' => '1'));
$db->query('', '
	DELETE FROM {db_prefix}settings
	WHERE variable = {string:var}',
	array(
		'var' => 'shd_removed',
	)
);
