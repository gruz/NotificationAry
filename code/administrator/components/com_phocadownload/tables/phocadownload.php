<?php
/*
 * @package		Joomla.Framework
 * @copyright	Copyright (C) 2005 - 2010 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 *
 * @component Phoca Component
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License version 2 or later;
 */
defined('_JEXEC') or die('Restricted access');
jimport('joomla.filter.input');

class TablePhocaDownload extends TablePhocaDownloadDefault
{
	public function check()
	{
		$result = parent::check();

		if ($result) 
		{
			$isNew = empty($this->id) ? false : true;
			JPluginHelper::importPlugin( 'system' );
			$dispatcher = JEventDispatcher::getInstance();
			$dispatcher->trigger( 'onContentBeforSave', array('com_phocadownload.upload', $this, $isNew ) );
		}

		return $result;
	}

	public function store($updateNulls = false)
	{
		$isNew = empty($this->id) ? false : true;

		$result = parent::store($updateNulls);

		if ($result) 
		{
			JPluginHelper::importPlugin( 'system' );
			$dispatcher = JEventDispatcher::getInstance();
			$dispatcher->trigger( 'onContentAfterSave', array('com_phocadownload.upload', $this, $isNew ) );
		}

		return $result;
	}
}

