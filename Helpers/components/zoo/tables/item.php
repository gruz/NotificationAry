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
class ZooTableItem extends \JTable
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
		parent::__construct('#__zoo_item', 'id', $db);
	}

	public function loadTODEL($id = null, $reset = true)
	{
		parent::load($id, $reset);

		$db    = \JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('category_id');
		$query->from($db->quoteName('#__zoo_category_item'));
		$query->where($db->quoteName('item_id') . " = " . $db->quote($this->id));

		$db->setQuery($query);
		$categories = $db->loadColumn();
		dump($categories, '$categories');
		$this->params = json_decode($this->params);
		dump($this->params->{'config.primary_category'}, 'prmary');

		if (!empty($categories))
		{
			array_unshift($categories, $this->params->{'config.primary_category'});
			$this->catid = $categories;
		}
		else
		{
			$this->catid = $this->params->{'config.primary_category'};
		}
	}
}
