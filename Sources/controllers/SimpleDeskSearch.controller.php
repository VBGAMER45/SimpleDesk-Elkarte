<?php
/**
 * SimpleDesk Search Controller
 *
 * Handles searching through helpdesk tickets. Provides the search form
 * (action_search) and processes search queries with text and filter-based
 * matching (action_search2).
 *
 * Ported from SimpleDesk-Search.php (SMF version).
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Controller for helpdesk search functionality.
 */
class SimpleDeskSearch_Controller extends Action_Controller
{
	/**
	 * Default entry point - redirects to main helpdesk.
	 */
	public function action_index()
	{
		redirectexit('action=helpdesk');
	}

	/**
	 * Search form display.
	 *
	 * Sets up the search form with department list, urgency options,
	 * and scope filters. Loads the autosuggest JS for member name fields.
	 */
	public function action_search()
	{
		global $context, $txt, $modSettings, $scripturl;

		$db = database();

		require_once(SUBSDIR . '/SimpleDeskSearch.subs.php');

		shd_is_allowed_to('shd_search', 0);

		loadLanguage('SimpleDesk');
		loadTemplate('SimpleDeskSearch');

		loadJavascriptFile('suggest.js', array('defer' => false, 'minimize' => false), 'suggest');

		if (!empty($context['load_average']) && !empty($modSettings['loadavg_search']) && $context['load_average'] >= $modSettings['loadavg_search'])
			shd_fatal_lang_error('loadavg_search_disabled', false);

		$visible_depts = shd_allowed_to('access_helpdesk', false);
		$context['dept_list'] = array();

		$query = $db->query('', '
			SELECT id_dept, dept_name
			FROM {db_prefix}helpdesk_depts
			WHERE id_dept IN ({array_int:depts})
			ORDER BY dept_order',
			array(
				'depts' => $visible_depts,
			)
		);

		while ($row = $db->fetch_assoc($query))
			$context['dept_list'][$row['id_dept']] = $row['dept_name'];

		$db->free_result($query);

		$context['sub_template'] = 'search';
		$context['page_title'] = $txt['shd_search'];
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=helpdesk;sa=search',
			'name' => $txt['shd_search'],
		);
	}

	/**
	 * Process search results.
	 *
	 * Handles the full search pipeline: department filtering, urgency filtering,
	 * status/scope filtering (open/closed/recycle), named person lookup
	 * (starter/assignee), text tokenization, word-table matching, pagination,
	 * and spam protection with session caching.
	 */
	public function action_search2()
	{
		global $context, $txt, $modSettings, $scripturl;

		$db = database();

		shd_is_allowed_to('shd_search', 0);

		if (!empty($context['load_average']) && !empty($modSettings['loadavg_search']) && $context['load_average'] >= $modSettings['loadavg_search'])
			shd_fatal_lang_error('loadavg_search_disabled', false);

		// No, no, no... this is a bit hard on the server, so don't you go prefetching it!
		if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
		{
			ob_end_clean();
			header('HTTP/1.1 403 Forbidden');
			die;
		}

		// We will need this.
		require_once(SUBSDIR . '/SimpleDeskSearch.subs.php');

		loadLanguage('SimpleDesk');
		loadTemplate('SimpleDeskSearch');

		$context['page_title'] = $txt['shd_search_results'];

		$context['linktree'][] = array(
			'name' => $txt['shd_search_results'],
		);

		$context['search_clauses'] = array('{query_see_ticket}');
		$context['search_params'] = array();

		// Departments first.
		$visible_depts = shd_allowed_to('access_helpdesk', false);
		$using_depts = array();

		if (!empty($_POST['search_dept']) && is_array($_POST['search_dept']))
		{
			foreach ($_POST['search_dept'] as $dept)
			{
				if ((int) $dept > 0)
					$using_depts[] = (int) $dept;
			}
		}

		if (!empty($using_depts))
			$using_depts = array_intersect($using_depts, $visible_depts);

		// No departments? Can't really do a lot, sorry. Bye then.
		if (empty($using_depts))
			return $context['sub_template'] = 'search_no_results';

		// Is the selected list the same size as the list we can see? If it is, theory says
		// that means we're picking every department we can see and don't need to exclude it.
		if (count($using_depts) != count($visible_depts))
		{
			$context['search_clauses'][] = 'hdt.id_dept IN ({array_int:visible_depts})';
			$context['search_params']['visible_depts'] = $using_depts;

			// Also, we need to get the department list for displaying, only if we can
			// actually see multiple departments at all.
			if ($context['shd_multi_dept'])
			{
				$query = $db->query('', '
					SELECT id_dept, dept_name
					FROM {db_prefix}helpdesk_depts
					WHERE id_dept IN ({array_int:dept_list})
					ORDER BY dept_order',
					array(
						'dept_list' => $using_depts,
					)
				);

				$context['search_dept_list'] = array();

				while ($row = $db->fetch_assoc($query))
					$context['search_dept_list'][$row['id_dept']] = $row['dept_name'];

				$db->free_result($query);
			}
		}
		else
		{
			$visible_depts = shd_allowed_to('access_helpdesk', false);
			$context['dept_list'] = array();

			$query = $db->query('', '
				SELECT id_dept, dept_name
				FROM {db_prefix}helpdesk_depts
				WHERE id_dept IN ({array_int:depts})
				ORDER BY dept_order',
				array(
					'depts' => $visible_depts,
				)
			);

			while ($row = $db->fetch_assoc($query))
				$context['dept_list'][$row['id_dept']] = $row['dept_name'];

			$db->free_result($query);
		}

		// Ticket urgency
		$using_urgency = array();
		if (!empty($_POST['urgency']) && is_array($_POST['urgency']))
		{
			foreach ($_POST['urgency'] as $urgency)
			{
				$urgency = (int) $urgency;
				if ($urgency >= 0 && $urgency <= 5) // All the currently defined urgencies
					$using_urgency[] = $urgency;
			}
		}

		if (empty($using_urgency))
			return $context['sub_template'] = 'search_no_results';
		else
		{
			$using_urgency = array_unique($using_urgency);
			if (count($using_urgency) < 6)
			{
				// We have less than 6 selected urgencies, which means we actually need to
				// filter on them, as opposed to if all 6 are selected when we don't.
				$context['search_clauses'][] = 'hdt.urgency IN ({array_int:urgency})';
				$context['search_params']['urgency'] = $using_urgency;
			}
		}

		// Ticket scope
		// All empty? If so, bye.
		if (empty($_POST['scope_open']) && empty($_POST['scope_closed']) && empty($_POST['scope_recycle']))
			return $context['sub_template'] = 'search_no_results';
		// At least one empty? That way we have to do some filtering, you see.
		// (All set means no filtering required.)
		elseif (empty($_POST['scope_open']) || empty($_POST['scope_closed']) || empty($_POST['scope_recycle']))
		{
			$status = array();
			if (!empty($_POST['scope_open']))
			{
				$status = array_merge($status, array(
					TICKET_STATUS_NEW,
					TICKET_STATUS_PENDING_STAFF,
					TICKET_STATUS_PENDING_USER,
					TICKET_STATUS_WITH_SUPERVISOR,
					TICKET_STATUS_ESCALATED,
				));
				$context['search_params']['scope_open'] = true;
			}
			if (!empty($_POST['scope_closed']))
			{
				$status = array_merge($status, array(TICKET_STATUS_CLOSED));
				$context['search_params']['scope_closed'] = true;
			}
			if (!empty($_POST['scope_recycle']))
			{
				$status = array_merge($status, array(TICKET_STATUS_DELETED));
				$context['search_params']['scope_recycle'] = true;
			}

			$context['search_clauses'][] = 'hdt.status IN ({array_int:status})';
			$context['search_params']['status'] = $status;

			// That's ticket level status taken care of. We'll pick up recycled items
			// in non recycled tickets separately since it's only relevant if you're
			// actually searching text.
		}

		// Ticket starter
		$starters = $this->shd_get_named_people('starter');
		if (!empty($starters))
		{
			$context['search_clauses'][] = 'hdt.id_member_started IN ({array_int:member_started})';
			$context['search_params']['member_started'] = $starters;
		}

		// Ticket assigned to
		$assignees = $this->shd_get_named_people('assigned');
		if (!empty($assignees))
		{
			$context['search_clauses'][] = 'hdt.id_member_assigned IN ({array_int:member_assigned})';
			$context['search_params']['member_assigned'] = $assignees;
		}

		// Lastly, page number.
		if (isset($_POST['page']))
			$context['pagenum'] = (int) $_POST['page'];
		if (empty($context['pagenum']) || $context['pagenum'] < 1)
			$context['pagenum'] = 1;

		// Pages.
		$context['current_page'] = $context['pagenum'];
		$context['next_page'] = $context['pagenum'] + 1;
		$context['prev_page'] = $context['pagenum'] < 2 ? 0 : $context['pagenum'] - 1;

		$number_per_page = 20;
		$context['num_pages'] = 1;

		// OK, so are there any words? If not, execute this sucker the quick way
		// and get out to the template quick.
		$context['search_terms'] = !empty($_POST['search']) ? trim($_POST['search']) : '';

		// Also, did we select some text but fail to select what it was searching in?
		// If so, kick it out.
		if (!empty($context['search_terms']) && empty($_POST['search_subjects']) && empty($_POST['search_tickets']) && empty($_POST['search_replies']))
			return $context['sub_template'] = 'search_no_results';
		elseif (!empty($context['search_terms']))
		{
			// We're using search terms, and we need to store the areas we're covering.
			// Only makes sense if we're using terms though.
			$context['search_params']['areas'] = array();
			foreach (array('subjects', 'tickets', 'replies') as $area)
			{
				if (!empty($_POST['search_' . $area]))
					$context['search_params']['areas'][$area] = true;
			}

			// While we're at it, see if we actually have any words to search for.
			$tokens = shd_tokeniser($context['search_terms']);
			$count_tokens = count($tokens);

			// No actual words?
			if ($count_tokens == 0)
			{
				$context['search_terms'] = '';
				unset($context['search_params']['areas']);
			}
		}

		// Search type.
		$context['search_params']['searchtype'] = isset($_POST['searchtype']) && in_array($_POST['searchtype'], array('any', 'all')) ? $_POST['searchtype'] : 'all';

		// Spam me not!
		if (empty($_SESSION['lastsearch']))
			spamProtection('search');
		else
		{
			$temp = json_decode($_SESSION['lastsearch'], true);
			if (is_array($temp))
			{
				list($temp_clauses, $temp_params, $temp_terms) = $temp;
				if ($temp_clauses != $context['search_clauses'] || $temp_params != $context['search_params'] || $temp_terms != $context['search_terms'])
					spamProtection('search');
			}
			else
				spamProtection('search');
		}
		$_SESSION['lastsearch'] = json_encode(array($context['search_clauses'], $context['search_params'], $context['search_terms']));

		$context['search_params']['start'] = ($context['pagenum'] - 1) * $number_per_page;
		$context['search_params']['limit'] = $number_per_page;

		if (empty($context['search_terms']) || empty($tokens) || empty($count_tokens))
		{
			// This is where it starts to get expensive. We first have to query to
			// get the number of applicable rows.
			$query = shd_db_query('', '
				SELECT COUNT(id_ticket)
				FROM {db_prefix}helpdesk_tickets AS hdt
				WHERE ' . implode(' AND ', $context['search_clauses']) . ' LIMIT 1000',
				$context['search_params']
			);

			list($context['num_results']) = $db->fetch_row($query);

			if ($context['num_results'] == 0)
			{
				$db->free_result($query);
				return $context['sub_template'] = 'search_no_results';
			}

			// OK, at least one result, awesome. Are we off the end of the list?
			if ($context['search_params']['start'] > $context['num_results'])
			{
				$context['search_params']['start'] = $context['num_results'] - ($context['num_results'] % $number_per_page);
				$context['pagenum'] = ($context['search_params']['start'] / $number_per_page) + 1;
			}
			else
				$context['pagenum'] = 1;

			$context['num_pages'] = ceil($context['num_results'] / $number_per_page);

			$query = shd_db_query('', '
				SELECT hdt.id_ticket, hdt.id_dept, hdd.dept_name, hdt.subject, hdt.urgency, hdt.private,
					hdt.last_updated, hdtr.body, hdtr.smileys_enabled,
					hdtr.id_member AS id_member, COALESCE(mem.real_name, hdtr.poster_name) AS poster_name,
					hdtr.poster_time
				FROM {db_prefix}helpdesk_tickets AS hdt
					INNER JOIN {db_prefix}helpdesk_ticket_replies AS hdtr ON (hdt.id_first_msg = hdtr.id_msg)
					INNER JOIN {db_prefix}helpdesk_depts AS hdd ON (hdt.id_dept = hdd.id_dept)
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = hdtr.id_member)
				WHERE ' . implode(' AND ', $context['search_clauses']) . '
				ORDER BY hdt.last_updated DESC
				LIMIT {int:start}, {int:limit}',
				$context['search_params']
			);

			$context['search_results'] = array();
			$page_pos = $context['search_params']['start'];

			while ($row = $db->fetch_assoc($query))
			{
				$row['result'] = ++$page_pos;
				$row['display_id'] = str_pad($row['id_ticket'], $modSettings['shd_zerofill'], '0', STR_PAD_LEFT);
				$row['is_ticket'] = true;
				$row['dept_link'] = !$context['shd_multi_dept'] ? '' : '[<a href="' . $scripturl . '?action=helpdesk;sa=main;dept=' . $row['id_dept'] . '">' . $row['dept_name'] . '</a>] ';

				$context['search_results'][] = $row;
			}
			$db->free_result($query);

			return $context['sub_template'] = 'search_results';
		}
		else
		{
			$context['match_all'] = empty($_POST['searchtype']) || $_POST['searchtype'] == 'all';

			// Then figure out what terms are being matched.
			$matches = array(
				'subjects' => array(),
				'messages' => array(),
				'id_msg' => array(),
			);

			// Doing subjects. Fetch all the instances that match and begin filtering
			// as we go.
			if (!empty($context['search_params']['areas']['subjects']))
			{
				$query = shd_db_query('', '
					SELECT hdssw.id_word, hdt.id_first_msg
					FROM {db_prefix}helpdesk_search_subject_words AS hdssw
						INNER JOIN {db_prefix}helpdesk_tickets AS hdt ON (hdssw.id_ticket = hdt.id_ticket)
					WHERE {query_see_ticket}
						AND id_word IN ({array_string:tokens})',
					array(
						'tokens' => $tokens,
					)
				);

				while ($row = $db->fetch_assoc($query))
					$matches['subjects'][$row['id_first_msg']][$row['id_word']] = true;

				$db->free_result($query);

				// Now go through and figure out which tickets we're interested in keeping.
				if ($context['match_all'])
				{
					foreach ($matches['subjects'] as $msg => $ticket_words)
					{
						if (count($ticket_words) != $count_tokens)
							unset($matches['subjects'][$msg]);
					}
				}

				// Now, we just have a list of tickets to play with. Let's put that
				// together in a master list.
				foreach ($matches['subjects'] as $msg => $ticket_words)
					$matches['id_msg'][$msg] = true;

				unset($matches['subjects']);
			}

			// Now we get the list of words that apply to tickets and replies. The process
			// is different if we do one or both. Both, first.
			if (!empty($context['search_params']['areas']['tickets']) && !empty($context['search_params']['areas']['replies']))
			{
				// If we're doing both replies and tickets themselves, we don't have to
				// care too much about the message itself, except for being deleted.
				$query = shd_db_query('', '
					SELECT hdstw.id_word, hdstw.id_msg
					FROM {db_prefix}helpdesk_search_ticket_words AS hdstw
						INNER JOIN {db_prefix}helpdesk_ticket_replies AS hdtr ON (hdstw.id_msg = hdtr.id_msg)
						INNER JOIN {db_prefix}helpdesk_tickets AS hdt ON (hdtr.id_ticket = hdt.id_ticket)
					WHERE {query_see_ticket}
						AND id_word IN ({array_string:tokens})' . (empty($_POST['scope_recycle']) || !shd_allowed_to('shd_access_recyclebin', 0) ? '
						AND hdtr.message_status = {int:not_deleted}' : ''),
					array(
						'tokens' => $tokens,
						'not_deleted' => MSG_STATUS_NORMAL,
					)
				);

				while ($row = $db->fetch_assoc($query))
					$matches['messages'][$row['id_msg']][$row['id_word']] = true;

				$db->free_result($query);

				if ($context['match_all'])
				{
					foreach ($matches['messages'] as $msg => $ticket_words)
					{
						if (count($ticket_words) != $count_tokens)
							unset($matches['messages'][$msg]);
					}
				}

				// Now, we just have a list of tickets to play with. Let's put that
				// together in a master list.
				foreach ($matches['messages'] as $msg => $ticket_words)
					$matches['id_msg'][$msg] = true;

				unset($matches['messages']);
			}
			// Just tickets OR replies
			elseif (!empty($context['search_params']['areas']['tickets']) || !empty($context['search_params']['areas']['replies']))
			{
				$query = $db->query('', '
					SELECT hdstw.id_word, hdstw.id_msg
					FROM {db_prefix}helpdesk_search_ticket_words AS hdstw
						INNER JOIN {db_prefix}helpdesk_ticket_replies AS hdtr ON (hdstw.id_msg = hdtr.id_msg)
						INNER JOIN {db_prefix}helpdesk_tickets AS hdt ON (hdtr.id_ticket = hdt.id_ticket)
					WHERE id_word IN ({array_string:tokens})
						AND hdstw.id_msg {raw:operator} hdt.id_first_msg' . (empty($_POST['scope_recycle']) || !shd_allowed_to('shd_access_recyclebin', 0) ? '
						AND hdtr.message_status = {int:not_deleted}' : ''),
					array(
						'tokens' => $tokens,
						'not_deleted' => MSG_STATUS_NORMAL,
						'operator' => !empty($context['search_params']['areas']['tickets']) ? '=' : '!=',
					)
				);

				while ($row = $db->fetch_assoc($query))
					$matches['messages'][$row['id_msg']][$row['id_word']] = true;

				$db->free_result($query);

				if ($context['match_all'])
				{
					foreach ($matches['messages'] as $ticket => $ticket_words)
					{
						if (count($ticket_words) != $count_tokens)
							unset($matches['messages'][$ticket]);
					}
				}

				// Now, we just have a list of tickets to play with. Let's put that
				// together in a master list.
				foreach ($matches['messages'] as $msg => $ticket_words)
					$matches['id_msg'][$msg] = true;

				unset($matches['messages']);
			}

			// Aw, no matches?
			if (empty($matches['id_msg']))
				return $context['sub_template'] = 'search_no_results';

			$context['search_clauses'][] = 'hdtr.id_msg IN ({array_int:msg})';
			$context['search_params']['msg'] = array_keys($matches['id_msg']);

			// How many results are there in total?
			$query = shd_db_query('', '
				SELECT COUNT(*)
				FROM {db_prefix}helpdesk_tickets AS hdt
					INNER JOIN {db_prefix}helpdesk_ticket_replies AS hdtr ON (hdtr.id_ticket = hdt.id_ticket)
				WHERE ' . implode(' AND ', $context['search_clauses']) . ' LIMIT 1000',
				$context['search_params']
			);

			list($context['num_results']) = $db->fetch_row($query);

			if ($context['num_results'] == 0)
			{
				$db->free_result($query);
				return $context['sub_template'] = 'search_no_results';
			}

			// OK, at least one result, awesome. Are we off the end of the list?
			if ($context['search_params']['start'] > $context['num_results'])
			{
				$context['search_params']['start'] = $context['num_results'] - ($context['num_results'] % $number_per_page);
				$context['pagenum'] = ($context['search_params']['start'] / $number_per_page) + 1;
			}
			else
				$context['pagenum'] = 1;

			$context['num_pages'] = ceil($context['num_results'] / $number_per_page);

			// Get the results for displaying.
			$query = shd_db_query('', '
				SELECT hdt.id_ticket, hdt.id_dept, hdd.dept_name, hdt.subject, hdt.urgency, hdt.private,
					hdt.last_updated, hdtr.body, hdtr.smileys_enabled,
					hdtr.id_member AS id_member, COALESCE(mem.real_name, hdtr.poster_name) AS poster_name,
					hdtr.poster_time, hdt.id_first_msg, hdtr.id_msg
				FROM {db_prefix}helpdesk_ticket_replies AS hdtr
					INNER JOIN {db_prefix}helpdesk_tickets AS hdt ON (hdt.id_ticket = hdtr.id_ticket)
					INNER JOIN {db_prefix}helpdesk_depts AS hdd ON (hdt.id_dept = hdd.id_dept)
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = hdtr.id_member)
				WHERE ' . implode(' AND ', $context['search_clauses']) . '
				ORDER BY hdt.last_updated DESC, hdtr.id_msg DESC
				LIMIT {int:start}, {int:limit}',
				$context['search_params']
			);

			$context['search_results'] = array();
			$page_pos = $context['search_params']['start'];

			while ($row = $db->fetch_assoc($query))
			{
				$row['result'] = ++$page_pos;
				$row['display_id'] = str_pad($row['id_ticket'], $modSettings['shd_zerofill'], '0', STR_PAD_LEFT);
				$row['is_ticket'] = $row['id_msg'] == $row['id_first_msg'];
				$row['dept_link'] = !$context['shd_multi_dept'] ? '' : '[<a href="' . $scripturl . '?action=helpdesk;sa=main;dept=' . $row['id_dept'] . '">' . $row['dept_name'] . '</a>] ';

				$context['search_results'][] = $row;
			}
			$db->free_result($query);

			return $context['sub_template'] = 'search_results';
		}
	}

	/**
	 * Finds all members specified in an input field and returns their id_member.
	 *
	 * Handles both the autosuggest form values (member IDs from JavaScript)
	 * and the plain text name field (for users without JavaScript). Names
	 * are resolved via ElkArte's findMembers() function.
	 *
	 * @param string $field The input field name containing member names/IDs.
	 * @return array Array of unique member IDs found.
	 */
	private function shd_get_named_people($field)
	{
		global $context, $user_profile;

		if (!isset($context['named_people']))
			$context['named_people'] = array();

		$members = array();

		// First look for the autosuggest values.
		if (!empty($_POST[$field . '_name_form']) && is_array($_POST[$field . '_name_form']))
		{
			foreach ($_POST[$field . '_name_form'] as $member)
			{
				if ((int) $member > 0)
					$members[] = (int) $member;
			}

			if (!empty($members))
			{
				require_once(SUBSDIR . '/Members.subs.php');
				$loaded = loadMemberData($members, false, 'minimal');

				if (!empty($loaded))
				{
					foreach ($loaded as $id_member)
					{
						if (!isset($context['named_people'][$id_member]) && isset($user_profile[$id_member]))
							$context['named_people'][$id_member] = $user_profile[$id_member]['real_name'];
					}
				}
			}
		}

		// Failing that, let's look at the name itself for those without JS.
		if (!empty($_POST[$field . '_name']))
		{
			// We're going to take out the "s anyway ;).
			$names = strtr($_POST[$field . '_name'], array('\\"' => '"'));

			preg_match_all('~"([^"]+)"~', $names, $matches);
			$namedlist = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $names))));

			foreach ($namedlist as $index => $name)
			{
				if (strlen(trim($name)) > 0)
					$namedlist[$index] = Util::htmlspecialchars(Util::strtolower(trim($name)));
				else
					unset($namedlist[$index]);
			}

			if (!empty($namedlist))
			{
				require_once(SUBSDIR . '/Members.subs.php');
				$foundMembers = findMembers($namedlist);

				foreach ($foundMembers as $member)
				{
					$members[] = $member['id'];

					if (!isset($context['named_people'][$member['id']]))
						$context['named_people'][$member['id']] = $member['name'];
				}
			}
		}

		return array_unique($members);
	}
}
