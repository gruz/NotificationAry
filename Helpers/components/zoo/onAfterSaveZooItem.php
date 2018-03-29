<?php
/**
 * Bridge to tie NotificationAry and Zoo
 *
 * @package    NotificationAry
 * @author     Gruz <arygroup@gmail.com>
 * @copyright  0000 Copyleft - All rights reversed
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

use NotificationAry;
/**
 * `static` before function name is a must
 *
 * @param   object $event Event object
 *
 * @return void
 */
static function onAfterSaveZooItem($event)
{
	$contentItem = $event->getSubject();

	if (!NotificationAry\PlgSystemNotificationaryCore::isZooEditPage($contentItem))
	{
		return;
	}

	$isNew = $event['new'];
	$context = 'com_zoo.item';

	\JDispatcher::getInstance()->trigger(
		'onContentAfterSave',
		array(
			$context,
			$contentItem,
			$isNew
		)
	);

	/**
	if (!empty($rules))
	{
		$this->shouldShowSwitchCheckFlag = true;

		if (!empty($vevent->data['custom_runnotificationary']))
		{
			$jform['params']['runnotificationary'] = $vevent->data['custom_runnotificationary'];
			$jinput = JFactory::getApplication()->input;
			$jform = $jinput->set('jform', $jform);
			$jform = $jinput->get('jform', null, null);
		}
	}

	if (!empty($contentItem->data['SUMMARY']))
	{
		$contentItem->title = $contentItem->data['SUMMARY'];
	}

	if (!empty($contentItem->data['DESCRIPTION']))
	{
		$contentItem->fulltext = $contentItem->data['DESCRIPTION'];
	}
	return $this->onContentAfterSave($context, $contentItem, $isNew);
	*/
	return;
}
