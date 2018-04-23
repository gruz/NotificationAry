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
use Joomla\String\StringHelper;

class TablePhocaGallery extends TablePhocaGalleryDefault {
	public function check()
	{
		$result = parent::check();

		if ($result && false ) 
		{
			$isNew = empty($this->id) ? true : false;
			JPluginHelper::importPlugin( 'system' );
			$dispatcher = JEventDispatcher::getInstance();
			$dispatcher->trigger( 'onContentBeforSave', array('com_phocadownload.upload', $this, $isNew ) );
		}

		return $result;
	}

	public function store($updateNulls = false)
	{
		$isNew = empty($this->id) ? true : false;

		$result = parent::store($updateNulls);

        if ($result) 
		{
			$tmp = new stdClass;

			foreach ($this as $key => $value) {
				if (!is_object($value)) {

					switch ($key) {
						case 'userid':
							$tmp->created_by = $value;
							$tmp->modified_by = $value;
							# code...
							break;
						
						default:
							$tmp->$key = $value;
							break;
					}
				}
			}

			dump($tmp, $result);

			$key       = 'uploadedImages';
			$namespace = 'NotificationAry.PhocaGalleryMultipleUpload';

			$session           = \JFactory::getSession();
			$uploadedImages = $session->get($key, [], $namespace);
			// $uploadedImages[] = (object) (array) $this;
			$uploadedImages[] = $tmp;
			$uploadedImages = $session->set($key, $uploadedImages, $namespace);
		}

		return $result;
	}
	
}
