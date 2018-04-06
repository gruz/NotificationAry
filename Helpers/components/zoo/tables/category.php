<?php
/**
 * @package     NotificationAry
 * @subpackage  com_zoo
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * A proxy class to load a content item table
 *
 * @since  1.6
 */
class ZooTableCategory extends \JTable
{
	/**
	 * Constructor
	 *
	 * @param   JDatabaseDriver  $db  Database connector object
	 *
	 * @since   1.6
	 */
	public function __construct(&$db)
	{
		parent::__construct('#__zoo_category', 'id', $db);
	}
}
