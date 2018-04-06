<?php
/**
 * Zoo
 *
 * @package     NotificationAry
 *
 * @author      Gruz <arygroup@gmail.com>
 * @copyright   Copyleft (є) 2018 - All rights reversed
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */


namespace NotificationAry\Traits\Extensions;

/**
 * A helper trait
 *
 * @since 0.2.17
 */
trait Zoo
{

	/**
	 * Loads Zoo compatibility code
	 *
	 * @return   void
	 */
	public static function loadZoo()
	{
		// Make sure ZOO exists
		if (!\JComponentHelper::getComponent('com_zoo', true)->enabled)
		{
			return;
		}

		// Load ZOO config
		jimport('joomla.filesystem.file');

		if (!\JFile::exists(JPATH_ADMINISTRATOR . '/components/com_zoo/config.php') || !\JComponentHelper::getComponent('com_zoo', true)->enabled)
		{
			return;
		}

		require_once JPATH_ADMINISTRATOR . '/components/com_zoo/config.php';

		// Make sure App class exists
		if (!class_exists('App'))
		{
			return;
		}

		// Here are a number of events for demonstration purposes.

		// Have a look at administrator/components/com_zoo/config.php
		// and also at administrator/components/com_zoo/events/

		// Get the ZOO App instance
		$zoo = App::getInstance('zoo');

		// Register event
		// ~ $zoo->event->dispatcher->connect('item:saved', array('plgSystemZooevent', 'itemSaved'));

		$zoo->event->register('PlgSystemNotificationary');

		$zoo->event->dispatcher->connect('application:init', array('PlgSystemNotificationary', 'onEditZooItem'),('init'));
		$zoo->event->dispatcher->connect('item:init', array('PlgSystemNotificationary', 'onEditZooItem'));
		// ~ $zoo->event->dispatcher->connect('item:beforedisplay', array('PlgSystemNotificationary', 'onEditZooItem'));
		$zoo->event->dispatcher->connect('item:save', array('PlgSystemNotificationary', 'onBeforeSaveZooItem'));
		$zoo->event->dispatcher->connect('item:saved', array('PlgSystemNotificationary', 'onAfterSaveZooItem'));
		// ~ $zoo->event->dispatcher->connect('item:stateChanged', array('PlgSystemNotificationary', 'onStateChangedZooItem'));


		/********************************************************************************************************************

		$zoo->event->register('PlgSystemNotificationary');
		$zoo->event->dispatcher->connect('application:installed', array('PlgSystemNotificationary', 'onEditZooItem'),('installed'));
		$zoo->event->dispatcher->connect('application:init', array('PlgSystemNotificationary', 'onEditZooItem'),('init'));
		$zoo->event->dispatcher->connect('application:saved', array('PlgSystemNotificationary', 'onEditZooItem'),('saved'));
		$zoo->event->dispatcher->connect('application:deleted', array('PlgSystemNotificationary', 'onEditZooItem'),('deleted'));
		$zoo->event->dispatcher->connect('application:addmenuitems', array('PlgSystemNotificationary', 'onEditZooItem'),('addmenuitems'));
		$zoo->event->dispatcher->connect('application:configparams', array('PlgSystemNotificationary', 'onEditZooItem'),('configparams'));
		$zoo->event->dispatcher->connect('application:sefbuildroute', array('PlgSystemNotificationary', 'onEditZooItem'),('sefbuildroute'));
		$zoo->event->dispatcher->connect('application:sefparseroute', array('PlgSystemNotificationary', 'onEditZooItem'),('sefparseroute'));
		$zoo->event->dispatcher->connect('application:sh404sef', array('PlgSystemNotificationary', 'onEditZooItem'),('sh404sef'));

		$zoo->event->register('PlgSystemNotificationary');
		$zoo->event->dispatcher->connect('category:init', array('PlgSystemNotificationary', 'onEditZooItem'),('init'));
		$zoo->event->dispatcher->connect('category:saved', array('PlgSystemNotificationary', 'onEditZooItem'),('saved'));
		$zoo->event->dispatcher->connect('category:deleted', array('PlgSystemNotificationary', 'onEditZooItem'),('deleted'));
		$zoo->event->dispatcher->connect('category:stateChanged', array('PlgSystemNotificationary', 'onEditZooItem'),('stateChanged'));

		$zoo->event->register('PlgSystemNotificationary');
		$zoo->event->dispatcher->connect('item:init', array('PlgSystemNotificationary', 'onEditZooItem'),('init'));
		$zoo->event->dispatcher->connect('item:save', array('PlgSystemNotificationary', 'onEditZooItem'),('save'));
		$zoo->event->dispatcher->connect('item:saved', array('PlgSystemNotificationary', 'onEditZooItem'),('saved'));
		$zoo->event->dispatcher->connect('item:deleted', array('PlgSystemNotificationary', 'onEditZooItem'),('deleted'));
		$zoo->event->dispatcher->connect('item:stateChanged', array('PlgSystemNotificationary', 'onEditZooItem'),('stateChanged'));
		$zoo->event->dispatcher->connect('item:beforedisplay', array('PlgSystemNotificationary', 'onEditZooItem'),('beforeDisplay'));
		$zoo->event->dispatcher->connect('item:afterdisplay', array('PlgSystemNotificationary', 'onEditZooItem'),('afterDisplay'));
		$zoo->event->dispatcher->connect('item:beforeSaveCategoryRelations', array('PlgSystemNotificationary', 'onEditZooItem'),('beforeSaveCategoryRelations'));
		$zoo->event->dispatcher->connect('item:orderquery', array('PlgSystemNotificationary', 'onEditZooItem'),('orderquery'));

		$zoo->event->register('PlgSystemNotificationary');
		$zoo->event->dispatcher->connect('comment:init', array('PlgSystemNotificationary', 'onEditZooItem'),('init'));
		$zoo->event->dispatcher->connect('comment:saved', array('PlgSystemNotificationary', 'onEditZooItem'),('saved'));
		$zoo->event->dispatcher->connect('comment:deleted', array('PlgSystemNotificationary', 'onEditZooItem'),('deleted'));
		$zoo->event->dispatcher->connect('comment:stateChanged', array('PlgSystemNotificationary', 'onEditZooItem'),('stateChanged'));

		$zoo->event->register('PlgSystemNotificationary');
		$zoo->event->dispatcher->connect('submission:init', array('PlgSystemNotificationary', 'onEditZooItem'),('init'));
		$zoo->event->dispatcher->connect('submission:beforesave', array('PlgSystemNotificationary', 'onEditZooItem'),('beforeSave'));
		$zoo->event->dispatcher->connect('submission:saved', array('PlgSystemNotificationary', 'onEditZooItem'),('saved'));
		$zoo->event->dispatcher->connect('submission:deleted', array('PlgSystemNotificationary', 'deleted'));

		$zoo->event->register('PlgSystemNotificationary');
		$zoo->event->dispatcher->connect('element:download', array('PlgSystemNotificationary', 'onEditZooItem'),('download'));
		$zoo->event->dispatcher->connect('element:configform', array('PlgSystemNotificationary', 'onEditZooItem'),('configForm'));
		$zoo->event->dispatcher->connect('element:configparams', array('PlgSystemNotificationary', 'onEditZooItem'),('configParams'));
		$zoo->event->dispatcher->connect('element:configxml', array('PlgSystemNotificationary', 'onEditZooItem'),('configXML'));
		$zoo->event->dispatcher->connect('element:afterdisplay', array('PlgSystemNotificationary', 'onEditZooItem'),('afterDisplay'));
		$zoo->event->dispatcher->connect('element:beforedisplay', array('PlgSystemNotificationary', 'onEditZooItem'),('beforeDisplay'));
		$zoo->event->dispatcher->connect('element:aftersubmissiondisplay', array('PlgSystemNotificationary', 'onEditZooItem'),('afterSubmissionDisplay'));
		$zoo->event->dispatcher->connect('element:beforesubmissiondisplay', array('PlgSystemNotificationary', 'onEditZooItem'),('beforeSubmissionDisplay'));
		$zoo->event->dispatcher->connect('element:beforeedit', array('PlgSystemNotificationary', 'onEditZooItem'),('beforeEdit'));
		$zoo->event->dispatcher->connect('element:afteredit', array('PlgSystemNotificationary', 'onEditZooItem'),('afterEdit'));

		$zoo->event->register('PlgSystemNotificationary');
		$zoo->event->dispatcher->connect('layout:init', array('PlgSystemNotificationary',  'onEditZooItem'),('init'));

		$zoo->event->register('PlgSystemNotificationary');
		$zoo->event->dispatcher->connect('tag:saved', array('PlgSystemNotificationary',  'onEditZooItem'),('saved'));
		$zoo->event->dispatcher->connect('tag:deleted', array('PlgSystemNotificationary',  'onEditZooItem'),('deleted'));

		$zoo->event->register('PlgSystemNotificationary');
		$zoo->event->dispatcher->connect('type:beforesave', array('PlgSystemNotificationary', 'onEditZooItem'),('beforesave'));
		$zoo->event->dispatcher->connect('type:aftersave', array('PlgSystemNotificationary', 'onEditZooItem'),('aftersave'));
		$zoo->event->dispatcher->connect('type:copied', array('PlgSystemNotificationary', 'onEditZooItem'),('copied'));
		$zoo->event->dispatcher->connect('type:deleted', array('PlgSystemNotificationary', 'onEditZooItem'),('deleted'));
		$zoo->event->dispatcher->connect('type:editdisplay', array('PlgSystemNotificationary', 'onEditZooItem'),('editDisplay'));
		$zoo->event->dispatcher->connect('type:coreconfig', array('PlgSystemNotificationary', 'onEditZooItem'),('coreconfig'));
		$zoo->event->dispatcher->connect('type:assignelements', array('PlgSystemNotificationary', 'onEditZooItem'),('assignelements'));
	*/
	}

	/**
	 * Loads Zoo compatibility code
	 *
	 * @param   int  $contentItem  Zoo content item
	 *
	 * @return   bool  True if editing a Zoo item page
	 */
	public static function isZooEditPage($contentItem)
	{
		if (get_class($contentItem) != 'Item')
		{
			return false;
		}

		$contentItemId = $contentItem->id;

		$jinput = \JFactory::getApplication()->input;
		$option = $jinput->get('option');

		if ($option !== 'com_zoo')
		{
			return false;
		}

		$app = \JFactory::getApplication();

		// Replace plugin code at Frontend
		if ($app->isSite())
		{
			$mustBeVars = [
				// ~ 'view' => 'submission',
				'layout' => 'submission',
				'type_id' => 'article',
				'item_id' => $contentItemId
			];

			foreach ($mustBeVars as $k => $v)
			{
				if ($v != $jinput->get($k))
				{
					return false;
				}
			}

		}
		else
		{
			$mustBeVars = [
				'task' => ['edit','apply', 'publish', 'unpublish'],
				'controller' => 'item',
				'cid' => [[$contentItemId], $contentItemId],
			];


			foreach ($mustBeVars as $k => $v)
			{
				$param = $jinput->get($k);

				if ($v == $param)
				{
					continue;
				}

				if (is_array($v) && in_array($param, $v))
				{
					continue;
				}

				return false;
			}
		}

		return true;
	}

}
