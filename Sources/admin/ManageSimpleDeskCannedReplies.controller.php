<?php
/**
 * SimpleDesk Canned Replies Management Controller
 *
 * Handles administration of pre-written reply templates organized by
 * category. Supports creating, editing, reordering, and deleting both
 * canned reply categories and individual canned replies. Replies can
 * be assigned to specific departments.
 *
 * @package SimpleDesk
 * @author SimpleDesk Team
 * @copyright 2025 SimpleDesk Team
 * @license BSD 3-Clause License
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Controller for managing canned replies and their categories.
 *
 * Dispatched from ManageSimpleDesk_Controller when sa=canned_replies.
 * Uses the 'do' parameter for internal sub-action routing since 'sa'
 * is already consumed by the parent controller.
 *
 * URL pattern: action=admin;area=helpdesk;sa=canned_replies;do=X
 */
class ManageSimpleDeskCannedReplies_Controller extends Action_Controller
{
	/**
	 * Entry point for canned replies management.
	 *
	 * Routes to the appropriate sub-action based on $_REQUEST['do'].
	 * Loads required language and template files, verifies admin
	 * permission, and initializes the helpdesk subsystem.
	 */
	public function action_index()
	{
		global $context, $txt;

		require_once(SUBSDIR . '/SimpleDesk.subs.php');
		shd_init();

		isAllowedTo('admin_forum');

		loadLanguage('SimpleDesk');
		loadLanguage('SimpleDeskAdmin');
		loadTemplate('SimpleDeskAdminCannedReplies');

		$subActions = array(
			'list' => 'action_list',
			'createcat' => 'action_createcat',
			'movecat' => 'action_movecat',
			'editcat' => 'action_editcat',
			'savecat' => 'action_savecat',
			'createreply' => 'action_createreply',
			'movereply' => 'action_movereply',
			'movereplycat' => 'action_movereplycat',
			'editreply' => 'action_editreply',
			'savereply' => 'action_savereply',
		);

		$do = isset($_REQUEST['do']) ? $_REQUEST['do'] : 'list';

		if (isset($subActions[$do]))
			$this->{$subActions[$do]}();
		else
			$this->action_list();
	}

	/**
	 * Lists all canned replies organized by category.
	 *
	 * Fetches all categories and their replies, including department
	 * assignments for each reply. Builds the full data structure for
	 * the template with move_up/move_down flags for reordering controls.
	 */
	public function action_list()
	{
		global $context, $txt, $scripturl;

		$db = database();

		$context['page_title'] = isset($txt['shd_admin_cannedreplies_title']) ? $txt['shd_admin_cannedreplies_title'] : $txt['shd_admin_cannedreplies'];
		$context['sub_template'] = 'shd_cannedreplies_home';

		// First query: Get department names per reply via JOIN.
		$reply_depts = array();
		$request = $db->query('', '
			SELECT crd.id_reply, hd.dept_name
			FROM {db_prefix}helpdesk_cannedreplies_depts AS crd
				INNER JOIN {db_prefix}helpdesk_depts AS hd ON (crd.id_dept = hd.id_dept)
			ORDER BY hd.dept_order ASC',
			array()
		);

		while ($row = $db->fetch_assoc($request))
		{
			if (!isset($reply_depts[$row['id_reply']]))
				$reply_depts[$row['id_reply']] = array();
			$reply_depts[$row['id_reply']][] = $row['dept_name'];
		}
		$db->free_result($request);

		// Second query: Get all categories and their replies.
		$context['canned_replies'] = array();

		$request = $db->query('', '
			SELECT cc.id_cat, cc.cat_name, cc.cat_order,
				cr.id_reply, cr.title, cr.body, cr.vis_user, cr.vis_staff,
				cr.reply_order, cr.active
			FROM {db_prefix}helpdesk_cannedreplies_cats AS cc
				LEFT JOIN {db_prefix}helpdesk_cannedreplies AS cr ON (cc.id_cat = cr.id_cat)
			ORDER BY cc.cat_order ASC, cr.reply_order ASC',
			array()
		);

		while ($row = $db->fetch_assoc($request))
		{
			$cat_id = $row['id_cat'];

			if (!isset($context['canned_replies'][$cat_id]))
			{
				$context['canned_replies'][$cat_id] = array(
					'id' => $cat_id,
					'name' => $row['cat_name'],
					'cat_order' => $row['cat_order'],
					'move_up' => true,
					'move_down' => true,
					'replies' => array(),
				);
			}

			// If there is a reply in this row (LEFT JOIN may yield null).
			if (!empty($row['id_reply']))
			{
				$dept_list = isset($reply_depts[$row['id_reply']]) ? $reply_depts[$row['id_reply']] : array();
				$context['canned_replies'][$cat_id]['replies'][$row['id_reply']] = array(
					'id' => $row['id_reply'],
					'title' => $row['title'],
					'vis_user' => $row['vis_user'],
					'vis_staff' => $row['vis_staff'],
					'active' => $row['active'],
					'active_string' => !empty($row['active']) ? (isset($txt['shd_admin_cannedreplies_active_on']) ? $txt['shd_admin_cannedreplies_active_on'] : 'Yes') : (isset($txt['shd_admin_cannedreplies_active_off']) ? $txt['shd_admin_cannedreplies_active_off'] : 'No'),
					'reply_order' => $row['reply_order'],
					'move_up' => true,
					'move_down' => true,
					'depts' => !empty($dept_list) ? implode(', ', $dept_list) : '',
				);
			}
		}
		$db->free_result($request);

		// Set move_up/move_down flags for categories.
		if (!empty($context['canned_replies']))
		{
			$cat_ids = array_keys($context['canned_replies']);
			$context['canned_replies'][$cat_ids[0]]['move_up'] = false;
			$context['canned_replies'][$cat_ids[count($cat_ids) - 1]]['move_down'] = false;

			// Set move_up/move_down flags for replies within each category.
			foreach ($context['canned_replies'] as &$cat)
			{
				if (!empty($cat['replies']))
				{
					$reply_ids = array_keys($cat['replies']);
					$cat['replies'][$reply_ids[0]]['move_up'] = false;
					$cat['replies'][$reply_ids[count($reply_ids) - 1]]['move_down'] = false;
				}
			}

			// Allow moving replies between categories if there are multiple.
			$context['move_between_cats'] = count($context['canned_replies']) > 1;
		}
	}

	/**
	 * Displays the form to create a new canned reply category.
	 *
	 * Sets up context for an empty category form and loads the edit
	 * category sub-template.
	 */
	public function action_createcat()
	{
		global $context, $txt;

		$context['page_title'] = isset($txt['shd_admin_canned_createcat']) ? $txt['shd_admin_canned_createcat'] : $txt['shd_admin_cannedreplies'];
		$context['sub_template'] = 'shd_edit_canned_category';

		$context['canned_category'] = 'new';
		$context['category_name'] = '';

		checkSubmitOnce('register');
	}

	/**
	 * Displays the form to edit an existing canned reply category.
	 *
	 * Loads the category data from the database and sets up the edit
	 * category sub-template.
	 */
	public function action_editcat()
	{
		global $context, $txt;

		$db = database();

		$cat_id = isset($_REQUEST['cat']) ? (int) $_REQUEST['cat'] : 0;
		if (empty($cat_id))
			fatal_lang_error('shd_no_ticket', false);

		// Load the category.
		$request = $db->query('', '
			SELECT id_cat, cat_name
			FROM {db_prefix}helpdesk_cannedreplies_cats
			WHERE id_cat = {int:cat}',
			array(
				'cat' => $cat_id,
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			fatal_lang_error('shd_no_ticket', false);
		}

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		$context['canned_category'] = $row['id_cat'];
		$context['category_name'] = $row['cat_name'];

		$context['page_title'] = isset($txt['shd_admin_canned_editcat']) ? $txt['shd_admin_canned_editcat'] : $txt['shd_admin_cannedreplies'];
		$context['sub_template'] = 'shd_edit_canned_category';

		checkSubmitOnce('register');
	}

	/**
	 * Saves a canned reply category (create, update, or delete).
	 *
	 * Handles three operations:
	 * - DELETE: Removes the category, all its replies, and their dept mappings.
	 *   Reorders remaining categories.
	 * - CREATE: Inserts a new category with the next available order position.
	 * - UPDATE: Updates the category name.
	 *
	 * Redirects to the canned replies list on completion.
	 */
	public function action_savecat()
	{
		global $context, $txt, $scripturl;

		$db = database();

		checkSession();
		checkSubmitOnce('check');

		$cat_id = isset($_POST['cat']) ? $_POST['cat'] : '';

		// Handle delete.
		if (isset($_POST['delete']) && $cat_id !== 'new')
		{
			$cat_id = (int) $cat_id;
			if (empty($cat_id))
				fatal_lang_error('shd_no_ticket', false);

			// Get all reply IDs in this category for dept mapping cleanup.
			$reply_ids = array();
			$request = $db->query('', '
				SELECT id_reply
				FROM {db_prefix}helpdesk_cannedreplies
				WHERE id_cat = {int:cat}',
				array(
					'cat' => $cat_id,
				)
			);

			while ($row = $db->fetch_assoc($request))
				$reply_ids[] = $row['id_reply'];
			$db->free_result($request);

			// Delete department mappings for all replies in this category.
			if (!empty($reply_ids))
			{
				$db->query('', '
					DELETE FROM {db_prefix}helpdesk_cannedreplies_depts
					WHERE id_reply IN ({array_int:replies})',
					array(
						'replies' => $reply_ids,
					)
				);
			}

			// Delete all replies in this category.
			$db->query('', '
				DELETE FROM {db_prefix}helpdesk_cannedreplies
				WHERE id_cat = {int:cat}',
				array(
					'cat' => $cat_id,
				)
			);

			// Delete the category itself.
			$db->query('', '
				DELETE FROM {db_prefix}helpdesk_cannedreplies_cats
				WHERE id_cat = {int:cat}',
				array(
					'cat' => $cat_id,
				)
			);

			// Reorder remaining categories.
			$request = $db->query('', '
				SELECT id_cat
				FROM {db_prefix}helpdesk_cannedreplies_cats
				ORDER BY cat_order ASC',
				array()
			);

			$new_order = 1;
			while ($row = $db->fetch_assoc($request))
			{
				$db->query('', '
					UPDATE {db_prefix}helpdesk_cannedreplies_cats
					SET cat_order = {int:new_order}
					WHERE id_cat = {int:cat}',
					array(
						'new_order' => $new_order,
						'cat' => $row['id_cat'],
					)
				);
				$new_order++;
			}
			$db->free_result($request);

			redirectexit('action=admin;area=helpdesk;sa=canned_replies');
		}

		// Validate category name.
		$cat_name = isset($_POST['catname']) ? Util::htmltrim(Util::htmlspecialchars($_POST['catname'])) : '';
		if (empty($cat_name))
			fatal_lang_error('shd_admin_cannedreplies_nocatname', false);

		// Handle create.
		if ($cat_id === 'new')
		{
			// Get the next cat_order value.
			$request = $db->query('', '
				SELECT MAX(cat_order)
				FROM {db_prefix}helpdesk_cannedreplies_cats',
				array()
			);
			list($max_order) = $db->fetch_row($request);
			$db->free_result($request);

			$new_order = (int) $max_order + 1;

			$db->insert('insert',
				'{db_prefix}helpdesk_cannedreplies_cats',
				array(
					'cat_name' => 'string',
					'cat_order' => 'int',
				),
				array(
					$cat_name,
					$new_order,
				),
				array('id_cat')
			);

			redirectexit('action=admin;area=helpdesk;sa=canned_replies');
		}

		// Handle update.
		$cat_id = (int) $cat_id;
		if (empty($cat_id))
			fatal_lang_error('shd_no_ticket', false);

		$db->query('', '
			UPDATE {db_prefix}helpdesk_cannedreplies_cats
			SET cat_name = {string:cat_name}
			WHERE id_cat = {int:cat}',
			array(
				'cat_name' => $cat_name,
				'cat' => $cat_id,
			)
		);

		redirectexit('action=admin;area=helpdesk;sa=canned_replies');
	}

	/**
	 * Moves a canned reply category up or down in display order.
	 *
	 * Validates the session, loads current ordering, and swaps positions
	 * between the target category and its neighbor. Redirects to the
	 * canned replies list afterward.
	 */
	public function action_movecat()
	{
		$db = database();

		checkSession('get');

		$cat_id = isset($_REQUEST['cat']) ? (int) $_REQUEST['cat'] : 0;
		$direction = isset($_REQUEST['direction']) ? $_REQUEST['direction'] : '';

		if (empty($cat_id) || !in_array($direction, array('up', 'down')))
			fatal_lang_error('shd_no_ticket', false);

		// Load all categories in order.
		$cats = array();
		$request = $db->query('', '
			SELECT id_cat, cat_order
			FROM {db_prefix}helpdesk_cannedreplies_cats
			ORDER BY cat_order ASC',
			array()
		);

		while ($row = $db->fetch_assoc($request))
			$cats[] = array(
				'id_cat' => (int) $row['id_cat'],
				'cat_order' => (int) $row['cat_order'],
			);
		$db->free_result($request);

		// Find the current category's position.
		$current_index = null;
		foreach ($cats as $index => $cat)
		{
			if ($cat['id_cat'] == $cat_id)
			{
				$current_index = $index;
				break;
			}
		}

		if ($current_index === null)
			fatal_lang_error('shd_no_ticket', false);

		// Determine the swap target.
		if ($direction === 'up' && $current_index > 0)
			$swap_index = $current_index - 1;
		elseif ($direction === 'down' && $current_index < count($cats) - 1)
			$swap_index = $current_index + 1;
		else
			redirectexit('action=admin;area=helpdesk;sa=canned_replies');

		// Swap the cat_order values.
		$db->query('', '
			UPDATE {db_prefix}helpdesk_cannedreplies_cats
			SET cat_order = CASE id_cat
				WHEN {int:cat1} THEN {int:order2}
				WHEN {int:cat2} THEN {int:order1}
				ELSE cat_order
			END
			WHERE id_cat IN ({int:cat1}, {int:cat2})',
			array(
				'cat1' => $cats[$current_index]['id_cat'],
				'order2' => $cats[$swap_index]['cat_order'],
				'cat2' => $cats[$swap_index]['id_cat'],
				'order1' => $cats[$current_index]['cat_order'],
			)
		);

		redirectexit('action=admin;area=helpdesk;sa=canned_replies');
	}

	/**
	 * Displays the form to create a new canned reply.
	 *
	 * Verifies that the specified category exists, loads the department
	 * list for assignment checkboxes, and initializes the rich text
	 * editor for the reply body. Sets the edit reply sub-template.
	 */
	public function action_createreply()
	{
		global $context, $txt;

		$db = database();

		$cat_id = isset($_REQUEST['cat']) ? (int) $_REQUEST['cat'] : 0;

		// Verify the category exists.
		if (!empty($cat_id))
		{
			$request = $db->query('', '
				SELECT id_cat, cat_name
				FROM {db_prefix}helpdesk_cannedreplies_cats
				WHERE id_cat = {int:cat}',
				array(
					'cat' => $cat_id,
				)
			);

			if ($db->num_rows($request) == 0)
			{
				$db->free_result($request);
				fatal_lang_error('shd_no_ticket', false);
			}

			$cat_row = $db->fetch_assoc($request);
			$db->free_result($request);
		}
		else
		{
			// Need a category to create a reply in.
			fatal_lang_error('shd_no_ticket', false);
		}

		$context['page_title'] = isset($txt['shd_admin_canned_createreply']) ? $txt['shd_admin_canned_createreply'] : $txt['shd_admin_cannedreplies'];
		$context['sub_template'] = 'shd_edit_canned_reply';

		$context['canned_reply'] = array(
			'id' => 'new',
			'cat' => $cat_id,
			'cat_name' => $cat_row['cat_name'],
			'title' => '',
			'body' => '',
			'vis_user' => 0,
			'vis_staff' => 0,
			'active' => 1,
			'departments' => array(),
		);

		// Load department list.
		$this->_load_departments();

		// Set up the rich text editor.
		$this->_load_editor('');

		checkSubmitOnce('register');
	}

	/**
	 * Displays the form to edit an existing canned reply.
	 *
	 * Loads the reply data, un-preparses the body for editing, loads
	 * the department list with selected departments marked, and
	 * initializes the rich text editor. Sets the edit reply sub-template.
	 */
	public function action_editreply()
	{
		global $context, $txt;

		$db = database();

		$reply_id = isset($_REQUEST['reply']) ? (int) $_REQUEST['reply'] : 0;
		if (empty($reply_id))
			fatal_lang_error('shd_no_ticket', false);

		// Load the reply with its category name.
		$request = $db->query('', '
			SELECT cr.id_reply, cr.id_cat, cr.title, cr.body,
				cr.vis_user, cr.vis_staff, cr.active,
				cc.cat_name
			FROM {db_prefix}helpdesk_cannedreplies AS cr
				INNER JOIN {db_prefix}helpdesk_cannedreplies_cats AS cc ON (cr.id_cat = cc.id_cat)
			WHERE cr.id_reply = {int:reply}',
			array(
				'reply' => $reply_id,
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			fatal_lang_error('shd_no_ticket', false);
		}

		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		// Un-preparsecode the body for editing.
		require_once(SUBSDIR . '/Post.subs.php');
		$body = un_preparsecode($row['body']);

		$context['canned_reply'] = array(
			'id' => $row['id_reply'],
			'cat' => $row['id_cat'],
			'cat_name' => $row['cat_name'],
			'title' => $row['title'],
			'body' => $body,
			'vis_user' => $row['vis_user'],
			'vis_staff' => $row['vis_staff'],
			'active' => $row['active'],
			'departments' => array(),
		);

		// Load departments assigned to this reply.
		$request = $db->query('', '
			SELECT id_dept
			FROM {db_prefix}helpdesk_cannedreplies_depts
			WHERE id_reply = {int:reply}',
			array(
				'reply' => $reply_id,
			)
		);

		while ($dept_row = $db->fetch_assoc($request))
			$context['canned_reply']['departments'][] = (int) $dept_row['id_dept'];
		$db->free_result($request);

		$context['page_title'] = isset($txt['shd_admin_canned_editreply']) ? $txt['shd_admin_canned_editreply'] : $txt['shd_admin_cannedreplies'];
		$context['sub_template'] = 'shd_edit_canned_reply';

		// Load department list.
		$this->_load_departments();

		// Set up the rich text editor.
		$this->_load_editor($body);

		checkSubmitOnce('register');
	}

	/**
	 * Saves a canned reply (create, update, or delete).
	 *
	 * Handles three operations:
	 * - DELETE: Removes the reply and its department mappings, then
	 *   reorders remaining replies in the same category.
	 * - CREATE: Validates input, preparses the body, inserts the reply
	 *   with the next available order, and creates department mappings.
	 * - UPDATE: Validates input, preparses the body, updates the reply
	 *   record, and replaces department mappings.
	 *
	 * Redirects to the canned replies list on completion.
	 */
	public function action_savereply()
	{
		global $context, $txt;

		$db = database();

		checkSession();
		checkSubmitOnce('check');

		$reply_id = isset($_POST['reply']) ? $_POST['reply'] : '';

		// Handle delete.
		if (isset($_POST['delete']) && $reply_id !== 'new')
		{
			$reply_id = (int) $reply_id;
			if (empty($reply_id))
				fatal_lang_error('shd_no_ticket', false);

			// Get the category for reordering after deletion.
			$request = $db->query('', '
				SELECT id_cat
				FROM {db_prefix}helpdesk_cannedreplies
				WHERE id_reply = {int:reply}',
				array(
					'reply' => $reply_id,
				)
			);

			if ($db->num_rows($request) == 0)
			{
				$db->free_result($request);
				fatal_lang_error('shd_no_ticket', false);
			}

			list($cat_id) = $db->fetch_row($request);
			$db->free_result($request);

			// Delete department mappings.
			$db->query('', '
				DELETE FROM {db_prefix}helpdesk_cannedreplies_depts
				WHERE id_reply = {int:reply}',
				array(
					'reply' => $reply_id,
				)
			);

			// Delete the reply.
			$db->query('', '
				DELETE FROM {db_prefix}helpdesk_cannedreplies
				WHERE id_reply = {int:reply}',
				array(
					'reply' => $reply_id,
				)
			);

			// Reorder remaining replies in this category.
			$request = $db->query('', '
				SELECT id_reply
				FROM {db_prefix}helpdesk_cannedreplies
				WHERE id_cat = {int:cat}
				ORDER BY reply_order ASC',
				array(
					'cat' => $cat_id,
				)
			);

			$new_order = 1;
			while ($row = $db->fetch_assoc($request))
			{
				$db->query('', '
					UPDATE {db_prefix}helpdesk_cannedreplies
					SET reply_order = {int:new_order}
					WHERE id_reply = {int:reply}',
					array(
						'new_order' => $new_order,
						'reply' => $row['id_reply'],
					)
				);
				$new_order++;
			}
			$db->free_result($request);

			redirectexit('action=admin;area=helpdesk;sa=canned_replies');
		}

		// Validate title.
		$title = isset($_POST['title']) ? Util::htmltrim(Util::htmlspecialchars($_POST['title'])) : '';
		if (empty($title))
			fatal_lang_error('shd_admin_cannedreplies_notitle', false);

		// Validate and process body.
		require_once(SUBSDIR . '/Post.subs.php');

		$body = isset($_POST['message']) ? $_POST['message'] : '';
		$body = Util::htmlspecialchars($body, ENT_QUOTES);

		// Handle WYSIWYG mode conversion if applicable.
		if (!empty($_REQUEST['message_mode']) && isset($_REQUEST['message']))
		{
			require_once(SUBSDIR . '/Editor.subs.php');

			$body = html_to_bbc($body);
			$body = un_htmlspecialchars($body);
			$body = Util::htmlspecialchars($body, ENT_QUOTES);
		}

		require_once(SUBSDIR . '/Post.subs.php');
		preparsecode($body);

		if (Util::htmltrim(strip_tags(parse_bbc($body, false), '<img>')) === '')
			fatal_lang_error('shd_admin_cannedreplies_nobody', false);

		// Process visibility options.
		$vis_user = !empty($_POST['vis_user']) ? 1 : 0;
		$vis_staff = !empty($_POST['vis_staff']) ? 1 : 0;
		$active = !empty($_POST['active']) ? 1 : 0;

		// Process category.
		$cat_id = isset($_POST['cat']) ? (int) $_POST['cat'] : 0;
		if (empty($cat_id))
			fatal_lang_error('shd_no_ticket', false);

		// Process department checkboxes.
		$departments = array();
		$request = $db->query('', '
			SELECT id_dept
			FROM {db_prefix}helpdesk_depts
			ORDER BY dept_order ASC',
			array()
		);

		while ($row = $db->fetch_assoc($request))
		{
			if (!empty($_POST['dept_' . $row['id_dept']]))
				$departments[] = (int) $row['id_dept'];
		}
		$db->free_result($request);

		// Handle create.
		if ($reply_id === 'new')
		{
			// Get the next reply_order value within this category.
			$request = $db->query('', '
				SELECT MAX(reply_order)
				FROM {db_prefix}helpdesk_cannedreplies
				WHERE id_cat = {int:cat}',
				array(
					'cat' => $cat_id,
				)
			);
			list($max_order) = $db->fetch_row($request);
			$db->free_result($request);

			$new_order = (int) $max_order + 1;

			$db->insert('insert',
				'{db_prefix}helpdesk_cannedreplies',
				array(
					'id_cat' => 'int',
					'title' => 'string',
					'body' => 'string',
					'vis_user' => 'int',
					'vis_staff' => 'int',
					'reply_order' => 'int',
					'active' => 'int',
				),
				array(
					$cat_id,
					$title,
					$body,
					$vis_user,
					$vis_staff,
					$new_order,
					$active,
				),
				array('id_reply')
			);

			$new_reply_id = $db->insert_id('{db_prefix}helpdesk_cannedreplies', 'id_reply');

			// Insert department mappings.
			foreach ($departments as $dept_id)
			{
				$db->insert('insert',
					'{db_prefix}helpdesk_cannedreplies_depts',
					array(
						'id_dept' => 'int',
						'id_reply' => 'int',
					),
					array(
						$dept_id,
						$new_reply_id,
					),
					array('id_dept', 'id_reply')
				);
			}

			redirectexit('action=admin;area=helpdesk;sa=canned_replies');
		}

		// Handle update.
		$reply_id = (int) $reply_id;
		if (empty($reply_id))
			fatal_lang_error('shd_no_ticket', false);

		$db->query('', '
			UPDATE {db_prefix}helpdesk_cannedreplies
			SET title = {string:title},
				body = {string:body},
				vis_user = {int:vis_user},
				vis_staff = {int:vis_staff},
				active = {int:active}
			WHERE id_reply = {int:reply}',
			array(
				'title' => $title,
				'body' => $body,
				'vis_user' => $vis_user,
				'vis_staff' => $vis_staff,
				'active' => $active,
				'reply' => $reply_id,
			)
		);

		// Replace department mappings: delete all existing, then re-insert.
		$db->query('', '
			DELETE FROM {db_prefix}helpdesk_cannedreplies_depts
			WHERE id_reply = {int:reply}',
			array(
				'reply' => $reply_id,
			)
		);

		foreach ($departments as $dept_id)
		{
			$db->insert('insert',
				'{db_prefix}helpdesk_cannedreplies_depts',
				array(
					'id_dept' => 'int',
					'id_reply' => 'int',
				),
				array(
					$dept_id,
					$reply_id,
				),
				array('id_dept', 'id_reply')
			);
		}

		redirectexit('action=admin;area=helpdesk;sa=canned_replies');
	}

	/**
	 * Moves a canned reply up or down within its category.
	 *
	 * Validates the session, loads all replies in the same category,
	 * finds the target reply, and swaps its position with the adjacent
	 * reply. Redirects to the canned replies list afterward.
	 */
	public function action_movereply()
	{
		$db = database();

		checkSession('get');

		$reply_id = isset($_REQUEST['reply']) ? (int) $_REQUEST['reply'] : 0;
		$direction = isset($_REQUEST['direction']) ? $_REQUEST['direction'] : '';

		if (empty($reply_id) || !in_array($direction, array('up', 'down')))
			fatal_lang_error('shd_no_ticket', false);

		// Get the reply's category.
		$request = $db->query('', '
			SELECT id_cat
			FROM {db_prefix}helpdesk_cannedreplies
			WHERE id_reply = {int:reply}',
			array(
				'reply' => $reply_id,
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			fatal_lang_error('shd_no_ticket', false);
		}

		list($cat_id) = $db->fetch_row($request);
		$db->free_result($request);

		// Load all replies in this category in order.
		$replies = array();
		$request = $db->query('', '
			SELECT id_reply, reply_order
			FROM {db_prefix}helpdesk_cannedreplies
			WHERE id_cat = {int:cat}
			ORDER BY reply_order ASC',
			array(
				'cat' => $cat_id,
			)
		);

		while ($row = $db->fetch_assoc($request))
			$replies[] = array(
				'id_reply' => (int) $row['id_reply'],
				'reply_order' => (int) $row['reply_order'],
			);
		$db->free_result($request);

		// Find the current reply's position.
		$current_index = null;
		foreach ($replies as $index => $reply)
		{
			if ($reply['id_reply'] == $reply_id)
			{
				$current_index = $index;
				break;
			}
		}

		if ($current_index === null)
			fatal_lang_error('shd_no_ticket', false);

		// Determine the swap target.
		if ($direction === 'up' && $current_index > 0)
			$swap_index = $current_index - 1;
		elseif ($direction === 'down' && $current_index < count($replies) - 1)
			$swap_index = $current_index + 1;
		else
			redirectexit('action=admin;area=helpdesk;sa=canned_replies');

		// Swap the reply_order values.
		$db->query('', '
			UPDATE {db_prefix}helpdesk_cannedreplies
			SET reply_order = CASE id_reply
				WHEN {int:reply1} THEN {int:order2}
				WHEN {int:reply2} THEN {int:order1}
				ELSE reply_order
			END
			WHERE id_reply IN ({int:reply1}, {int:reply2})',
			array(
				'reply1' => $replies[$current_index]['id_reply'],
				'order2' => $replies[$swap_index]['reply_order'],
				'reply2' => $replies[$swap_index]['id_reply'],
				'order1' => $replies[$current_index]['reply_order'],
			)
		);

		redirectexit('action=admin;area=helpdesk;sa=canned_replies');
	}

	/**
	 * Moves a canned reply to a different category.
	 *
	 * Two-phase operation:
	 * - Phase 1 (no 'part' parameter): Displays a form with a category
	 *   dropdown (excluding the current category) for selecting the
	 *   destination. Uses the shd_move_reply_cat sub-template.
	 * - Phase 2 ('part' parameter present): Moves the reply to the
	 *   selected category at the end of the order, then reorders the
	 *   source category to close any gaps.
	 */
	public function action_movereplycat()
	{
		global $context, $txt, $scripturl;

		$db = database();

		$reply_id = isset($_REQUEST['reply']) ? (int) $_REQUEST['reply'] : 0;
		if (empty($reply_id))
			fatal_lang_error('shd_no_ticket', false);

		// Load the reply's current category.
		$request = $db->query('', '
			SELECT cr.id_reply, cr.id_cat, cr.title, cc.cat_name
			FROM {db_prefix}helpdesk_cannedreplies AS cr
				INNER JOIN {db_prefix}helpdesk_cannedreplies_cats AS cc ON (cr.id_cat = cc.id_cat)
			WHERE cr.id_reply = {int:reply}',
			array(
				'reply' => $reply_id,
			)
		);

		if ($db->num_rows($request) == 0)
		{
			$db->free_result($request);
			fatal_lang_error('shd_no_ticket', false);
		}

		$reply_info = $db->fetch_assoc($request);
		$db->free_result($request);

		// Phase 2: Process the move (form submission).
		if (isset($_POST['tocat']))
		{
			checkSession();

			$new_cat = (int) $_POST['tocat'];

			// Validate the new category exists and is different.
			if (empty($new_cat) || $new_cat == $reply_info['id_cat'])
				redirectexit('action=admin;area=helpdesk;sa=canned_replies');

			$request = $db->query('', '
				SELECT id_cat
				FROM {db_prefix}helpdesk_cannedreplies_cats
				WHERE id_cat = {int:cat}',
				array(
					'cat' => $new_cat,
				)
			);

			if ($db->num_rows($request) == 0)
			{
				$db->free_result($request);
				fatal_lang_error('shd_no_ticket', false);
			}
			$db->free_result($request);

			// Get the next order position in the new category.
			$request = $db->query('', '
				SELECT MAX(reply_order)
				FROM {db_prefix}helpdesk_cannedreplies
				WHERE id_cat = {int:cat}',
				array(
					'cat' => $new_cat,
				)
			);
			list($max_order) = $db->fetch_row($request);
			$db->free_result($request);

			$new_order = (int) $max_order + 1;

			$old_cat = (int) $reply_info['id_cat'];

			// Move the reply to the new category.
			$db->query('', '
				UPDATE {db_prefix}helpdesk_cannedreplies
				SET id_cat = {int:new_cat},
					reply_order = {int:new_order}
				WHERE id_reply = {int:reply}',
				array(
					'new_cat' => $new_cat,
					'new_order' => $new_order,
					'reply' => $reply_id,
				)
			);

			// Reorder the old category to close the gap.
			$request = $db->query('', '
				SELECT id_reply
				FROM {db_prefix}helpdesk_cannedreplies
				WHERE id_cat = {int:cat}
				ORDER BY reply_order ASC',
				array(
					'cat' => $old_cat,
				)
			);

			$reorder = 1;
			while ($row = $db->fetch_assoc($request))
			{
				$db->query('', '
					UPDATE {db_prefix}helpdesk_cannedreplies
					SET reply_order = {int:new_order}
					WHERE id_reply = {int:reply}',
					array(
						'new_order' => $reorder,
						'reply' => $row['id_reply'],
					)
				);
				$reorder++;
			}
			$db->free_result($request);

			redirectexit('action=admin;area=helpdesk;sa=canned_replies');
		}

		// Phase 1: Display the move form.
		$context['page_title'] = isset($txt['shd_admin_canned_movereplycat']) ? $txt['shd_admin_canned_movereplycat'] : $txt['shd_admin_cannedreplies'];
		$context['sub_template'] = 'shd_move_reply_cat';

		checkSubmitOnce('register');

		$context['canned_reply'] = array(
			'id' => $reply_info['id_reply'],
			'title' => $reply_info['title'],
			'cat' => $reply_info['id_cat'],
			'cat_name' => $reply_info['cat_name'],
		);

		// Load all categories except the current one for the dropdown.
		$context['cannedreply_cats'] = array();
		$request = $db->query('', '
			SELECT id_cat, cat_name
			FROM {db_prefix}helpdesk_cannedreplies_cats
			WHERE id_cat != {int:current_cat}
			ORDER BY cat_order ASC',
			array(
				'current_cat' => $reply_info['id_cat'],
			)
		);

		while ($row = $db->fetch_assoc($request))
			$context['cannedreply_cats'][$row['id_cat']] = $row['cat_name'];
		$db->free_result($request);
	}

	/**
	 * Loads the list of all helpdesk departments into context.
	 *
	 * Used by the reply create/edit forms to display department
	 * assignment checkboxes.
	 */
	private function _load_departments()
	{
		global $context;

		$db = database();

		$context['shd_departments'] = array();

		$request = $db->query('', '
			SELECT id_dept, dept_name
			FROM {db_prefix}helpdesk_depts
			ORDER BY dept_order ASC',
			array()
		);

		while ($row = $db->fetch_assoc($request))
		{
			$context['shd_departments'][$row['id_dept']] = array(
				'id_dept' => $row['id_dept'],
				'dept_name' => $row['dept_name'],
			);
		}
		$db->free_result($request);
	}

	/**
	 * Initializes the ElkArte rich text editor for the reply body.
	 *
	 * Sets up the editor in $context so the template can render it.
	 * Used by both create and edit reply actions.
	 *
	 * @param string $message The initial message content for the editor.
	 */
	private function _load_editor($message = '')
	{
		global $context;

		require_once(SUBSDIR . '/Editor.subs.php');

		$editorOptions = array(
			'id' => 'message',
			'value' => $message,
			'labels' => array(
				'post_button' => 'shd_save',
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
