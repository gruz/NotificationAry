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

	public function store($updateNulls = false)
	{
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
							break;
						default:
							$tmp->$key = $value;
							break;
					}
				}
			}

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
