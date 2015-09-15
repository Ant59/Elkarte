<?php

/**
 * Router
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

Elk_Autoloader::getInstance()->register(EXTDIR . '/phroute', '\Phroute\Phroute');

class Router extends Phroute\Phroute\RouteCollector
{
    public function route($name, array $args = null)
    {
    	if (!parent::hasRoute($name))
    	{
    		$url = $name;
    		if (!empty($args['sa']))
    		{
    			$url .= '/'.$args['sa'];
    			unset($args['sa']);
    		}
    		if ($args != null)
    		{
    			$url .= '/'.implode('/', $args);
    		}
    		return $url;
    	}

    	return parent::route($name, $args);
    }
}