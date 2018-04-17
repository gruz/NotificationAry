<?php
/*
 * @package		Joomla.Framework
 * @copyright	Copyright (C) Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 * @component Phoca Component
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License version 2 or later;
 */

 

class PhocaGalleryViewCategory extends PhocaGalleryViewCategoryDefault
{
	function display($tpl = null)
	{

		$app = JFactory::getApplication();
		$muFailed = $app->input->get( 'mufailed', '0', 'int' );
		$muUploaded = $app->input->get( 'muuploaded', '0', 'int' );

		if ($muUploaded > 0) {
			$key       = 'uploadedImages';
			$namespace = 'NotificationAry.PhocaGalleryMultipleUpload';
			
			// TODO Here prepare for onContentAfterSave
			$session           = \JFactory::getSession();
			$uploadedImages = $session->get($key, [], $namespace);

			dump($uploadedImages, '$uploadedImages');

			if (count($uploadedImages > 0)) {

				$context     = 'com_phocagallery.multipleupload';
				$isNew       = true;
				$contentItem = (object) $uploadedImages[0];
				$contentItem->title = $contentItem->filename;

				for ($i=1; $i < count($uploadedImages); $i++) { 
					$contentItem->title .= ', ' . $uploadedImages[$i]['filename'];
				}

				$contentItem->files = $uploadedImages;
				
	
				\JEventDispatcher::getInstance()->trigger(
					'onContentAfterSave',
					array(
						$context,
						$contentItem,
						$isNew
					)
				);
			}
			


			$uploadedImages = $session->set($key, [], $namespace);
		}

	
	
		return parent::display($tpl);
	}
}
