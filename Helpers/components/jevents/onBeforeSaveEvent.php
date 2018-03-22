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

function onBeforeSaveEvent($vevent, $dryrun)
{
	if ($dryrun)
	{
		return;
	}

	// ~ dump ('onBeforeSaveEvent');
	$dataModel = new JEventsDataModel;

	foreach ($vevent as $k => $v)
	{
		$dataModel->$k = $v;
	}

	$dataModel->id = $dataModel->evid;
	$contentItem   = $dataModel;

	$context = 'jevents.edit.icalevent';
	$jinput  = JFactory::getApplication()->input;
	$evid    = $jinput->post->get('evid');
	$isNew   = true;

	if ($evid > 0)
	{
		$isNew = false;
	}

	$this->isNew = $isNew;

	return $this->onContentBeforeSave($context, $contentItem, $isNew);
}
