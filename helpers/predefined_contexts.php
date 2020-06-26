<?php
/**
 * Predefind template
 *
 * @package		NotificationAry
 * @subpackage	site
 * @author Gruz <arygroup@gmail.com>
 * @copyright	Copyleft - All rights reversed
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access
defined('_JEXEC') or die;

$rows = array(
	'Context',
	'Item table class',
	'View link',
	'Frontend edit link',
	'Backend edit link',
	'Category table class',
	'Category context',
	);
$functionsToBeAliased = array(
	'onContentAfterSave',
	'onContentBeforeSave',
	'onContentChangeState',
	'onContentPrepareForm',
);
$contextAliases = array(
	'contextAliases'
);
$otherParams = array(
	'RouterClass::RouterMethod',
);
$rows = array_merge($rows, $functionsToBeAliased, $contextAliases, $otherParams);

$predefined_context_templates = array(
	'com_hotspots.marker' => array(
		'Title' => 'HotSpots Marker',
		'Item table class' => 'TableMarker',
		'View link'=> 'index.php?option=com_hotspots&view=hotspot&id=##ID##',
		'Frontend edit link'=> 'index.php?option=com_hotspots&task=form.edit&id=##ID##',
		//'Frontend edit link'=> 'index.php?option=com_hotspots&view=form&layout=edit&id=##ID##',
		'Backend edit link'=> 'index.php?option=com_hotspots&view=hotspot&layout=edit&id=##ID##',
		'Category table class' => 'CategoriesTableCategory',
		'Category context' => 'com_hotspots.category',
		'onContentAfterSave' => 'onAfterHotspotSave', // Just an alias name of the function which is used by the extension. It's assumed the the extension function uses the same parameters and core joomla plugin event function
		'onContentBeforeSave' => 'onBeforeHotspotSave', // Just an alias name of the function which is used by the extension. It's assumed the the extension function uses the same parameters and core joomla plugin event function
		'contextAliases' => 'com_hotspots.hotspot', // may be comma separated, here it's used when publishing/unpublishing an Marker'
		'RouterClass::RouterMethod' => 'HotspotsHelperRoute::getHotspotRoute',


		),
	'com_k2.item' => array(
		'Title' => 'K2 Item',
		'Item table class' => 'TableK2Item',
		'View link'=> 'index.php?option=com_k2&view=item&layout=item&id=##ID##',
		'Frontend edit link'=> 'index.php?option=com_k2&view=item&task=edit&cid=##ID##',
		'Backend edit link'=> 'index.php?option=com_k2&view=item&cid=##ID##',
		'Category table class' => 'TableK2Category',
		'Category context' => 'com_k2.category',
		'onContentChangeState' => 'onFinderChangeState', // Just an alias name of the function which is used by the extension. It's assumed the the extension function uses the same parameters and core joomla plugin event function
		'RouterClass::RouterMethod' => 'K2HelperRoute::getItemRoute',
		),
	'com_dpcalendar.event' => array(
		'Title' => 'DP Calendar Event',
		'Item table class' => null,
		'View link'=> 'index.php?option=com_dpcalendar&view=event&id=##ID##',
		'Frontend edit link'=> 'index.php?option=com_dpcalendar&task=event.edit&layout=edit&e_id=##ID##',
		'Backend edit link'=> 'index.php?option=com_dpcalendar&task=event.edit&id=##ID##',
		//'Category table class' => '',
		//'RouterClass::RouterMethod' => 'DPCalendarHelperRoute::getEventRoute',
		),
	'com_jdownloads.download' => array(
		'Title' => 'JDownloads Download',
		//~ 'Item table class' => null,
		'View link'=> 'index.php?option=com_jdownloads&view=download&id=##ID##',
		'Frontend edit link'=> 'index.php?option=com_jdownloads&task=download.edit&a_id=##ID##',
		'Backend edit link'=> 'index.php?option=com_jdownloads&task=download.edit&file_id=##ID##',
		//'Category table class' => '',
		//~ 'RouterClass::RouterMethod' => 'DPCalendarHelperRoute::getEventRoute',
		),
	'jevents.edit.icalevent' => array(
		'Title' => 'JEvent Event',
		// 'Item table class' => 'iCalEvent',
		// 'Item table class' => 'plugins/system/notificationary/helpers/components/jevents/tables/:ZooTableItem',
		'Item table class' => 'plugins/system/notificationary/helpers/components/jevents/tables/:JeventsTableVevdetail',
		'View link'=> 'index.php?option=com_jevents&task=icalevent.detail&evid=##ID##',
		'Frontend edit link'=> 'index.php?option=com_jevents&task=icalevent.edit&evid=##ID##',
		'Backend edit link'=> 'index.php?option=com_jevents&task=icalevent.edit&evid=##ID##',
		'Category table class' => 'CategoriesTableCategory ',
		'Category context' => 'com_jevents.category',
		//~ 'RouterClass::RouterMethod' => 'DPCalendarHelperRoute::getEventRoute',
		//~ 'onContentBeforeSave' => 'jevents/function o1nBeforeSaveEvent (&$vevent, $dryrun) { if ($dryrun) { return; } dump ($vevent,"vevent"); return;return $this->onContentBeforeSave($context = \'jevents.edit.icalevent\', $contentItem = $vevent, $isNew = false);	}',
		'onContentAfterSave' => 'jevents/onAfterSaveEvent.php', // The extension uses own function with own parameters. Have to catch the parametes, rework them to the form accepted by the regural joomla event, and pass to the regular joomla event
		'onContentBeforeSave' => 'jevents/onBeforeSaveEvent.php', // The extension uses own function with own parameters. Have to catch the parametes, rework them to the form accepted by the regural joomla event, and pass to the regular joomla event
		'onContentChangeState' => 'jevents/onPublishEvent.php', // The extension uses own function with own parameters. Have to catch the parametes, rework them to the form accepted by the regural joomla event, and pass to the regular joomla event
		'onContentPrepareForm' => 'jevents/onEventEdit.php', // The extension uses own function with own parameters. Have to catch the parametes, rework them to the form accepted by the regural joomla event, and pass to the regular joomla event
		),
	'com_zoo.item' => array(
		'Title' => 'Zoo item',
		'Item table class' => 'plugins/system/notificationary/helpers/components/zoo/tables/:ZooTableItem',
		'View link'=> 'index.php?option=com_zoo&task=item&item_id=##ID##',
		'Frontend edit link'=> 'index.php?option=com_zoo&view=submission&layout=submission&submission_id=&type_id=article&item_id=##ID##&redirect=itemedit&submission_hash=##SUBMISSION_HASH##',
		'Backend edit link'=> 'index.php?option=com_zoo&controller=item&task=edit&cid%5B%5D=##ID##',
		'Category table class' => 'plugins/system/notificationary/helpers/components/zoo/tables/:ZooTableCategory',
		// ~ 'Category context' => 'com_jevents.category',
		// ~ 'RouterClass::RouterMethod' => 'DPCalendarHelperRoute::getEventRoute',
		'onContentAfterSave' => 'zoo/onAfterSaveZooItem.php', // The extension uses own function with own parameters. Have to catch the parametes, rework them to the form accepted by the regural joomla event, and pass to the regular joomla event
		'onContentBeforeSave' => 'zoo/onBeforeSaveZooItem.php', // The extension uses own function with own parameters. Have to catch the parametes, rework them to the form accepted by the regural joomla event, and pass to the regular joomla event
		// ~ 'onContentChangeState' => 'zoo/onStateChangedZooItem.php', // The extension uses own function with own parameters. Have to catch the parametes, rework them to the form accepted by the regural joomla event, and pass to the regular joomla event
		'onContentPrepareForm' => 'zoo/onEditZooItem.php', // The extension uses own function with own parameters. Have to catch the parametes, rework them to the form accepted by the regural joomla event, and pass to the regular joomla event
		),
	'com_menus.item' => array(
		'Title' => 'Joomla menu',
		'Item table class' => 'MenusTableMenu',
		// 'View link'=> 'index.php?option=com_zoo&task=item&item_id=##ID##',
		// 'Frontend edit link'=> 'index.php?option=com_zoo&view=submission&layout=submission&submission_id=&type_id=article&item_id=##ID##&redirect=itemedit&submission_hash=##SUBMISSION_HASH##',
		'Backend edit link'=> 'index.php?option=com_menus&task=item.edit&id=##ID##',
		// 'Category table class' => 'plugins/system/notificationary/helpers/components/zoo/tables/:ZooTableCategory',
		// ~ 'Category context' => 'com_jevents.category',
		// ~ 'RouterClass::RouterMethod' => 'DPCalendarHelperRoute::getEventRoute',
		// 'onContentAfterSave' => 'zoo/onAfterSaveZooItem.php', // The extension uses own function with own parameters. Have to catch the parametes, rework them to the form accepted by the regural joomla event, and pass to the regular joomla event
		// 'onContentBeforeSave' => 'zoo/onBeforeSaveZooItem.php', // The extension uses own function with own parameters. Have to catch the parametes, rework them to the form accepted by the regural joomla event, and pass to the regular joomla event
		// ~ 'onContentChangeState' => 'zoo/onStateChangedZooItem.php', // The extension uses own function with own parameters. Have to catch the parametes, rework them to the form accepted by the regural joomla event, and pass to the regular joomla event
		// 'onContentPrepareForm' => 'zoo/onEditZooItem.php', // The extension uses own function with own parameters. Have to catch the parametes, rework them to the form accepted by the regural joomla event, and pass to the regular joomla event
		),
	'com_users.user' => [
		'Title' => 'Joomla Users',
		'Item table class' => 'JTableUser',
		// 'View link'=> 'index.php?option=com_hotspots&view=hotspot&id=##ID##',
		// 'Frontend edit link'=> 'index.php?option=com_hotspots&task=form.edit&id=##ID##',
		'Backend edit link'=> 'index.php?option=com_users&task=user.edit&id=##ID##',
		'Category table class' => 'JTableUsergroup',
		'Category context' => 'com_users.group',
		'onContentAfterSave' => 'com_users/onUserAfterSave.php', // Just an alias name of the function which is used by the extension. It's assumed the the extension function uses the same parameters and core joomla plugin event function
		'onContentBeforeSave' => 'com_users/onUserBeforeSave.php', // Just an alias name of the function which is used by the extension. It's assumed the the extension function uses the same parameters and core joomla plugin event function
		// 'onContentChangeState' => 'com_users/onUserBeforeSave.php', // The extension uses own function with own parameters. Have to catch the parametes, rework them to the form accepted by the regural joomla event, and pass to the regular joomla event
		'contextAliases' => 'com_users.users,com_users.users.default.filter,com_users.profile', // may be comma separated, here it's used when publishing/unpublishing an Marker'
		// 'RouterClass::RouterMethod' => 'HotspotsHelperRoute::getHotspotRoute',
	],
);

ksort($predefined_context_templates);
