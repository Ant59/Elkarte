<?php

/**
 * Handle splitting of topics
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
 * @version 1.0.2
 *
 * Original module by Mach8 - We'll never forget you.
 */

if (!defined('ELK'))
	die('No access...');

/**
 * SplitTopics Controller.  Allows to take a topic and split at a point or select
 * individual messages to split to a new topic.
 * requires the split_any permission
 */
class SplitTopics_Controller extends Action_Controller
{
	/**
	 * Holds the new subject for the split toic
	 *
	 * @var string
	 */
	private $_new_topic_subject = null;

	/**
	 * Intended entry point for this class.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// Call the right method.
	}

	/**
	 * Splits a topic into two topics.
	 * delegates to the other functions (based on the URL parameter 'sa').
	 * loads the SplitTopics template.
	 * requires the split_any permission.
	 * is accessed with ?action=splittopics.
	 */
	public function action_splittopics()
	{
		global $topic;

		// And... which topic were you splitting, again?
		if (empty($topic))
			fatal_lang_error('numbers_one_to_nine', false);

		// Load up the "dependencies" - the template, getMsgMemberID().
		if (!isset($_REQUEST['xml']))
			loadTemplate('SplitTopics');

		require_once(SUBSDIR . '/Boards.subs.php');
		require_once(SUBSDIR . '/Post.subs.php');
		require_once(SUBSDIR . '/Action.class.php');

		$subActions = array(
			'selectTopics' => array($this, 'action_splitSelectTopics', 'permission' => 'split_any'),
			'execute' => array($this, 'action_splitExecute', 'permission' => 'split_any'),
			'index' => array($this, 'action_splitIndex', 'permission' => 'split_any'),
			'splitSelection' => array($this, 'action_splitSelection', 'permission' => 'split_any'),
		);

		// ?action=splittopics;sa=LETSBREAKIT won't work, sorry.
		$action = new Action();
		$subAction = $action->initialize($subActions, 'index');
		$action->dispatch($subAction);
	}

	/**
	 * Screen shown before the actual split.
	 * is accessed with ?action=splittopics;sa=index.
	 * default sub action for ?action=splittopics.
	 * uses 'ask' sub template of the SplitTopics template.
	 * redirects to action_splitSelectTopics if the message given turns out to be
	 * the first message of a topic.
	 * shows the user three ways to split the current topic.
	 */
	public function action_splitIndex()
	{
		global $txt, $context, $modSettings;

		// Validate "at".
		if (empty($_GET['at']))
			fatal_lang_error('numbers_one_to_nine', false);

		// Split at a specific topic
		$splitAt = (int) $_GET['at'];

		// We deal with topics here.
		require_once(SUBSDIR . '/Boards.subs.php');
		require_once(SUBSDIR . '/Messages.subs.php');

		// Let's load up the boards in case they are useful.
		$context += getBoardList(array('not_redirection' => true));

		// Retrieve message info for the message at the split point.
		$messageInfo = basicMessageInfo($splitAt, false, true);
		if ($messageInfo === false)
			fatal_lang_error('cant_find_messages');

		// If not approved validate they can approve it.
		if ($modSettings['postmod_active'] && !$messageInfo['topic_approved'])
			isAllowedTo('approve_posts');

		// If this topic has unapproved posts, we need to count them too...
		if ($modSettings['postmod_active'] && allowedTo('approve_posts'))
			$messageInfo['num_replies'] += $messageInfo['unapproved_posts'] - ($messageInfo['topic_approved'] ? 0 : 1);

		$context['can_move'] = allowedTo('move_any') || allowedTo('move_own');

		// Check if there is more than one message in the topic.  (there should be.)
		if ($messageInfo['num_replies'] < 1)
			fatal_lang_error('topic_one_post', false);

		// Check if this is the first message in the topic (if so, the first and second option won't be available)
		if ($messageInfo['id_first_msg'] == $splitAt)
		{
			$this->_new_topic_subject = $messageInfo['subject'];
			$this->_set_session_values();
			return $this->action_splitSelectTopics();
		}

		// Basic template information....
		$context['message'] = array(
			'id' => $splitAt,
			'subject' => $messageInfo['subject']
		);
		$context['sub_template'] = 'ask';
		$context['page_title'] = $txt['split_topic'];
	}

	/**
	 * Do the actual split.
	 * is accessed with ?action=splittopics;sa=execute.
	 * uses the main SplitTopics template.
	 * supports three ways of splitting:
	 * (1) only one message is split off.
	 * (2) all messages after and including a given message are split off.
	 * (3) select topics to split (redirects to action_splitSelectTopics()).
	 * uses splitTopic function to do the actual splitting.
	 */
	public function action_splitExecute()
	{
		global $txt, $context, $topic;

		// Check the session to make sure they meant to do this.
		checkSession();

		// Set the form options in to session
		$this->_set_session_values();

		// Cant post an empty redirect topic
		if (!empty($_SESSION['messageRedirect']) && empty($_SESSION['reason']))
		{
			$this->_unset_session_values();
			fatal_lang_error('splittopic_no_reason', false);
		}

		// Redirect to the selector if they chose selective.
		if ($_POST['step2'] == 'selective')
		{
			if (!empty($_POST['at']))
				$_SESSION['split_selection'][$topic][] = (int) $_POST['at'];
			return $this->action_splitSelectTopics();
		}

		// We work with them topics.
		require_once(SUBSDIR . '/Topic.subs.php');
		require_once(SUBSDIR . '/Boards.subs.php');

		// Make sure they can see the board they are trying to move to
		// (and get whether posts count in the target board).
		// Before the actual split because of the fatal_lang_errors
		$boards = splitDestinationBoard($_SESSION['move_to_board']);

		$splitAt = (int) $_POST['at'];
		$messagesToBeSplit = array();

		// Fetch the message IDs of the topic that are at or after the message.
		if ($_POST['step2'] == 'afterthis')
			$messagesToBeSplit = messagesSince($topic, $splitAt, true);
		// Only the selected message has to be split. That should be easy.
		elseif ($_POST['step2'] == 'onlythis')
			$messagesToBeSplit[] = $splitAt;
		// There's another action?!
		else
		{
			$this->_unset_session_values();
			fatal_lang_error('no_access', false);
		}

		$context['old_topic'] = $topic;
		$context['new_topic'] = splitTopic($topic, $messagesToBeSplit, $_SESSION['new_topic_subject']);
		$context['page_title'] = $txt['split_topic'];
		$context['sub_template'] = 'split_successful';

		splitAttemptMove($boards, $context['new_topic']);

		// Create a link to this in the old topic.
		// @todo Does this make sense if the topic was unapproved before? We are not yet sure if the resulting topic is unapproved.
		if ($_SESSION['messageRedirect'])
			postSplitRedirect($_SESSION['reason'], $_SESSION['new_topic_subject'], $boards['destination'], $context['new_topic']);

		$this->_unset_session_values();
	}

	/**
	 * Do the actual split of a selection of topics.
	 * is accessed with ?action=splittopics;sa=splitSelection.
	 * uses the main SplitTopics template.
	 * uses splitTopic function to do the actual splitting.
	 */
	public function action_splitSelection()
	{
		global $txt, $topic, $context;

		// Make sure the session id was passed with post.
		checkSession();

		require_once(SUBSDIR . '/Topic.subs.php');

		// You must've selected some messages!  Can't split out none!
		if (empty($_SESSION['split_selection'][$topic]))
		{
			$this->_unset_session_values();
			fatal_lang_error('no_posts_selected', false);
		}

		// This is here because there are two fatal_lang_errors in there
		$boards = splitDestinationBoard($_SESSION['move_to_board']);

		$context['old_topic'] = $topic;
		$context['new_topic'] = splitTopic($topic, $_SESSION['split_selection'][$topic], $_SESSION['new_topic_subject']);
		$context['page_title'] = $txt['split_topic'];
		$context['sub_template'] = 'split_successful';

		splitAttemptMove($boards, $context['new_topic']);

		// Create a link to this in the old topic.
		// @todo Does this make sense if the topic was unapproved before? We are not yet sure if the resulting topic is unapproved.
		if ($_SESSION['messageRedirect'])
			postSplitRedirect($_SESSION['reason'], $_SESSION['new_topic_subject'], $boards['destination'], $context['new_topic']);

		$this->_unset_session_values();
	}

	/**
	 * Allows the user to select the messages to be split.
	 * is accessed with ?action=splittopics;sa=selectTopics.
	 * uses 'select' sub template of the SplitTopics template or (for
	 * XMLhttp) the 'split' sub template of the Xml template.
	 * supports XMLhttp for adding/removing a message to the selection.
	 * uses a session variable to store the selected topics.
	 * shows two independent page indexes for both the selected and
	 * not-selected messages (;topic=1.x;start2=y).
	 */
	public function action_splitSelectTopics()
	{
		global $txt, $scripturl, $topic, $context, $modSettings, $options;

		$context['page_title'] = $txt['split_topic'] . ' - ' . $txt['select_split_posts'];
		$context['destination_board'] = !empty($_POST['move_to_board']) ? (int) $_POST['move_to_board'] : 0;

		// Haven't selected anything have we?
		$_SESSION['split_selection'][$topic] = empty($_SESSION['split_selection'][$topic]) ? array() : $_SESSION['split_selection'][$topic];

		// This is a special case for split topics from quick-moderation checkboxes
		if (isset($_REQUEST['subname_enc']))
		{
			$this->_new_topic_subject = urldecode($_REQUEST['subname_enc']);
			$this->_set_session_values();
		}

		require_once(SUBSDIR . '/Topic.subs.php');
		require_once(SUBSDIR . '/Messages.subs.php');

		$context['not_selected'] = array(
			'num_messages' => 0,
			'start' => empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'],
			'messages' => array(),
		);

		$context['selected'] = array(
			'num_messages' => 0,
			'start' => empty($_REQUEST['start2']) ? 0 : (int) $_REQUEST['start2'],
			'messages' => array(),
		);

		$context['topic'] = array(
			'id' => $topic,
			'subject' => urlencode($_SESSION['new_topic_subject']),
		);

		// Some stuff for our favorite template.
		$context['new_subject'] = $_SESSION['new_topic_subject'];

		// Using the "select" sub template.
		$context['sub_template'] = isset($_REQUEST['xml']) ? 'split' : 'select';

		// All of the js for topic split selection is needed
		if (!isset($_REQUEST['xml']))
			loadJavascriptFile('topic.js');

		// Are we using a custom messages per page?
		$context['messages_per_page'] = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];

		// Get the message ID's from before the move.
		if (isset($_REQUEST['xml']))
		{
			$original_msgs = array(
				'not_selected' => messageAt($context['not_selected']['start'], $topic, array(
					'not_in' => empty($_SESSION['split_selection'][$topic]) ? array() : $_SESSION['split_selection'][$topic],
					'only_approved' => !$modSettings['postmod_active'] || !allowedTo('approve_posts'),
					'limit' => $context['messages_per_page'],
				)),
				'selected' => array(),
			);

			// You can't split the last message off.
			if (empty($context['not_selected']['start']) && count($original_msgs['not_selected']) <= 1 && $_REQUEST['move'] == 'down')
				$_REQUEST['move'] = '';

			if (!empty($_SESSION['split_selection'][$topic]))
			{
				$original_msgs['selected'] = messageAt($context['selected']['start'], $topic, array(
					'include' => empty($_SESSION['split_selection'][$topic]) ? array() : $_SESSION['split_selection'][$topic],
					'only_approved' => !$modSettings['postmod_active'] || !allowedTo('approve_posts'),
					'limit' => $context['messages_per_page'],
				));
			}
		}

		// (De)select a message..
		if (!empty($_REQUEST['move']))
		{
			$_REQUEST['msg'] = (int) $_REQUEST['msg'];

			if ($_REQUEST['move'] == 'reset')
				$_SESSION['split_selection'][$topic] = array();
			elseif ($_REQUEST['move'] == 'up')
				$_SESSION['split_selection'][$topic] = array_diff($_SESSION['split_selection'][$topic], array($_REQUEST['msg']));
			else
				$_SESSION['split_selection'][$topic][] = $_REQUEST['msg'];
		}

		// Make sure the selection is still accurate.
		if (!empty($_SESSION['split_selection'][$topic]))
		{
			$_SESSION['split_selection'][$topic] = messageAt(0, $topic, array(
				'include' => empty($_SESSION['split_selection'][$topic]) ? array() : $_SESSION['split_selection'][$topic],
				'only_approved' => !$modSettings['postmod_active'] || !allowedTo('approve_posts'),
				'limit' => false,
			));
			$selection = $_SESSION['split_selection'][$topic];
		}
		else
			$selection = array();

		// Get the number of messages (not) selected to be split.
		$split_counts = countSplitMessages($topic, !$modSettings['postmod_active'] || allowedTo('approve_posts'), $selection);
		foreach ($split_counts as $key => $num_messages)
			$context[$key]['num_messages'] = $num_messages;

		// Fix an oversized starting page (to make sure both pageindexes are properly set).
		if ($context['selected']['start'] >= $context['selected']['num_messages'])
			$context['selected']['start'] = $context['selected']['num_messages'] <= $context['messages_per_page'] ? 0 : ($context['selected']['num_messages'] - (($context['selected']['num_messages'] % $context['messages_per_page']) == 0 ? $context['messages_per_page'] : ($context['selected']['num_messages'] % $context['messages_per_page'])));

		$page_index_url = $scripturl . '?action=splittopics;sa=selectTopics;subname=' . strtr(urlencode($_SESSION['new_topic_subject']), array('%' => '%%')) . ';topic=' . $topic;
		// Build a page list of the not-selected topics...
		$context['not_selected']['page_index'] = constructPageIndex($page_index_url . '.%1$d;start2=' . $context['selected']['start'], $context['not_selected']['start'], $context['not_selected']['num_messages'], $context['messages_per_page'], true);

		// ...and one of the selected topics.
		$context['selected']['page_index'] = constructPageIndex($page_index_url . '.' . $context['not_selected']['start'] . ';start2=%1$d', $context['selected']['start'], $context['selected']['num_messages'], $context['messages_per_page'], true);

		// Retrieve the unselected messages.
		$context['not_selected']['messages'] = selectMessages($topic, $context['not_selected']['start'], $context['messages_per_page'], empty($_SESSION['split_selection'][$topic]) ? array() : array('excluded' => $_SESSION['split_selection'][$topic]), $modSettings['postmod_active'] && !allowedTo('approve_posts'));

		// Now retrieve the selected messages.
		if (!empty($_SESSION['split_selection'][$topic]))
			$context['selected']['messages'] = selectMessages($topic, $context['selected']['start'], $context['messages_per_page'], array('included' => $_SESSION['split_selection'][$topic]), $modSettings['postmod_active'] && !allowedTo('approve_posts'));

		// The XMLhttp method only needs the stuff that changed, so let's compare.
		if (isset($_REQUEST['xml']))
		{
			$changes = array(
				'remove' => array(
					'not_selected' => array_diff($original_msgs['not_selected'], array_keys($context['not_selected']['messages'])),
					'selected' => array_diff($original_msgs['selected'], array_keys($context['selected']['messages'])),
				),
				'insert' => array(
					'not_selected' => array_diff(array_keys($context['not_selected']['messages']), $original_msgs['not_selected']),
					'selected' => array_diff(array_keys($context['selected']['messages']), $original_msgs['selected']),
				),
			);

			$context['changes'] = array();
			foreach ($changes as $change_type => $change_array)
			{
				foreach ($change_array as $section => $msg_array)
				{
					if (empty($msg_array))
						continue;

					foreach ($msg_array as $id_msg)
					{
						$context['changes'][$change_type . $id_msg] = array(
							'id' => $id_msg,
							'type' => $change_type,
							'section' => $section,
						);
						if ($change_type == 'insert')
							$context['changes']['insert' . $id_msg]['insert_value'] = $context[$section]['messages'][$id_msg];
					}
				}
			}
		}
	}

	/**
	 * Set the values for this split session
	 */
	private function _set_session_values()
	{
		global $txt;

		// Clean up the subject.
		if (isset($_POST['subname']) && empty($this->_new_topic_subject))
			$this->_new_topic_subject = trim(Util::htmlspecialchars($_POST['subname']));

		if (empty($this->_new_topic_subject))
			$this->_new_topic_subject = $txt['new_topic'];

		// Save in session so its available across all the form pages
		if (empty($_SESSION['move_to_board']))
		{
			$_SESSION['move_to_board'] = (!empty($_POST['move_new_topic']) && !empty($_POST['move_to_board'])) ? (int) $_POST['move_to_board'] : 0;
			$_SESSION['reason'] = !empty($_POST['reason']) ? trim(Util::htmlspecialchars($_POST['reason'], ENT_QUOTES)) : '';
			$_SESSION['messageRedirect'] = !empty($_POST['messageRedirect']);
			$_SESSION['new_topic_subject'] = $this->_new_topic_subject;
		}
	}

	/**
	 * Clear out this split session
	 */
	private function _unset_session_values()
	{
		unset(
			$_SESSION['move_to_board'],
			$_SESSION['reason'],
			$_SESSION['messageRedirect'],
			$_SESSION['split_selection'],
			$_SESSION['new_topic_subject']
		);
	}
}