<?php
/**
 * SimpleDesk Attachments Subs
 *
 * Helper functions for integrating helpdesk attachments with ElkArte's
 * core attachment management system. Prevents orphaned helpdesk attachments
 * from being deleted during maintenance, handles helpdesk attachment removal,
 * adds browse tabs for helpdesk attachments in the admin panel, and provides
 * list data functions for the attachment browser.
 *
 * Ported from SMF Subs-SimpleDeskManageAttachments.php to ElkArte.
 *
 * @package SimpleDesk
 * @version 2.1.5
 */

if (!defined('ELK'))
	die('No direct access...');

/**
 * Finds helpdesk attachments that have no associated message (orphaned)
 * and adds their IDs to the ignore list to prevent core attachment
 * maintenance from deleting them.
 *
 * Helpdesk attachments use id_msg = 0 or reference helpdesk ticket replies
 * rather than forum messages. The core attachment repair tool would flag
 * these as orphaned. This function identifies them so they can be excluded.
 *
 * @param array &$ignore_ids Array of attachment IDs to skip (passed by reference).
 * @param int $min_substep Minimum attachment ID for this maintenance step.
 * @param int $max_substep Maximum attachment ID for this maintenance step.
 */
function shd_repair_attachments_nomsg(&$ignore_ids, $min_substep, $max_substep)
{
	$db = database();

	// Find all helpdesk attachments in the current substep range.
	// Helpdesk attachments have id_msg = 0 and are linked via the
	// helpdesk_attachments table, or are of type 'shd_attach' or 'shd_thumb'.
	$request = $db->query('', '
		SELECT a.id_attach
		FROM {db_prefix}attachments AS a
		WHERE a.id_attach BETWEEN {int:substep_min} AND {int:substep_max}
			AND a.id_msg = {int:no_msg}
			AND (
				a.attachment_type IN ({array_int:shd_types})
				OR EXISTS (
					SELECT 1
					FROM {db_prefix}helpdesk_attachments AS hda
					WHERE hda.id_attach = a.id_attach
				)
			)',
		array(
			'substep_min' => $min_substep,
			'substep_max' => $max_substep,
			'no_msg' => 0,
			'shd_types' => array(4, 5), // 4 = shd_attach, 5 = shd_thumb (custom types)
		)
	);

	while ($row = $db->fetch_assoc($request))
		$ignore_ids[] = (int) $row['id_attach'];

	$db->free_result($request);
}

/**
 * Handles removal of helpdesk-specific attachments.
 *
 * When attachments of types shd_attach or shd_thumb are removed, this
 * function cleans up the corresponding entries in the helpdesk_attachments
 * linking table. Called as part of the attachment removal pipeline.
 *
 * @param array &$filesRemoved Array of removed file info (passed by reference, for tracking).
 * @param array $attachments Array of attachment IDs being removed.
 */
function shd_attachment_remove(&$filesRemoved, $attachments)
{
	if (empty($attachments))
		return;

	$db = database();

	// Remove entries from the helpdesk attachments linking table.
	$db->query('', '
		DELETE FROM {db_prefix}helpdesk_attachments
		WHERE id_attach IN ({array_int:attachments})',
		array(
			'attachments' => $attachments,
		)
	);
}

/**
 * Adds helpdesk attachment/thumbnail browse tabs to the admin attachment manager.
 *
 * Integrates with ElkArte's attachment management admin page to add additional
 * tabs for browsing helpdesk-specific attachments and thumbnails. If the user
 * is currently viewing one of these helpdesk tabs, calls the appropriate
 * browse function.
 *
 * @param array &$listOptions The list options array for the attachment manager (passed by reference).
 * @param array &$titles Array of tab titles (passed by reference).
 * @param string &$list_title Current list title (passed by reference).
 */
function shd_attachments_browse(&$listOptions, &$titles, &$list_title)
{
	global $txt, $scripturl, $context;

	loadLanguage('SimpleDesk');

	// Determine the current browse type.
	$browse_type = isset($_REQUEST['shd_browse']) ? $_REQUEST['shd_browse'] : '';

	// Add helpdesk attachment browse tabs.
	$titles['shd_attach'] = array(
		'label' => isset($txt['shd_admin_attachments_title']) ? $txt['shd_admin_attachments_title'] : 'Helpdesk Attachments',
		'description' => isset($txt['shd_admin_attachments_desc']) ? $txt['shd_admin_attachments_desc'] : 'Browse attachments uploaded to helpdesk tickets.',
		'href' => $scripturl . '?action=admin;area=manageattachments;sa=browse;shd_browse=shd_attach',
		'is_selected' => ($browse_type === 'shd_attach'),
	);

	$titles['shd_thumb'] = array(
		'label' => isset($txt['shd_admin_thumbs_title']) ? $txt['shd_admin_thumbs_title'] : 'Helpdesk Thumbnails',
		'description' => isset($txt['shd_admin_thumbs_desc']) ? $txt['shd_admin_thumbs_desc'] : 'Browse thumbnails for helpdesk ticket attachments.',
		'href' => $scripturl . '?action=admin;area=manageattachments;sa=browse;shd_browse=shd_thumb',
		'is_selected' => ($browse_type === 'shd_thumb'),
	);

	// If we are browsing a helpdesk attachment type, build the list.
	if (in_array($browse_type, array('shd_attach', 'shd_thumb')))
	{
		shd_admin_browse_attachments($list_title, $browse_type);
	}
}

/**
 * Builds ElkArte list options for browsing helpdesk attachments.
 *
 * Sets up the createList parameters for a sortable, paginated list of
 * helpdesk attachments or thumbnails. Columns include name, filesize,
 * member, date, downloads, and a selection checkbox for bulk operations.
 *
 * @param string &$list_title The list title to set (passed by reference).
 * @param string $browse_type The type of browse: 'shd_attach' or 'shd_thumb'.
 */
function shd_admin_browse_attachments(&$list_title, $browse_type = 'shd_attach')
{
	global $context, $txt, $scripturl, $modSettings;

	require_once(SUBSDIR . '/SimpleDesk.subs.php');
	shd_init();

	$is_thumbs = ($browse_type === 'shd_thumb');

	$list_title = $is_thumbs
		? (isset($txt['shd_admin_thumbs_title']) ? $txt['shd_admin_thumbs_title'] : 'Helpdesk Thumbnails')
		: (isset($txt['shd_admin_attachments_title']) ? $txt['shd_admin_attachments_title'] : 'Helpdesk Attachments');

	// Build the createList options array.
	$listOptions = array(
		'id' => 'shd_file_list',
		'title' => $list_title,
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'no_items_label' => isset($txt['shd_no_attachments']) ? $txt['shd_no_attachments'] : 'No helpdesk attachments found.',
		'base_href' => $scripturl . '?action=admin;area=manageattachments;sa=browse;shd_browse=' . $browse_type,
		'default_sort_col' => 'shd_filename',
		'get_items' => array(
			'function' => 'shd_list_get_files',
			'params' => array(
				$browse_type,
			),
		),
		'get_count' => array(
			'function' => 'shd_list_get_num_files',
			'params' => array(
				$browse_type,
			),
		),
		'columns' => array(
			'shd_filename' => array(
				'header' => array(
					'value' => isset($txt['shd_attach_filename']) ? $txt['shd_attach_filename'] : 'File Name',
				),
				'data' => array(
					'function' => function ($rowData) use ($scripturl) {
						$link = !empty($rowData['id_ticket'])
							? '<a href="' . $scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $rowData['id_ticket'] . '">' . $rowData['filename'] . '</a>'
							: $rowData['filename'];

						return $link;
					},
				),
				'sort' => array(
					'default' => 'a.filename ASC',
					'reverse' => 'a.filename DESC',
				),
			),
			'shd_filesize' => array(
				'header' => array(
					'value' => isset($txt['shd_attach_filesize']) ? $txt['shd_attach_filesize'] : 'File Size',
				),
				'data' => array(
					'function' => function ($rowData) {
						return round($rowData['size'] / 1024, 2) . ' KB';
					},
					'class' => 'centertext',
				),
				'sort' => array(
					'default' => 'a.size DESC',
					'reverse' => 'a.size ASC',
				),
			),
			'shd_member' => array(
				'header' => array(
					'value' => isset($txt['shd_attach_member']) ? $txt['shd_attach_member'] : 'Posted By',
				),
				'data' => array(
					'function' => function ($rowData) use ($scripturl) {
						if (!empty($rowData['id_member']))
							return '<a href="' . $scripturl . '?action=profile;u=' . $rowData['id_member'] . '">' . $rowData['poster_name'] . '</a>';
						else
							return $rowData['poster_name'];
					},
				),
				'sort' => array(
					'default' => 'hdtr.poster_name ASC',
					'reverse' => 'hdtr.poster_name DESC',
				),
			),
			'shd_date' => array(
				'header' => array(
					'value' => isset($txt['shd_attach_date']) ? $txt['shd_attach_date'] : 'Date',
				),
				'data' => array(
					'function' => function ($rowData) {
						return standardTime($rowData['poster_time']);
					},
					'class' => 'centertext',
				),
				'sort' => array(
					'default' => 'hdtr.poster_time DESC',
					'reverse' => 'hdtr.poster_time ASC',
				),
			),
			'shd_downloads' => array(
				'header' => array(
					'value' => isset($txt['shd_attach_downloads']) ? $txt['shd_attach_downloads'] : 'Downloads',
				),
				'data' => array(
					'db' => 'downloads',
					'class' => 'centertext',
				),
				'sort' => array(
					'default' => 'a.downloads DESC',
					'reverse' => 'a.downloads ASC',
				),
			),
			'shd_ticket' => array(
				'header' => array(
					'value' => isset($txt['shd_attach_ticket']) ? $txt['shd_attach_ticket'] : 'Ticket',
				),
				'data' => array(
					'function' => function ($rowData) use ($scripturl) {
						if (!empty($rowData['id_ticket']))
							return '<a href="' . $scripturl . '?action=helpdesk;sa=viewticket;ticket=' . $rowData['id_ticket'] . '">#' . $rowData['id_ticket'] . ' - ' . $rowData['ticket_subject'] . '</a>';
						else
							return isset($txt['shd_unknown']) ? $txt['shd_unknown'] : 'Unknown';
					},
				),
				'sort' => array(
					'default' => 'hdt.id_ticket DESC',
					'reverse' => 'hdt.id_ticket ASC',
				),
			),
			'shd_check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
					'class' => 'centertext',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="remove[]" value="%1$d" class="input_check" />',
						'params' => array(
							'id_attach' => false,
						),
					),
					'class' => 'centertext',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=manageattachments;sa=remove;shd_browse=' . $browse_type,
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => array(
				$context['session_var'] => $context['session_id'],
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="remove_submit" value="' . (isset($txt['shd_attach_remove']) ? $txt['shd_attach_remove'] : 'Remove Selected') . '" class="right_submit" />',
			),
		),
	);

	// Create the list.
	require_once(SUBSDIR . '/GenericList.class.php');
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'shd_file_list';
}

/**
 * Retrieves helpdesk attachments for the admin attachment browser.
 *
 * Queries the attachments table joined with helpdesk tables to get file
 * information along with the associated ticket and reply data.
 *
 * @param int $start Pagination start offset.
 * @param int $items_per_page Number of items per page.
 * @param string $sort SQL ORDER BY clause.
 * @param string $browse_type The browse type: 'shd_attach' or 'shd_thumb'.
 * @return array Array of attachment data rows.
 */
function shd_list_get_files($start, $items_per_page, $sort, $browse_type)
{
	$db = database();

	$is_thumbs = ($browse_type === 'shd_thumb');

	// Helpdesk attachments are linked through the helpdesk_attachments table.
	// We join to ticket replies and tickets to get poster and ticket context.
	$request = $db->query('', '
		SELECT a.id_attach, a.filename, a.file_hash, a.size, a.downloads,
			a.width, a.height, a.mime_type,
			hda.id_msg,
			COALESCE(hdtr.id_member, 0) AS id_member,
			COALESCE(hdtr.poster_name, {string:unknown}) AS poster_name,
			COALESCE(hdtr.poster_time, 0) AS poster_time,
			COALESCE(hdtr.id_ticket, 0) AS id_ticket,
			COALESCE(hdt.subject, {string:unknown}) AS ticket_subject
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}helpdesk_attachments AS hda ON (hda.id_attach = a.id_attach)
			LEFT JOIN {db_prefix}helpdesk_ticket_replies AS hdtr ON (hdtr.id_msg = hda.id_msg)
			LEFT JOIN {db_prefix}helpdesk_tickets AS hdt ON (hdt.id_ticket = hdtr.id_ticket)
		WHERE a.attachment_type = {int:attach_type}
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:per_page}',
		array(
			'attach_type' => $is_thumbs ? 5 : 4,
			'sort' => $sort,
			'start' => $start,
			'per_page' => $items_per_page,
			'unknown' => 'Unknown',
		)
	);

	$files = array();
	while ($row = $db->fetch_assoc($request))
	{
		$files[] = array(
			'id_attach' => (int) $row['id_attach'],
			'filename' => $row['filename'],
			'file_hash' => $row['file_hash'],
			'size' => (int) $row['size'],
			'downloads' => (int) $row['downloads'],
			'width' => (int) $row['width'],
			'height' => (int) $row['height'],
			'mime_type' => $row['mime_type'],
			'id_msg' => (int) $row['id_msg'],
			'id_member' => (int) $row['id_member'],
			'poster_name' => $row['poster_name'],
			'poster_time' => (int) $row['poster_time'],
			'id_ticket' => (int) $row['id_ticket'],
			'ticket_subject' => $row['ticket_subject'],
		);
	}
	$db->free_result($request);

	return $files;
}

/**
 * Counts the total number of helpdesk attachments of a given type.
 *
 * Used as the get_count callback for the createList pagination.
 *
 * @param string $browse_type The browse type: 'shd_attach' or 'shd_thumb'.
 * @return int Total number of matching attachments.
 */
function shd_list_get_num_files($browse_type)
{
	$db = database();

	$is_thumbs = ($browse_type === 'shd_thumb');

	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}helpdesk_attachments AS hda ON (hda.id_attach = a.id_attach)
		WHERE a.attachment_type = {int:attach_type}',
		array(
			'attach_type' => $is_thumbs ? 5 : 4,
		)
	);

	list($num_files) = $db->fetch_row($request);
	$db->free_result($request);

	return (int) $num_files;
}
