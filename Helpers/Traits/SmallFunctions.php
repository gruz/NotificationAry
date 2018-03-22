<?php
/**
 * A helper trait with small functions
 *
 * @package     NotificationAry
 *
 * @author      Gruz <arygroup@gmail.com>
 * @copyright   Copyleft (є) 2018 - All rights reversed
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */


namespace NotificationAry\Helpers\Traits;

/**
 * Small helper functions
 *
 * @since 0.2.17
 */
trait SmallFunctions
{
	/**
	 * Check if item is an instance of JForm
	 *
	 * @param   any $form A form instance
	 *
	 * @return boolean
	 */
	public function checkIsForm($form)
	{
		if (($form instanceof JForm) || ($form instanceof Joomla\CMS\Form\Form))
		{
			return true;
		}
		else
		{
			return false;
		}
	}
}
