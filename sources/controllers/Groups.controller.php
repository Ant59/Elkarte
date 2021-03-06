<?php

/**
 * This file currently just shows group info, and allows certain priviledged
 * members to add/remove members.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Groups_Controller class, shows group access and allows for add/remove group members
 */
class Groups_Controller extends Action_Controller
{
	/**
	 * Entry point to groups.
	 * It allows moderators and users to access the group showing functions.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $context;

		require_once(SUBSDIR . '/Action.class.php');

		// Little short on the list here
		$subActions = array(
			'list' => array($this, 'action_list', 'permission' => 'view_mlist'),
			'members' => array($this, 'action_members', 'permission' => 'view_mlist'),
			'requests' => array($this, 'action_requests'),
		);

		// I don't think we know what to do... throw dies?
		$action = new Action();
		$subAction = $action->initialize($subActions, 'list');
		$context['sub_action'] = $subAction;
		$action->dispatch($subAction);
	}

	/**
	 * Set up templates and pre-requisites for any request processed by this class.
	 *
	 * - Called automagically before any action_() call.
	 * - It handles permission checks, and puts the moderation bar on as required.
	 */
	public function pre_dispatch()
	{
		global $context, $txt, $scripturl, $user_info;

		// Get the template stuff up and running.
		loadLanguage('ManageMembers');
		loadLanguage('ModerationCenter');
		loadTemplate('ManageMembergroups');

		// If we can see the moderation center, and this has a mod bar entry, add the mod center bar.
		if (allowedTo('access_mod_center') || $user_info['mod_cache']['bq'] != '0=1' || $user_info['mod_cache']['gq'] != '0=1' || allowedTo('manage_membergroups'))
		{
			require_once(CONTROLLERDIR . '/ModerationCenter.controller.php');
			$_GET['area'] = (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'requests') ? 'groups' : 'viewgroups';
			$controller = new ModerationCenter_Controller();
			$controller->prepareModcenter();
		}
		// Otherwise add something to the link tree, for normal people.
		else
		{
			isAllowedTo('view_mlist');

			$context['linktree'][] = array(
				'url' => $scripturl . '?action=groups',
				'name' => $txt['groups'],
			);
		}
	}

	/**
	 * This very simply lists the groups, nothing snazy.
	 */
	public function action_list()
	{
		global $txt, $context, $scripturl, $user_info;

		$context['page_title'] = $txt['viewing_groups'];
		$current_area = isset($context['admin_menu_name']) ? $context['admin_menu_name'] : (isset($context['moderation_menu_name']) ? $context['moderation_menu_name'] : '');
		if (!empty($current_area))
			$context[$current_area]['tab_data'] = array(
				'title' => $txt['mc_group_requests'],
			);

		$base_url = $scripturl . (isset($context['admin_menu_name']) ? '?action=admin;area=membergroups;sa=members' : (isset($context['moderation_menu_name']) ? '?action=moderate;area=viewgroups;sa=members' : '?action=groups;sa=members'));

		// Making a list is not hard with this beauty.
		require_once(SUBSDIR . '/GenericList.class.php');

		// Use the standard templates for showing this.
		$listOptions = array(
			'id' => 'group_lists',
			'base_href' => $base_url,
			'default_sort_col' => 'group',
			'get_items' => array(
				'file' => SUBSDIR . '/Membergroups.subs.php',
				'function' => 'list_getMembergroups',
				'params' => array(
					'regular',
					$user_info['id'],
					allowedTo('manage_membergroups'),
					allowedTo('admin_forum'),
				),
			),
			'columns' => array(
				'group' => array(
					'header' => array(
						'value' => $txt['name'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $scripturl;

							// Since the moderator group has no explicit members, no link is needed.
							if ($rowData[\'id_group\'] == 3)
								$group_name = $rowData[\'group_name\'];
							else
							{
								$group_name = sprintf(\'<a href="%1$s;group=%2$d">%3$s</a>\', \'' . $base_url . '\', $rowData[\'id_group\'], $rowData[\'group_name_color\']);
							}

							// Add a help option for moderator and administrator.
							if ($rowData[\'id_group\'] == 1)
								$group_name .= sprintf(\' (<a href="%1$s?action=quickhelp;help=membergroup_administrator" onclick="return reqOverlayDiv(this.href);">?</a>)\', $scripturl);
							elseif ($rowData[\'id_group\'] == 3)
								$group_name .= sprintf(\' (<a href="%1$s?action=quickhelp;help=membergroup_moderator" onclick="return reqOverlayDiv(this.href);">?</a>)\', $scripturl);

							return $group_name;
						'),
					),
					'sort' => array(
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name DESC',
					),
				),
				'icons' => array(
					'header' => array(
						'value' => $txt['membergroups_icons'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $settings;

							if (!empty($rowData[\'icons\'][0]) && !empty($rowData[\'icons\'][1]))
								return str_repeat(\'<img src="\' . $settings[\'images_url\'] . \'/group_icons/\' . $rowData[\'icons\'][1] . \'" alt="*" />\', $rowData[\'icons\'][0]);
							else
								return \'\';
						'),
					),
					'sort' => array(
						'default' => 'mg.icons',
						'reverse' => 'mg.icons DESC',
					)
				),
				'moderators' => array(
					'header' => array(
						'value' => $txt['moderators'],
					),
					'data' => array(
						'function' => create_function('$group', '
							global $txt;

							return empty($group[\'moderators\']) ? \'<em>\' . $txt[\'membergroups_new_copy_none\'] . \'</em>\' : implode(\', \', $group[\'moderators\']);
						'),
					),
				),
				'members' => array(
					'header' => array(
						'value' => $txt['membergroups_members_top'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $txt;

							// No explicit members for the moderator group.
							return $rowData[\'id_group\'] == 3 ? $txt[\'membergroups_guests_na\'] : comma_format($rowData[\'num_members\']);
						'),
						'class' => 'centertext',
					),
					'sort' => array(
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1 DESC',
					),
				),
			),
		);

		// Create the request list.
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'group_lists';
	}

	/**
	 * Display members of a group, and allow adding of members to a group.
	 *
	 * What it does:
	 * - It can be called from ManageMembergroups if it needs templating within the admin environment.
	 * - It shows a list of members that are part of a given membergroup.
	 * - It is called by ?action=moderate;area=viewgroups;sa=members;group=x
	 * - It requires the manage_membergroups permission.
	 * - It allows to add and remove members from the selected membergroup.
	 * - It allows sorting on several columns.
	 * - It redirects to itself.
	 * @uses ManageMembergroups template, group_members sub template.
	 */
	public function action_members()
	{
		global $txt, $scripturl, $context, $modSettings, $user_info, $settings;

		$current_group = isset($_REQUEST['group']) ? (int) $_REQUEST['group'] : 0;

		// No browsing of guests, membergroup 0 or moderators.
		if (in_array($current_group, array(-1, 0, 3)))
			fatal_lang_error('membergroup_does_not_exist', false);

		// These will be needed
		require_once(SUBSDIR . '/Membergroups.subs.php');
		require_once(SUBSDIR . '/Members.subs.php');

		// Load up the group details.
		$context['group'] = membergroupById($current_group, true, true);
		$context['group']['id'] = $context['group']['id_group'];
		$context['group']['name'] = $context['group']['group_name'];

		// Fix the membergroup icons.
		$context['group']['icons'] = explode('#', $context['group']['icons']);
		$context['group']['icons'] = !empty($context['group']['icons'][0]) && !empty($context['group']['icons'][1]) ? str_repeat('<img src="' . $settings['images_url'] . '/group_icons/' . $context['group']['icons'][1] . '" alt="*" />', $context['group']['icons'][0]) : '';
		$context['group']['can_moderate'] = allowedTo('manage_membergroups') && (allowedTo('admin_forum') || $context['group']['group_type'] != 1);

		// The template is very needy
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=groups;sa=members;group=' . $context['group']['id'],
			'name' => $context['group']['name'],
		);
		$context['can_send_email'] = allowedTo('send_email_to_members');
		$context['sort_direction'] = isset($_REQUEST['desc']) ? 'down' : 'up';
		$context['start'] = $_REQUEST['start'];
		$context['can_moderate_forum'] = allowedTo('moderate_forum');

		// @todo: use createList

		// Load all the group moderators, for fun.
		$context['group']['moderators'] = array();
		$moderators = getGroupModerators($current_group);
		foreach ($moderators as $id_member => $name)
		{
			$context['group']['moderators'][] = array(
				'id' => $id_member,
				'name' => $name
			);

			if ($user_info['id'] == $id_member && $context['group']['group_type'] != 1)
				$context['group']['can_moderate'] = true;
		}

		// If this group is hidden then it can only "exist" if the user can moderate it!
		if ($context['group']['hidden'] && !$context['group']['can_moderate'])
			fatal_lang_error('membergroup_does_not_exist', false);

		// You can only assign membership if you are the moderator and/or can manage groups!
		if (!$context['group']['can_moderate'])
			$context['group']['assignable'] = 0;
		// Non-admins cannot assign admins.
		elseif ($context['group']['id'] == 1 && !allowedTo('admin_forum'))
			$context['group']['assignable'] = 0;

		// Removing member from group?
		if (isset($_POST['remove']) && !empty($_REQUEST['rem']) && is_array($_REQUEST['rem']) && $context['group']['assignable'])
		{
			// Security first
			checkSession();
			validateToken('mod-mgm');

			// Make sure we're dealing with integers only.
			foreach ($_REQUEST['rem'] as $key => $group)
				$_REQUEST['rem'][$key] = (int) $group;

			removeMembersFromGroups($_REQUEST['rem'], $current_group, true);
		}
		// Must be adding new members to the group...
		elseif (isset($_REQUEST['add']) && (!empty($_REQUEST['toAdd']) || !empty($_REQUEST['member_add'])) && $context['group']['assignable'])
		{
			// Make sure you can do this
			checkSession();
			validateToken('mod-mgm');

			$member_query = array(array('and' => 'not_in_group'));
			$member_parameters = array('not_in_group' => $current_group);

			// Get all the members to be added... taking into account names can be quoted ;)
			$_REQUEST['toAdd'] = strtr(Util::htmlspecialchars($_REQUEST['toAdd'], ENT_QUOTES), array('&quot;' => '"'));
			preg_match_all('~"([^"]+)"~', $_REQUEST['toAdd'], $matches);
			$member_names = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_REQUEST['toAdd']))));

			foreach ($member_names as $index => $member_name)
			{
				$member_names[$index] = trim(Util::strtolower($member_names[$index]));

				if (strlen($member_names[$index]) == 0)
					unset($member_names[$index]);
			}

			// Any members passed by ID?
			$member_ids = array();
			if (!empty($_REQUEST['member_add']))
			{
				foreach ($_REQUEST['member_add'] as $id)
				{
					if ($id > 0)
						$member_ids[] = (int) $id;
				}
			}

			// Construct the query pelements, first for adds by name
			if (!empty($member_ids))
			{
				$member_query[] = array('or' => 'member_ids');
				$member_parameters['member_ids'] = $member_ids;
			}

			// And then adds by ID
			if (!empty($member_names))
			{
				$member_query[] = array('or' => 'member_names');
				$member_parameters['member_names'] = $member_names;
			}

			// Get back the ones that were not already in the group
			$members = membersBy($member_query, $member_parameters);

			// Do the updates...
			if (!empty($members))
				addMembersToGroup($members, $current_group, $context['group']['hidden'] ? 'only_additional' : 'auto', true);
		}

		// Sort out the sorting!
		$sort_methods = array(
			'name' => 'real_name',
			'email' => allowedTo('moderate_forum') ? 'email_address' : 'hide_email ' . (isset($_REQUEST['desc']) ? 'DESC' : 'ASC') . ', email_address',
			'active' => 'last_login',
			'registered' => 'date_registered',
			'posts' => 'posts',
		);

		// They didn't pick one, or tried a wrong one, so default to by name..
		if (!isset($_REQUEST['sort']) || !isset($sort_methods[$_REQUEST['sort']]))
		{
			$context['sort_by'] = 'name';
			$querySort = 'real_name' . (isset($_REQUEST['desc']) ? ' DESC' : ' ASC');
		}
		// Otherwise sort by what they asked
		else
		{
			$context['sort_by'] = $_REQUEST['sort'];
			$querySort = $sort_methods[$_REQUEST['sort']] . (isset($_REQUEST['desc']) ? ' DESC' : ' ASC');
		}

		// The where on the query is interesting. Non-moderators should only see people who are in this group as primary.
		if ($context['group']['can_moderate'])
			$where = $context['group']['is_post_group'] ? 'in_post_group' : 'in_group';
		else
			$where = $context['group']['is_post_group'] ? 'in_post_group' : 'in_group_no_add';

		// Count members of the group.
		$context['total_members'] = countMembersBy($where, array($where => $current_group));
		$context['total_members'] = comma_format($context['total_members']);

		// Create the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=' . ($context['group']['can_moderate'] ? 'moderate;area=viewgroups' : 'groups') . ';sa=members;group=' . $current_group . ';sort=' . $context['sort_by'] . (isset($_REQUEST['desc']) ? ';desc' : ''), $_REQUEST['start'], $context['total_members'], $modSettings['defaultMaxMembers']);

		// Fetch the members that meet the where criteria
		$context['members'] = membersBy($where, array($where => $current_group, 'order' => $querySort), true);
		foreach ($context['members'] as $id => $row)
		{
			$last_online = empty($row['last_login']) ? $txt['never'] : standardTime($row['last_login']);

			// Italicize the online note if they aren't activated.
			if ($row['is_activated'] % 10 != 1)
				$last_online = '<em title="' . $txt['not_activated'] . '">' . $last_online . '</em>';

			$context['members'][$id] = array(
				'id' => $row['id_member'],
				'name' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
				'email' => $row['email_address'],
				'show_email' => showEmailAddress(!empty($row['hide_email']), $row['id_member']),
				'ip' => '<a href="' . $scripturl . '?action=trackip;searchip=' . $row['member_ip'] . '">' . $row['member_ip'] . '</a>',
				'registered' => standardTime($row['date_registered']),
				'last_online' => $last_online,
				'posts' => comma_format($row['posts']),
				'is_activated' => $row['is_activated'] % 10 == 1,
			);
		}

		if (!empty($context['group']['assignable']))
			loadJavascriptFile('suggest.js', array('defer' => true));

		// Select the template.
		$context['sub_template'] = 'group_members';
		$context['page_title'] = $txt['membergroups_members_title'] . ': ' . $context['group']['name'];
		createToken('mod-mgm');
	}

	/**
	 * Show and manage all group requests.
	 */
	public function action_requests()
	{
		global $txt, $context, $scripturl, $user_info, $modSettings;

		// Set up the template stuff...
		$context['page_title'] = $txt['mc_group_requests'];
		$context['sub_template'] = 'show_list';
		$context[$context['moderation_menu_name']]['tab_data'] = array(
			'title' => $txt['mc_group_requests'],
		);

		// Verify we can be here.
		if ($user_info['mod_cache']['gq'] == '0=1')
			isAllowedTo('manage_membergroups');

		// Normally, we act normally...
		$where = $user_info['mod_cache']['gq'] == '1=1' || $user_info['mod_cache']['gq'] == '0=1' ? $user_info['mod_cache']['gq'] : 'lgr.' . $user_info['mod_cache']['gq'];
		$where_parameters = array();

		// We've submitted?
		if (isset($_POST[$context['session_var']]) && !empty($_POST['groupr']) && !empty($_POST['req_action']))
		{
			checkSession('post');
			validateToken('mod-gr');

			require_once(SUBSDIR . '/Membergroups.subs.php');

			// Clean the values.
			foreach ($_POST['groupr'] as $k => $request)
				$_POST['groupr'][$k] = (int) $request;

			// If we are giving a reason (And why shouldn't we?), then we don't actually do much.
			if ($_POST['req_action'] == 'reason')
			{
				// Different sub template...
				$context['sub_template'] = 'group_request_reason';

				// And a limitation. We don't care that the page number bit makes no sense, as we don't need it!
				$where .= ' AND lgr.id_request IN ({array_int:request_ids})';
				$where_parameters['request_ids'] = $_POST['groupr'];

				$context['group_requests'] = list_getGroupRequests(0, $modSettings['defaultMaxMessages'], 'lgr.id_request', $where, $where_parameters);
				createToken('mod-gr');

				// Let obExit etc sort things out.
				obExit();
			}
			// Otherwise we do something!
			else
			{
				// Get the details of all the members concerned...
				require_once(SUBSDIR . '/Members.subs.php');
				$concerned = getConcernedMembers($_POST['groupr'], $where);

				// Cleanup old group requests..
				deleteGroupRequests($_POST['groupr']);

				// Ensure everyone who is online gets their changes right away.
				updateSettings(array('settings_updated' => time()));

				if (!empty($concerned['email_details']))
				{
					require_once(SUBSDIR . '/Mail.subs.php');

					// They are being approved?
					if ($_POST['req_action'] == 'approve')
					{
						// Make the group changes.
						foreach ($concerned['group_changes'] as $id => $groups)
						{
							// Sanity check!
							foreach ($groups['add'] as $key => $value)
								if ($value == 0 || trim($value) == '')
									unset($groups['add'][$key]);

							assignGroupsToMember($id, $groups['primary'], $groups['add']);
						}

						foreach ($concerned['email_details'] as $email)
						{
							$replacements = array(
								'USERNAME' => $email['member_name'],
								'GROUPNAME' => $email['group_name'],
							);

							$emaildata = loadEmailTemplate('mc_group_approve', $replacements, $email['language']);

							sendmail($email['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 2);
						}
					}
					// Otherwise, they are getting rejected (With or without a reason).
					else
					{
						// Same as for approving, kind of.
						foreach ($concerned['email_details'] as $email)
						{
							$custom_reason = isset($_POST['groupreason']) && isset($_POST['groupreason'][$email['rid']]) ? $_POST['groupreason'][$email['rid']] : '';

							$replacements = array(
								'USERNAME' => $email['member_name'],
								'GROUPNAME' => $email['group_name'],
							);

							if (!empty($custom_reason))
								$replacements['REASON'] = $custom_reason;

							$emaildata = loadEmailTemplate(empty($custom_reason) ? 'mc_group_reject' : 'mc_group_reject_reason', $replacements, $email['language']);

							sendmail($email['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 2);
						}
					}
				}

				// Restore the current language.
				loadLanguage('ModerationCenter');
			}
		}

		// We're going to want this for making our list.
		require_once(SUBSDIR . '/GenericList.class.php');
		require_once(SUBSDIR . '/Membergroups.subs.php');

		// This is all the information required for a group listing.
		$listOptions = array(
			'id' => 'group_request_list',
			'width' => '100%',
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['mc_groupr_none_found'],
			'base_href' => $scripturl . '?action=groups;sa=requests',
			'default_sort_col' => 'member',
			'get_items' => array(
				'function' => 'list_getGroupRequests',
				'params' => array(
					$where,
					$where_parameters,
				),
			),
			'get_count' => array(
				'function' => 'list_getGroupRequestCount',
				'params' => array(
					$where,
					$where_parameters,
				),
			),
			'columns' => array(
				'member' => array(
					'header' => array(
						'value' => $txt['mc_groupr_member'],
					),
					'data' => array(
						'db' => 'member_link',
					),
					'sort' => array(
						'default' => 'mem.member_name',
						'reverse' => 'mem.member_name DESC',
					),
				),
				'group' => array(
					'header' => array(
						'value' => $txt['mc_groupr_group'],
					),
					'data' => array(
						'db' => 'group_link',
					),
					'sort' => array(
						'default' => 'mg.group_name',
						'reverse' => 'mg.group_name DESC',
					),
				),
				'reason' => array(
					'header' => array(
						'value' => $txt['mc_groupr_reason'],
					),
					'data' => array(
						'db' => 'reason',
					),
				),
				'date' => array(
					'header' => array(
						'value' => $txt['date'],
						'style' => 'width: 18%; white-space:nowrap;',
					),
					'data' => array(
						'db' => 'time_submitted',
					),
				),
				'action' => array(
					'header' => array(
						'value' => '<input type="checkbox" class="input_check" onclick="invertAll(this, this.form);" />',
						'style' => 'width: 4%;text-align: center;',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<input type="checkbox" name="groupr[]" value="%1$d" class="input_check" />',
							'params' => array(
								'id' => false,
							),
						),
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=groups;sa=requests',
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => array(
					$context['session_var'] => $context['session_id'],
				),
				'token' => 'mod-gr',
			),
			'additional_rows' => array(
				array(
					'position' => 'bottom_of_list',
					'value' => '
						<select name="req_action" onchange="if (this.value != 0 &amp;&amp; (this.value == \'reason\' || confirm(\'' . $txt['mc_groupr_warning'] . '\'))) this.form.submit();">
							<option value="0">' . $txt['with_selected'] . ':</option>
							<option value="0" disabled="disabled">' . str_repeat('&#8212;', strlen($txt['mc_groupr_approve'])) . '</option>
							<option value="approve">' . (isBrowser('ie8') ? '&#187;' : '&#10148;') . '&nbsp;' . $txt['mc_groupr_approve'] . '</option>
							<option value="reject">' . (isBrowser('ie8') ? '&#187;' : '&#10148;') . '&nbsp;' . $txt['mc_groupr_reject'] . '</option>
							<option value="reason">' . (isBrowser('ie8') ? '&#187;' : '&#10148;') . '&nbsp;' . $txt['mc_groupr_reject_w_reason'] . '</option>
						</select>
						<input type="submit" name="go" value="' . $txt['go'] . '" onclick="var sel = document.getElementById(\'req_action\'); if (sel.value != 0 &amp;&amp; sel.value != \'reason\' &amp;&amp; !confirm(\'' . $txt['mc_groupr_warning'] . '\')) return false;" class="right_submit" />',
					'class' => 'floatright',
				),
			),
		);

		// Create the request list.
		createToken('mod-gr');
		createList($listOptions);

		$context['default_list'] = 'group_request_list';
	}
}