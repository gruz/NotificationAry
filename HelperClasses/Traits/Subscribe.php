<?php
/**
 * NotificationaryCore helper class
 *
 * @package    Notificationary

 * @author     Gruz <arygroup@gmail.com>
 * @copyright  0000 Copyleft (є) 2017 - All rights reversed
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace NotificationAry\HelperClasses\Traits;

use Joomla\CMS\Language\Text;

// No direct access
defined('_JEXEC') or die('Restricted access');

trait Subscribe
{
	public function profileHelper($value) {
		$return = [];
		foreach ($value as $row) {
			$row['name'] = '<b>' . $row['name'] . '</b>';
			if (array_key_exists('id', $row)) {
				$res = $row['name'] . '. ' . Text::_('PLG_SYSTEM_NOTIFICATIONARY_FIELD_CATEGORIES') . ': ';
				$res .= implode(',', $row['id']);
			} else {
				$res = $row['name'] . ': ' . $row['text'];
			}

			 $return[] = $res;
		}
		$return = implode('<br/>' , $return);
		// return '<pre>'.print_r($return, true).'</pre>';
		return $return;
	}
}
