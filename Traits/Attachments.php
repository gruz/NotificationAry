<?php
/**
 * Handles Attachments
 *
 * @package     NotificationAry
 *
 * @author      Gruz <arygroup@gmail.com>
 * @copyright   Copyleft (є) 2018 - All rights reversed
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */


namespace NotificationAry\Traits;

/**
 * A helper trait
 *
 * @since 0.2.17
 */
trait Attachments
{

	/**
	 * Clean attachment files from the temp folder
	 *
	 * @return   void
	 */
	protected function _cleanAttachments ()
	{
		$session = \JFactory::getSession();
		$session->set('Attachments', null, $this->plgName);
		$session->set('Diffs', null, $this->plgName);

		// Remove accidently unremoved attachments
		$files = \JFolder::files(\JFactory::getApplication()->getCfg('tmp_path'), 'diff_id_*', false, true);
		\JFile::delete($files);
		$files = \JFolder::files(\JFactory::getApplication()->getCfg('tmp_path'), 'prev_version_id_*', false, true);
		\JFile::delete($files);
		$files = \JFolder::files(\JFactory::getApplication()->getCfg('tmp_path'), $this->plgName . '_*', false, true);
		\JFile::delete($files);
	}

}
