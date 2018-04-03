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

use NotificationAry, JFactory, JDispatcher;
/**
 * `static` before function name is a must
 *
 * @param   object $event Event object
 *
 * @return void
 */
static function onBeforeSaveZooItem($event)
{
	// ~ dumpMessage('onBeforeSaveZooItem');

	$contentItem = $event->getSubject();

	if (!NotificationAry\PlgSystemNotificationaryCore::isZooEditPage($contentItem))
	{
		return;
	}

	// ~ dumpMessage('onBeforeSaveZooItem 1');

	$isNew = $event['new'];

	$context                   = 'com_zoo.item';
	$jinput                    = JFactory::getApplication()->input;
	$customRunNotificationAry  = $jinput->post->get('params', array(), 'array')['config']['custom_runnotificationary'];

	$jinput = JFactory::getApplication()->input;
	$jform  = $jinput->get('jform', null, null);

	if (empty($jform))
	{
		$jform = array();
	}

	$jform['params']['runnotificationary'] = $customRunNotificationAry;
	$jinput->set('jform', $jform);

	$contentItem->params->{'config.custom_runnotificationary'} = $customRunNotificationAry;

	$session           = JFactory::getSession();
	$customReplacement = $session->get('CustomReplacement', null, 'notificationary');

	if (!empty($customReplacement))
	{
	}

	\JEventDispatcher::getInstance()->trigger(
		'onContentBeforeSave',
		array(
				$context,
				$contentItem,
				$isNew,
			)
	);
}
