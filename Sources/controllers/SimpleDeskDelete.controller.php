<?php
/**
 * SimpleDesk Delete Controller
 *
 * Handles deletion (recycle) and restoration of tickets and replies,
 * permanent deletion from the recycle bin, and attachment deletion.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Controller for ticket/reply deletion and restoration.
 */
class SimpleDeskDelete_Controller extends Action_Controller
{
	/**
	 * Default entry point - redirects to main helpdesk.
	 */
	public function action_index()
	{
		redirectexit('action=helpdesk');
	}

	/**
	 * Generic delete action dispatcher.
	 * Routes to action_deleteticket().
	 */
	public function action_delete()
	{
		$this->action_deleteticket();
	}

	/**
	 * Generic restore action dispatcher.
	 * Routes to action_restoreticket().
	 */
	public function action_restore()
	{
		$this->action_restoreticket();
	}

	/**
	 * Delete (recycle) a ticket.
	 *
	 * Moves the ticket to the recycle bin by setting its status to deleted
	 * and clearing the assignment. Requires shd_delete_ticket_any or
	 * shd_delete_ticket_own (if the user is the ticket starter).
	 */
	public function action_deleteticket()
	{
		global $scripturl, $user_info, $context;

		$db = database();

		checkSession('get');

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		$ticket_id = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;

		if (empty($ticket_id))
			fatal_lang_error('shd_no_ticket', false);

		// Load the ticket
		$request = shd_db_query('', '
			SELECT hdt.id_ticket, hdt.id_dept, hdt.id_member_started, hdt.subject, hdt.status
			FROM {db_prefix}helpdesk_tickets AS hdt
			WHERE hdt.id_ticket = {int:ticket}
				AND {query_see_ticket}',
			array(
				'ticket' => $ticket_id,
			)
		);

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		if (empty($row))
			fatal_lang_error('shd_no_ticket', false);

		$dept = (int) $row['id_dept'];
		$starter = (int) $row['id_member_started'];

		// Cannot delete an already-deleted ticket
		if ((int) $row['status'] == TICKET_STATUS_DELETED)
			fatal_lang_error('shd_no_ticket', false);

		// Permission check: delete any, or delete own if the user started the ticket
		if (!shd_allowed_to('shd_delete_ticket_any', $dept))
		{
			if (shd_allowed_to('shd_delete_ticket_own', $dept) && $starter == $user_info['id'])
			{
				// OK - own ticket
			}
			else
				fatal_lang_error('shd_cannot_delete_ticket', false);
		}

		// Set ticket to deleted status and unassign
		$db->query('', '
			UPDATE {db_prefix}helpdesk_tickets
			SET status = {int:status_deleted},
				id_member_assigned = {int:zero},
				last_updated = {int:time}
			WHERE id_ticket = {int:ticket}',
			array(
				'status_deleted' => TICKET_STATUS_DELETED,
				'zero' => 0,
				'time' => time(),
				'ticket' => $ticket_id,
			)
		);

		// Log the action
		shd_log_action('delete', array(
			'ticket' => $ticket_id,
			'subject' => $row['subject'],
		));

		// Clear ticket cache for the department
		shd_clear_active_tickets($dept);

		// Redirect to helpdesk home
		redirectexit($scripturl . '?action=helpdesk;sa=main');
	}

	/**
	 * Delete (recycle) a reply.
	 *
	 * Marks a reply as deleted (message_status = MSG_STATUS_DELETED).
	 * The reply cannot be the ticket's first message. Requires
	 * shd_delete_reply_any or shd_delete_reply_own (if the user authored the reply).
	 */
	public function action_deletereply()
	{
		global $scripturl, $user_info, $context;

		$db = database();

		checkSession('get');

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		$ticket_id = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;
		$reply_id = isset($_REQUEST['reply']) ? (int) $_REQUEST['reply'] : 0;

		if (empty($ticket_id) || empty($reply_id))
			fatal_lang_error('shd_no_ticket', false);

		// Load the ticket and verify the reply belongs to it
		$request = shd_db_query('', '
			SELECT hdt.id_ticket, hdt.id_dept, hdt.id_first_msg, hdt.id_member_started,
				hdt.subject, hdt.status,
				hdtr.id_msg, hdtr.id_member AS reply_member, hdtr.message_status
			FROM {db_prefix}helpdesk_tickets AS hdt
				INNER JOIN {db_prefix}helpdesk_ticket_replies AS hdtr ON (hdtr.id_ticket = hdt.id_ticket)
			WHERE hdt.id_ticket = {int:ticket}
				AND hdtr.id_msg = {int:reply}
				AND {query_see_ticket}',
			array(
				'ticket' => $ticket_id,
				'reply' => $reply_id,
			)
		);

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		if (empty($row))
			fatal_lang_error('shd_no_ticket', false);

		$dept = (int) $row['id_dept'];

		// Cannot delete the first message of a ticket (use delete ticket instead)
		if ((int) $row['id_first_msg'] == $reply_id)
			fatal_lang_error('shd_no_ticket', false);

		// Cannot delete replies on closed or deleted tickets
		if ((int) $row['status'] == TICKET_STATUS_CLOSED || (int) $row['status'] == TICKET_STATUS_DELETED)
			fatal_lang_error('shd_no_ticket', false);

		// Cannot delete an already-deleted reply
		if ((int) $row['message_status'] == MSG_STATUS_DELETED)
			fatal_lang_error('shd_no_ticket', false);

		// Permission check: delete any, or delete own if the user authored the reply
		$reply_member = (int) $row['reply_member'];

		if (!shd_allowed_to('shd_delete_reply_any', $dept))
		{
			if (shd_allowed_to('shd_delete_reply_own', $dept) && $reply_member == $user_info['id'])
			{
				// OK - own reply
			}
			else
				fatal_lang_error('shd_cannot_delete_reply', false);
		}

		// Mark the reply as deleted
		$db->query('', '
			UPDATE {db_prefix}helpdesk_ticket_replies
			SET message_status = {int:status_deleted}
			WHERE id_msg = {int:reply}',
			array(
				'status_deleted' => MSG_STATUS_DELETED,
				'reply' => $reply_id,
			)
		);

		// Log the action
		shd_log_action('delete_reply', array(
			'ticket' => $ticket_id,
			'msg' => $reply_id,
			'subject' => $row['subject'],
		));

		// Recalculate ticket counters (num_replies, deleted_replies, id_last_msg, etc.)
		list($starter_id, $replier_id, $num_replies) = shd_recalc_ids($ticket_id);

		// Determine the new ticket status after deleting a reply
		$new_status = shd_determine_status('deletereply', $starter_id, $replier_id, $num_replies, $dept);

		$db->query('', '
			UPDATE {db_prefix}helpdesk_tickets
			SET status = {int:status}
			WHERE id_ticket = {int:ticket}',
			array(
				'status' => $new_status,
				'ticket' => $ticket_id,
			)
		);

		// Clear ticket cache
		shd_clear_active_tickets($dept);

		// Redirect back to the ticket
		redirectexit($scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $ticket_id);
	}

	/**
	 * Restore a recycled ticket.
	 *
	 * Brings a deleted ticket back from the recycle bin by determining
	 * its appropriate status. Requires shd_restore_ticket_any or
	 * shd_restore_ticket_own (if the user is the ticket starter).
	 */
	public function action_restoreticket()
	{
		global $scripturl, $user_info, $context;

		$db = database();

		checkSession('get');

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		$ticket_id = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;

		if (empty($ticket_id))
			fatal_lang_error('shd_no_ticket', false);

		// Load the ticket
		$request = shd_db_query('', '
			SELECT hdt.id_ticket, hdt.id_dept, hdt.id_member_started, hdt.subject, hdt.status,
				hdt.num_replies, hdt.id_member_updated
			FROM {db_prefix}helpdesk_tickets AS hdt
			WHERE hdt.id_ticket = {int:ticket}
				AND {query_see_ticket}',
			array(
				'ticket' => $ticket_id,
			)
		);

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		if (empty($row))
			fatal_lang_error('shd_no_ticket', false);

		$dept = (int) $row['id_dept'];
		$starter = (int) $row['id_member_started'];

		// Can only restore a deleted ticket
		if ((int) $row['status'] != TICKET_STATUS_DELETED)
			fatal_lang_error('shd_no_ticket', false);

		// Permission check: restore any, or restore own if the user started the ticket
		if (!shd_allowed_to('shd_restore_ticket_any', $dept))
		{
			if (shd_allowed_to('shd_restore_ticket_own', $dept) && $starter == $user_info['id'])
			{
				// OK - own ticket
			}
			else
				fatal_lang_error('shd_cannot_restore_ticket', false);
		}

		// Recalculate IDs to get accurate counts for status determination
		list($starter_id, $replier_id, $num_replies) = shd_recalc_ids($ticket_id);

		// Determine the new status for the restored ticket
		$new_status = shd_determine_status('restoreticket', $starter_id, $replier_id, $num_replies, $dept);

		// Update the ticket status
		$db->query('', '
			UPDATE {db_prefix}helpdesk_tickets
			SET status = {int:status},
				last_updated = {int:time}
			WHERE id_ticket = {int:ticket}',
			array(
				'status' => $new_status,
				'time' => time(),
				'ticket' => $ticket_id,
			)
		);

		// Log the action
		shd_log_action('restore', array(
			'ticket' => $ticket_id,
			'subject' => $row['subject'],
		));

		// Clear ticket cache
		shd_clear_active_tickets($dept);

		// Redirect: if 'home' param is set, go to helpdesk home; otherwise go to the ticket
		if (isset($_REQUEST['home']))
			redirectexit($scripturl . '?action=helpdesk;sa=main');
		else
			redirectexit($scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $ticket_id);
	}

	/**
	 * Restore a recycled reply.
	 *
	 * Brings a deleted reply back by setting its message_status to normal.
	 * The ticket must not itself be deleted or closed. Requires
	 * shd_restore_reply_any or shd_restore_reply_own (if the user authored the reply).
	 */
	public function action_restorereply()
	{
		global $scripturl, $user_info, $context;

		$db = database();

		checkSession('get');

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		$ticket_id = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;
		$reply_id = isset($_REQUEST['reply']) ? (int) $_REQUEST['reply'] : 0;

		if (empty($ticket_id) || empty($reply_id))
			fatal_lang_error('shd_no_ticket', false);

		// Load the ticket and verify the reply belongs to it
		$request = shd_db_query('', '
			SELECT hdt.id_ticket, hdt.id_dept, hdt.id_first_msg, hdt.id_member_started,
				hdt.subject, hdt.status,
				hdtr.id_msg, hdtr.id_member AS reply_member, hdtr.message_status
			FROM {db_prefix}helpdesk_tickets AS hdt
				INNER JOIN {db_prefix}helpdesk_ticket_replies AS hdtr ON (hdtr.id_ticket = hdt.id_ticket)
			WHERE hdt.id_ticket = {int:ticket}
				AND hdtr.id_msg = {int:reply}
				AND {query_see_ticket}',
			array(
				'ticket' => $ticket_id,
				'reply' => $reply_id,
			)
		);

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		if (empty($row))
			fatal_lang_error('shd_no_ticket', false);

		$dept = (int) $row['id_dept'];

		// Reply must be deleted to restore it
		if ((int) $row['message_status'] != MSG_STATUS_DELETED)
			fatal_lang_error('shd_no_ticket', false);

		// Cannot restore replies on deleted or closed tickets
		if ((int) $row['status'] == TICKET_STATUS_DELETED || (int) $row['status'] == TICKET_STATUS_CLOSED)
			fatal_lang_error('shd_no_ticket', false);

		// Permission check: restore any, or restore own if the user authored the reply
		$reply_member = (int) $row['reply_member'];

		if (!shd_allowed_to('shd_restore_reply_any', $dept))
		{
			if (shd_allowed_to('shd_restore_reply_own', $dept) && $reply_member == $user_info['id'])
			{
				// OK - own reply
			}
			else
				fatal_lang_error('shd_cannot_restore_reply', false);
		}

		// Restore the reply
		$db->query('', '
			UPDATE {db_prefix}helpdesk_ticket_replies
			SET message_status = {int:status_normal}
			WHERE id_msg = {int:reply}',
			array(
				'status_normal' => MSG_STATUS_NORMAL,
				'reply' => $reply_id,
			)
		);

		// Log the action
		shd_log_action('restore_reply', array(
			'ticket' => $ticket_id,
			'msg' => $reply_id,
			'subject' => $row['subject'],
		));

		// Recalculate ticket counters
		list($starter_id, $replier_id, $num_replies) = shd_recalc_ids($ticket_id);

		// Determine the new ticket status after restoring a reply
		$new_status = shd_determine_status('restorereply', $starter_id, $replier_id, $num_replies, $dept);

		$db->query('', '
			UPDATE {db_prefix}helpdesk_tickets
			SET status = {int:status}
			WHERE id_ticket = {int:ticket}',
			array(
				'status' => $new_status,
				'ticket' => $ticket_id,
			)
		);

		// Clear ticket cache
		shd_clear_active_tickets($dept);

		// Redirect back to the ticket
		redirectexit($scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $ticket_id);
	}

	/**
	 * Permanently delete a ticket or reply.
	 *
	 * Removes the ticket or reply from the database entirely, including
	 * associated custom field values and search index entries.
	 * Requires the shd_delete_recycling permission.
	 */
	public function action_permadelete()
	{
		global $scripturl, $user_info, $context;

		$db = database();

		checkSession('get');

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		$ticket_id = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;
		$reply_id = isset($_REQUEST['reply']) ? (int) $_REQUEST['reply'] : 0;

		if (empty($ticket_id))
			fatal_lang_error('shd_no_ticket', false);

		// Are we deleting a reply or an entire ticket?
		if (!empty($reply_id))
			$this->_permadelete_reply($ticket_id, $reply_id);
		else
			$this->_permadelete_ticket($ticket_id);
	}

	/**
	 * Permanently deletes an entire ticket and all its messages.
	 *
	 * @param int $ticket_id The ticket to permanently delete.
	 */
	private function _permadelete_ticket($ticket_id)
	{
		global $scripturl, $user_info;

		$db = database();

		// Load the ticket
		$request = shd_db_query('', '
			SELECT hdt.id_ticket, hdt.id_dept, hdt.id_member_started, hdt.subject, hdt.status
			FROM {db_prefix}helpdesk_tickets AS hdt
			WHERE hdt.id_ticket = {int:ticket}
				AND {query_see_ticket}',
			array(
				'ticket' => $ticket_id,
			)
		);

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		if (empty($row))
			fatal_lang_error('shd_no_ticket', false);

		$dept = (int) $row['id_dept'];

		// Must have recycling permission
		shd_is_allowed_to('shd_delete_recycling', $dept);

		// Ticket must be in deleted status to permanently delete
		if ((int) $row['status'] != TICKET_STATUS_DELETED)
			fatal_lang_error('shd_no_ticket', false);

		// Get all message IDs for this ticket
		$msg_ids = array();
		$request = $db->query('', '
			SELECT id_msg
			FROM {db_prefix}helpdesk_ticket_replies
			WHERE id_ticket = {int:ticket}',
			array(
				'ticket' => $ticket_id,
			)
		);
		while ($msg_row = $db->fetch_assoc($request))
			$msg_ids[] = (int) $msg_row['id_msg'];
		$db->free_result($request);

		// Delete custom field values for replies (CFIELD_REPLY type, keyed by msg id)
		if (!empty($msg_ids))
		{
			$db->query('', '
				DELETE FROM {db_prefix}helpdesk_custom_fields_values
				WHERE id_post IN ({array_int:msgs})
					AND post_type = {int:type_reply}',
				array(
					'msgs' => $msg_ids,
					'type_reply' => CFIELD_REPLY,
				)
			);
		}

		// Delete custom field values for the ticket itself (CFIELD_TICKET type, keyed by ticket id)
		$db->query('', '
			DELETE FROM {db_prefix}helpdesk_custom_fields_values
			WHERE id_post = {int:ticket}
				AND post_type = {int:type_ticket}',
			array(
				'ticket' => $ticket_id,
				'type_ticket' => CFIELD_TICKET,
			)
		);

		// Delete the ticket record
		$db->query('', '
			DELETE FROM {db_prefix}helpdesk_tickets
			WHERE id_ticket = {int:ticket}',
			array(
				'ticket' => $ticket_id,
			)
		);

		// Delete all replies for this ticket
		$db->query('', '
			DELETE FROM {db_prefix}helpdesk_ticket_replies
			WHERE id_ticket = {int:ticket}',
			array(
				'ticket' => $ticket_id,
			)
		);

		// Delete search index entries for the messages
		if (!empty($msg_ids))
		{
			$db->query('', '
				DELETE FROM {db_prefix}helpdesk_search_ticket_words
				WHERE id_msg IN ({array_int:msgs})',
				array(
					'msgs' => $msg_ids,
				)
			);
		}

		// Delete search index entries for the ticket subject
		$db->query('', '
			DELETE FROM {db_prefix}helpdesk_search_subject_words
			WHERE id_ticket = {int:ticket}',
			array(
				'ticket' => $ticket_id,
			)
		);

		// Delete attachment mappings for this ticket
		$db->query('', '
			DELETE FROM {db_prefix}helpdesk_attachments
			WHERE id_ticket = {int:ticket}',
			array(
				'ticket' => $ticket_id,
			)
		);

		// Delete read log entries
		$db->query('', '
			DELETE FROM {db_prefix}helpdesk_log_read
			WHERE id_ticket = {int:ticket}',
			array(
				'ticket' => $ticket_id,
			)
		);

		// Delete notification overrides
		$db->query('', '
			DELETE FROM {db_prefix}helpdesk_notify_override
			WHERE id_ticket = {int:ticket}',
			array(
				'ticket' => $ticket_id,
			)
		);

		// Delete relationships involving this ticket
		$db->query('', '
			DELETE FROM {db_prefix}helpdesk_relationships
			WHERE primary_ticket = {int:ticket}
				OR secondary_ticket = {int:ticket}',
			array(
				'ticket' => $ticket_id,
			)
		);

		// Log the action
		shd_log_action('permadelete', array(
			'ticket' => $ticket_id,
			'subject' => $row['subject'],
		));

		// Clear ticket cache
		shd_clear_active_tickets($dept);

		// Redirect to the recycle bin
		redirectexit($scripturl . '?action=helpdesk;sa=recyclebin');
	}

	/**
	 * Permanently deletes a single reply from a ticket.
	 *
	 * @param int $ticket_id The ticket containing the reply.
	 * @param int $reply_id The reply to permanently delete.
	 */
	private function _permadelete_reply($ticket_id, $reply_id)
	{
		global $scripturl, $user_info;

		$db = database();

		// Load the ticket and verify the reply
		$request = shd_db_query('', '
			SELECT hdt.id_ticket, hdt.id_dept, hdt.id_first_msg, hdt.id_member_started,
				hdt.subject, hdt.status,
				hdtr.id_msg, hdtr.message_status
			FROM {db_prefix}helpdesk_tickets AS hdt
				INNER JOIN {db_prefix}helpdesk_ticket_replies AS hdtr ON (hdtr.id_ticket = hdt.id_ticket)
			WHERE hdt.id_ticket = {int:ticket}
				AND hdtr.id_msg = {int:reply}
				AND {query_see_ticket}',
			array(
				'ticket' => $ticket_id,
				'reply' => $reply_id,
			)
		);

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		if (empty($row))
			fatal_lang_error('shd_no_ticket', false);

		$dept = (int) $row['id_dept'];

		// Must have recycling permission
		shd_is_allowed_to('shd_delete_recycling', $dept);

		// Reply must already be in deleted status
		if ((int) $row['message_status'] != MSG_STATUS_DELETED)
			fatal_lang_error('shd_no_ticket', false);

		// Ticket must NOT be deleted (we're only perma-deleting a reply from an active ticket)
		if ((int) $row['status'] == TICKET_STATUS_DELETED)
			fatal_lang_error('shd_no_ticket', false);

		// Cannot permanently delete the first message (use ticket perma-delete instead)
		if ((int) $row['id_first_msg'] == $reply_id)
			fatal_lang_error('shd_no_ticket', false);

		// Delete the reply
		$db->query('', '
			DELETE FROM {db_prefix}helpdesk_ticket_replies
			WHERE id_msg = {int:reply}',
			array(
				'reply' => $reply_id,
			)
		);

		// Delete custom field values for this reply
		$db->query('', '
			DELETE FROM {db_prefix}helpdesk_custom_fields_values
			WHERE id_post = {int:reply}
				AND post_type = {int:type_reply}',
			array(
				'reply' => $reply_id,
				'type_reply' => CFIELD_REPLY,
			)
		);

		// Delete search index entries for this reply
		$db->query('', '
			DELETE FROM {db_prefix}helpdesk_search_ticket_words
			WHERE id_msg = {int:reply}',
			array(
				'reply' => $reply_id,
			)
		);

		// Delete attachment mappings for this reply
		$db->query('', '
			DELETE FROM {db_prefix}helpdesk_attachments
			WHERE id_msg = {int:reply}',
			array(
				'reply' => $reply_id,
			)
		);

		// Log the action
		shd_log_action('permadelete_reply', array(
			'ticket' => $ticket_id,
			'msg' => $reply_id,
			'subject' => $row['subject'],
		));

		// Recalculate ticket counters
		list($starter_id, $replier_id, $num_replies) = shd_recalc_ids($ticket_id);

		// Determine the new ticket status
		$new_status = shd_determine_status('permadelete_reply', $starter_id, $replier_id, $num_replies, $dept);

		$db->query('', '
			UPDATE {db_prefix}helpdesk_tickets
			SET status = {int:status}
			WHERE id_ticket = {int:ticket}',
			array(
				'status' => $new_status,
				'ticket' => $ticket_id,
			)
		);

		// Clear ticket cache
		shd_clear_active_tickets($dept);

		// Redirect back to the ticket
		redirectexit($scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $ticket_id);
	}

	/**
	 * Delete an attachment from a ticket.
	 *
	 * Removes the attachment mapping from the helpdesk_attachments table.
	 * Requires the shd_delete_attachment permission. Actual file deletion
	 * is deferred to a later phase.
	 */
	public function action_deleteattach()
	{
		global $scripturl, $user_info, $context;

		$db = database();

		checkSession('get');

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		$ticket_id = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;
		$attach_id = isset($_GET['attach']) ? (int) $_GET['attach'] : 0;

		if (empty($ticket_id) || empty($attach_id))
			fatal_lang_error('shd_no_ticket', false);

		// Verify the attachment belongs to this ticket
		$request = shd_db_query('', '
			SELECT hda.id_attach, hda.id_ticket, hda.id_msg,
				hdt.id_dept, hdt.subject,
				COALESCE(a.filename, {string:empty}) AS filename
			FROM {db_prefix}helpdesk_attachments AS hda
				INNER JOIN {db_prefix}helpdesk_tickets AS hdt ON (hdt.id_ticket = hda.id_ticket)
				LEFT JOIN {db_prefix}attachments AS a ON (a.id_attach = hda.id_attach)
			WHERE hda.id_attach = {int:attach}
				AND hda.id_ticket = {int:ticket}
				AND {query_see_ticket}',
			array(
				'attach' => $attach_id,
				'ticket' => $ticket_id,
				'empty' => '',
			)
		);

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		if (empty($row))
			fatal_lang_error('shd_no_ticket', false);

		$dept = (int) $row['id_dept'];

		// Permission check
		shd_is_allowed_to('shd_delete_attachment', $dept);

		// Log the action as an edit with attachment removal info
		shd_log_action('editticket', array(
			'ticket' => $ticket_id,
			'subject' => $row['subject'],
			'att_removed' => $row['filename'],
		));

		// Remove the attachment mapping from the helpdesk
		$db->query('', '
			DELETE FROM {db_prefix}helpdesk_attachments
			WHERE id_attach = {int:attach}',
			array(
				'attach' => $attach_id,
			)
		);

		// Redirect back to the ticket
		redirectexit($scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $ticket_id);
	}
}
