<?php
/**
 * SimpleDesk Post Controller
 *
 * Handles creating and editing tickets and replies. Provides the forms
 * for new tickets, editing tickets, posting replies, and editing replies,
 * as well as the save handlers that validate and persist the data.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Controller for posting tickets and replies.
 */
class SimpleDeskPost_Controller extends Action_Controller
{
	/**
	 * Default entry point - redirects to main helpdesk.
	 */
	public function action_index()
	{
		redirectexit('action=helpdesk');
	}

	/**
	 * Display the new ticket form.
	 *
	 * Checks that the user has permission to create tickets, sets up
	 * $context['ticket_form'] with all the fields the template needs,
	 * loads custom fields and urgency options, and handles proxy tickets
	 * (staff posting on behalf of another user).
	 */
	public function action_newticket()
	{
		global $context, $scripturl, $txt, $modSettings, $user_info;

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		require_once(SUBSDIR . '/SimpleDeskPost.subs.php');
		require_once(SUBSDIR . '/Post.subs.php');

		loadLanguage('SimpleDesk');
		loadTemplate('SimpleDeskPost');
		loadCSSFile('helpdesk.css');

		// Determine department
		$dept = isset($_REQUEST['dept']) ? (int) $_REQUEST['dept'] : 0;

		// If no department specified and multi-department, we need to figure it out
		if (empty($dept))
		{
			$postable_depts = shd_get_postable_depts();

			if (empty($postable_depts))
				fatal_lang_error('cannot_shd_new_ticket', false);

			if (count($postable_depts) == 1)
			{
				$first = reset($postable_depts);
				$dept = $first['id'];
			}
		}

		// Permission check: must be able to post in this department
		if (!empty($dept))
			shd_is_allowed_to('shd_new_ticket', $dept);
		else
			shd_is_allowed_to('shd_new_ticket', 0);

		$context['shd_department'] = $dept;

		// Build the postable departments list for multi-dept selector
		$context['postable_departments'] = shd_get_postable_depts();
		$context['shd_multi_dept'] = count($context['postable_departments']) > 1;

		// Handle proxy ticket: staff posting on behalf of a user
		$proxy_member = 0;
		$proxy_name = '';

		if (isset($_REQUEST['proxy']) && shd_allowed_to('shd_post_proxy', $dept))
		{
			$proxy_member = (int) $_REQUEST['proxy'];

			if (!empty($proxy_member))
			{
				$db = database();
				$request = $db->query('', '
					SELECT real_name
					FROM {db_prefix}members
					WHERE id_member = {int:member}',
					array(
						'member' => $proxy_member,
					)
				);

				if ($db->num_rows($request) > 0)
				{
					$row = $db->fetch_assoc($request);
					$proxy_name = $row['real_name'];
				}
				else
				{
					$proxy_member = 0;
				}
				$db->free_result($request);
			}
		}

		// Privacy options
		$can_private = shd_allowed_to(array('shd_alter_privacy_own', 'shd_alter_privacy_any'), $dept);
		$default_privacy = !empty($modSettings['shd_privacy_display']) && $modSettings['shd_privacy_display'] === 'private' ? 1 : 0;

		// Build the form context
		$context['ticket_form'] = array(
			'is_new' => true,
			'is_reply' => false,
			'is_editing' => false,
			'dept' => $dept,
			'form_title' => isset($txt['shd_new_ticket']) ? $txt['shd_new_ticket'] : 'Post New Ticket',
			'form_action' => $scripturl . '?action=helpdesk;sa=saveticket',
			'subject' => '',
			'message' => '',
			'ticket' => 0,
			'msg' => 0,
			'status' => TICKET_STATUS_NEW,
			'urgency' => array(
				'setting' => TICKET_URGENCY_LOW,
				'options' => array(),
			),
			'private' => array(
				'setting' => $default_privacy,
				'can_change' => $can_private,
				'options' => array(
					0 => isset($txt['shd_ticket_is_not_private']) ? $txt['shd_ticket_is_not_private'] : 'Not Private',
					1 => isset($txt['shd_ticket_private']) ? $txt['shd_ticket_private'] : 'Private',
				),
			),
			'proxy' => array(
				'id' => $proxy_member,
				'name' => $proxy_name,
			),
			'member' => array(
				'id' => $user_info['id'],
				'name' => $user_info['name'],
				'link' => '<a href="' . $scripturl . '?action=profile;u=' . $user_info['id'] . '">' . $user_info['name'] . '</a>',
			),
			'assigned' => array(
				'id' => 0,
				'name' => '',
				'link' => '',
			),
			'selecting_dept' => !empty($context['shd_multi_dept']),
			'errors' => array(),
			'do_attach' => shd_allowed_to('shd_post_attachment', $dept) && !empty($modSettings['shd_attachments_mode']),
			'smileys_enabled' => true,
			'additional_opts' => array(),
		);

		// Build the simplified postable department list
		$context['postable_dept_list'] = array();
		foreach ($context['postable_departments'] as $pd)
			$context['postable_dept_list'][$pd['id']] = $pd['name'];

		// Context flags for template
		$context['display_private'] = $can_private;
		$context['can_solve'] = false; // Can't resolve a new ticket
		$context['can_post_proxy'] = shd_allowed_to('shd_post_proxy', $dept);

		// Load urgency options
		shd_get_urgency_options(true, $dept);

		// Load custom fields for tickets
		shd_load_custom_fields(true, 0, $dept);

		// Form submission protection
		checkSubmitOnce('register');

		// Page setup
		$context['page_title'] = $context['ticket_form']['form_title'];
		$context['sub_template'] = 'shd_post_ticket';

		// Linktree
		$context['linktree'][] = array(
			'name' => $txt['shd_helpdesk'],
			'url' => $scripturl . '?action=helpdesk;sa=main',
		);
		$context['linktree'][] = array(
			'name' => $context['ticket_form']['form_title'],
		);

		// Load the editor
		$this->_load_editor($context['ticket_form']['message']);
	}

	/**
	 * Display the edit ticket form.
	 *
	 * Loads the existing ticket data, verifies the user has permission to
	 * edit it, un-preparses the message body for editing, and builds
	 * $context['ticket_form'] with the existing values.
	 */
	public function action_editticket()
	{
		global $context, $scripturl, $txt, $modSettings, $user_info;

		$db = database();

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		require_once(SUBSDIR . '/SimpleDeskPost.subs.php');
		require_once(SUBSDIR . '/Post.subs.php');

		loadLanguage('SimpleDesk');
		loadTemplate('SimpleDeskPost');
		loadCSSFile('helpdesk.css');

		$ticket_id = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;

		if (empty($ticket_id))
			fatal_lang_error('shd_no_ticket', false);

		// Load the ticket and its first message
		$request = shd_db_query('', '
			SELECT hdt.id_ticket, hdt.id_dept, hdt.id_first_msg, hdt.id_member_started,
				hdt.id_member_assigned, hdt.subject, hdt.urgency, hdt.status, hdt.private,
				hdt.num_replies,
				hdtr.body, hdtr.smileys_enabled, hdtr.poster_time, hdtr.id_member,
				hdtr.poster_name, hdtr.poster_email, hdtr.poster_ip
			FROM {db_prefix}helpdesk_tickets AS hdt
				INNER JOIN {db_prefix}helpdesk_ticket_replies AS hdtr ON (hdtr.id_msg = hdt.id_first_msg)
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

		$ticket = $db->fetch_assoc($request);
		$db->free_result($request);

		$dept = (int) $ticket['id_dept'];
		$context['shd_department'] = $dept;

		$status = (int) $ticket['status'];
		$starter_id = (int) $ticket['id_member_started'];
		$is_own = ($starter_id == $user_info['id']);

		// Cannot edit closed or deleted tickets
		if ($status == TICKET_STATUS_CLOSED)
			fatal_lang_error('shd_cannot_edit_closed', false);
		if ($status == TICKET_STATUS_DELETED)
			fatal_lang_error('shd_cannot_edit_deleted', false);

		// Permission check: edit_ticket_any or (edit_ticket_own and is own)
		$can_edit_any = shd_allowed_to('shd_edit_ticket_any', $dept);
		$can_edit_own = shd_allowed_to('shd_edit_ticket_own', $dept) && $is_own;

		if (!$can_edit_any && !$can_edit_own)
			fatal_lang_error('cannot_shd_edit_ticket', false);

		// Un-preparseCode the body for editing
		$body = $ticket['body'];
		$body = un_preparsecode($body);

		// Privacy options
		$can_private = shd_allowed_to(array('shd_alter_privacy_own', 'shd_alter_privacy_any'), $dept);

		// Look up the starter's display name and assigned staff name
		$starter_name = $ticket['poster_name'];
		$assigned_id = (int) $ticket['id_member_assigned'];
		$assigned_name = '';

		$member_ids = array_filter(array($starter_id, $assigned_id));
		if (!empty($member_ids))
		{
			$name_request = $db->query('', '
				SELECT id_member, real_name
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:ids})',
				array(
					'ids' => $member_ids,
				)
			);
			while ($row = $db->fetch_assoc($name_request))
			{
				if ((int) $row['id_member'] === $starter_id)
					$starter_name = $row['real_name'];
				if ((int) $row['id_member'] === $assigned_id)
					$assigned_name = $row['real_name'];
			}
			$db->free_result($name_request);
		}

		// Build the form context
		$context['ticket_form'] = array(
			'is_new' => false,
			'is_reply' => false,
			'is_editing' => true,
			'dept' => $dept,
			'form_title' => isset($txt['shd_edit_ticket']) ? $txt['shd_edit_ticket'] : 'Edit Ticket',
			'form_action' => $scripturl . '?action=helpdesk;sa=saveticket',
			'subject' => $ticket['subject'],
			'message' => $body,
			'ticket' => $ticket_id,
			'msg' => (int) $ticket['id_first_msg'],
			'status' => $status,
			'urgency' => array(
				'setting' => (int) $ticket['urgency'],
				'options' => array(),
			),
			'private' => array(
				'setting' => !empty($ticket['private']) ? 1 : 0,
				'can_change' => $can_private,
				'options' => array(
					0 => isset($txt['shd_ticket_is_not_private']) ? $txt['shd_ticket_is_not_private'] : 'Not Private',
					1 => isset($txt['shd_ticket_private']) ? $txt['shd_ticket_private'] : 'Private',
				),
			),
			'proxy' => array(
				'id' => 0,
				'name' => '',
			),
			'member' => array(
				'id' => $starter_id,
				'name' => $starter_name,
				'link' => '<a href="' . $scripturl . '?action=profile;u=' . $starter_id . '">' . $starter_name . '</a>',
			),
			'assigned' => array(
				'id' => $assigned_id,
				'name' => $assigned_name,
				'link' => !empty($assigned_id) ? '<a href="' . $scripturl . '?action=profile;u=' . $assigned_id . '">' . $assigned_name . '</a>' : '',
			),
			'selecting_dept' => false,
			'errors' => array(),
			'do_attach' => shd_allowed_to('shd_post_attachment', $dept) && !empty($modSettings['shd_attachments_mode']),
			'smileys_enabled' => !empty($ticket['smileys_enabled']),
			'starter' => array(
				'id' => $starter_id,
				'name' => $starter_name,
			),
			'additional_opts' => array(),
		);

		// Context flags for template
		$context['display_private'] = $can_private;
		$context['can_solve'] = shd_allowed_to('shd_resolve_ticket_any', $dept) || ($is_own && shd_allowed_to('shd_resolve_ticket_own', $dept));
		$context['can_post_proxy'] = false;

		// Load urgency options
		shd_get_urgency_options($is_own, $dept);

		// Load custom fields with existing values
		shd_load_custom_fields(true, $ticket_id, $dept);

		// Load existing attachments for display in the edit form
		if (!empty($context['ticket_form']['do_attach']))
			shd_load_attachments($ticket_id, (int) $ticket['id_first_msg']);

		// Form submission protection
		checkSubmitOnce('register');

		// Page setup
		$context['page_title'] = $context['ticket_form']['form_title'] . ' - ' . $ticket['subject'];
		$context['sub_template'] = 'shd_post_ticket';

		// Linktree
		$context['linktree'][] = array(
			'name' => $txt['shd_helpdesk'],
			'url' => $scripturl . '?action=helpdesk;sa=main',
		);
		$context['linktree'][] = array(
			'name' => $ticket['subject'],
			'url' => $scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $ticket_id,
		);
		$context['linktree'][] = array(
			'name' => $context['ticket_form']['form_title'],
		);

		// Load the editor with existing content
		$this->_load_editor($context['ticket_form']['message']);
	}

	/**
	 * Process and save a ticket form submission.
	 *
	 * Handles both new ticket creation and ticket editing. Validates the
	 * subject and message body, processes custom fields, handles privacy
	 * and urgency settings, and either creates or updates the ticket.
	 */
	public function action_saveticket()
	{
		global $context, $scripturl, $txt, $modSettings, $user_info;

		$db = database();

		checkSession('post');
		checkSubmitOnce('check');

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		require_once(SUBSDIR . '/SimpleDeskPost.subs.php');
		require_once(SUBSDIR . '/Post.subs.php');

		loadLanguage('SimpleDesk');

		$errors = array();

		// Determine if this is a new ticket or an edit
		$ticket_id = isset($_POST['ticket']) ? (int) $_POST['ticket'] : 0;
		$is_new = empty($ticket_id);

		// Get department
		$dept = isset($_POST['dept']) ? (int) $_POST['dept'] : 0;

		// For editing, load the existing ticket
		$existing_ticket = null;
		if (!$is_new)
		{
			$request = shd_db_query('', '
				SELECT hdt.id_ticket, hdt.id_dept, hdt.id_first_msg, hdt.id_member_started,
					hdt.id_member_assigned, hdt.subject, hdt.urgency, hdt.status, hdt.private,
					hdt.num_replies
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

			$existing_ticket = $db->fetch_assoc($request);
			$db->free_result($request);

			$dept = (int) $existing_ticket['id_dept'];
			$status = (int) $existing_ticket['status'];
			$starter_id = (int) $existing_ticket['id_member_started'];
			$is_own = ($starter_id == $user_info['id']);

			// Cannot edit closed or deleted tickets
			if ($status == TICKET_STATUS_CLOSED)
				fatal_lang_error('shd_cannot_edit_closed', false);
			if ($status == TICKET_STATUS_DELETED)
				fatal_lang_error('shd_cannot_edit_deleted', false);

			// Permission check
			$can_edit_any = shd_allowed_to('shd_edit_ticket_any', $dept);
			$can_edit_own = shd_allowed_to('shd_edit_ticket_own', $dept) && $is_own;

			if (!$can_edit_any && !$can_edit_own)
				fatal_lang_error('cannot_shd_edit_ticket', false);
		}
		else
		{
			// New ticket: validate department
			if (empty($dept))
			{
				$postable_depts = shd_get_postable_depts();
				if (count($postable_depts) == 1)
				{
					$first = reset($postable_depts);
					$dept = $first['id'];
				}
				else
				{
					$errors[] = isset($txt['error_no_dept']) ? $txt['error_no_dept'] : 'No department selected.';
				}
			}

			if (!empty($dept))
				shd_is_allowed_to('shd_new_ticket', $dept);
			else
				shd_is_allowed_to('shd_new_ticket', 0);
		}

		$context['shd_department'] = $dept;

		// Validate subject
		$subject = isset($_POST['subject']) ? $_POST['subject'] : '';
		$subject = Util::htmlspecialchars($subject);
		$subject = str_replace(array("\r", "\n", "\t"), '', $subject);
		$subject = Util::htmltrim($subject);

		if (Util::strlen($subject) === 0)
			$errors[] = isset($txt['shd_no_subject']) ? $txt['shd_no_subject'] : 'You must enter a subject.';
		elseif (Util::strlen($subject) > 100)
			$subject = Util::substr($subject, 0, 100);

		// Validate message body
		$message = isset($_POST['message']) ? $_POST['message'] : '';
		$message = Util::htmlspecialchars($message, ENT_QUOTES);
		preparsecode($message);

		if (Util::htmltrim(strip_tags(parse_bbc($message, false), '<img>')) === '')
			$errors[] = isset($txt['shd_no_message']) ? $txt['shd_no_message'] : 'You must enter a message.';

		// Process urgency
		$urgency = isset($_POST['urgency']) ? (int) $_POST['urgency'] : TICKET_URGENCY_LOW;

		// Cap urgency based on permissions
		$max_urgency = TICKET_URGENCY_MEDIUM;
		$is_own_urgency = $is_new || (!$is_new && (int) $existing_ticket['id_member_started'] == $user_info['id']);

		if (shd_allowed_to('shd_alter_urgency_any', $dept) || ($is_own_urgency && shd_allowed_to('shd_alter_urgency_own', $dept)))
		{
			$max_urgency = TICKET_URGENCY_HIGH;

			if (shd_allowed_to('shd_alter_urgency_higher_any', $dept) || ($is_own_urgency && shd_allowed_to('shd_alter_urgency_higher_own', $dept)))
				$max_urgency = TICKET_URGENCY_CRITICAL;
		}

		if ($urgency > $max_urgency)
			$urgency = $max_urgency;
		if ($urgency < TICKET_URGENCY_LOW)
			$urgency = TICKET_URGENCY_LOW;

		// Process privacy
		$private = 0;
		$can_private = shd_allowed_to(array('shd_alter_privacy_own', 'shd_alter_privacy_any'), $dept);

		if ($can_private && isset($_POST['private']))
			$private = !empty($_POST['private']) ? 1 : 0;
		elseif (!$is_new && $existing_ticket !== null)
			$private = !empty($existing_ticket['private']) ? 1 : 0;

		// Load and validate custom fields
		shd_load_custom_fields(true, $is_new ? 0 : $ticket_id, $dept);
		$cf_result = shd_validate_custom_fields(CFIELD_TICKET, $dept);

		if (!empty($cf_result['errors']))
			$errors = array_merge($errors, $cf_result['errors']);

		// Handle proxy ticket
		$proxy_member = 0;
		$proxy_name = '';

		if ($is_new && isset($_POST['proxy']) && shd_allowed_to('shd_post_proxy', $dept))
		{
			$proxy_member = (int) $_POST['proxy'];

			if (!empty($proxy_member))
			{
				$request = $db->query('', '
					SELECT real_name, email_address
					FROM {db_prefix}members
					WHERE id_member = {int:member}',
					array(
						'member' => $proxy_member,
					)
				);

				if ($db->num_rows($request) > 0)
				{
					$proxy_row = $db->fetch_assoc($request);
					$proxy_name = $proxy_row['real_name'];
					$proxy_email = $proxy_row['email_address'];
				}
				else
				{
					$proxy_member = 0;
				}
				$db->free_result($request);
			}
		}

		// If there are errors, rebuild the form and redisplay
		if (!empty($errors))
		{
			loadTemplate('SimpleDeskPost');
			loadCSSFile('helpdesk.css');

			$context['postable_departments'] = shd_get_postable_depts();
			$context['shd_multi_dept'] = count($context['postable_departments']) > 1;

			// Un-preparseCode the message for the editor
			$display_message = un_preparsecode($message);

			// Build the simplified postable department list
			$context['postable_dept_list'] = array();
			foreach ($context['postable_departments'] as $pd)
				$context['postable_dept_list'][$pd['id']] = $pd['name'];

			$context['ticket_form'] = array(
				'is_new' => $is_new,
				'is_reply' => false,
				'is_editing' => !$is_new,
				'dept' => $dept,
				'form_title' => $is_new
					? (isset($txt['shd_new_ticket']) ? $txt['shd_new_ticket'] : 'Post New Ticket')
					: (isset($txt['shd_edit_ticket']) ? $txt['shd_edit_ticket'] : 'Edit Ticket'),
				'form_action' => $scripturl . '?action=helpdesk;sa=saveticket',
				'subject' => $subject,
				'message' => $display_message,
				'ticket' => $ticket_id,
				'msg' => !$is_new ? (int) $existing_ticket['id_first_msg'] : 0,
				'status' => !$is_new ? (int) $existing_ticket['status'] : TICKET_STATUS_NEW,
				'urgency' => array(
					'setting' => $urgency,
					'options' => array(),
				),
				'private' => array(
					'setting' => $private,
					'can_change' => $can_private,
					'options' => array(
						0 => isset($txt['shd_ticket_is_not_private']) ? $txt['shd_ticket_is_not_private'] : 'Not Private',
						1 => isset($txt['shd_ticket_private']) ? $txt['shd_ticket_private'] : 'Private',
					),
				),
				'proxy' => array(
					'id' => $proxy_member,
					'name' => $proxy_name,
				),
				'member' => array(
					'id' => $user_info['id'],
					'name' => $user_info['name'],
					'link' => '<a href="' . $scripturl . '?action=profile;u=' . $user_info['id'] . '">' . $user_info['name'] . '</a>',
				),
				'assigned' => array(
					'id' => 0,
					'name' => '',
					'link' => '',
				),
				'selecting_dept' => $is_new && !empty($context['shd_multi_dept']),
				'errors' => $errors,
				'do_attach' => shd_allowed_to('shd_post_attachment', $dept) && !empty($modSettings['shd_attachments_mode']),
				'smileys_enabled' => true,
				'additional_opts' => array(),
			);

			// Context flags for template
			$context['display_private'] = $can_private;
			$context['can_solve'] = !$is_new && (shd_allowed_to('shd_resolve_ticket_any', $dept) || (!empty($existing_ticket) && (int) $existing_ticket['id_member_started'] == $user_info['id'] && shd_allowed_to('shd_resolve_ticket_own', $dept)));
			$context['can_post_proxy'] = $is_new && shd_allowed_to('shd_post_proxy', $dept);

			shd_get_urgency_options($is_new || (!empty($existing_ticket) && (int) $existing_ticket['id_member_started'] == $user_info['id']), $dept);

			// Form submission protection (re-register for the redisplay)
			checkSubmitOnce('register');

			$context['page_title'] = $context['ticket_form']['form_title'];
			$context['sub_template'] = 'shd_post_ticket';

			$context['linktree'][] = array(
				'name' => $txt['shd_helpdesk'],
				'url' => $scripturl . '?action=helpdesk;sa=main',
			);
			$context['linktree'][] = array(
				'name' => $context['ticket_form']['form_title'],
			);

			$this->_load_editor($context['ticket_form']['message']);
			return;
		}

		// Process smileys toggle
		$smileys_enabled = empty($_POST['ns']) ? 1 : 0;

		// Process resolve checkbox (edit only)
		$resolve = false;
		if (!$is_new && !empty($_POST['resolve']))
		{
			$can_resolve = shd_allowed_to('shd_resolve_ticket_any', $dept) || (!empty($existing_ticket) && (int) $existing_ticket['id_member_started'] == $user_info['id'] && shd_allowed_to('shd_resolve_ticket_own', $dept));
			if ($can_resolve)
				$resolve = true;
		}

		// Process attachments
		$attachIDs = array();
		if (shd_allowed_to('shd_post_attachment', $dept) && !empty($modSettings['shd_attachments_mode']))
			$attachIDs = shd_handle_attachments($dept);

		// Save the ticket
		if ($is_new)
		{
			// Determine poster info (for proxy tickets, the starter is the proxy user)
			if (!empty($proxy_member))
			{
				$posterOptions = array(
					'id' => $proxy_member,
					'name' => $proxy_name,
					'email' => !empty($proxy_email) ? $proxy_email : '',
					'ip' => $user_info['ip'],
				);
			}
			else
			{
				$posterOptions = array(
					'id' => $user_info['id'],
					'name' => $user_info['name'],
					'email' => $user_info['email'],
					'ip' => $user_info['ip'],
				);
			}

			$msgOptions = array(
				'body' => $message,
				'smileys_enabled' => $smileys_enabled,
				'attachments' => $attachIDs,
			);

			$ticketOptions = array(
				'id' => 0,
				'dept' => $dept,
				'subject' => $subject,
				'urgency' => $urgency,
				'private' => $private,
				'status' => TICKET_STATUS_NEW,
				'custom_fields' => !empty($cf_result['fields']) ? $cf_result['fields'] : array(),
			);

			// For proxy tickets, mark as read for the actual staff user
			if (!empty($proxy_member))
				$ticketOptions['mark_as_read_proxy'] = $user_info['id'];

			shd_create_ticket_post($msgOptions, $ticketOptions, $posterOptions);

			// Log the action
			$log_action = !empty($proxy_member) ? 'newticketproxy' : 'newticket';
			$log_params = array(
				'ticket' => $ticketOptions['id'],
				'msg' => $msgOptions['id'],
				'subject' => $subject,
			);

			if (!empty($proxy_member))
			{
				$log_params['proxy'] = $proxy_name;
				$log_params['proxy_id'] = $proxy_member;
			}

			shd_log_action($log_action, $log_params);

			// Redirect to the new ticket
			redirectexit('action=helpdesk;sa=viewticket;ticket=' . $ticketOptions['id']);
		}
		else
		{
			// Editing an existing ticket
			$msgOptions = array(
				'id' => (int) $existing_ticket['id_first_msg'],
				'body' => $message,
				'smileys_enabled' => $smileys_enabled,
				'attachments' => $attachIDs,
			);

			$ticketOptions = array(
				'id' => $ticket_id,
				'dept' => $dept,
				'subject' => $subject,
				'urgency' => $urgency,
				'private' => $private,
				'is_ticket_edit' => true,
				'custom_fields' => !empty($cf_result['fields']) ? $cf_result['fields'] : array(),
			);

			// If resolve was checked, set status to closed
			if ($resolve)
				$ticketOptions['status'] = TICKET_STATUS_CLOSED;

			$posterOptions = array(
				'id' => $user_info['id'],
				'name' => $user_info['name'],
				'email' => $user_info['email'],
				'ip' => $user_info['ip'],
			);

			shd_modify_ticket_post($msgOptions, $ticketOptions, $posterOptions);

			// Log the action
			shd_log_action('editticket', array(
				'ticket' => $ticket_id,
				'msg' => $msgOptions['id'],
				'subject' => $subject,
			));

			if ($resolve)
			{
				shd_log_action('resolve', array(
					'ticket' => $ticket_id,
					'subject' => $subject,
				));
			}

			// Redirect back to the ticket
			redirectexit('action=helpdesk;sa=viewticket;ticket=' . $ticket_id);
		}
	}

	/**
	 * Display the reply form for an existing ticket.
	 *
	 * Loads the ticket data for context (including the opening post for
	 * reference), verifies the user has permission to reply, and sets up
	 * $context['ticket_form'] for the reply template.
	 */
	public function action_reply()
	{
		global $context, $scripturl, $txt, $modSettings, $user_info;

		$db = database();

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		require_once(SUBSDIR . '/SimpleDeskPost.subs.php');
		require_once(SUBSDIR . '/Post.subs.php');

		loadLanguage('SimpleDesk');
		loadTemplate('SimpleDeskPost');
		loadCSSFile('helpdesk.css');

		$ticket_id = isset($_REQUEST['ticket']) ? (int) $_REQUEST['ticket'] : 0;

		if (empty($ticket_id))
			fatal_lang_error('shd_no_ticket', false);

		// Load the ticket
		$request = shd_db_query('', '
			SELECT hdt.id_ticket, hdt.id_dept, hdt.id_first_msg, hdt.id_member_started,
				hdt.id_member_assigned, hdt.subject, hdt.urgency, hdt.status, hdt.private,
				hdt.num_replies,
				hdtr.body, hdtr.smileys_enabled,
				COALESCE(ms.real_name, hdtr.poster_name) AS starter_name
			FROM {db_prefix}helpdesk_tickets AS hdt
				INNER JOIN {db_prefix}helpdesk_ticket_replies AS hdtr ON (hdtr.id_msg = hdt.id_first_msg)
				LEFT JOIN {db_prefix}members AS ms ON (ms.id_member = hdt.id_member_started)
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

		$ticket = $db->fetch_assoc($request);
		$db->free_result($request);

		$dept = (int) $ticket['id_dept'];
		$context['shd_department'] = $dept;

		$status = (int) $ticket['status'];
		$starter_id = (int) $ticket['id_member_started'];
		$is_own = ($starter_id == $user_info['id']);

		// Cannot reply to closed or deleted tickets
		if ($status == TICKET_STATUS_CLOSED)
			fatal_lang_error('shd_cannot_reply_closed', false);
		if ($status == TICKET_STATUS_DELETED)
			fatal_lang_error('shd_cannot_reply_deleted', false);

		// Permission check
		$can_reply_any = shd_allowed_to('shd_reply_ticket_any', $dept);
		$can_reply_own = shd_allowed_to('shd_reply_ticket_own', $dept) && $is_own;

		if (!$can_reply_any && !$can_reply_own)
			fatal_lang_error('shd_cannot_reply_any', false);

		// Look up assigned staff name
		$assigned_id = (int) $ticket['id_member_assigned'];
		$assigned_name = '';
		if (!empty($assigned_id))
		{
			$name_request = $db->query('', '
				SELECT real_name
				FROM {db_prefix}members
				WHERE id_member = {int:member}',
				array(
					'member' => $assigned_id,
				)
			);
			if ($db->num_rows($name_request) > 0)
			{
				$row = $db->fetch_assoc($name_request);
				$assigned_name = $row['real_name'];
			}
			$db->free_result($name_request);
		}

		// Build form context
		$context['ticket_form'] = array(
			'is_new' => true,
			'is_reply' => true,
			'is_editing' => false,
			'dept' => $dept,
			'form_title' => isset($txt['shd_post_reply']) ? $txt['shd_post_reply'] : 'Post Reply',
			'form_action' => $scripturl . '?action=helpdesk;sa=savereply',
			'subject' => $ticket['subject'],
			'message' => '',
			'ticket' => $ticket_id,
			'msg' => 0,
			'status' => $status,
			'urgency' => array(
				'setting' => (int) $ticket['urgency'],
				'options' => array(),
			),
			'private' => array(
				'setting' => !empty($ticket['private']) ? 1 : 0,
				'can_change' => false,
			),
			'member' => array(
				'id' => $starter_id,
				'name' => $ticket['starter_name'],
				'link' => '<a href="' . $scripturl . '?action=profile;u=' . $starter_id . '">' . $ticket['starter_name'] . '</a>',
			),
			'assigned' => array(
				'id' => $assigned_id,
				'name' => $assigned_name,
				'link' => !empty($assigned_id) ? '<a href="' . $scripturl . '?action=profile;u=' . $assigned_id . '">' . $assigned_name . '</a>' : '',
			),
			'errors' => array(),
			'do_attach' => shd_allowed_to('shd_post_attachment', $dept) && !empty($modSettings['shd_attachments_mode']),
			'smileys_enabled' => true,
			'additional_opts' => array(),
		);

		// Context flags for template
		$context['display_private'] = false;
		$context['can_solve'] = shd_allowed_to('shd_resolve_ticket_any', $dept) || ($is_own && shd_allowed_to('shd_resolve_ticket_own', $dept));

		// Provide ticket body for reference
		$context['ticket_form']['ticket_body'] = parse_bbc($ticket['body'], $ticket['smileys_enabled']);
		$context['ticket_form']['ticket_subject'] = $ticket['subject'];
		$context['ticket_form']['ticket_starter'] = $ticket['starter_name'];
		$context['ticket_form']['ticket_starter_id'] = $starter_id;

		// Load custom fields for replies
		shd_load_custom_fields(false, 0, $dept);

		// Handle quoted reply
		if (isset($_REQUEST['quote']))
		{
			$quote_msg_id = (int) $_REQUEST['quote'];

			if (!empty($quote_msg_id))
			{
				$quote_request = shd_db_query('', '
					SELECT hdtr.body, hdtr.poster_time, hdtr.id_member,
						COALESCE(mem.real_name, hdtr.poster_name) AS poster_name
					FROM {db_prefix}helpdesk_ticket_replies AS hdtr
						LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = hdtr.id_member)
					WHERE hdtr.id_msg = {int:msg}
						AND hdtr.id_ticket = {int:ticket}',
					array(
						'msg' => $quote_msg_id,
						'ticket' => $ticket_id,
					)
				);

				if ($db->num_rows($quote_request) > 0)
				{
					$quote_row = $db->fetch_assoc($quote_request);

					$quote_body = $quote_row['body'];
					$quote_body = un_preparsecode($quote_body);

					$context['ticket_form']['message'] = '[quote author=' . $quote_row['poster_name'] . ' date=' . $quote_row['poster_time'] . ']' . "\n" . $quote_body . "\n" . '[/quote]' . "\n\n";
				}
				$db->free_result($quote_request);
			}
		}

		// Page setup
		$context['page_title'] = $context['ticket_form']['form_title'] . ' - ' . $ticket['subject'];
		$context['sub_template'] = 'shd_post_reply';

		// Linktree
		$context['linktree'][] = array(
			'name' => $txt['shd_helpdesk'],
			'url' => $scripturl . '?action=helpdesk;sa=main',
		);
		$context['linktree'][] = array(
			'name' => $ticket['subject'],
			'url' => $scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $ticket_id,
		);
		$context['linktree'][] = array(
			'name' => $context['ticket_form']['form_title'],
		);

		// Form submission protection
		checkSubmitOnce('register');

		// Load the editor
		$this->_load_editor($context['ticket_form']['message']);
	}

	/**
	 * Display the edit reply form.
	 *
	 * Loads the reply data and the parent ticket, verifies the user has
	 * permission to edit the reply, un-preparses the message body, and
	 * sets up $context['ticket_form'] for the template.
	 */
	public function action_editreply()
	{
		global $context, $scripturl, $txt, $modSettings, $user_info;

		$db = database();

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		require_once(SUBSDIR . '/SimpleDeskPost.subs.php');
		require_once(SUBSDIR . '/Post.subs.php');

		loadLanguage('SimpleDesk');
		loadTemplate('SimpleDeskPost');
		loadCSSFile('helpdesk.css');

		$msg_id = isset($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : 0;

		if (empty($msg_id))
			fatal_lang_error('shd_no_reply', false);

		// Load the reply and its parent ticket
		$request = shd_db_query('', '
			SELECT hdtr.id_msg, hdtr.id_ticket, hdtr.body, hdtr.smileys_enabled,
				hdtr.id_member, hdtr.poster_name, hdtr.poster_email, hdtr.poster_ip,
				hdtr.poster_time, hdtr.message_status,
				hdt.id_dept, hdt.id_first_msg, hdt.id_member_started, hdt.subject,
				hdt.status, hdt.private, hdt.urgency
			FROM {db_prefix}helpdesk_ticket_replies AS hdtr
				INNER JOIN {db_prefix}helpdesk_tickets AS hdt ON (hdt.id_ticket = hdtr.id_ticket)
			WHERE hdtr.id_msg = {int:msg}
				AND {query_see_ticket}',
			array(
				'msg' => $msg_id,
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			fatal_lang_error('shd_no_reply', false);
		}

		$reply = $db->fetch_assoc($request);
		$db->free_result($request);

		$ticket_id = (int) $reply['id_ticket'];
		$dept = (int) $reply['id_dept'];
		$context['shd_department'] = $dept;

		$status = (int) $reply['status'];
		$reply_member = (int) $reply['id_member'];
		$starter_id = (int) $reply['id_member_started'];
		$is_own = ($reply_member == $user_info['id']);

		// Cannot edit replies on closed or deleted tickets
		if ($status == TICKET_STATUS_CLOSED)
			fatal_lang_error('shd_cannot_edit_closed', false);
		if ($status == TICKET_STATUS_DELETED)
			fatal_lang_error('shd_cannot_edit_deleted', false);

		// Permission check
		$can_edit_any = shd_allowed_to('shd_edit_reply_any', $dept);
		$can_edit_own = shd_allowed_to('shd_edit_reply_own', $dept) && $is_own;

		if (!$can_edit_any && !$can_edit_own)
			fatal_lang_error('shd_cannot_edit_reply_any', false);

		// Un-preparseCode the body for editing
		$body = $reply['body'];
		$body = un_preparsecode($body);

		// Look up starter and assigned staff names
		$assigned_id = 0;
		$assigned_name = '';
		$starter_name = $reply['poster_name'];

		// Get the assigned staff from the ticket
		$assign_request = $db->query('', '
			SELECT hdt.id_member_assigned,
				COALESCE(ms.real_name, {string:empty}) AS starter_name,
				COALESCE(ma.real_name, {string:empty}) AS assigned_name
			FROM {db_prefix}helpdesk_tickets AS hdt
				LEFT JOIN {db_prefix}members AS ms ON (ms.id_member = hdt.id_member_started)
				LEFT JOIN {db_prefix}members AS ma ON (ma.id_member = hdt.id_member_assigned)
			WHERE hdt.id_ticket = {int:ticket}',
			array(
				'ticket' => $ticket_id,
				'empty' => '',
			)
		);
		if ($db->num_rows($assign_request) > 0)
		{
			$assign_row = $db->fetch_assoc($assign_request);
			$assigned_id = (int) $assign_row['id_member_assigned'];
			$assigned_name = $assign_row['assigned_name'];
			if (!empty($assign_row['starter_name']))
				$starter_name = $assign_row['starter_name'];
		}
		$db->free_result($assign_request);

		// Build form context
		$context['ticket_form'] = array(
			'is_new' => false,
			'is_reply' => true,
			'is_editing' => true,
			'dept' => $dept,
			'form_title' => isset($txt['shd_edit_reply']) ? $txt['shd_edit_reply'] : 'Edit Reply',
			'form_action' => $scripturl . '?action=helpdesk;sa=savereply',
			'subject' => $reply['subject'],
			'message' => $body,
			'ticket' => $ticket_id,
			'msg' => $msg_id,
			'status' => $status,
			'urgency' => array(
				'setting' => (int) $reply['urgency'],
				'options' => array(),
			),
			'private' => array(
				'setting' => !empty($reply['private']) ? 1 : 0,
				'can_change' => false,
			),
			'member' => array(
				'id' => $starter_id,
				'name' => $starter_name,
				'link' => '<a href="' . $scripturl . '?action=profile;u=' . $starter_id . '">' . $starter_name . '</a>',
			),
			'assigned' => array(
				'id' => $assigned_id,
				'name' => $assigned_name,
				'link' => !empty($assigned_id) ? '<a href="' . $scripturl . '?action=profile;u=' . $assigned_id . '">' . $assigned_name . '</a>' : '',
			),
			'errors' => array(),
			'do_attach' => shd_allowed_to('shd_post_attachment', $dept) && !empty($modSettings['shd_attachments_mode']),
			'smileys_enabled' => !empty($reply['smileys_enabled']),
			'additional_opts' => array(),
		);

		// Context flags for template
		$context['display_private'] = false;
		$context['can_solve'] = false;

		// Load custom fields for replies with existing values
		shd_load_custom_fields(false, $msg_id, $dept);

		// Load existing attachments for display in the edit form
		if (!empty($context['ticket_form']['do_attach']))
			shd_load_attachments($ticket_id, $msg_id);

		// Form submission protection
		checkSubmitOnce('register');

		// Page setup
		$context['page_title'] = $context['ticket_form']['form_title'] . ' - ' . $reply['subject'];
		$context['sub_template'] = 'shd_post_reply';

		// Linktree
		$context['linktree'][] = array(
			'name' => $txt['shd_helpdesk'],
			'url' => $scripturl . '?action=helpdesk;sa=main',
		);
		$context['linktree'][] = array(
			'name' => $reply['subject'],
			'url' => $scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $ticket_id,
		);
		$context['linktree'][] = array(
			'name' => $context['ticket_form']['form_title'],
		);

		// Load the editor with existing content
		$this->_load_editor($context['ticket_form']['message']);
	}

	/**
	 * Process and save a reply form submission.
	 *
	 * Handles both new reply creation and reply editing. Validates the
	 * message body, determines the new ticket status based on who is
	 * posting, and either creates or updates the reply.
	 */
	public function action_savereply()
	{
		global $context, $scripturl, $txt, $modSettings, $user_info;

		$db = database();

		checkSession('post');
		checkSubmitOnce('check');

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		require_once(SUBSDIR . '/SimpleDeskPost.subs.php');
		require_once(SUBSDIR . '/Post.subs.php');

		loadLanguage('SimpleDesk');

		$errors = array();

		$ticket_id = isset($_POST['ticket']) ? (int) $_POST['ticket'] : 0;
		$msg_id = isset($_POST['msg']) ? (int) $_POST['msg'] : 0;
		$is_new_reply = empty($msg_id);

		if (empty($ticket_id))
			fatal_lang_error('shd_no_ticket', false);

		// Load the ticket
		$request = shd_db_query('', '
			SELECT hdt.id_ticket, hdt.id_dept, hdt.id_first_msg, hdt.id_member_started,
				hdt.id_member_assigned, hdt.subject, hdt.urgency, hdt.status, hdt.private,
				hdt.num_replies
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

		$ticket = $db->fetch_assoc($request);
		$db->free_result($request);

		$dept = (int) $ticket['id_dept'];
		$context['shd_department'] = $dept;

		$status = (int) $ticket['status'];
		$starter_id = (int) $ticket['id_member_started'];
		$is_own = ($starter_id == $user_info['id']);
		$is_staff = shd_allowed_to('shd_staff', $dept);

		// Cannot reply to or edit replies on closed or deleted tickets
		if ($status == TICKET_STATUS_CLOSED)
			fatal_lang_error('shd_cannot_reply_closed', false);
		if ($status == TICKET_STATUS_DELETED)
			fatal_lang_error('shd_cannot_reply_deleted', false);

		// Permission checks
		if ($is_new_reply)
		{
			$can_reply_any = shd_allowed_to('shd_reply_ticket_any', $dept);
			$can_reply_own = shd_allowed_to('shd_reply_ticket_own', $dept) && $is_own;

			if (!$can_reply_any && !$can_reply_own)
				fatal_lang_error('shd_cannot_reply_any', false);
		}
		else
		{
			// Editing: load the existing reply to verify ownership
			$reply_request = shd_db_query('', '
				SELECT hdtr.id_msg, hdtr.id_member, hdtr.message_status
				FROM {db_prefix}helpdesk_ticket_replies AS hdtr
					INNER JOIN {db_prefix}helpdesk_tickets AS hdt ON (hdt.id_ticket = hdtr.id_ticket)
				WHERE hdtr.id_msg = {int:msg}
					AND hdtr.id_ticket = {int:ticket}
					AND {query_see_ticket}',
				array(
					'msg' => $msg_id,
					'ticket' => $ticket_id,
				)
			);

			if ($db->num_rows($reply_request) == 0)
			{
				$db->free_result($reply_request);
				fatal_lang_error('shd_no_reply', false);
			}

			$existing_reply = $db->fetch_assoc($reply_request);
			$db->free_result($reply_request);

			$reply_is_own = ((int) $existing_reply['id_member'] == $user_info['id']);

			$can_edit_any = shd_allowed_to('shd_edit_reply_any', $dept);
			$can_edit_own = shd_allowed_to('shd_edit_reply_own', $dept) && $reply_is_own;

			if (!$can_edit_any && !$can_edit_own)
				fatal_lang_error('shd_cannot_edit_reply_any', false);
		}

		// Validate message body
		$message = isset($_POST['message']) ? $_POST['message'] : '';
		$message = Util::htmlspecialchars($message, ENT_QUOTES);
		preparsecode($message);

		if (Util::htmltrim(strip_tags(parse_bbc($message, false), '<img>')) === '')
			$errors[] = isset($txt['shd_no_message']) ? $txt['shd_no_message'] : 'You must enter a message.';

		// Load and validate custom fields for replies
		shd_load_custom_fields(false, $is_new_reply ? 0 : $msg_id, $dept);
		$cf_result = shd_validate_custom_fields(CFIELD_REPLY, $dept);

		if (!empty($cf_result['errors']))
			$errors = array_merge($errors, $cf_result['errors']);

		// If there are errors, rebuild the form and redisplay
		if (!empty($errors))
		{
			loadTemplate('SimpleDeskPost');
			loadCSSFile('helpdesk.css');

			// Un-preparseCode the message for the editor
			$display_message = un_preparsecode($message);

			// Look up starter and assigned names for the sidebar
			$starter_name_display = '';
			$assigned_name_display = '';
			$assigned_id_display = (int) $ticket['id_member_assigned'];

			$member_ids_display = array_filter(array($starter_id, $assigned_id_display));
			if (!empty($member_ids_display))
			{
				$name_req = $db->query('', '
					SELECT id_member, real_name
					FROM {db_prefix}members
					WHERE id_member IN ({array_int:ids})',
					array(
						'ids' => array_values($member_ids_display),
					)
				);
				while ($nr = $db->fetch_assoc($name_req))
				{
					if ((int) $nr['id_member'] === $starter_id)
						$starter_name_display = $nr['real_name'];
					if ((int) $nr['id_member'] === $assigned_id_display)
						$assigned_name_display = $nr['real_name'];
				}
				$db->free_result($name_req);
			}

			$context['ticket_form'] = array(
				'is_new' => $is_new_reply,
				'is_reply' => true,
				'is_editing' => !$is_new_reply,
				'dept' => $dept,
				'form_title' => $is_new_reply
					? (isset($txt['shd_post_reply']) ? $txt['shd_post_reply'] : 'Post Reply')
					: (isset($txt['shd_edit_reply']) ? $txt['shd_edit_reply'] : 'Edit Reply'),
				'form_action' => $scripturl . '?action=helpdesk;sa=savereply',
				'subject' => $ticket['subject'],
				'message' => $display_message,
				'ticket' => $ticket_id,
				'msg' => $msg_id,
				'status' => $status,
				'urgency' => array(
					'setting' => (int) $ticket['urgency'],
					'options' => array(),
				),
				'private' => array(
					'setting' => !empty($ticket['private']) ? 1 : 0,
					'can_change' => false,
				),
				'member' => array(
					'id' => $starter_id,
					'name' => $starter_name_display,
					'link' => !empty($starter_id) ? '<a href="' . $scripturl . '?action=profile;u=' . $starter_id . '">' . $starter_name_display . '</a>' : '',
				),
				'assigned' => array(
					'id' => $assigned_id_display,
					'name' => $assigned_name_display,
					'link' => !empty($assigned_id_display) ? '<a href="' . $scripturl . '?action=profile;u=' . $assigned_id_display . '">' . $assigned_name_display . '</a>' : '',
				),
				'errors' => $errors,
				'do_attach' => shd_allowed_to('shd_post_attachment', $dept) && !empty($modSettings['shd_attachments_mode']),
				'smileys_enabled' => true,
				'additional_opts' => array(),
			);

			// Context flags for template
			$context['display_private'] = false;
			$context['can_solve'] = $is_new_reply && (shd_allowed_to('shd_resolve_ticket_any', $dept) || ($is_own && shd_allowed_to('shd_resolve_ticket_own', $dept)));

			// Form submission protection (re-register for the redisplay)
			checkSubmitOnce('register');

			$context['page_title'] = $context['ticket_form']['form_title'] . ' - ' . $ticket['subject'];
			$context['sub_template'] = 'shd_post_reply';

			$context['linktree'][] = array(
				'name' => $txt['shd_helpdesk'],
				'url' => $scripturl . '?action=helpdesk;sa=main',
			);
			$context['linktree'][] = array(
				'name' => $ticket['subject'],
				'url' => $scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $ticket_id,
			);
			$context['linktree'][] = array(
				'name' => $context['ticket_form']['form_title'],
			);

			$this->_load_editor($context['ticket_form']['message']);
			return;
		}

		// Process smileys toggle
		$smileys_enabled = empty($_POST['ns']) ? 1 : 0;

		// Process resolve checkbox (new replies only)
		$resolve = false;
		if ($is_new_reply && !empty($_POST['resolve']))
		{
			$can_resolve = shd_allowed_to('shd_resolve_ticket_any', $dept) || ($is_own && shd_allowed_to('shd_resolve_ticket_own', $dept));
			if ($can_resolve)
				$resolve = true;
		}

		// Process attachments
		$attachIDs = array();
		if (shd_allowed_to('shd_post_attachment', $dept) && !empty($modSettings['shd_attachments_mode']))
			$attachIDs = shd_handle_attachments($dept);

		// Determine the new ticket status based on who is posting
		if ($resolve)
		{
			$new_status = TICKET_STATUS_CLOSED;
		}
		elseif ($is_new_reply)
		{
			// If the poster is the ticket starter, set to pending staff.
			// If the poster is not the starter (staff), set to pending user.
			if ($user_info['id'] == $starter_id)
				$new_status = TICKET_STATUS_PENDING_STAFF;
			else
				$new_status = TICKET_STATUS_PENDING_USER;
		}
		else
		{
			// When editing, keep the existing status
			$new_status = $status;
		}

		// Save the reply
		if ($is_new_reply)
		{
			$posterOptions = array(
				'id' => $user_info['id'],
				'name' => $user_info['name'],
				'email' => $user_info['email'],
				'ip' => $user_info['ip'],
			);

			$msgOptions = array(
				'body' => $message,
				'smileys_enabled' => $smileys_enabled,
				'attachments' => $attachIDs,
			);

			$ticketOptions = array(
				'id' => $ticket_id,
				'dept' => $dept,
				'status' => $new_status,
				'custom_fields' => !empty($cf_result['fields']) ? $cf_result['fields'] : array(),
			);

			shd_create_ticket_post($msgOptions, $ticketOptions, $posterOptions);

			// Log the action
			shd_log_action('newreply', array(
				'ticket' => $ticket_id,
				'msg' => $msgOptions['id'],
				'subject' => $ticket['subject'],
			));

			if ($resolve)
			{
				shd_log_action('resolve', array(
					'ticket' => $ticket_id,
					'subject' => $ticket['subject'],
				));
			}

			// Redirect to the new reply (at the end of the ticket)
			redirectexit('action=helpdesk;sa=viewticket;ticket=' . $ticket_id . '.msg' . $msgOptions['id'] . '#msg' . $msgOptions['id']);
		}
		else
		{
			// Editing an existing reply
			$msgOptions = array(
				'id' => $msg_id,
				'body' => $message,
				'smileys_enabled' => $smileys_enabled,
				'attachments' => $attachIDs,
			);

			$ticketOptions = array(
				'id' => $ticket_id,
				'dept' => $dept,
				'is_ticket_edit' => false,
				'custom_fields' => !empty($cf_result['fields']) ? $cf_result['fields'] : array(),
			);

			$posterOptions = array(
				'id' => $user_info['id'],
				'name' => $user_info['name'],
				'email' => $user_info['email'],
				'ip' => $user_info['ip'],
			);

			shd_modify_ticket_post($msgOptions, $ticketOptions, $posterOptions);

			// Log the action
			shd_log_action('editreply', array(
				'ticket' => $ticket_id,
				'msg' => $msg_id,
				'subject' => $ticket['subject'],
			));

			// Redirect back to the reply
			redirectexit('action=helpdesk;sa=viewticket;ticket=' . $ticket_id . '.msg' . $msg_id . '#msg' . $msg_id);
		}
	}

	/**
	 * Loads the ElkArte editor for the post form.
	 *
	 * Sets up the editor in $context so the template can render it. Uses
	 * ElkArte's createEventManager and editor integration.
	 *
	 * @param string $message The initial message content for the editor.
	 */
	private function _load_editor($message = '')
	{
		global $context, $modSettings;

		// Load the ElkArte editor requirements
		require_once(SUBSDIR . '/Editor.subs.php');

		// Set up the editor options
		$editorOptions = array(
			'id' => 'message',
			'value' => $message,
			'labels' => array(
				'post_button' => isset($context['ticket_form']['is_reply']) && $context['ticket_form']['is_reply']
					? (isset($context['ticket_form']['is_editing']) && $context['ticket_form']['is_editing'] ? 'shd_edit_reply' : 'shd_post_reply')
					: (isset($context['ticket_form']['is_editing']) && $context['ticket_form']['is_editing'] ? 'shd_edit_ticket' : 'shd_post_ticket'),
			),
			'height' => '250px',
			'width' => '100%',
			'preview_type' => 0,
			'required' => true,
		);

		create_control_richedit($editorOptions);

		$context['post_box_name'] = $editorOptions['id'];
	}
}
