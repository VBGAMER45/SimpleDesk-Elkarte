<?php
/**
 * SimpleDesk Permissions - Language File
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

// =========================================================================
// Permission name/help strings (used in ElkArte permission UI and role editor)
// =========================================================================

// General permissions
$txt['permissionname_access_helpdesk'] = 'Access the helpdesk';
$txt['permissionhelp_access_helpdesk'] = 'This permission allows users to see the helpdesk, required to do anything within the helpdesk.';
$txt['permissionname_admin_helpdesk'] = 'Administrate the helpdesk';
$txt['permissionhelp_admin_helpdesk'] = 'This permission controls whether administrative functions of the helpdesk can be passed on to non-administrators.';
$txt['permissionname_shd_staff'] = 'Treat as helpdesk staff';
$txt['permissionhelp_shd_staff'] = 'Users with this permission will be treated as staff.';

// Ticket visibility: general
$txt['permissionname_shd_view_ticket'] = 'What tickets can users see?';
$txt['permissionhelp_shd_view_ticket'] = 'Controls whether users can see tickets, and whether they have the standard user view or staff level view.';
$txt['permissionname_shd_view_ticket_no'] = 'None';
$txt['permissionname_shd_view_ticket_own'] = 'Their own tickets';
$txt['permissionname_shd_view_ticket_any'] = 'Any tickets';

// Ticket visibility: privacy
$txt['permissionname_shd_view_ticket_private'] = 'What private tickets can users see?';
$txt['permissionhelp_shd_view_ticket_private'] = 'Controls whether tickets marked private can be seen by users.';
$txt['permissionname_shd_view_ticket_private_no'] = 'None';
$txt['permissionname_shd_view_ticket_private_own'] = 'Own private tickets';
$txt['permissionname_shd_view_ticket_private_any'] = 'Any private tickets';

// Ticket visibility: closed
$txt['permissionname_shd_view_closed'] = 'Can users see closed tickets?';
$txt['permissionhelp_shd_view_closed'] = 'Controls whether users can see tickets previously marked closed.';
$txt['permissionname_shd_view_closed_no'] = 'No';
$txt['permissionname_shd_view_closed_own'] = 'Own closed tickets';
$txt['permissionname_shd_view_closed_any'] = 'Any closed tickets';

// Ticket modifications: privacy
$txt['permissionname_shd_alter_privacy'] = 'Change ticket privacy';
$txt['permissionhelp_shd_alter_privacy'] = 'Allows a user to change the privacy on a ticket.';
$txt['permissionname_shd_alter_privacy_own'] = 'Own tickets';
$txt['permissionname_shd_alter_privacy_any'] = 'Any tickets';

// Ticket modifications: urgency
$txt['permissionname_shd_alter_urgency'] = 'Change ticket urgency';
$txt['permissionhelp_shd_alter_urgency'] = 'Allows users to raise and lower urgency up to High.';
$txt['permissionname_shd_alter_urgency_own'] = 'Own tickets';
$txt['permissionname_shd_alter_urgency_any'] = 'Any tickets';
$txt['permissionname_shd_alter_urgency_higher'] = 'Change to higher urgencies';
$txt['permissionhelp_shd_alter_urgency_higher'] = 'Extends urgency permission to allow Very High, Severe, and Critical levels.';
$txt['permissionname_shd_alter_urgency_higher_own'] = 'Own tickets';
$txt['permissionname_shd_alter_urgency_higher_any'] = 'Any tickets';

// Assignment
$txt['permissionname_shd_assign_ticket'] = 'Assign a ticket';
$txt['permissionhelp_shd_assign_ticket'] = 'Allows users to assign tickets to staff.';
$txt['permissionname_shd_assign_ticket_own'] = 'To themselves';
$txt['permissionname_shd_assign_ticket_any'] = 'Any staff member';

// Ticket Hold
$txt['permissionname_shd_alter_hold'] = 'Alter Ticket Hold';

// Email: monitoring/ignoring
$txt['permissionname_shd_monitor_ticket'] = 'Monitor a ticket';
$txt['permissionhelp_shd_monitor_ticket'] = 'Allows users to monitor tickets for notifications on any replies.';
$txt['permissionname_shd_monitor_ticket_own'] = 'Their own tickets';
$txt['permissionname_shd_monitor_ticket_any'] = 'Any tickets';
$txt['permissionname_shd_ignore_ticket'] = 'Ignore a ticket';
$txt['permissionhelp_shd_ignore_ticket'] = 'Allows users to expressly turn off notifications on a ticket.';
$txt['permissionname_shd_ignore_ticket_own'] = 'Their own tickets';
$txt['permissionname_shd_ignore_ticket_any'] = 'Any tickets';

// Email: singleton
$txt['permissionname_shd_singleton_email'] = 'Send a reply notification';
$txt['permissionhelp_shd_singleton_email'] = 'Allows selecting extra recipients when replying to a ticket.';

// Email: silent update
$txt['permissionname_shd_silent_update'] = 'Silent update';
$txt['permissionhelp_shd_silent_update'] = 'Allows replying to a ticket while suppressing notifications.';

// Resolution
$txt['permissionname_shd_resolve_ticket'] = 'Mark a ticket resolved';
$txt['permissionhelp_shd_resolve_ticket'] = 'Allows users to mark tickets resolved.';
$txt['permissionname_shd_resolve_ticket_no'] = 'No tickets';
$txt['permissionname_shd_resolve_ticket_own'] = 'Their own tickets';
$txt['permissionname_shd_resolve_ticket_any'] = 'Any tickets';

$txt['permissionname_shd_unresolve_ticket'] = 'Mark a ticket unresolved';
$txt['permissionhelp_shd_unresolve_ticket'] = 'Allows users to mark tickets unresolved.';
$txt['permissionname_shd_unresolve_ticket_no'] = 'No tickets';
$txt['permissionname_shd_unresolve_ticket_own'] = 'Their own tickets';
$txt['permissionname_shd_unresolve_ticket_any'] = 'Any tickets';

// Posting: new ticket
$txt['permissionname_shd_new_ticket'] = 'Post new tickets';
$txt['permissionhelp_shd_new_ticket'] = 'Allows filing new tickets.';

// Posting: replies
$txt['permissionname_shd_reply_ticket'] = 'Reply to tickets';
$txt['permissionhelp_shd_reply_ticket'] = 'Allows replying to tickets.';
$txt['permissionname_shd_reply_ticket_own'] = 'Own tickets';
$txt['permissionname_shd_reply_ticket_any'] = 'Any tickets';

// Posting: edit ticket
$txt['permissionname_shd_edit_ticket'] = 'Edit ticket details';
$txt['permissionhelp_shd_edit_ticket'] = 'Allows editing the core details of a ticket.';
$txt['permissionname_shd_edit_ticket_own'] = 'Own tickets';
$txt['permissionname_shd_edit_ticket_any'] = 'Any tickets';

// Posting: edit reply
$txt['permissionname_shd_edit_reply'] = 'Edit ticket replies';
$txt['permissionhelp_shd_edit_reply'] = 'Allows editing replies in tickets.';
$txt['permissionname_shd_edit_reply_own'] = 'Own replies';
$txt['permissionname_shd_edit_reply_any'] = 'Any replies';

// Deletion: tickets
$txt['permissionname_shd_delete_ticket'] = 'Delete a ticket';
$txt['permissionhelp_shd_delete_ticket'] = 'Allows deleting tickets.';
$txt['permissionname_shd_delete_ticket_own'] = 'Own tickets';
$txt['permissionname_shd_delete_ticket_any'] = 'Any tickets';

// Deletion: replies
$txt['permissionname_shd_delete_reply'] = 'Delete ticket replies';
$txt['permissionhelp_shd_delete_reply'] = 'Allows deleting replies in tickets.';
$txt['permissionname_shd_delete_reply_own'] = 'Own replies';
$txt['permissionname_shd_delete_reply_any'] = 'Any replies';

// Deletion: permadelete
$txt['permissionname_shd_delete_recycling'] = '<strong>Permanently</strong> delete items';
$txt['permissionhelp_shd_delete_recycling'] = 'Allows removing tickets and posts from the recycling area permanently.';

// Deletion: recycle bin
$txt['permissionname_shd_access_recyclebin'] = 'Access recycling bin';
$txt['permissionhelp_shd_access_recyclebin'] = 'Allows accessing the list of recycled tickets.';

// Deletion: restore
$txt['permissionname_shd_restore_ticket'] = 'Restore deleted tickets';
$txt['permissionhelp_shd_restore_ticket'] = 'Allows restoring deleted tickets.';
$txt['permissionname_shd_restore_ticket_own'] = 'Own tickets';
$txt['permissionname_shd_restore_ticket_any'] = 'Any tickets';

$txt['permissionname_shd_restore_reply'] = 'Restore deleted replies';
$txt['permissionhelp_shd_restore_reply'] = 'Allows restoring deleted replies.';
$txt['permissionname_shd_restore_reply_own'] = 'Own replies';
$txt['permissionname_shd_restore_reply_any'] = 'Any replies';

// Attachments
$txt['permissionname_shd_view_attachment'] = 'View attachments';
$txt['permissionhelp_shd_view_attachment'] = 'Allows viewing attachments on tickets.';
$txt['permissionname_shd_post_attachment'] = 'Post attachments';
$txt['permissionhelp_shd_post_attachment'] = 'Allows posting attachments to tickets.';
$txt['permissionname_shd_delete_attachment'] = 'Delete attachments';
$txt['permissionhelp_shd_delete_attachment'] = 'Allows removing attachments from tickets.';

// Proxy
$txt['permissionname_shd_post_proxy'] = 'Post proxy tickets';
$txt['permissionhelp_shd_post_proxy'] = 'Allows creating a ticket on behalf of another user.';

// Custom fields override
$txt['permissionname_shd_override_cf'] = 'Override custom fields';
$txt['permissionhelp_shd_override_cf'] = 'Allows overriding required custom field checks.';

// View IP
$txt['permissionname_shd_view_ip'] = 'View IP addresses';
$txt['permissionhelp_shd_view_ip'] = 'Controls whether users can see IP addresses on posts.';
$txt['permissionname_shd_view_ip_own'] = 'Only their own';
$txt['permissionname_shd_view_ip_any'] = 'Anyone\'s';

// Search
$txt['permissionname_shd_search'] = 'Search tickets';
$txt['permissionhelp_shd_search'] = 'Allows searching tickets.';

// Ticket logs
$txt['permissionname_shd_view_ticket_logs'] = 'View ticket action logs';
$txt['permissionhelp_shd_view_ticket_logs'] = 'Allows viewing a log of all actions taken in a ticket.';
$txt['permissionname_shd_view_ticket_logs_own'] = 'For own tickets';
$txt['permissionname_shd_view_ticket_logs_any'] = 'For any ticket';

// Move dept
$txt['permissionname_shd_move_dept'] = 'Move tickets between departments';
$txt['permissionhelp_shd_move_dept'] = 'Allows moving a ticket from one department to another.';
$txt['permissionname_shd_move_dept_no'] = 'No tickets';
$txt['permissionname_shd_move_dept_own'] = 'Their own tickets';
$txt['permissionname_shd_move_dept_any'] = 'Any tickets';

// Relationships
$txt['permissionname_shd_view_relationships'] = 'View relationships';
$txt['permissionhelp_shd_view_relationships'] = 'Allows viewing ticket relationships.';
$txt['permissionname_shd_create_relationships'] = 'Create relationships';
$txt['permissionhelp_shd_create_relationships'] = 'Allows creating relationships between tickets.';
$txt['permissionname_shd_delete_relationships'] = 'Remove relationships';
$txt['permissionhelp_shd_delete_relationships'] = 'Allows removing relationships between tickets.';

// Profile
$txt['permissionname_shd_view_profile'] = 'View helpdesk profiles';
$txt['permissionhelp_shd_view_profile'] = 'Allows viewing helpdesk user profiles.';
$txt['permissionname_shd_view_profile_no'] = 'None';
$txt['permissionname_shd_view_profile_own'] = 'Only their own';
$txt['permissionname_shd_view_profile_any'] = 'Anyone\'s';

$txt['permissionname_shd_view_profile_log'] = 'View profile action logs';
$txt['permissionhelp_shd_view_profile_log'] = 'Allows viewing action logs from a user profile.';
$txt['permissionname_shd_view_profile_log_no'] = 'None';
$txt['permissionname_shd_view_profile_log_own'] = 'Only their own';
$txt['permissionname_shd_view_profile_log_any'] = 'Anyone\'s';

$txt['permissionname_shd_view_preferences'] = 'View user preferences';
$txt['permissionhelp_shd_view_preferences'] = 'Allows viewing and changing user preferences.';
$txt['permissionname_shd_view_preferences_no'] = 'None';
$txt['permissionname_shd_view_preferences_own'] = 'Only their own';
$txt['permissionname_shd_view_preferences_any'] = 'Anyone\'s';

// =========================================================================
// Permission categories (for role template summary and role editor groups)
// =========================================================================

$txt['shd_permgroup_general'] = 'General helpdesk permissions';
$txt['shd_permgroup_posting'] = 'Ticket/reply posting';
$txt['shd_permgroup_ticketactions'] = 'Ticket actions';
$txt['shd_permgroup_deletion'] = 'Recycle bin and deletion';
$txt['shd_permgroup_attachments'] = 'Attachments';
$txt['shd_permgroup_email'] = 'Email notifications';
$txt['shd_permgroup_profile'] = 'User profiles';
$txt['shd_permgroup_relationships'] = 'Ticket relationships';
$txt['shd_permgroup_moderation'] = 'Ticket moderation';

$txt['shd_permgroup_short_general'] = 'General';
$txt['shd_permgroup_short_posting'] = 'Posting';
$txt['shd_permgroup_short_ticketactions'] = 'Actions';
$txt['shd_permgroup_short_deletion'] = 'Delete/Restore';
$txt['shd_permgroup_short_attachments'] = 'Attachments';
$txt['shd_permgroup_short_email'] = 'Emails';
$txt['shd_permgroup_short_profile'] = 'Profile';
$txt['shd_permgroup_short_relationships'] = 'Relationships';
$txt['shd_permgroup_short_moderation'] = 'Moderation';

$txt['shd_permgroup_denied'] = 'Denied';

// =========================================================================
// Admin Permissions page - role management UI
// =========================================================================

$txt['shd_admin_permissions'] = 'Helpdesk Permissions';
$txt['shd_admin_permissions_desc'] = 'This area allows you to configure the permissions for SimpleDesk - create roles based on templates and assign them to membergroups.';
$txt['shd_admin_permissions_homedesc'] = 'This area allows you to configure the permissions for SimpleDesk - create roles based on templates and assign them to membergroups.';

// Role templates section
$txt['shd_role_templates'] = 'Templates';
$txt['shd_role_templates_desc'] = 'These are the permission templates. Use them as a base when creating your own roles.';
$txt['shd_role_template'] = 'Template';
$txt['shd_permissions_summary'] = 'Permissions Summary';
$txt['shd_create_role_from_template'] = 'Create Role From Template';

// Roles table
$txt['shd_roles'] = 'Roles';
$txt['shd_roles_desc'] = 'These are the roles in your helpdesk, listing type, permissions, and membergroup associations.';
$txt['shd_role'] = 'Role';
$txt['shd_permissions'] = 'Permissions';
$txt['shd_membergroups'] = 'Membergroups';
$txt['shd_departments'] = 'Departments';
$txt['shd_actions'] = 'Actions';

// Role type labels
$txt['shd_permrole_user'] = 'Helpdesk Users';
$txt['shd_permrole_staff'] = 'Helpdesk Staff';
$txt['shd_permrole_admin'] = 'Helpdesk Administrators';

// Role CRUD
$txt['shd_create_role'] = 'Create New Role';
$txt['shd_create_role_desc'] = 'Create a new role based on a template. You can customize its permissions after creation.';
$txt['shd_no_defined_roles'] = 'You have not created any roles yet!';
$txt['shd_no_roles_defined'] = 'No roles have been defined yet. Create a role from one of the templates above.';
$txt['shd_based_on'] = 'Based on \'%1$s\' template';
$txt['shd_edit_role'] = 'Edit Role';
$txt['shd_copy_role'] = 'Copy Role';
$txt['shd_copy_role_desc'] = 'Copy an existing role to create a new role with the same permissions.';
$txt['shd_copy_source'] = 'Source Role';
$txt['shd_new_role_name'] = 'New Role Name';
$txt['shd_copy_groups_depts'] = 'Copy groups and departments';
$txt['shd_copy_groups_depts_desc'] = 'If checked, the new role will also have the same membergroup and department assignments as the source role.';
$txt['shd_copy_role_groups'] = 'Copy this role\'s groups and departments as well?';
$txt['shd_delete_role'] = 'Delete Role';
$txt['shd_delete_role_confirm'] = 'Do you really want to delete this role?';
$txt['shd_create_based_on'] = 'The new role will be based on';
$txt['shd_is_based_on'] = 'This role is based on the template';
$txt['shd_create_name'] = 'What should the new role be called?';

// Role edit form
$txt['shd_role_name'] = 'Role Name';
$txt['shd_role_details'] = 'Role Details';
$txt['shd_unknown_template_label'] = 'Unknown';

// Role membergroups section
$txt['shd_role_membergroups'] = 'Membergroups This Role Applies To';
$txt['shd_role_membergroups_desc'] = 'Set what groups this role applies to. Groups may have multiple roles attached.';
$txt['shd_membergroups_desc'] = 'Select which membergroups this role should apply to. A group may have multiple roles.';
$txt['shd_group_name'] = 'Group Name';
$txt['shd_no_groups_assigned'] = 'No groups assigned';
$txt['shd_badge_stars'] = 'Badge/Stars';
$txt['shd_assign_group'] = 'Assign Role/Group';

// Role departments section
$txt['shd_role_departments'] = 'Departments This Role Is Part Of';
$txt['shd_role_departments_desc'] = 'Each role is part of one or more departments, indicating which departments the groups in this role have access to.';
$txt['shd_departments_desc'] = 'Select which departments this role applies to.';
$txt['shd_dept_name'] = 'Department';
$txt['shd_no_depts_assigned'] = 'No departments assigned';
$txt['shd_no_departments'] = 'No departments have been created yet.';

// Permission dropdown labels (role edit form)
$txt['shd_perm_disallow'] = 'Not allowed';
$txt['shd_perm_allow'] = 'Allowed';
$txt['shd_perm_allow_own'] = 'Allowed (own only)';
$txt['shd_perm_allow_any'] = 'Allowed (any)';
$txt['shd_perm_deny'] = 'Denied';
$txt['shd_no_permissions'] = 'No permissions set';

// Permission value labels
$txt['shd_roleperm_allow'] = 'Allowed';
$txt['shd_roleperm_disallow'] = 'Not allowed';
$txt['shd_roleperm_deny'] = 'Never allowed';
$txt['shd_none'] = 'None';

// =========================================================================
// Admin Departments page - department management UI
// =========================================================================

// Department list page
$txt['shd_admin_departments_title'] = 'Helpdesk Departments';
$txt['shd_admin_departments_desc'] = 'Manage your helpdesk departments here. Departments allow you to separate different types of requests.';
$txt['shd_admin_departments_home'] = 'Helpdesk Departments';
$txt['shd_admin_departments_homedesc'] = 'Manage your helpdesk departments here. Departments allow you to separate different types of requests.';

// Department table column headers
$txt['shd_admin_dept_name'] = 'Department Name';
$txt['shd_admin_dept_board_cat'] = 'Board Index Category';
$txt['shd_admin_dept_roles'] = 'Roles';
$txt['shd_admin_dept_move'] = 'Move';
$txt['shd_admin_dept_actions'] = 'Actions';
$txt['shd_admin_dept_none'] = 'No departments have been created.';
$txt['shd_admin_dept_no_roles'] = 'No roles assigned';
$txt['shd_admin_dept_edit'] = 'Edit';
$txt['shd_admin_dept_create'] = 'Create New Department';

// Move up/down
$txt['shd_admin_move_up'] = 'Move Up';
$txt['shd_admin_move_down'] = 'Move Down';

// Department creation form
$txt['shd_admin_dept_create_title'] = 'Create New Department';
$txt['shd_admin_dept_create_desc'] = 'Use this form to create a new department for your helpdesk.';
$txt['shd_admin_dept_name_desc'] = 'Enter a short name for this department.';
$txt['shd_admin_dept_description'] = 'Description';
$txt['shd_admin_dept_description_desc'] = 'A brief description of what this department is for.';
$txt['shd_admin_dept_board_cat_desc'] = 'Select the board index category where this department should be displayed, or choose not to show it on the board index.';
$txt['shd_admin_dept_beforeafter'] = 'Board Position';
$txt['shd_admin_dept_beforeafter_desc'] = 'Should the department appear before or after the boards in the selected category?';
$txt['shd_admin_dept_before_boards'] = 'Before boards';
$txt['shd_admin_dept_after_boards'] = 'After boards';
$txt['shd_admin_dept_create_btn'] = 'Create Department';

// Department edit form
$txt['shd_admin_dept_edit_title'] = 'Edit Department: %1$s';
$txt['shd_admin_dept_settings'] = 'Department Settings';
$txt['shd_admin_dept_autoclose'] = 'Auto-close after (days)';
$txt['shd_admin_dept_autoclose_desc'] = 'Automatically close tickets with no activity after this many days. Set to 0 to disable.';
$txt['shd_admin_dept_roles_section'] = 'Role Assignments';
$txt['shd_admin_dept_roles_desc'] = 'Select which roles should be active in this department. Only users in roles assigned to a department can access tickets in that department.';
$txt['shd_admin_dept_no_roles_available'] = 'No roles have been created yet. Create roles in the Permissions area first.';
$txt['shd_admin_dept_role_name'] = 'Role Name';
$txt['shd_admin_dept_role_type'] = 'Role Type';
$txt['shd_admin_dept_save'] = 'Save Department';
$txt['shd_admin_dept_delete'] = 'Delete Department';
$txt['shd_admin_dept_delete_confirm'] = 'Do you really want to delete this department? This cannot be undone.';
$txt['shd_unknown'] = 'Unknown';

// Legacy department strings (used by department admin subs)
$txt['shd_department_name'] = 'Department Name';
$txt['shd_dept_boardindex'] = 'Board Index Display';
$txt['shd_roles_in_dept'] = 'Roles in Department';
$txt['shd_roles_in_dept_desc'] = 'Select which roles should be active in this department.';
$txt['shd_no_roles_in_dept'] = 'No roles assigned to this department.';
$txt['shd_create_dept'] = 'Create New Department';
$txt['shd_edit_dept'] = 'Edit Department';
$txt['shd_delete_dept'] = 'Delete Department';
$txt['shd_delete_dept_confirm'] = 'Do you really want to delete this department? This cannot be undone.';
$txt['shd_new_dept_name'] = 'Department Name';
$txt['shd_dept_description'] = 'Description';
$txt['shd_dept_boardindex_cat'] = 'Show on board index in category';
$txt['shd_boardindex_cat_none'] = '(Do not show on board index)';
$txt['shd_boardindex_cat_where'] = 'Position within category';
$txt['shd_boardindex_cat_before'] = 'Before boards';
$txt['shd_boardindex_cat_after'] = 'After boards';
$txt['shd_dept_inside_category'] = 'In category';
$txt['shd_dept_cat_before_boards'] = 'Displayed before boards';
$txt['shd_dept_cat_after_boards'] = 'Displayed after boards';
$txt['shd_dept_no_boardindex'] = 'Not shown on board index';
$txt['shd_dept_theme'] = 'Department Theme';
$txt['shd_dept_theme_use_default'] = 'Use forum default';
$txt['shd_dept_autoclose_days'] = 'Auto-close after (days)';
$txt['shd_dept_autoclose_days_note'] = 'Set to 0 to disable auto-close for this department.';
$txt['shd_assign_dept'] = 'In Department?';

// =========================================================================
// Error strings
// =========================================================================

$txt['shd_unknown_template'] = 'The specified template does not exist.';
$txt['shd_no_role_name'] = 'You did not enter the name for the role.';
$txt['shd_could_not_create_role'] = 'There was an error creating the role.';
$txt['shd_unknown_role'] = 'That role does not exist.';
$txt['shd_no_dept_name'] = 'You did not enter a department name.';
$txt['shd_invalid_category'] = 'The specified category is not valid.';
$txt['shd_could_not_create_dept'] = 'There was an error creating the department.';
$txt['shd_unknown_dept'] = 'That department does not exist.';
$txt['shd_admin_cannot_move_dept'] = 'Cannot move that department.';
$txt['shd_admin_cannot_move_dept_up'] = 'Cannot move the department up further.';
$txt['shd_admin_cannot_move_dept_down'] = 'Cannot move the department down further.';
$txt['shd_must_have_dept'] = 'You cannot delete the last department.';
$txt['shd_dept_not_empty'] = 'You cannot delete a department that still has tickets. Move or delete the tickets first.';
