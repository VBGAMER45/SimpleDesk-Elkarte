<?php
/**
 * SimpleDesk Helpdesk - Database Installer
 *
 * Creates all database tables and inserts default settings.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

global $modSettings;

$db = database();
$db_table = db_table();

// =========================================================================
// 1. Create database tables
// =========================================================================

$tables = array();

// Tickets table
$tables[] = array(
	'table_name' => '{db_prefix}helpdesk_tickets',
	'columns' => array(
		array('name' => 'id_ticket', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'auto' => true),
		array('name' => 'id_dept', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_first_msg', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_member_started', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_last_msg', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_member_updated', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_member_assigned', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'num_replies', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
		array('name' => 'deleted_replies', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
		array('name' => 'subject', 'type' => 'varchar', 'size' => 100, 'default' => ''),
		array('name' => 'urgency', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
		array('name' => 'status', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
		array('name' => 'private', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
		array('name' => 'withdeleted', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
		array('name' => 'last_updated', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
	),
	'indexes' => array(
		array('type' => 'primary', 'columns' => array('id_ticket')),
		array('type' => 'index', 'name' => 'idx_status_assigned', 'columns' => array('status', 'id_member_assigned')),
		array('type' => 'index', 'name' => 'idx_member_private', 'columns' => array('id_member_started', 'private')),
		array('type' => 'index', 'name' => 'idx_status_deleted', 'columns' => array('status', 'withdeleted', 'deleted_replies')),
	),
);

// Ticket replies table
$tables[] = array(
	'table_name' => '{db_prefix}helpdesk_ticket_replies',
	'columns' => array(
		array('name' => 'id_msg', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
		array('name' => 'id_ticket', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'body', 'type' => 'mediumtext'),
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'poster_time', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
		array('name' => 'poster_name', 'type' => 'varchar', 'size' => 255, 'default' => ''),
		array('name' => 'poster_email', 'type' => 'varchar', 'size' => 255, 'default' => ''),
		array('name' => 'poster_ip', 'type' => 'varchar', 'size' => 255, 'default' => ''),
		array('name' => 'modified_time', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
		array('name' => 'modified_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'modified_name', 'type' => 'varchar', 'size' => 255, 'default' => ''),
		array('name' => 'smileys_enabled', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 1),
		array('name' => 'message_status', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
	),
	'indexes' => array(
		array('type' => 'primary', 'columns' => array('id_msg')),
		array('type' => 'index', 'name' => 'idx_ticket_msg_status', 'columns' => array('id_ticket', 'id_msg', 'message_status')),
	),
);

// Action log
$tables[] = array(
	'table_name' => '{db_prefix}helpdesk_log_action',
	'columns' => array(
		array('name' => 'id_action', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
		array('name' => 'log_time', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'ip', 'type' => 'varchar', 'size' => 255, 'default' => ''),
		array('name' => 'action', 'type' => 'varchar', 'size' => 30, 'default' => ''),
		array('name' => 'id_ticket', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_msg', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
		array('name' => 'extra', 'type' => 'mediumtext'),
	),
	'indexes' => array(
		array('type' => 'primary', 'columns' => array('id_action')),
		array('type' => 'index', 'name' => 'idx_ticket', 'columns' => array('id_ticket')),
	),
);

// Read log
$tables[] = array(
	'table_name' => '{db_prefix}helpdesk_log_read',
	'columns' => array(
		array('name' => 'id_ticket', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_msg', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
	),
	'indexes' => array(
		array('type' => 'primary', 'columns' => array('id_ticket', 'id_member')),
	),
);

// Attachments mapping
$tables[] = array(
	'table_name' => '{db_prefix}helpdesk_attachments',
	'columns' => array(
		array('name' => 'id_attach', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_ticket', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_msg', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
	),
	'indexes' => array(
		array('type' => 'primary', 'columns' => array('id_attach')),
	),
);

// Ticket relationships
$tables[] = array(
	'table_name' => '{db_prefix}helpdesk_relationships',
	'columns' => array(
		array('name' => 'primary_ticket', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'secondary_ticket', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'rel_type', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
	),
	'indexes' => array(
		array('type' => 'primary', 'columns' => array('primary_ticket', 'secondary_ticket')),
		array('type' => 'index', 'name' => 'idx_primary_rel', 'columns' => array('primary_ticket', 'rel_type')),
	),
);

// Custom fields
$tables[] = array(
	'table_name' => '{db_prefix}helpdesk_custom_fields',
	'columns' => array(
		array('name' => 'id_field', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'auto' => true),
		array('name' => 'active', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
		array('name' => 'field_order', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
		array('name' => 'field_name', 'type' => 'varchar', 'size' => 40, 'default' => ''),
		array('name' => 'field_desc', 'type' => 'text'),
		array('name' => 'field_loc', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
		array('name' => 'icon', 'type' => 'varchar', 'size' => 20, 'default' => ''),
		array('name' => 'field_type', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
		array('name' => 'field_length', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 255),
		array('name' => 'field_options', 'type' => 'text'),
		array('name' => 'bbc', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
		array('name' => 'default_value', 'type' => 'varchar', 'size' => 255, 'default' => ''),
		array('name' => 'can_see', 'type' => 'varchar', 'size' => 3, 'default' => '0'),
		array('name' => 'can_edit', 'type' => 'varchar', 'size' => 3, 'default' => '0'),
		array('name' => 'display_empty', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
		array('name' => 'placement', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
	),
	'indexes' => array(
		array('type' => 'primary', 'columns' => array('id_field')),
	),
);

// Custom field values
$tables[] = array(
	'table_name' => '{db_prefix}helpdesk_custom_fields_values',
	'columns' => array(
		array('name' => 'id_post', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_field', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
		array('name' => 'value', 'type' => 'text'),
		array('name' => 'post_type', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
	),
	'indexes' => array(
		array('type' => 'primary', 'columns' => array('id_post', 'id_field')),
	),
);

// Custom fields to departments mapping
$tables[] = array(
	'table_name' => '{db_prefix}helpdesk_custom_fields_depts',
	'columns' => array(
		array('name' => 'id_field', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_dept', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
		array('name' => 'required', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
	),
	'indexes' => array(
		array('type' => 'primary', 'columns' => array('id_field', 'id_dept')),
	),
);

// Roles
$tables[] = array(
	'table_name' => '{db_prefix}helpdesk_roles',
	'columns' => array(
		array('name' => 'id_role', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'auto' => true),
		array('name' => 'template', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
		array('name' => 'role_name', 'type' => 'varchar', 'size' => 80, 'default' => ''),
	),
	'indexes' => array(
		array('type' => 'primary', 'columns' => array('id_role')),
	),
);

// Role to membergroup mapping
$tables[] = array(
	'table_name' => '{db_prefix}helpdesk_role_groups',
	'columns' => array(
		array('name' => 'id_role', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_group', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
	),
	'indexes' => array(
		array('type' => 'primary', 'columns' => array('id_role', 'id_group')),
	),
);

// Role permissions
$tables[] = array(
	'table_name' => '{db_prefix}helpdesk_role_permissions',
	'columns' => array(
		array('name' => 'id_role', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
		array('name' => 'permission', 'type' => 'varchar', 'size' => 40, 'default' => ''),
		array('name' => 'add_type', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
	),
	'indexes' => array(
		array('type' => 'primary', 'columns' => array('id_role', 'permission')),
	),
);

// User preferences
$tables[] = array(
	'table_name' => '{db_prefix}helpdesk_preferences',
	'columns' => array(
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'variable', 'type' => 'varchar', 'size' => 30, 'default' => ''),
		array('name' => 'value', 'type' => 'text'),
	),
	'indexes' => array(
		array('type' => 'primary', 'columns' => array('id_member', 'variable')),
	),
);

// Departments
$tables[] = array(
	'table_name' => '{db_prefix}helpdesk_depts',
	'columns' => array(
		array('name' => 'id_dept', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'auto' => true),
		array('name' => 'dept_name', 'type' => 'varchar', 'size' => 50, 'default' => ''),
		array('name' => 'description', 'type' => 'text'),
		array('name' => 'board_cat', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
		array('name' => 'before_after', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
		array('name' => 'dept_order', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
		array('name' => 'dept_theme', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
		array('name' => 'autoclose_days', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
	),
	'indexes' => array(
		array('type' => 'primary', 'columns' => array('id_dept')),
	),
);

// Department to role mapping
$tables[] = array(
	'table_name' => '{db_prefix}helpdesk_dept_roles',
	'columns' => array(
		array('name' => 'id_role', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_dept', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
	),
	'indexes' => array(
		array('type' => 'primary', 'columns' => array('id_role', 'id_dept')),
	),
);

// Canned replies
$tables[] = array(
	'table_name' => '{db_prefix}helpdesk_cannedreplies',
	'columns' => array(
		array('name' => 'id_reply', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'auto' => true),
		array('name' => 'id_cat', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
		array('name' => 'title', 'type' => 'varchar', 'size' => 80, 'default' => ''),
		array('name' => 'body', 'type' => 'text'),
		array('name' => 'vis_user', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
		array('name' => 'vis_staff', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
		array('name' => 'reply_order', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
		array('name' => 'active', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 1),
	),
	'indexes' => array(
		array('type' => 'primary', 'columns' => array('id_reply')),
	),
);

// Canned reply categories
$tables[] = array(
	'table_name' => '{db_prefix}helpdesk_cannedreplies_cats',
	'columns' => array(
		array('name' => 'id_cat', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'auto' => true),
		array('name' => 'cat_name', 'type' => 'varchar', 'size' => 80, 'default' => ''),
		array('name' => 'cat_order', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
	),
	'indexes' => array(
		array('type' => 'primary', 'columns' => array('id_cat')),
	),
);

// Canned replies to departments mapping
$tables[] = array(
	'table_name' => '{db_prefix}helpdesk_cannedreplies_depts',
	'columns' => array(
		array('name' => 'id_dept', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_reply', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
	),
	'indexes' => array(
		array('type' => 'primary', 'columns' => array('id_dept', 'id_reply')),
	),
);

// Notification overrides per ticket
$tables[] = array(
	'table_name' => '{db_prefix}helpdesk_notify_override',
	'columns' => array(
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_ticket', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'notify_state', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
	),
	'indexes' => array(
		array('type' => 'primary', 'columns' => array('id_member', 'id_ticket')),
	),
);

// Search: ticket word index
$tables[] = array(
	'table_name' => '{db_prefix}helpdesk_search_ticket_words',
	'columns' => array(
		array('name' => 'id_word', 'type' => 'bigint', 'size' => 21, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_msg', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
	),
	'indexes' => array(
		array('type' => 'primary', 'columns' => array('id_word', 'id_msg')),
	),
);

// Search: subject word index
$tables[] = array(
	'table_name' => '{db_prefix}helpdesk_search_subject_words',
	'columns' => array(
		array('name' => 'id_word', 'type' => 'bigint', 'size' => 21, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_ticket', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
	),
	'indexes' => array(
		array('type' => 'primary', 'columns' => array('id_word', 'id_ticket')),
	),
);

// Create all tables
foreach ($tables as $table)
{
	$db_table->db_create_table($table['table_name'], $table['columns'], $table['indexes'], array(), 'ignore');
}

// =========================================================================
// 2. Create default department if none exists
// =========================================================================

$request = $db->query('', '
	SELECT COUNT(*) FROM {db_prefix}helpdesk_depts',
	array()
);
list($dept_count) = $db->fetch_row($request);
$db->free_result($request);

if ((int) $dept_count === 0)
{
	$db->insert('insert',
		'{db_prefix}helpdesk_depts',
		array(
			'dept_name' => 'string',
			'description' => 'string',
			'board_cat' => 'int',
			'before_after' => 'int',
			'dept_order' => 'int',
			'dept_theme' => 'int',
		),
		array(
			'Helpdesk',
			'',
			0,
			0,
			1,
			0,
		),
		array('id_dept')
	);
}

// Move any orphaned tickets (id_dept=0) into the last department
$request = $db->query('', '
	SELECT MAX(id_dept) FROM {db_prefix}helpdesk_depts',
	array()
);
list($new_dept) = $db->fetch_row($request);
$db->free_result($request);

if (!empty($new_dept))
{
	$db->query('', '
		UPDATE {db_prefix}helpdesk_tickets
		SET id_dept = {int:new_dept}
		WHERE id_dept = {int:old_dept}',
		array(
			'new_dept' => $new_dept,
			'old_dept' => 0,
		)
	);
}

// =========================================================================
// 3. Insert default settings (only if not already set)
// =========================================================================

$settings = array(
	'shd_attachments_mode' => 'ticket',
	'shd_staff_badge' => 'nobadge',
	'shd_ticketnav_style' => 'sd',
	'shd_privacy_display' => 'smart',
	'shd_allow_wikilinks' => '1',
	'shd_display_ticket_logs' => '1',
	'shd_logopt_resolve' => '1',
	'shd_logopt_autoclose' => '1',
	'shd_logopt_assign' => '1',
	'shd_logopt_privacy' => '1',
	'shd_logopt_urgency' => '1',
	'shd_logopt_tickettopicmove' => '1',
	'shd_logopt_cfchanges' => '1',
	'shd_logopt_delete' => '1',
	'shd_logopt_restore' => '1',
	'shd_logopt_permadelete' => '1',
	'shd_logopt_move_dept' => '1',
	'shd_logopt_relationships' => '1',
	'shd_logopt_newposts' => '1',
	'shd_logopt_editposts' => '1',
	'shd_thank_you_post' => '1',
	'shd_zerofill' => '5',
	'shd_notify_log' => '1',
	'shd_notify_with_body' => '1',
	'shd_notify_new_ticket' => '1',
	'shd_notify_new_reply_own' => '1',
	'shd_notify_new_reply_assigned' => '1',
	'shd_notify_new_reply_previous' => '1',
	'shd_notify_new_reply_any' => '1',
	'shd_notify_assign_me' => '1',
	'shd_notify_assign_own' => '1',
);

$new_settings = array();
foreach ($settings as $key => $value)
{
	if (!isset($modSettings[$key]))
		$new_settings[$key] = $value;
}

if (!empty($new_settings))
	updateSettings($new_settings);
