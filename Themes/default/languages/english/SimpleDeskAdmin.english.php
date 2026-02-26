<?php
/**
 * SimpleDesk Helpdesk - Admin Language File (English)
 *
 * All language strings for the admin information dashboard,
 * general options page, and admin sub-area labels.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

// ============================================================================
// Admin page titles
// ============================================================================
$txt['shd_admin_info_title'] = 'SimpleDesk Information';
$txt['shd_admin_options_title'] = 'Helpdesk Options';

// ============================================================================
// Info / Dashboard page
// ============================================================================
$txt['shd_admin_info_desc'] = 'This is the main information center for SimpleDesk. Here you can see the current version and other details about your helpdesk installation.';
$txt['shd_admin_info_general'] = 'General Information';
$txt['shd_admin_info_version'] = 'SimpleDesk Version';
$txt['shd_admin_info_tickets'] = 'Total Tickets';
$txt['shd_admin_info_open_tickets'] = 'Open Tickets';
$txt['shd_admin_info_closed_tickets'] = 'Closed Tickets';
$txt['shd_admin_info_recycled_tickets'] = 'Recycled Tickets';
$txt['shd_admin_info_departments'] = 'Departments';
$txt['shd_admin_info_staff'] = 'Staff Members';

$txt['shd_admin_no_staff'] = 'No staff members are currently assigned to the helpdesk.';

// Credits
$txt['shd_admin_credits'] = 'Credits';
$txt['shd_admin_credits_desc'] = 'SimpleDesk is developed and maintained by the SimpleDesk Team. We appreciate all contributions from the community.';
$txt['shd_admin_credits_developer'] = 'Developer';
$txt['shd_admin_credits_website'] = 'Website';
$txt['shd_admin_credits_license'] = 'License';

// ============================================================================
// Options page - general
// ============================================================================
$txt['shd_admin_options_desc'] = 'This page allows you to configure the general settings for your helpdesk.';

// ============================================================================
// Options - Display section
// ============================================================================
$txt['shd_admin_options_display'] = 'Display Options';

$txt['shd_staff_badge'] = 'Staff badge style';
$txt['shd_staff_badge_desc'] = 'Select how badges are displayed for staff members in ticket views.';
$txt['shd_staff_badge_nobadge'] = 'No badge';
$txt['shd_staff_badge_staffbadge'] = 'Staff badge only';
$txt['shd_staff_badge_userbadge'] = 'User group badge only';
$txt['shd_staff_badge_bothbadge'] = 'Both badges';

$txt['shd_display_avatar'] = 'Display avatars in tickets';
$txt['shd_display_avatar_desc'] = 'If enabled, user avatars will be shown alongside ticket posts and replies.';

$txt['shd_ticketnav_style'] = 'Ticket navigation style';
$txt['shd_ticketnav_style_desc'] = 'Choose the navigation style used above ticket listings.';
$txt['shd_ticketnav_style_sd'] = 'SimpleDesk full';
$txt['shd_ticketnav_style_sdcompact'] = 'SimpleDesk compact';
$txt['shd_ticketnav_style_smf'] = 'Forum style';

$txt['shd_zerofill'] = 'Minimum ticket number digits';
$txt['shd_zerofill_desc'] = 'Ticket numbers will be padded with leading zeros to this many digits. Set to 0 to disable.';

$txt['shd_hidemenuitem'] = 'Hide helpdesk menu item';
$txt['shd_hidemenuitem_desc'] = 'If enabled, the helpdesk link will not appear in the main forum menu.';

// ============================================================================
// Options - Posting section
// ============================================================================
$txt['shd_admin_options_posting'] = 'Posting Options';

$txt['shd_thank_you_post'] = 'Enable "thank you" post';
$txt['shd_thank_you_post_desc'] = 'If enabled, an automatic thank-you message is posted when a ticket is resolved.';

$txt['shd_allow_wikilinks'] = 'Allow wiki-style links';
$txt['shd_allow_wikilinks_desc'] = 'If enabled, [[wiki-style]] links can be used in ticket posts and replies.';

$txt['shd_allow_ticket_bbc'] = 'Allow BBCode in tickets';
$txt['shd_allow_ticket_bbc_desc'] = 'If enabled, users can use BBCode formatting in their ticket posts and replies.';

$txt['shd_allow_ticket_smileys'] = 'Allow smileys in tickets';
$txt['shd_allow_ticket_smileys_desc'] = 'If enabled, smiley codes will be converted to images in ticket posts and replies.';

$txt['shd_attachments_mode'] = 'Attachment handling';
$txt['shd_attachments_mode_desc'] = 'Select how file attachments are handled in the helpdesk.';
$txt['shd_attachments_mode_ticket'] = 'Attached to tickets';
$txt['shd_attachments_mode_reply'] = 'Attached to individual replies';

// ============================================================================
// Options - Admin section
// ============================================================================
$txt['shd_admin_options_admin'] = 'Admin Options';

$txt['shd_maintenance_mode'] = 'Enable maintenance mode';
$txt['shd_maintenance_mode_desc'] = 'When enabled, only forum administrators and helpdesk admins can access the helpdesk.';

$txt['shd_staff_ticket_self'] = 'Staff can assign tickets to themselves';
$txt['shd_staff_ticket_self_desc'] = 'If enabled, staff members can assign a ticket to themselves without admin intervention.';

$txt['shd_admins_not_assignable'] = 'Exclude admins from assignee list';
$txt['shd_admins_not_assignable_desc'] = 'If enabled, forum administrators will not appear in the ticket assignment dropdown.';

$txt['shd_privacy_display'] = 'Privacy indicator display';
$txt['shd_privacy_display_desc'] = 'Controls when the privacy indicator is shown on tickets.';
$txt['shd_privacy_display_smart'] = 'Smart (only when relevant)';
$txt['shd_privacy_display_always'] = 'Always show';

$txt['shd_disable_relationships'] = 'Disable ticket relationships';
$txt['shd_disable_relationships_desc'] = 'If enabled, the ability to link, duplicate, or set parent/child relationships between tickets is disabled.';

// ============================================================================
// Options - Action Log section
// ============================================================================
$txt['shd_admin_options_log'] = 'Action Log Options';

$txt['shd_disable_action_log'] = 'Disable action log entirely';
$txt['shd_disable_action_log_desc'] = 'If enabled, no helpdesk actions will be recorded in the action log.';

$txt['shd_logopt_newposts'] = 'Log new tickets and replies';
$txt['shd_logopt_newposts_desc'] = 'Record when new tickets are created or new replies are posted.';

$txt['shd_logopt_editposts'] = 'Log edits to tickets and replies';
$txt['shd_logopt_editposts_desc'] = 'Record when tickets or replies are edited.';

$txt['shd_logopt_resolve'] = 'Log resolve/unresolve actions';
$txt['shd_logopt_resolve_desc'] = 'Record when tickets are resolved or reopened.';

$txt['shd_logopt_assign'] = 'Log assignment changes';
$txt['shd_logopt_assign_desc'] = 'Record when tickets are assigned to or unassigned from staff members.';

$txt['shd_logopt_privacy'] = 'Log privacy changes';
$txt['shd_logopt_privacy_desc'] = 'Record when a ticket is marked as private or returned to public.';

$txt['shd_logopt_urgency'] = 'Log urgency changes';
$txt['shd_logopt_urgency_desc'] = 'Record when the urgency level of a ticket is increased or decreased.';

$txt['shd_logopt_delete'] = 'Log deletions';
$txt['shd_logopt_delete_desc'] = 'Record when tickets or replies are soft-deleted (sent to the recycle bin).';

$txt['shd_logopt_restore'] = 'Log restorations';
$txt['shd_logopt_restore_desc'] = 'Record when tickets or replies are restored from the recycle bin.';

$txt['shd_logopt_permadelete'] = 'Log permanent deletions';
$txt['shd_logopt_permadelete_desc'] = 'Record when tickets or replies are permanently deleted.';

$txt['shd_logopt_relationships'] = 'Log relationship changes';
$txt['shd_logopt_relationships_desc'] = 'Record when ticket relationships (linked, duplicated, parent/child) are created or removed.';

$txt['shd_logopt_move_dept'] = 'Log department moves';
$txt['shd_logopt_move_dept_desc'] = 'Record when a ticket is moved from one department to another.';

// ============================================================================
// Other admin area strings (shared across admin pages)
// ============================================================================
$txt['shd_admin_maint_title'] = 'Helpdesk Maintenance';
$txt['shd_admin_maint_desc'] = 'Here you can perform maintenance tasks on your helpdesk.';

$txt['shd_admin_plugins_title'] = 'Helpdesk Plugins';
$txt['shd_admin_plugins_desc'] = 'Here you can manage SimpleDesk plugins.';

// Permission strings
$txt['permissiongroup_simpledesk'] = 'SimpleDesk';
$txt['permissionname_admin_helpdesk'] = 'Administer the helpdesk';
$txt['permissionhelp_admin_helpdesk'] = 'This permission allows users to access the helpdesk administration panel.';

// Admin sidebar labels (used in SimpleDesk.english.php as well, duplicated here for completeness)
$txt['shd_admin_options_general'] = 'General Options';
$txt['shd_admin_options_notify'] = 'Notification Options';

// ============================================================================
// Department administration
// ============================================================================
$txt['shd_admin_departments_title'] = 'Department Management';
$txt['shd_admin_departments_desc'] = 'Departments allow you to separate tickets into distinct areas, each with their own roles and permissions. From here you can create, edit, reorder, and delete departments.';

// Department list table headings
$txt['shd_admin_dept_name'] = 'Department Name';
$txt['shd_admin_dept_board_cat'] = 'Board Index Category';
$txt['shd_admin_dept_roles'] = 'Roles';
$txt['shd_admin_dept_move'] = 'Move';
$txt['shd_admin_dept_actions'] = 'Actions';

// Department list labels
$txt['shd_admin_dept_none'] = 'No departments have been created yet.';
$txt['shd_admin_dept_no_roles'] = 'No roles assigned';
$txt['shd_admin_dept_edit'] = 'Edit';
$txt['shd_admin_dept_create'] = 'Create New Department';
$txt['shd_admin_move_up'] = 'Up';
$txt['shd_admin_move_down'] = 'Down';

// Category display
$txt['shd_dept_no_cat'] = '(No category)';
$txt['shd_dept_cat_none'] = '(No category)';

// Department creation
$txt['shd_admin_dept_create_title'] = 'Create New Department';
$txt['shd_admin_dept_create_desc'] = 'Fill in the details below to create a new department. After creation you will be able to assign roles to it.';
$txt['shd_admin_dept_create_btn'] = 'Create Department';

// Department form fields
$txt['shd_admin_dept_name_desc'] = 'The display name for this department.';
$txt['shd_admin_dept_description'] = 'Description';
$txt['shd_admin_dept_description_desc'] = 'A brief description of this department, shown in the department listing.';
$txt['shd_admin_dept_board_cat_desc'] = 'If set, this department will appear within the selected board index category.';
$txt['shd_admin_dept_beforeafter'] = 'Board Position';
$txt['shd_admin_dept_beforeafter_desc'] = 'Whether this department appears before or after the boards in the selected category.';
$txt['shd_admin_dept_before_boards'] = 'Before boards';
$txt['shd_admin_dept_after_boards'] = 'After boards';
$txt['shd_admin_dept_autoclose'] = 'Auto-close Days';
$txt['shd_admin_dept_autoclose_desc'] = 'Number of days of inactivity before a ticket in this department is automatically closed. Set to 0 to disable.';

// Department edit
$txt['shd_admin_dept_edit_title'] = 'Edit Department: %1$s';
$txt['shd_admin_dept_settings'] = 'Department Settings';

// Department roles section (edit page)
$txt['shd_admin_dept_roles_section'] = 'Role Assignments';
$txt['shd_admin_dept_roles_desc'] = 'Check the roles that should have access to this department. Users in those roles will be able to interact with tickets in this department according to their role permissions.';
$txt['shd_admin_dept_no_roles_available'] = 'No roles have been created yet. Create roles in the Permissions area first.';
$txt['shd_admin_dept_role_name'] = 'Role Name';
$txt['shd_admin_dept_role_type'] = 'Role Type';

// Department save/delete
$txt['shd_admin_dept_save'] = 'Save';
$txt['shd_admin_dept_delete'] = 'Delete Department';
$txt['shd_admin_dept_delete_confirm'] = 'Are you sure you want to delete this department? This cannot be undone.';

// Department error messages
$txt['shd_admin_dept_not_found'] = 'The department you requested could not be found.';
$txt['shd_admin_dept_no_name'] = 'You must provide a name for the department.';
$txt['shd_admin_dept_cannot_delete_last'] = 'You cannot delete the last remaining department. There must always be at least one department.';
$txt['shd_admin_dept_has_tickets'] = 'This department cannot be deleted because it still contains tickets. Move or delete the tickets first.';

// ============================================================================
// Custom field administration
// ============================================================================

// Custom fields list page
$txt['shd_admin_custom_fields_title'] = 'Custom Fields';
$txt['shd_admin_custom_fields_desc'] = 'Custom fields let you add additional data fields to tickets and replies. You can configure their type, visibility, and which departments they appear in.';
$txt['shd_admin_new_custom_field'] = 'Create New Custom Field';
$txt['shd_admin_custom_field_new'] = 'New Custom Field';
$txt['shd_admin_custom_field_edit'] = 'Edit';
$txt['shd_admin_custom_field_none'] = 'No custom fields have been created yet.';

// Custom field table column headers
$txt['shd_admin_custom_field_name'] = 'Field Name';
$txt['shd_admin_custom_field_type'] = 'Type';
$txt['shd_admin_custom_field_location'] = 'Location';
$txt['shd_admin_custom_field_visibility'] = 'Visible To';

// Custom field type labels
$txt['shd_cf_type_text'] = 'Text';
$txt['shd_cf_type_largetext'] = 'Large Text';
$txt['shd_cf_type_int'] = 'Integer';
$txt['shd_cf_type_float'] = 'Decimal';
$txt['shd_cf_type_select'] = 'Select';
$txt['shd_cf_type_checkbox'] = 'Checkbox';
$txt['shd_cf_type_radio'] = 'Radio';
$txt['shd_cf_type_multi'] = 'Multi-select';
$txt['shd_cf_type_unknown'] = 'Unknown';

// Custom field location labels
$txt['shd_cf_loc_ticket'] = 'Ticket';
$txt['shd_cf_loc_reply'] = 'Reply';
$txt['shd_cf_loc_both'] = 'Both';

// Custom field status labels
$txt['shd_cf_active'] = 'Active';
$txt['shd_cf_inactive'] = 'Inactive';
$txt['shd_admin_custom_field_active'] = 'Active';
$txt['shd_admin_custom_field_inactive'] = 'Inactive';

// Custom field form labels
$txt['shd_admin_custom_field_desc'] = 'Description';
$txt['shd_admin_custom_field_maxlength'] = 'Maximum Length';
$txt['shd_admin_custom_field_bbc'] = 'Allow BBCode';
$txt['shd_admin_custom_field_display_empty'] = 'Display when empty';
$txt['shd_admin_custom_field_placement'] = 'Placement';
$txt['shd_admin_custom_field_place_details'] = 'Ticket details';
$txt['shd_admin_custom_field_place_info'] = 'Additional information';
$txt['shd_admin_custom_field_place_prefix'] = 'Ticket prefix';
$txt['shd_admin_custom_field_place_prefixfilter'] = 'Ticket prefix (filterable)';
$txt['shd_admin_custom_field_loc_ticket'] = 'Ticket only';
$txt['shd_admin_custom_field_loc_reply'] = 'Reply only';
$txt['shd_admin_custom_field_loc_both'] = 'Ticket and reply';

// Custom field visibility options
$txt['shd_admin_custom_field_can_see_users'] = 'Users can see';
$txt['shd_admin_custom_field_can_edit_users'] = 'Users can edit';
$txt['shd_admin_custom_field_can_see_staff'] = 'Staff can see';
$txt['shd_admin_custom_field_can_edit_staff'] = 'Staff can edit';

// Custom field options (select/radio/multi)
$txt['shd_admin_custom_field_options'] = 'Options';
$txt['shd_admin_custom_field_default'] = 'Default';
$txt['shd_admin_custom_field_add_option'] = 'Add option';
$txt['shd_admin_custom_field_nobody'] = 'Nobody';

// Custom field textarea dimensions
$txt['shd_admin_custom_field_dimensions'] = 'Dimensions';
$txt['shd_admin_custom_field_rows'] = 'Rows';
$txt['shd_admin_custom_field_cols'] = 'Columns';

// Custom field department assignment
$txt['shd_admin_custom_field_dept_assignment'] = 'Department Assignment';
$txt['shd_admin_custom_field_dept_required'] = 'Required';

// Custom field actions
$txt['shd_admin_custom_field_save'] = 'Save Field';
$txt['shd_admin_custom_field_delete'] = 'Delete Field';
$txt['shd_admin_custom_field_delete_confirm'] = 'Are you sure you want to delete this custom field? All stored values will be lost.';
$txt['shd_admin_custom_field_cancel'] = 'Cancel';

// ============================================================================
// Canned replies administration
// ============================================================================

// Canned replies list page
$txt['shd_admin_cannedreplies_home'] = 'Canned Replies';
$txt['shd_admin_cannedreplies_desc'] = 'Canned replies are pre-written responses that staff can insert when replying to tickets. Organize them into categories for easy access.';
$txt['shd_admin_cannedreplies_no_cats'] = 'No canned reply categories have been created yet.';
$txt['shd_admin_cannedreplies_no_replies'] = 'No replies in this category.';

// Canned replies table columns
$txt['shd_admin_cannedreplies_cat'] = 'Category';
$txt['shd_admin_cannedreplies_title'] = 'Title';
$txt['shd_admin_cannedreplies_active'] = 'Active';
$txt['shd_admin_cannedreplies_vis_user'] = 'Users';
$txt['shd_admin_cannedreplies_vis_staff'] = 'Staff';
$txt['shd_admin_cannedreplies_depts'] = 'Departments';
$txt['shd_admin_cannedreplies_move'] = 'Move';
$txt['shd_admin_cannedreplies_actions'] = 'Actions';
$txt['shd_admin_cannedreplies_edit'] = 'Edit';
$txt['shd_admin_cannedreplies_replies'] = 'Replies';

// Canned reply category management
$txt['shd_admin_cannedreplies_createcat'] = 'Create Category';
$txt['shd_admin_cannedreplies_editcat'] = 'Edit Category';
$txt['shd_admin_cannedreplies_catname'] = 'Category Name';
$txt['shd_admin_cannedreplies_save'] = 'Save';
$txt['shd_admin_cannedreplies_deletecat'] = 'Delete Category';
$txt['shd_admin_cannedreplies_deletecat_confirm'] = 'Are you sure you want to delete this category? All replies within it will also be deleted.';

// Canned reply management
$txt['shd_admin_cannedreplies_addreply'] = 'Add Reply';
$txt['shd_admin_cannedreplies_editreply'] = 'Edit Reply';
$txt['shd_admin_cannedreplies_savereply'] = 'Save Reply';
$txt['shd_admin_cannedreplies_content'] = 'Reply Content';
$txt['shd_admin_cannedreplies_deletereply'] = 'Delete Reply';
$txt['shd_admin_cannedreplies_deletereply_confirm'] = 'Are you sure you want to delete this canned reply?';

// Move reply between categories
$txt['shd_admin_cannedreplies_move_between_cat'] = 'Move to Category';
$txt['shd_admin_cannedreplies_selectcat'] = 'Select destination category';

// Controller page title keys
$txt['shd_admin_canned_createcat'] = 'Create Canned Reply Category';
$txt['shd_admin_canned_editcat'] = 'Edit Canned Reply Category';
$txt['shd_admin_canned_createreply'] = 'Add Canned Reply';
$txt['shd_admin_canned_editreply'] = 'Edit Canned Reply';
$txt['shd_admin_canned_movereplycat'] = 'Move Reply to Category';

// ============================================================================
// Maintenance administration
// ============================================================================

$txt['shd_admin_maint_back'] = 'Back to Helpdesk Maintenance';

// Find and Repair
$txt['shd_admin_maint_findrepair'] = 'Find and Repair Errors';
$txt['shd_admin_maint_findrepair_desc'] = 'Sometimes, however unlikely, things get a little out of step inside the database. This operation performs an integrity check and attempts to repair any errors it encounters.';

// Reattribute
$txt['shd_admin_maint_reattribute'] = 'Reattribute User Posts';
$txt['shd_admin_maint_reattribute_desc'] = 'If a user\'s account has been removed, this allows for rejoining tickets from their old account with their new one.';
$txt['shd_admin_maint_reattribute_posts_made'] = 'Reattribute tickets and replies made by:';
$txt['shd_admin_maint_reattribute_posts_user'] = 'This user name';
$txt['shd_admin_maint_reattribute_posts_email'] = 'This email address';
$txt['shd_admin_maint_reattribute_posts_starter'] = 'Ticket Starter';
$txt['shd_admin_maint_reattribute_posts_to'] = 'And attach them to this user account:';
$txt['shd_admin_maint_reattribute_btn'] = 'Reattribute now';
$txt['shd_admin_maint_reattribute_success'] = 'All tickets and posts that could be found were reattributed. You should probably run the "Find and Repair Errors" maintenance option now.';
$txt['shd_reattribute_confirm'] = 'Are you sure you want to attribute all tickets and replies (from the previously deleted account) with %type% of "%find%" to member "%member_to%"?';
$txt['shd_reattribute_confirm_starter'] = 'Are you sure you want to attribute all ticket starters of "%find%" to member "%member_to%"?';
$txt['shd_reattribute_confirm_username'] = 'a username';
$txt['shd_reattribute_confirm_email'] = 'an email address';
$txt['shd_reattribute_cannot_find_member'] = 'The helpdesk could not find the user to reattribute tickets and replies to.';
$txt['shd_reattribute_no_email'] = 'No email address was supplied.';
$txt['shd_reattribute_no_user'] = 'No username was supplied.';
$txt['shd_reattribute_no_messages'] = 'No messages were found to be re-attributed.';
$txt['shd_reattribute_in_use'] = 'The only messages found are already listed against a current user, so no further re-attribution can be done.';

// Mass Department Move
$txt['shd_admin_maint_massdeptmove'] = 'Move Tickets Between Departments';
$txt['shd_admin_maint_massdeptmove_desc'] = 'This area allows you to mass-move tickets between departments.';
$txt['shd_admin_maint_massdeptmove_from'] = 'Move tickets from';
$txt['shd_admin_maint_massdeptmove_to'] = 'to';
$txt['shd_admin_maint_massdeptmove_success'] = 'All matching tickets were moved successfully to their new department.';
$txt['shd_admin_maint_massdeptmove_samedept'] = 'You must select different start and destination departments to move tickets to.';
$txt['shd_admin_maint_massdeptmove_open'] = 'Move open/outstanding tickets from this department';
$txt['shd_admin_maint_massdeptmove_closed'] = 'Move closed tickets from this department';
$txt['shd_admin_maint_massdeptmove_deleted'] = 'Move deleted tickets from this department';
$txt['shd_admin_maint_massdeptmove_lastupd_less'] = 'Tickets must have been last updated in the last %1$s days';
$txt['shd_admin_maint_massdeptmove_lastupd_more'] = 'Tickets must have been last updated more than %1$s days ago';

// Find/Repair result messages
$txt['shd_maint_zero_tickets'] = '%1$d ticket(s) were found with invalid ids; they have all been given new ids.';
$txt['shd_maint_zero_msgs'] = '%1$d ticket post(s) were found with invalid ids; they have all been given new ids.';
$txt['shd_maint_deleted'] = '%1$d ticket(s) had incorrect reply/deleted counts. All have been recalculated.';
$txt['shd_maint_first_last'] = '%1$d ticket(s) had incorrect first/last message associations. All have been rectified.';
$txt['shd_maint_status'] = '%1$d ticket(s) had the wrong status set. All have been rectified.';
$txt['shd_maint_starter_updater'] = '%1$d ticket(s) had the wrong starter or updater listed. All have been rectified.';
$txt['shd_maint_invalid_dept'] = '%1$d ticket(s) were in non-existent departments; all were moved to a "Recovered Tickets" department.';

// Recovered department
$txt['shd_admin_recovered_dept'] = 'Recovered Tickets';
$txt['shd_admin_recovered_dept_desc'] = 'These are tickets that were outside of existing departments. Move them to real departments, then delete this department.';

// Search maintenance
$txt['shd_maint_search_settings'] = 'Search Settings';
$txt['shd_maint_search_settings_warning'] = 'If you alter these settings, you will need to rebuild the search index.';
$txt['shd_maint_rebuild_index'] = 'Rebuild the Search Index';
$txt['shd_maint_rebuild_index_desc'] = 'If you have existing tickets or have altered the search settings below, you will need to rebuild the index. Building the search index is intensive; please leave this window open during the process.';
$txt['shd_search_min_size'] = 'Minimum number of letters to be considered a word (3-15)';
$txt['shd_search_max_size'] = 'Maximum number of letters to be considered a word (3-15)';
$txt['shd_search_prefix_size'] = 'Minimum number of letters for prefix searching (0 = disabled)';
$txt['shd_search_prefix_size_help'] = 'Prefix searching allows partial word matches (e.g. "walk" finds "walking"). Disabled by default as it makes the index significantly larger.';
$txt['shd_search_charset'] = 'Characters to consider as valid parts of words to search';
$txt['shd_search_rebuilt'] = 'The search index has been rebuilt.';

// Template-facing maintenance strings (shd_maint_* prefix)
// These are the keys used by SimpleDeskAdminMaint.template.php

// Find and Repair
$txt['shd_maint_findrepair'] = 'Find and Repair Errors';
$txt['shd_maint_findrepair_desc'] = 'Sometimes, however unlikely, things get a little out of step inside the database. This operation performs an integrity check and attempts to repair any errors it encounters.';
$txt['shd_maint_findrepair_go'] = 'Run Now';
$txt['shd_maint_findrepair_none'] = 'No errors were found. Everything looks good!';

// Reattribute
$txt['shd_maint_reattribute'] = 'Reattribute User Posts';
$txt['shd_maint_reattribute_desc'] = 'If a user\'s account has been removed, this allows you to rejoin their old tickets and replies with a new account.';
$txt['shd_maint_reattribute_type'] = 'Match posts by';
$txt['shd_maint_reattribute_email'] = 'Email Address';
$txt['shd_maint_reattribute_name'] = 'User Name';
$txt['shd_maint_reattribute_starter'] = 'Ticket Starter';
$txt['shd_maint_reattribute_from'] = 'From';
$txt['shd_maint_reattribute_from_email'] = 'Email';
$txt['shd_maint_reattribute_from_name'] = 'Name';
$txt['shd_maint_reattribute_from_starter'] = 'Ticket Starter';
$txt['shd_maint_reattribute_to'] = 'Attach to this user';
$txt['shd_maint_reattribute_go'] = 'Reattribute Now';
$txt['shd_maint_reattribute_confirm'] = 'Are you sure you want to reattribute these tickets and replies?';
$txt['shd_maint_reattribute_success'] = 'All tickets and posts that could be found were reattributed. You should run "Find and Repair Errors" now.';

// Mass Department Move
$txt['shd_maint_massdeptmove'] = 'Move Tickets Between Departments';
$txt['shd_maint_massdeptmove_desc'] = 'This area allows you to mass-move tickets between departments.';
$txt['shd_maint_massdeptmove_from'] = 'Move tickets from';
$txt['shd_maint_massdeptmove_to'] = 'to';
$txt['shd_maint_massdeptmove_status'] = 'Ticket Status';
$txt['shd_maint_massdeptmove_open'] = 'Move open/outstanding tickets';
$txt['shd_maint_massdeptmove_closed'] = 'Move closed tickets';
$txt['shd_maint_massdeptmove_deleted'] = 'Move deleted tickets';
$txt['shd_maint_massdeptmove_date'] = 'Date Range';
$txt['shd_maint_massdeptmove_date_desc'] = 'Optionally limit the move to tickets updated within a date range (leave blank for no limit).';
$txt['shd_maint_massdeptmove_date_from'] = 'From';
$txt['shd_maint_massdeptmove_date_to'] = 'To';
$txt['shd_maint_massdeptmove_go'] = 'Move Tickets';
$txt['shd_maint_massdeptmove_confirm'] = 'Are you sure you want to move these tickets?';
$txt['shd_maint_massdeptmove_success'] = 'All matching tickets were moved successfully to their new department.';

// Back link
$txt['shd_maint_back'] = 'Back to Helpdesk Maintenance';

// Search maintenance (template-facing keys)
$txt['shd_maint_search_rebuild'] = 'Rebuild the Search Index';
$txt['shd_maint_search_rebuild_desc'] = 'If you have existing tickets or have altered the search settings below, you will need to rebuild the index. Building the search index is intensive; please leave this window open during the process.';
$txt['shd_maint_search_rebuild_go'] = 'Rebuild Now';
$txt['shd_maint_search_rebuilt'] = 'The search index has been rebuilt.';
$txt['shd_maint_search_min_size'] = 'Minimum word length (3-15 characters)';
$txt['shd_maint_search_min_size_desc'] = 'Words shorter than this will not be indexed or searchable.';
$txt['shd_maint_search_max_size'] = 'Maximum word length (3-15 characters)';
$txt['shd_maint_search_max_size_desc'] = 'Words longer than this will not be indexed or searchable.';
$txt['shd_maint_search_prefix_size'] = 'Prefix search length (0 = disabled)';
$txt['shd_maint_search_prefix_size_desc'] = 'Prefix searching allows partial word matches (e.g. "walk" finds "walking"). Disabled by default as it increases index size significantly.';
$txt['shd_maint_search_charset'] = 'Valid word characters';
$txt['shd_maint_search_charset_desc'] = 'Characters considered to be valid parts of words for search indexing.';
$txt['shd_maint_search_rebuild_warning'] = 'If you change these settings, you will need to rebuild the search index.';

// ============================================================================
// Plugin administration
// ============================================================================

$txt['shd_admin_plugins_homedesc'] = 'This area allows you to manage any additional components for SimpleDesk. They are installed through the Package Manager and configured from here.';
$txt['shd_admin_plugins_none'] = 'No plugins are currently installed.';
$txt['shd_admin_plugins_writtenby'] = 'Written by';
$txt['shd_admin_plugins_website'] = 'Website';
$txt['shd_admin_plugins_wrong_version'] = 'Not supported by this version!';
$txt['shd_admin_plugins_versions_avail'] = 'Supported versions';
$txt['shd_admin_plugins_on'] = 'On';
$txt['shd_admin_plugins_off'] = 'Off';
$txt['shd_admin_plugins_enabled'] = 'Enabled';
$txt['shd_admin_plugins_disabled'] = 'Disabled';
$txt['shd_admin_plugins_languages'] = 'Available languages';
$txt['shd_admin_plugins_lang_english'] = 'English (US)';

// Board index integration
$txt['shd_open_ticket'] = 'open ticket';
$txt['shd_open_tickets'] = 'open tickets';

// Standalone mode
$txt['shd_helpdesk_only'] = 'Enable helpdesk only mode';
$txt['shd_helpdesk_only_note'] = 'Disables access to topics and boards, leaving only the helpdesk active.';
$txt['shd_disable_pm'] = 'Disable private messages entirely';
$txt['shd_disable_mlist'] = 'Disable the memberlist entirely';

// Notification options
$txt['shd_notify_email'] = 'Email address to use in notifications (leave blank for forum default: %1$s)';
$txt['shd_notify_log'] = 'Log notifications being sent';
$txt['shd_notify_with_body'] = 'Send the ticket/reply content in notification emails';
$txt['shd_notify_new_ticket'] = 'Allow staff to receive notifications on new tickets';
$txt['shd_notify_new_reply_own'] = 'Allow users to receive notifications when their tickets are replied to';
$txt['shd_notify_new_reply_assigned'] = 'Allow staff to receive notifications when tickets assigned to them are replied to';
$txt['shd_notify_new_reply_previous'] = 'Allow staff to receive notifications when tickets they replied to are replied to again';
$txt['shd_notify_new_reply_any'] = 'Allow staff to receive notifications when any tickets are replied to';
$txt['shd_notify_assign_me'] = 'Allow staff to receive notifications when a ticket is assigned to them';
$txt['shd_notify_assign_own'] = 'Allow users to receive notifications when their tickets are assigned to staff';

// Action log administration
$txt['shd_admin_actionlog_title'] = 'Helpdesk Action Log';
$txt['shd_admin_actionlog_action'] = 'Action';
$txt['shd_admin_actionlog_date'] = 'Date';
$txt['shd_admin_actionlog_member'] = 'Member';
$txt['shd_admin_actionlog_position'] = 'Position';
$txt['shd_admin_actionlog_ip'] = 'IP';
$txt['shd_admin_actionlog_none'] = 'No entries were found.';
$txt['shd_admin_actionlog_removeall'] = 'Empty out the entire log';
$txt['shd_admin_actionlog_removeall_confirm'] = 'This will permanently delete all entries in the action log older than %s hours. Are you sure?';
$txt['shd_admin_actionlog_unknown'] = 'Unknown';
$txt['shd_admin_actionlog_hidden'] = 'Hidden';
$txt['shd_delete_item'] = 'Delete this log item';

// Admin log tab and strings
$txt['shd_admin_actionlog'] = 'Action Log';
$txt['shd_admin_adminlog'] = 'Admin Log';
$txt['shd_admin_adminlog_desc'] = 'This is a list of all admin actions, such as changed options, canned replies, department changes.';
$txt['shd_admin_adminlog_title'] = 'Helpdesk Admin Log';
$txt['shd_admin_adminlog_action'] = 'Action';
$txt['shd_admin_adminlog_name'] = 'Name';
$txt['shd_admin_adminlog_to'] = 'To';
$txt['shd_admin_adminlog_from'] = 'From';
$txt['shd_admin_adminlog_setting'] = 'Setting';
$txt['shd_admin_default_state_on'] = 'Checked';
$txt['shd_admin_default_state_off'] = 'Not checked';

// Attachment manager integration
$txt['attachment_manager_shd_attach'] = 'Helpdesk attachments';
$txt['attachment_manager_shd_thumb'] = 'Helpdesk thumbnails';
$txt['attachment_manager_shd_attach_no_entries'] = 'There are currently no helpdesk attachments.';
$txt['attachment_manager_shd_thumb_no_entries'] = 'There are currently no helpdesk thumbnails.';

// Display ticket log option
$txt['shd_display_ticket_logs'] = 'Display a mini action log in each ticket';
$txt['shd_logopt_autoclose'] = 'Log tickets closed automatically by the helpdesk';
$txt['shd_logopt_monitor'] = 'Log tickets being added to monitor/ignore lists';
$txt['shd_logopt_cfchanges'] = 'Log changes to custom fields on tickets and replies';
$txt['shd_logopt_tickettopicmove'] = 'Log tickets being moved to topics and back';
$txt['shd_disable_boardint'] = 'Disable Board Index Integration';
$txt['shd_disable_boardint_note'] = 'Disable helpdesk departments from showing on the board index.';
