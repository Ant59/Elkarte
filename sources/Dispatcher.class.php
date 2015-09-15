<?php

/**
 * Primary site dispatch controller, sends the request to the function or method
 * registered to handle it.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 dev
 *
 */

if (!defined('ELK'))
	die('No access...');

use Phroute\Phroute\Dispatcher;

/**
 * Dispatch the request to the function or method registered to handle it.
 *
 * What it does:
 * - Try first the critical functionality (maintenance, no guest access)
 * - Then, in order:
 *     * forum's main actions: board index, message index, display topic
 *       the current/legacy file/functions registered by ElkArte core
 * - Fall back to naming patterns:
 *     * filename=[action].php function=[sa]
 *     * filename=[action].controller.php method=action_[sa]
 *     * filename=[action]-Controller.php method=action_[sa]
 * - An addon files to handle custom actions will be called if they follow
 * any of these patterns.
 */
class Dispatcher
{
	protected $_routes;

	/**
	 * Create an instance and initialize it.
	 * This does all the work to figure out which controller and method needs called.
	 */
	public function __construct()
	{
		global $board, $topic, $modSettings, $user_info, $maintenance;

		Elk_Autoloader::getInstance()->register(EXTDIR . '/phroute', '\Phroute\Phroute');

		// Default action of the forum: board index
		// Everytime we don't know what to do, we'll do this :P
		/*$this->_default_action = array(
			'controller' => 'BoardIndex_Controller',
			'function' => 'action_boardindex'
		);

		// Reminder: hooks need to account for multiple addons setting this hook.
		call_integration_hook('integrate_action_frontpage', array(&$this->_default_action));

		// Maintenance mode: you're out of here unless you're admin
		if (!empty($maintenance) && !allowedTo('admin_forum'))
		{
			// You can only login
			if (isset($_GET['action']) && ($_GET['action'] == 'login2' || $_GET['action'] == 'logout'))
			{
				$this->_controller_name = 'Auth_Controller';
				$this->_function_name = $_GET['action'] == 'login2' ? 'action_login2' : 'action_logout';
			}
			// "maintenance mode" page
			else
			{
				$this->_controller_name = 'Auth_Controller';
				$this->_function_name = 'action_maintenance_mode';
			}
		}
		// If guest access is disallowed, a guest is kicked out... politely. :P
		elseif (empty($modSettings['allow_guestAccess']) && $user_info['is_guest'] && (!isset($_GET['action']) || !in_array($_GET['action'], array('coppa', 'login', 'login2', 'register', 'register2', 'reminder', 'activate', 'help', 'quickhelp', 'mailq', 'verificationcode', 'openidreturn'))))
		{
			$this->_controller_name = 'Auth_Controller';
			$this->_function_name = 'action_kickguest';
		}
		elseif (empty($_GET['action']))
		{
			// Home page: board index
			if (empty($board) && empty($topic))
			{
				// Was it, wasn't it....
				if (empty($this->_function_name))
				{
					$this->_controller_name = $this->_default_action['controller'];
					$this->_function_name = $this->_default_action['function'];
				}
			}
			// ?board=b message index
			elseif (empty($topic))
			{
				$this->_controller_name = 'MessageIndex_Controller';
				$this->_function_name = 'action_messageindex';
			}
			// board=b;topic=t topic display
			else
			{
				$this->_controller_name = 'Display_Controller';
				$this->_function_name = 'action_display';
			}
		}

		// Now this return won't be cool, but lets do it
		if (!empty($this->_controller_name) && !empty($this->_function_name))
			return;*/

		$r = new Router();

		$r->get(['/activate', 'activate'],
			['Register_Controller', 'action_activate']);
		$r->get(['/attachapprove', 'attachapprove'],
			['ModerateAttachments_Controller', 'action_attachapprove']);
		$r->get(['/buddy', 'buddy'],
			['Members_Controller', 'action_buddy']);
		$r->get(['/collapse', 'collapse'],
			['BoardIndex_Controller', 'action_collapse']);
		$r->get(['/contact', 'contact'],
			['Register_Controller', 'action_contact']);
		$r->get(['/coppa', 'coppa'],
			['Register_Controller', 'action_coppa']);
		$r->get(['/deletemsg', 'deletemsg'],
			['RemoveTopic_Controller', 'action_deletemsg']);
		// @todo: move this to attachment action also
		$r->get(['/dlattach', 'dlattach'],
			['Attachment_Controller', 'action_index']);
		$r->get(['/unwatchtopic', 'unwatchtopic'],
			['Notify_Controller', 'action_unwatchtopic']);
		$r->get(['/editpoll', 'editpoll'],
			['Poll_Controller', 'action_editpoll']);
		$r->get(['/editpoll2', 'editpoll2'],
			['Poll_Controller', 'action_editpoll2']);
		$r->get(['/findmember', 'findmember'],
			['Members_Controller', 'action_findmember']);
		$r->get(['/quickhelp', 'quickhelp'],
			['Help_Controller', 'action_quickhelp']);
		$r->get(['/jsmodify', 'jsmodify'],
			['Post_Controller', 'action_jsmodify']);
		$r->get(['/lockvoting', 'lockvoting'],
			['Poll_Controller', 'action_lockvoting']);
		$r->get(['/login', 'login'],
			['Auth_Controller', 'action_login']);
		$r->get(['/login2', 'login2'],
			['Auth_Controller', 'action_login2']);
		$r->get(['/logout', 'logout'],
			['Auth_Controller', 'action_logout']);
		$r->get(['/markasread', 'markasread'],
			['MarkRead_Controller', 'action_index']);
		$r->get(['/mergetopics', 'mergetopics'],
			['MergeTopics_Controller', 'action_index']);
		$r->get(['/moderate', 'moderate'],
			['ModerationCenter_Controller', 'action_index']);
		$r->get(['/movetopic', 'movetopic'],
			['MoveTopic_Controller', 'action_movetopic']);
		$r->get(['/movetopic2', 'movetopic2'],
			['MoveTopic_Controller', 'action_movetopic2']);
		$r->get(['/notify', 'notify'],
			['Notify_Controller', 'action_notify']);
		$r->get(['/notifyboard', 'notifyboard'],
			['Notify_Controller', 'action_notifyboard']);
		$r->get(['/openidreturn', 'openidreturn'],
			['OpenID_Controller', 'action_openidreturn']);
		$r->get(['/xrds', 'xrds'],
			['OpenID_Controller', 'action_xrds']);
		$r->get(['/pm', 'pm'],
			['PersonalMessage_Controller', 'action_index']);
		$r->get(['/post2', 'post2'],
			['Post_Controller', 'action_post2']);
		$r->get(['/quotefast', 'quotefast'],
			['Post_Controller', 'action_quotefast']);
		$r->get(['/quickmod', 'quickmod'],
			['MessageIndex_Controller', 'action_quickmod']);
		$r->get(['/quickmod2', 'quickmod2'],
			['Display_Controller', 'action_quickmod2']);
		$r->get(['/register2', 'register2'],
			['Register_Controller', 'action_register2']);
		$r->get(['/removetopic2', 'removetopic2'],
			['RemoveTopic_Controller', 'action_removetopic2']);
		$r->get(['/reporttm', 'reporttm'],
			['Emailuser_Controller', 'action_reporttm']);
		$r->get(['/restoretopic', 'restoretopic'],
			['RemoveTopic_Controller', 'action_restoretopic']);
		$r->get(['/spellcheck', 'spellcheck'],
			['Post_Controller', 'action_spellcheck']);
		$r->get(['/splittopics', 'splittopics'],
			['SplitTopics_Controller', 'action_splittopics']);
		$r->get(['/trackip', 'trackip'],
			['ProfileHistory_Controller', 'action_trackip']);
		$r->get(['/unreadreplies', 'unreadreplies'],
			['Unread_Controller', 'action_unreadreplies']);
		$r->get(['/verificationcode', 'verificationcode'],
			['Register_Controller', 'action_verificationcode']);
		$r->get(['/viewprofile', 'viewprofile'],
			['Profile_Controller', 'action_index']);
		$r->get(['/.xml', '.xml'],
			['News_Controller', 'action_showfeed']);
		$r->get(['/xmlhttp', 'xmlhttp'],
			['Xml_Controller', 'action_index']);
		$r->get(['/xmlpreview', 'xmlpreview'],
			['XmlPreview_Controller', 'action_index']);

		// Admin
		$r->get(['/admin', 'admin'],
			['Admin_Controller', 'action_index']);
		$r->group(['prefix' => 'admin'], function($r) {
			$r->get(['/jsoption', 'jsoption'],
				['ManageThemes_Controller', 'action_jsoption']);
			$r->get(['/theme', 'theme'],
				['ManageThemes_Controller', 'action_thememain']);
			$r->get(['/viewquery', 'viewquery'],
				['AdminDebug_Controller', 'action_viewquery']);
			$r->get(['/viewadminfile', 'viewadminfile'],
				['AdminDebug_Controller', 'action_viewadminfile']);
		});

		// Default
		$r->get(['/', 'index'],
			['BoardIndex_Controller', 'action_boardindex']);

		// Fallback
		$r->get('/{action}/{sa}?', function($action, $sa = null) {
			$controllerName = ucfirst($action).'_Controller';
			$controller = new $controllerName;

			if ($sa != null)
			{
				call_user_func(array($controller, 'action_'.$sa));
			}
			else
			{
				call_user_func(array($controller, 'action_index'));
			}
		});

		$this->_routes = $r;
	}

	/**
	 * Relay control to the respective function or method.
	 */
	public function dispatch()
	{
		/*if (!empty($this->_controller_name))
		{
			// 3, 2, ... and go
			if (is_callable(array($this->_controller_name, $this->_function_name)))
				$method = $this->_function_name;
			elseif (is_callable(array($this->_controller_name, 'action_index')))
				$method = 'action_index';
			// This should never happen, that's why its here :P
			else
			{
				$this->_controller_name = $this->_default_action['controller'];
				$this->_function_name = $this->_default_action['function'];

				return $this->dispatch();
			}

			// Initialize this controller with its own event manager
			$controller = new $this->_controller_name(new Event_Manager());

			// Fetch controllers generic hook name from the action controller
			$hook = $controller->getHook();

			// Call the controller's pre-dispatch method
			$controller->pre_dispatch();

			// Call integrate_action_XYZ_before -> XYZ_controller -> integrate_action_XYZ_after
			call_integration_hook('integrate_action_' . $hook . '_before', array($this->_function_name));

			$result = $controller->$method();

			call_integration_hook('integrate_action_' . $hook . '_after', array($this->_function_name));

			return $result;
		}
		// Things went pretty bad, huh?
		else
		{
			// default action :P
			$this->_controller_name = $this->_default_action['controller'];
			$this->_function_name = $this->_default_action['function'];

			return $this->dispatch();
		}*/

		$dispatcher = new Dispatcher($this->_routes->getData());

		$dispatcher->dispatch($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
	}

	/**
	 * Returns the current action for the system
	 *
	 * @return string
	 */
	public function site_action()
	{
		if (!empty($this->_controller_name))
		{
			$action  = strtolower(str_replace('_Controller', '', $this->_controller_name));
			$action = substr($action, -1) == 2 ? substr($action, 0, -1) : $action;
		}

		return isset($action) ? $action : '';
	}
}