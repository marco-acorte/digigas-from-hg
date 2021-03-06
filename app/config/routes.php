<?php

/**
 * Short description for file.
 *
 * In this file, you set up routes to your controllers and their actions.
 * Routes are very important mechanism that allows you to freely connect
 * different urls to chosen controllers and their actions (functions).
 *
 * PHP versions 4 and 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2010, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2010, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       cake
 * @subpackage    cake.app.config
 * @since         CakePHP(tm) v 0.2.9
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
/**
 * Here, we are connecting '/' (base path) to controller called 'Pages',
 * its action called 'display', and we pass a param to select the view file
 * to use (in this case, /app/views/pages/home.ctp)...
 */

if (file_exists(APP . 'config' . DS . 'installed.txt')) {
	// the routes for when the application has been installed

	Router::connect('/', array('controller' => 'pages', 'action' => 'index'));
	Router::connect('/pages/*',
			array('controller' => 'pages', 'action' => 'view'));
	Router::connect('/:language/pages/*',
			array('controller' => 'pages', 'action' => 'view'),
			array('language' => '[a-z]{2}'));
	Router::connect('/digigas',
			array('controller' => 'hampers', 'action' => 'index'));
	Router::connect('/forum',
			array('controller' => 'forums', 'action' => 'index'));

	Router::connect('/admin', array('controller' => 'ordered_products', 'action' => 'index', 'admin' => true));
} else {
	Router::connect('/', array('controller' => 'installer', 'action' => 'index'));
	Router::connect(':controller/:action', array('controller' => 'installer'));
}
?>