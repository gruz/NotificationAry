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

static function onAfterSaveEvent(&$vevent, $dryrun)
{
	if ($dryrun)
	{
		return;
	}

	$contentItem = clone $vevent;
	$context     = 'jevents.edit.icalevent';
	$this->_prepareParams();

	$rules = $this->_leaveOnlyRulesForCurrentItem($context, $contentItem, 'showSwitch');

	if (!empty($rules))
	{
		self::$shouldShowSwitchCheckFlag = true;

		if (!empty($vevent->data['custom_runnotificationary']))
		{
			$jform['params']['runnotificationary'] = $vevent->data['custom_runnotificationary'];
			$jinput                                = JFactory::getApplication()->input;
			$jform                                 = $jinput->set('jform', $jform);
			$jform                                 = $jinput->get('jform', null, null);
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

	$jinput = JFactory::getApplication()->input;
	$evid   = $jinput->post->get('evid');
	$isNew  = true;

	if ($evid > 0)
	{
		$isNew = false;
	}

	$this->isNew = $isNew;

	return $this->onContentAfterSave($context = 'jevents.edit.icalevent', $contentItem, $isNew);
}
