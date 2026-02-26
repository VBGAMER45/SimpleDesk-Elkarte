<?php
/**
 * SimpleDesk Misc Controller
 *
 * Handles miscellaneous ticket operations: resolve/unresolve, privacy toggle,
 * urgency change, ticket relationships, and mark-as-unread.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Controller for miscellaneous ticket actions.
 */
class SimpleDeskMisc_Controller extends Action_Controller
{
	/**
	 * Default entry point - redirects to main helpdesk.
	 */
	public function action_index()
	{
		redirectexit('action=helpdesk');
	}

	/**
	 * Resolve or unresolve a ticket.
	 *
	 * Determines whether the ticket should be resolved (closed) or unresolved
	 * based on its current status. Checks permissions, verifies no open child
	 * tickets block resolution, updates the status, logs the action, and
	 * redirects.
	 */
	public function action_resolve()
	{
		global $context, $scripturl, $user_info, $modSettings;

		$db = database();

		checkSession('get');

		if (empty($context['ticket_id']))
			$context['ticket_id'] = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;

		if (empty($context['ticket_id']))
			fatal_lang_error('shd_no_ticket', false);

		// Where to return the user after resolution
		$context['shd_return_to'] = isset($_REQUEST['home']) ? $_REQUEST['home'] : '';

		// Load the ticket
		$request = shd_db_query('', '
			SELECT hdt.id_member_started, hdt.id_member_updated, hdt.status,
				hdt.num_replies, hdt.subject, hdt.id_dept
			FROM {db_prefix}helpdesk_tickets AS hdt
			WHERE hdt.id_ticket = {int:ticket}
				AND {query_see_ticket}',
			array(
				'ticket' => $context['ticket_id'],
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			fatal_lang_error('shd_no_ticket', false);
		}

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		$status = (int) $row['status'];
		$starter_id = (int) $row['id_member_started'];
		$dept = (int) $row['id_dept'];
		$subject = $row['subject'];
		$num_replies = (int) $row['num_replies'];
		$replier_id = (int) $row['id_member_updated'];

		// Cannot resolve/unresolve a deleted ticket
		if ($status == TICKET_STATUS_DELETED)
			fatal_lang_error('shd_no_ticket', false);

		// Determine action: if not closed, we resolve; if closed, we unresolve
		$action = ($status != TICKET_STATUS_CLOSED) ? 'resolve' : 'unresolve';

		// Permission check: need _any or (_own and is the starter)
		$is_own = ($starter_id == $user_info['id']);
		$can_any = shd_allowed_to('shd_' . $action . '_ticket_any', $dept);
		$can_own = shd_allowed_to('shd_' . $action . '_ticket_own', $dept) && $is_own;

		if (!$can_any && !$can_own)
			fatal_lang_error('shd_cannot_' . $action, false);

		// If resolving, check that there are no open child tickets (unless relationships disabled)
		if ($action === 'resolve' && empty($modSettings['shd_disable_relationships']))
		{
			$request = $db->query('', '
				SELECT hdt.id_ticket, hdt.subject
				FROM {db_prefix}helpdesk_relationships AS rel
					INNER JOIN {db_prefix}helpdesk_tickets AS hdt ON (hdt.id_ticket = rel.secondary_ticket)
				WHERE rel.primary_ticket = {int:ticket}
					AND rel.rel_type = {int:parent}
					AND hdt.status NOT IN ({array_int:closed_statuses})',
				array(
					'ticket' => $context['ticket_id'],
					'parent' => RELATIONSHIP_ISPARENT,
					'closed_statuses' => array(TICKET_STATUS_CLOSED, TICKET_STATUS_DELETED),
				)
			);

			if ($db->num_rows($request) > 0)
			{
				$db->free_result($request);
				fatal_lang_error('error_shd_cannot_resolve_children', false);
			}
			$db->free_result($request);
		}

		// Determine the new status
		$new_status = shd_determine_status($action, $starter_id, $replier_id, $num_replies, $dept);

		// Update the ticket status
		$db->query('', '
			UPDATE {db_prefix}helpdesk_tickets
			SET status = {int:status},
				last_updated = {int:time}
			WHERE id_ticket = {int:ticket}',
			array(
				'status' => $new_status,
				'time' => time(),
				'ticket' => $context['ticket_id'],
			)
		);

		// Log the action
		shd_log_action($action, array(
			'ticket' => $context['ticket_id'],
			'subject' => $subject,
		));

		// Clear the active tickets cache
		shd_clear_active_tickets($dept);

		// Redirect
		if ($context['shd_return_to'] === 'home' || ($action === 'resolve' && empty($context['shd_return_to'])))
			redirectexit('action=helpdesk;sa=main');
		else
			redirectexit('action=helpdesk;sa=viewticket;ticket=' . $context['ticket_id']);
	}

	/**
	 * Alias for action_resolve.
	 *
	 * In the original SMF source, resolve2 is redundant with resolve.
	 * Both routes point to the same logic.
	 */
	public function action_resolve2()
	{
		$this->action_resolve();
	}

	/**
	 * Toggle the privacy setting on a ticket.
	 *
	 * Flips the ticket between private and not-private. Checks that the ticket
	 * is not closed or deleted, verifies permissions, updates the privacy flag,
	 * logs the action, and redirects to the ticket.
	 */
	public function action_privacychange()
	{
		global $context, $scripturl, $user_info;

		$db = database();

		checkSession('get');

		if (empty($context['ticket_id']))
			$context['ticket_id'] = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;

		if (empty($context['ticket_id']))
			fatal_lang_error('shd_no_ticket', false);

		// Load the ticket
		$request = shd_db_query('', '
			SELECT hdt.id_member_started, hdt.subject, hdt.private, hdt.status, hdt.id_dept
			FROM {db_prefix}helpdesk_tickets AS hdt
			WHERE hdt.id_ticket = {int:ticket}
				AND {query_see_ticket}',
			array(
				'ticket' => $context['ticket_id'],
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			fatal_lang_error('shd_no_ticket', false);
		}

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		$status = (int) $row['status'];
		$starter_id = (int) $row['id_member_started'];
		$dept = (int) $row['id_dept'];
		$subject = $row['subject'];
		$is_private = !empty($row['private']);

		// Cannot change privacy on closed or deleted tickets
		if ($status == TICKET_STATUS_CLOSED || $status == TICKET_STATUS_DELETED)
			fatal_lang_error('shd_cannot_change_privacy', false);

		// Permission check
		$is_own = ($starter_id == $user_info['id']);
		$can_any = shd_allowed_to('shd_alter_privacy_any', $dept);
		$can_own = shd_allowed_to('shd_alter_privacy_own', $dept) && $is_own;

		if (!$can_any && !$can_own)
			fatal_lang_error('shd_cannot_change_privacy', false);

		// Toggle the privacy flag
		$new_private = $is_private ? 0 : 1;

		$msgOptions = array();
		$ticketOptions = array(
			'id' => $context['ticket_id'],
			'dept' => $dept,
			'private' => $new_private,
		);
		$posterOptions = array();

		require_once(SUBSDIR . '/SimpleDeskPost.subs.php');
		shd_modify_ticket_post($msgOptions, $ticketOptions, $posterOptions);

		// Log the action
		$log_action = $new_private ? 'markprivate' : 'marknotprivate';
		shd_log_action($log_action, array(
			'ticket' => $context['ticket_id'],
			'subject' => $subject,
		));

		// Redirect back to the ticket
		redirectexit('action=helpdesk;sa=viewticket;ticket=' . $context['ticket_id']);
	}

	/**
	 * Change the urgency of a ticket.
	 *
	 * Increases or decreases the urgency by one level. Uses
	 * shd_can_alter_urgency() to validate the change is permitted,
	 * updates the ticket, logs the action, and redirects.
	 */
	public function action_urgencychange()
	{
		global $context, $scripturl, $user_info;

		$db = database();

		checkSession('get');

		if (empty($context['ticket_id']))
			$context['ticket_id'] = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;

		if (empty($context['ticket_id']))
			fatal_lang_error('shd_no_ticket', false);

		// Load the ticket
		$request = shd_db_query('', '
			SELECT hdt.id_member_started, hdt.subject, hdt.urgency, hdt.status, hdt.id_dept
			FROM {db_prefix}helpdesk_tickets AS hdt
			WHERE hdt.id_ticket = {int:ticket}
				AND {query_see_ticket}',
			array(
				'ticket' => $context['ticket_id'],
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			fatal_lang_error('shd_no_ticket', false);
		}

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		$status = (int) $row['status'];
		$starter_id = (int) $row['id_member_started'];
		$dept = (int) $row['id_dept'];
		$subject = $row['subject'];
		$urgency = (int) $row['urgency'];

		$is_closed = ($status == TICKET_STATUS_CLOSED);
		$is_deleted = ($status == TICKET_STATUS_DELETED);

		// Use the standard urgency permission check
		$can_urgency = shd_can_alter_urgency($urgency, $starter_id, $is_closed, $is_deleted, $dept);

		// Validate the change direction
		$change = isset($_GET['change']) ? $_GET['change'] : '';

		if ($change === 'increase' && !empty($can_urgency['increase']))
			$new_urgency = $urgency + 1;
		elseif ($change === 'decrease' && !empty($can_urgency['decrease']))
			$new_urgency = $urgency - 1;
		else
			fatal_lang_error('shd_cannot_change_urgency', false);

		// Clamp to valid range
		if ($new_urgency < TICKET_URGENCY_LOW)
			$new_urgency = TICKET_URGENCY_LOW;
		if ($new_urgency > TICKET_URGENCY_CRITICAL)
			$new_urgency = TICKET_URGENCY_CRITICAL;

		// Update the ticket urgency
		$msgOptions = array();
		$ticketOptions = array(
			'id' => $context['ticket_id'],
			'dept' => $dept,
			'urgency' => $new_urgency,
		);
		$posterOptions = array();

		require_once(SUBSDIR . '/SimpleDeskPost.subs.php');
		shd_modify_ticket_post($msgOptions, $ticketOptions, $posterOptions);

		// Log the action
		$log_action = ($change === 'increase') ? 'urgency_increase' : 'urgency_decrease';
		shd_log_action($log_action, array(
			'ticket' => $context['ticket_id'],
			'subject' => $subject,
			'urgency' => $new_urgency,
		));

		// Redirect back to the ticket
		redirectexit('action=helpdesk;sa=viewticket;ticket=' . $context['ticket_id']);
	}

	/**
	 * Manage ticket relationships.
	 *
	 * Supports creating relationships (linked, duplicated, parent, child)
	 * between two tickets, or deleting an existing relationship. Validates
	 * permissions, prevents self-relationships, inserts both directions of
	 * the relationship into the database, logs the action on both tickets,
	 * and redirects.
	 */
	public function action_relation()
	{
		global $context, $scripturl, $user_info, $modSettings;

		$db = database();

		checkSession('request');

		// Relationships must not be disabled
		if (!empty($modSettings['shd_disable_relationships']))
			fatal_lang_error('shd_relationships_are_disabled', false);

		if (empty($context['ticket_id']))
			$context['ticket_id'] = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;

		if (empty($context['ticket_id']))
			fatal_lang_error('shd_no_ticket', false);

		$otherticket = isset($_REQUEST['otherticket']) ? (int) $_REQUEST['otherticket'] : 0;

		if (empty($otherticket))
			fatal_lang_error('shd_no_ticket', false);

		// Cannot relate a ticket to itself
		if ($context['ticket_id'] == $otherticket)
			fatal_lang_error('shd_cannot_relate_self', false);

		// Load the action type
		$action = isset($_REQUEST['relation']) ? $_REQUEST['relation'] : '';

		// Valid actions
		$valid_actions = array('linked', 'duplicated', 'parent', 'child', 'delete');
		if (!in_array($action, $valid_actions))
			fatal_lang_error('shd_no_ticket', false);

		// Load both tickets via shd_load_ticket to verify visibility
		require_once(SUBSDIR . '/SimpleDeskDisplay.subs.php');

		$ticket = shd_load_ticket($context['ticket_id']);
		if ($ticket === false)
			fatal_lang_error('shd_no_ticket', false);

		$other = shd_load_ticket($otherticket);
		if ($other === false)
			fatal_lang_error('shd_no_ticket', false);

		$dept = (int) $ticket['id_dept'];

		// Permission check
		if ($action === 'delete')
			shd_is_allowed_to('shd_delete_relationships', $dept);
		else
			shd_is_allowed_to('shd_create_relationships', $dept);

		if ($action === 'delete')
		{
			// Delete the relationship in both directions
			$db->query('', '
				DELETE FROM {db_prefix}helpdesk_relationships
				WHERE (primary_ticket = {int:ticket} AND secondary_ticket = {int:other})
					OR (primary_ticket = {int:other} AND secondary_ticket = {int:ticket})',
				array(
					'ticket' => $context['ticket_id'],
					'other' => $otherticket,
				)
			);

			// Log deletion on both tickets
			shd_log_action('rel_delete', array(
				'ticket' => $context['ticket_id'],
				'subject' => $ticket['subject'],
				'otherticket' => $otherticket,
				'othersubject' => $other['subject'],
			));

			shd_log_action('rel_delete', array(
				'ticket' => $otherticket,
				'subject' => $other['subject'],
				'otherticket' => $context['ticket_id'],
				'othersubject' => $ticket['subject'],
			));
		}
		else
		{
			// Map action to relationship type constants
			$rel_map = array(
				'linked' => RELATIONSHIP_LINKED,
				'duplicated' => RELATIONSHIP_DUPLICATED,
				'parent' => RELATIONSHIP_ISPARENT,
				'child' => RELATIONSHIP_ISCHILD,
			);

			// The inverse relationship for the other ticket's direction
			$inverse_map = array(
				RELATIONSHIP_LINKED => RELATIONSHIP_LINKED,
				RELATIONSHIP_DUPLICATED => RELATIONSHIP_DUPLICATED,
				RELATIONSHIP_ISPARENT => RELATIONSHIP_ISCHILD,
				RELATIONSHIP_ISCHILD => RELATIONSHIP_ISPARENT,
			);

			$rel_type = $rel_map[$action];
			$inverse_type = $inverse_map[$rel_type];

			// Remove any existing relationship between these two tickets first
			$db->query('', '
				DELETE FROM {db_prefix}helpdesk_relationships
				WHERE (primary_ticket = {int:ticket} AND secondary_ticket = {int:other})
					OR (primary_ticket = {int:other} AND secondary_ticket = {int:ticket})',
				array(
					'ticket' => $context['ticket_id'],
					'other' => $otherticket,
				)
			);

			// Insert both directions
			$db->insert('',
				'{db_prefix}helpdesk_relationships',
				array(
					'primary_ticket' => 'int',
					'secondary_ticket' => 'int',
					'rel_type' => 'int',
				),
				array(
					$context['ticket_id'],
					$otherticket,
					$rel_type,
				),
				array('primary_ticket', 'secondary_ticket')
			);

			$db->insert('',
				'{db_prefix}helpdesk_relationships',
				array(
					'primary_ticket' => 'int',
					'secondary_ticket' => 'int',
					'rel_type' => 'int',
				),
				array(
					$otherticket,
					$context['ticket_id'],
					$inverse_type,
				),
				array('primary_ticket', 'secondary_ticket')
			);

			// Map action to log action name
			$log_map = array(
				'linked' => 'rel_linked',
				'duplicated' => 'rel_duplicated',
				'parent' => 'rel_parent',
				'child' => 'rel_child',
			);

			$inverse_log_map = array(
				'linked' => 'rel_linked',
				'duplicated' => 'rel_duplicated',
				'parent' => 'rel_child',
				'child' => 'rel_parent',
			);

			// Log on the primary ticket
			shd_log_action($log_map[$action], array(
				'ticket' => $context['ticket_id'],
				'subject' => $ticket['subject'],
				'otherticket' => $otherticket,
				'othersubject' => $other['subject'],
			));

			// Log on the secondary ticket (with inverse relationship name)
			shd_log_action($inverse_log_map[$action], array(
				'ticket' => $otherticket,
				'subject' => $other['subject'],
				'otherticket' => $context['ticket_id'],
				'othersubject' => $ticket['subject'],
			));
		}

		// Redirect back to the ticket
		redirectexit('action=helpdesk;sa=viewticket;ticket=' . $context['ticket_id']);
	}

	/**
	 * Mark a ticket as unread for the current user.
	 *
	 * Deletes the read log entry for this ticket/user combination, then
	 * redirects to the helpdesk home page.
	 */
	public function action_markunread()
	{
		global $context, $scripturl, $user_info;

		$db = database();

		checkSession('get');
		is_not_guest();

		if (empty($context['ticket_id']))
			$context['ticket_id'] = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;

		if (empty($context['ticket_id']))
			fatal_lang_error('shd_no_ticket', false);

		// Delete the read log entry for this user and ticket
		$db->query('', '
			DELETE FROM {db_prefix}helpdesk_log_read
			WHERE id_ticket = {int:ticket}
				AND id_member = {int:member}',
			array(
				'ticket' => $context['ticket_id'],
				'member' => $user_info['id'],
			)
		);

		// Redirect to the helpdesk home
		redirectexit('action=helpdesk;sa=main');
	}

	/**
	 * Download a helpdesk attachment.
	 *
	 * Verifies the attachment belongs to the requested ticket, checks that
	 * the user can view the ticket and has attachment view permissions,
	 * then serves the file. This is needed because helpdesk attachments
	 * have id_msg = 0 in the attachments table, so ElkArte's standard
	 * dlattach action won't serve them.
	 */
	public function action_dlattach()
	{
		global $context, $modSettings;

		$db = database();

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		require_once(SUBSDIR . '/Attachments.subs.php');

		$ticket_id = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;
		$attach_id = isset($_REQUEST['attach']) ? (int) $_REQUEST['attach'] : 0;

		if (empty($ticket_id) || empty($attach_id))
			fatal_lang_error('shd_no_ticket', false);

		// Verify the ticket exists and the user can see it
		$request = shd_db_query('', '
			SELECT hdt.id_ticket, hdt.id_dept
			FROM {db_prefix}helpdesk_tickets AS hdt
			WHERE hdt.id_ticket = {int:ticket}
				AND {query_see_ticket}',
			array(
				'ticket' => $ticket_id,
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			fatal_lang_error('shd_no_ticket', false);
		}

		$ticket_row = $db->fetch_assoc($request);
		$db->free_result($request);

		$dept = (int) $ticket_row['id_dept'];

		// Check attachment view permission
		shd_is_allowed_to('shd_view_attachment', $dept);

		// Verify this attachment belongs to this ticket
		$request = shd_db_query('', '
			SELECT hda.id_attach, hda.id_ticket,
				a.filename, a.file_hash, COALESCE(a.size, 0) AS filesize, a.id_folder, a.mime_type,
				a.width, a.height, a.attachment_type
			FROM {db_prefix}helpdesk_attachments AS hda
				INNER JOIN {db_prefix}attachments AS a ON (a.id_attach = hda.id_attach)
			WHERE hda.id_attach = {int:attach}
				AND hda.id_ticket = {int:ticket}',
			array(
				'attach' => $attach_id,
				'ticket' => $ticket_id,
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			fatal_lang_error('shd_no_ticket', false);
		}

		$attach = $db->fetch_assoc($request);
		$db->free_result($request);

		// Resolve the physical file path
		$filename = getAttachmentFilename(
			$attach['filename'],
			$attach_id,
			$attach['id_folder'],
			false,
			$attach['file_hash']
		);

		if (!file_exists($filename))
			fatal_lang_error('shd_no_ticket', false);

		// Is this an image request? (for inline display)
		$is_image = isset($_REQUEST['image']);

		// Determine MIME type
		$mime = !empty($attach['mime_type']) ? $attach['mime_type'] : 'application/octet-stream';

		// Send the file
		ob_end_clean();

		if ($is_image && !empty($attach['width']) && !empty($attach['height']))
		{
			header('Content-Type: ' . $mime);
			header('Content-Disposition: inline; filename="' . $attach['filename'] . '"');
		}
		else
		{
			header('Content-Type: ' . ($is_image ? $mime : 'application/octet-stream'));
			header('Content-Disposition: attachment; filename="' . $attach['filename'] . '"');
		}

		header('Content-Length: ' . $attach['filesize']);
		header('Cache-Control: private');

		// Stream the file
		$fp = fopen($filename, 'rb');
		if ($fp)
		{
			while (!feof($fp))
			{
				echo fread($fp, 8192);
				flush();
			}
			fclose($fp);
		}

		obExit(false);
	}
}
