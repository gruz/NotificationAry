<?php
/**
 * Bridge to tie NotificationAry and JEvents
 *
 * @package    NotificationAry
 * @author     Gruz <arygroup@gmail.com>
 * @copyright  0000 Copyleft - All rights reversed
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

static function onBeforeSaveZooItem ($event)
{
// ~ dumpMessage('onBeforeSaveZooItem');

	$contentItem = $event->getSubject();

	if (!NotificationAryHelper::isZooEditPage($contentItem))
	{
		return;
	}

// ~ dumpMessage('onBeforeSaveZooItem 1');

	$isNew = $event['new'];

	$context = 'com_zoo.item';
	$jinput = JFactory::getApplication()->input;
	$custom_runnotificationary = $jinput->post->get('params', [], 'array')['config']['custom_runnotificationary'];

	$jinput = JFactory::getApplication()->input;
	$jform = $jinput->get('jform', null, null);

	if (empty($jform))
	{
		$jform = [];
	}

	$jform['params']['runnotificationary'] = $custom_runnotificationary;
	$jinput->set('jform', $jform);

	$contentItem->params->{'config.custom_runnotificationary'} = $custom_runnotificationary;

	$session = JFactory::getSession();
	$CustomReplacement = $session->get('CustomReplacement', null, 'notificationary');

	if (!empty($CustomReplacement))
	{

	}

	JDispatcher::getInstance()->trigger(
			'onContentBeforeSave',
			array(
				$context,
				$contentItem,
				$isNew
			)
		);

}
