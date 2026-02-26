<?php
/**
 * SimpleDesk Helpdesk - Main Language File (English)
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

// General strings
$txt['shd_helpdesk'] = 'Helpdesk';
$txt['shd_helpdesk_maintenance'] = 'The helpdesk is currently in <strong>maintenance mode</strong>. Only forum and helpdesk administrators can see this.';
$txt['shd_open_ticket'] = 'open ticket';
$txt['shd_open_tickets'] = 'open tickets';
$txt['shd_none'] = 'None';
$txt['shd_unknown'] = 'Unknown';
$txt['shd_display_nojs'] = 'JavaScript is not enabled in your browser. Some functions may not work properly.';

// Admin panel strings
$txt['shd_admin_welcome'] = 'Welcome to the main helpdesk administration center!';
$txt['shd_admin_title'] = 'Helpdesk';
$txt['shd_staff_list'] = 'Helpdesk staff';
$txt['shd_admin_info'] = 'Information';
$txt['shd_admin_options'] = 'Options';
$txt['shd_admin_custom_fields'] = 'Custom Fields';
$txt['shd_admin_departments'] = 'Departments';
$txt['shd_admin_permissions'] = 'Permissions';
$txt['shd_admin_plugins'] = 'Plugins';
$txt['shd_admin_cannedreplies'] = 'Canned Replies';
$txt['shd_admin_maint'] = 'Maintenance';

// Urgency levels
$txt['shd_urgency_0'] = 'Low';
$txt['shd_urgency_1'] = 'Medium';
$txt['shd_urgency_2'] = 'High';
$txt['shd_urgency_3'] = 'Very High';
$txt['shd_urgency_4'] = 'Severe';
$txt['shd_urgency_5'] = 'Critical';
$txt['shd_urgency_increase'] = 'Increase';
$txt['shd_urgency_decrease'] = 'Decrease';

// Status strings
$txt['shd_status_0'] = 'New';
$txt['shd_status_1'] = 'Pending Staff Comment';
$txt['shd_status_2'] = 'Pending User Comment';
$txt['shd_status_3'] = 'Resolved/Closed';
$txt['shd_status_4'] = 'Referred to Supervisor';
$txt['shd_status_5'] = 'Escalated - Urgent';
$txt['shd_status_6'] = 'Deleted';
$txt['shd_status_7'] = 'On Hold';

// Status headings
$txt['shd_status_0_heading'] = 'New Tickets';
$txt['shd_status_1_heading'] = 'Tickets Awaiting Staff Response';
$txt['shd_status_2_heading'] = 'Tickets Awaiting User Response';
$txt['shd_status_3_heading'] = 'Closed Tickets';
$txt['shd_status_4_heading'] = 'Tickets Referred to Supervisor';
$txt['shd_status_5_heading'] = 'Urgent Tickets';
$txt['shd_status_6_heading'] = 'Recycled Tickets';
$txt['shd_status_7_heading'] = 'On Hold Tickets';
$txt['shd_status_assigned_heading'] = 'Assigned to Me';
$txt['shd_status_withdeleted_heading'] = 'Tickets with Deleted Replies';

// Ticket types
$txt['shd_tickets_open'] = 'Open Tickets';
$txt['shd_tickets_closed'] = 'Closed Tickets';
$txt['shd_tickets_recycled'] = 'Recycled Tickets';
$txt['shd_assigned'] = 'Assigned';
$txt['shd_unassigned'] = 'Unassigned';
$txt['shd_read_ticket'] = 'Read Ticket';
$txt['shd_unread_ticket'] = 'Unread Ticket';
$txt['shd_unread_tickets'] = 'Unread Tickets';
$txt['shd_owned'] = 'Owned Ticket';
$txt['shd_count_ticket_1'] = 'ticket';
$txt['shd_count_tickets'] = 'tickets';

// Errors
$txt['cannot_access_helpdesk'] = 'You are not permitted to access the helpdesk.';
$txt['shd_no_ticket'] = 'The ticket you have requested does not appear to exist.';
$txt['shd_no_reply'] = 'The ticket reply you have requested does not appear to exist.';
$txt['shd_ticket_no_perms'] = 'You do not have permission to view that ticket.';
$txt['shd_error_no_tickets'] = 'No tickets were found.';
$txt['shd_inactive'] = 'The helpdesk is currently deactivated.';
$txt['shd_cannot_assign'] = 'You are not permitted to assign tickets.';
$txt['shd_cannot_resolve'] = 'You do not have permission to mark this ticket as resolved.';
$txt['shd_cannot_unresolve'] = 'You do not have permission to reopen a resolved ticket.';
$txt['shd_cannot_change_privacy'] = 'You do not have permission to alter the privacy on this ticket.';
$txt['shd_cannot_change_urgency'] = 'You do not have permission to alter the urgency on this ticket.';
$txt['shd_cannot_delete_ticket'] = 'You are not permitted to delete this ticket.';
$txt['shd_cannot_delete_reply'] = 'You are not permitted to delete that reply.';
$txt['shd_cannot_restore_ticket'] = 'You are not permitted to restore this ticket.';
$txt['shd_cannot_restore_reply'] = 'You are not permitted to restore that reply.';
$txt['shd_cannot_view_resolved'] = 'You are not permitted to access resolved tickets.';
$txt['cannot_shd_access_recyclebin'] = 'You cannot access the recycle bin.';
$txt['cannot_shd_new_ticket'] = 'You do not have permission to create a new ticket.';
$txt['cannot_shd_edit_ticket'] = 'You do not have permission to edit this ticket.';
$txt['shd_cannot_reply_any'] = 'You do not have permission to reply to any tickets.';
$txt['shd_cannot_edit_reply_any'] = 'You do not have permission to edit any replies.';
$txt['shd_cannot_edit_closed'] = 'You cannot edit resolved tickets; you need to mark it unresolved first.';
$txt['shd_cannot_reply_closed'] = 'You cannot reply to resolved tickets; you need to mark them unresolved first.';
$txt['shd_cannot_edit_deleted'] = 'You cannot edit tickets in the recycle bin.';
$txt['shd_cannot_reply_deleted'] = 'You cannot reply to tickets in the recycle bin.';
$txt['shd_cannot_move_dept'] = 'You cannot move this ticket, there is nowhere to move it to.';
$txt['shd_no_perm_move_dept'] = 'You are not permitted to move this ticket to another department.';
$txt['error_no_dept'] = 'You did not select a department to post this ticket into.';
$txt['error_invalid_fields'] = 'The following fields have values that cannot be used: %1$s';
$txt['error_missing_fields'] = 'The following fields were not completed and need to be: %1$s';
$txt['shd_ticket_unavailable'] = 'This ticket is currently not available for modification.';

// Main helpdesk
$txt['shd_home'] = 'Helpdesk';
$txt['shd_departments'] = 'Departments';
$txt['shd_new_ticket'] = 'Post New Ticket';
$txt['shd_new_ticket_proxy'] = 'Post Proxy Ticket';
$txt['shd_helpdesk_profile'] = 'Helpdesk Profile';
$txt['shd_welcome'] = 'Welcome, %s!';
$txt['shd_go'] = 'Go!';
$txt['shd_go_to_ticket'] = 'Go to ticket';
$txt['shd_options'] = 'Options';
$txt['shd_search_menu'] = 'Search';

// Ticket listing columns
$txt['shd_ticket'] = 'Ticket';
$txt['shd_ticket_name'] = 'Subject';
$txt['shd_ticket_started_by'] = 'Started By';
$txt['shd_ticket_replies'] = 'Replies';
$txt['shd_ticket_status'] = 'Status';
$txt['shd_ticket_urgency'] = 'Urgency';
$txt['shd_ticket_assigned'] = 'Assigned To';
$txt['shd_ticket_updated'] = 'Last Updated';
$txt['shd_ticket_private'] = 'Private';

// Greetings
$txt['shd_user_greeting'] = 'Here you can file new tickets for the site staff to action, and check on current tickets already underway.';
$txt['shd_staff_greeting'] = 'Here are all the tickets that require attention.';

// Ticket display
$txt['shd_ticket_details'] = 'Ticket Details';
$txt['shd_ticket_poster'] = 'Posted by';
$txt['shd_ticket_date'] = 'Posted on';
$txt['shd_ticket_dept'] = 'Department';
$txt['shd_ticket_id'] = 'Ticket ID';
$txt['shd_back_to_helpdesk'] = 'Back to Helpdesk';
$txt['shd_back_to_ticket'] = 'Back to Ticket';
$txt['shd_reply'] = 'Reply';
$txt['shd_post_a_reply'] = 'Post a Reply';
$txt['shd_ticket_log'] = 'Ticket Log';
$txt['shd_ticket_log_date'] = 'Date';
$txt['shd_ticket_log_member'] = 'Member';
$txt['shd_ticket_log_action'] = 'Action';
$txt['shd_ticket_log_none'] = 'No log entries for this ticket.';
$txt['shd_ticket_has_been_assigned'] = 'This ticket is assigned to %1$s.';
$txt['shd_ticket_is_private'] = 'This ticket is marked as private.';
$txt['shd_ticket_is_not_private'] = 'Not Private';
$txt['shd_ticket_is_closed'] = 'This ticket has been resolved.';
$txt['shd_ticket_is_deleted'] = 'This ticket has been deleted.';
$txt['shd_reply_deleted_warning'] = 'This reply has been deleted.';
$txt['shd_ticket_replies_header'] = 'Replies';
$txt['shd_modified_by'] = 'Last modified by %1$s on %2$s';
$txt['shd_quote'] = 'Quote';
$txt['shd_mark_unread'] = 'Mark Unread';
$txt['shd_staff'] = 'Staff';
$txt['shd_permadelete'] = 'Permanently Delete';

// Posting
$txt['shd_post_ticket'] = 'Post Ticket';
$txt['shd_edit_ticket'] = 'Edit Ticket';
$txt['shd_post_reply'] = 'Post Reply';
$txt['shd_edit_reply'] = 'Edit Reply';
$txt['shd_ticket_subject'] = 'Subject';
$txt['shd_ticket_body'] = 'Message';
$txt['shd_ticket_department'] = 'Department';

// Actions
$txt['shd_ticket_resolved'] = 'Resolved';
$txt['shd_ticket_unresolved'] = 'Reopened';
$txt['shd_reply_ticket'] = 'Reply';
$txt['shd_resolve_ticket'] = 'Resolve';
$txt['shd_unresolve_ticket'] = 'Unresolve';
$txt['shd_delete_ticket'] = 'Delete';
$txt['shd_restore_ticket'] = 'Restore';
$txt['shd_delete_reply'] = 'Delete Reply';
$txt['shd_restore_reply'] = 'Restore Reply';
$txt['shd_assign_ticket'] = 'Assign';
$txt['shd_move_dept'] = 'Move Department';
$txt['shd_mark_private'] = 'Mark Private';
$txt['shd_mark_not_private'] = 'Remove Private';

// Navigation
$txt['shd_nav_helpdesk'] = 'Helpdesk';
$txt['shd_nav_back'] = 'Back';

// Block titles (ticket listing) - used as $context['ticket_blocks'][x]['title'] keys
$txt['shd_tickets_assigned'] = 'Assigned to Me';
$txt['shd_tickets_new'] = 'New Tickets';
$txt['shd_tickets_pending_staff'] = 'Awaiting Staff Response';
$txt['shd_tickets_waiting_staff'] = 'Waiting for Staff';
$txt['shd_tickets_pending_user'] = 'Awaiting User Response';
$txt['shd_tickets_waiting_user'] = 'Waiting for Your Reply';
$txt['shd_tickets_hold'] = 'On Hold';
$txt['shd_tickets_deleted'] = 'Deleted Tickets';
$txt['shd_tickets_with_deleted_replies'] = 'Tickets with Deleted Replies';

// Ticket listing
$txt['shd_num_tickets'] = '%1$d tickets';
$txt['shd_num_ticket'] = '1 ticket';
$txt['shd_no_tickets_in_block'] = 'There are no tickets in this category.';
$txt['shd_view_all_in_block'] = 'View all';
$txt['shd_go_to_ticket'] = 'Go to ticket';
$txt['shd_go_to_ticket_number'] = 'Go to ticket #:';
$txt['shd_sort_by'] = 'Sort by';
$txt['shd_sort_ticket_id'] = 'Ticket ID';
$txt['shd_sort_ticket_name'] = 'Subject';
$txt['shd_sort_replies'] = 'Replies';
$txt['shd_sort_urgency'] = 'Urgency';
$txt['shd_sort_updated'] = 'Last Updated';
$txt['shd_sort_assigned'] = 'Assigned To';
$txt['shd_sort_status'] = 'Status';
$txt['shd_sort_starter'] = 'Started By';

// Department list
$txt['shd_dept_ticket_count'] = '%1$d open tickets';
$txt['shd_dept_no_tickets'] = 'No open tickets';
$txt['shd_no_departments'] = 'No departments are available.';

// Posting
$txt['shd_errors_occurred'] = 'The following errors occurred:';
$txt['shd_ticket_notprivate'] = 'Not Private';
$txt['shd_ticket_user'] = 'User';
$txt['shd_ticket_assigned_to'] = 'Assigned to';
$txt['shd_ticket_display_id'] = 'Ticket ID';
$txt['shd_additional_details'] = 'Additional Details';
$txt['shd_additional_information'] = 'Additional Information';
$txt['shd_disable_smileys_post'] = 'Turn off smileys in this post';
$txt['shd_resolve_this_ticket'] = 'Resolve this ticket when saving';
$txt['shd_attach'] = 'Attach';
$txt['shd_attach_restrictions'] = 'Restrictions';
$txt['shd_ticket_attachments'] = 'Attachments';
$txt['shd_delete_attach'] = 'Delete';
$txt['shd_delete_attach_confirm'] = 'Are you sure you want to delete this attachment?';
$txt['shd_no_attachments_yet'] = 'No attachments.';
$txt['shd_attachments_existing'] = 'Existing Attachments';
$txt['shd_attachments_existing_uncheck'] = 'Uncheck to remove:';
$txt['shd_attach_add_file'] = 'Add file';
$txt['shd_attach_allowed_types'] = 'Allowed file types';
$txt['shd_proxy_post_for'] = 'Post on behalf of';
$txt['shd_proxy_post_for_desc'] = 'Enter the username of the member you are posting this ticket for.';
$txt['shd_save'] = 'Save';
$txt['shd_ticket_hold'] = 'On Hold';

// Posting errors
$txt['shd_no_subject'] = 'You must enter a subject for this ticket.';
$txt['shd_no_message'] = 'You must enter a message.';
$txt['shd_ticket_proxy_for_info'] = 'This ticket is being created on behalf of %1$s.';

// Assignment
$txt['shd_ticket_assign_ticket'] = 'Assign Ticket';
$txt['shd_ticket_assignedto'] = 'Currently Assigned To';
$txt['shd_ticket_assign_to'] = 'Assign To';
$txt['shd_no_staff_assign'] = 'There are no staff members available to assign this ticket to.';
$txt['shd_cannot_assign_other'] = 'This ticket is already assigned to another staff member.';
$txt['shd_assigned_not_permitted'] = 'The user you tried to assign this ticket to is not permitted to handle it.';
$txt['shd_cancel_ticket'] = 'Cancel';
$txt['shd_cancel_home'] = 'Cancel';

// Move department
$txt['shd_ticket_move'] = 'Move Ticket';
$txt['shd_ticket_move_dept'] = 'Move Department';
$txt['shd_ticket_move_to'] = 'Move To';
$txt['shd_current_dept'] = 'Current Department';
$txt['shd_move_send_pm'] = 'Notify ticket starter via PM';
$txt['shd_move_why'] = 'Please enter a brief reason for the move, to be sent to the ticket starter.';
$txt['shd_move_dept_default'] = 'Dear {user},\n\nYour ticket, {subject}, has been moved from the {current_dept} department to the {new_dept} department.\n\nYou can view your ticket at:\n{link}';
$txt['shd_ticket_moved_subject'] = 'Your ticket has been moved';
$txt['shd_move_no_pm'] = 'You chose to send a PM but did not enter a message.';
$txt['shd_user_no_hd_access'] = 'The ticket starter will not be able to see the ticket after moving.';
$txt['shd_user_helpdesk_access'] = 'The ticket starter will be able to see the ticket after moving.';
$txt['shd_user_hd_access_dept_1'] = 'The ticket starter can see the ticket in: ';
$txt['shd_user_hd_access_dept'] = 'The ticket starter can see the ticket in these departments: ';

// Relationships
$txt['shd_relationships_are_disabled'] = 'Ticket relationships are disabled.';
$txt['shd_cannot_relate_self'] = 'You cannot create a relationship between a ticket and itself.';
$txt['shd_invalid_relation'] = 'Invalid relationship type specified.';
$txt['shd_no_relation_delete'] = 'There is no relationship to delete.';

// Resolve errors
$txt['error_shd_cannot_resolve_children'] = 'You cannot resolve this ticket while it has open child tickets.';

// Search strings
$txt['shd_search'] = 'Search Tickets';
$txt['shd_search_results'] = 'Search Tickets - Results';
$txt['shd_search_text'] = 'Words you are looking for:';
$txt['shd_search_match'] = 'What should be matched?';
$txt['shd_search_match_all'] = 'Match all words supplied';
$txt['shd_search_match_any'] = 'Match any words supplied';
$txt['shd_search_scope'] = 'Include which types of tickets:';
$txt['shd_search_scope_open'] = 'Open tickets';
$txt['shd_search_scope_closed'] = 'Closed tickets';
$txt['shd_search_scope_recycle'] = 'Items in the recycle bin';
$txt['shd_search_result_ticket'] = 'Ticket %1$s';
$txt['shd_search_result_reply'] = 'Reply to ticket %1$s';
$txt['shd_search_last_updated'] = 'Last updated:';
$txt['shd_search_ticket_opened_by'] = 'Ticket opened by:';
$txt['shd_search_ticket_replied_by'] = 'Ticket replied to by:';
$txt['shd_search_dept'] = 'Search in which department(s):';
$txt['shd_search_urgency'] = 'Include which levels of urgency:';
$txt['shd_search_where'] = 'Which items to search:';
$txt['shd_search_where_tickets'] = 'The bodies of tickets';
$txt['shd_search_where_replies'] = 'The replies in tickets';
$txt['shd_search_where_subjects'] = 'Ticket subjects';
$txt['shd_search_ticket_starter'] = 'Tickets started by:';
$txt['shd_search_ticket_assignee'] = 'Tickets assigned to:';
$txt['shd_search_ticket_named_person'] = 'Type in the name of the person(s) you are interested in.';
$txt['shd_search_no_results'] = 'No results were found with the given criteria. You may wish to go back and try altering your search criteria.';
$txt['shd_search_criteria'] = 'Search Criteria:';
$txt['shd_search_excluded'] = 'If every possible option was selected, it has not been included in the above (e.g. if all possible levels of urgency were ticked, it is not stated above, so you can concentrate on what is specific to your search)';
$txt['shd_search_warning_nonadmin'] = 'The search facility may not list all available tickets; it is currently being investigated.';
$txt['shd_search_warning_admin'] = 'The search facility requires that its index be rebuilt. You can achieve this from the Maintenance option, in the Helpdesk area, in the administration panel.';

// Notification errors
$txt['cannot_monitor_ticket'] = 'You are not permitted to monitor this ticket.';
$txt['cannot_unmonitor_ticket'] = 'You are not permitted to unmonitor this ticket.';
$txt['shd_is_ticket_opener'] = ' (ticket opener)';

// Board index strings
$txt['shd_replies_from_ip'] = 'Helpdesk replies from IP';
$txt['shd_replies_from_ip_desc'] = 'Below is a list of all helpdesk replies from this IP address.';

// Permission role names
$txt['shd_permrole_user'] = 'User';
$txt['shd_permrole_staff'] = 'Staff';
$txt['shd_permrole_admin'] = 'Admin';
