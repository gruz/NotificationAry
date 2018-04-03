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
function onPublishEvent($pks, $state)
{
	$jinput = \JFactory::getApplication()->input;

	if ($jinput->get('option', null) == 'com_dump')
	{
		return;
	}

	$this->prepareParams();
	$context = 'jevents.edit.icalevent';

	if (!in_array($context, $this->allowedContexts))
	{
		return true;
	}

	$this->onContentChangeStateFired = true;

	foreach ($pks as $id)
	{
		$dataModel                    = new JEventsDataModel;
		$contentItem                  = $dataModel->queryModel->getEventById(intval($id), 1, "icaldb");
		$contentItem->{'modified_by'} = \JFactory::getUser()->id;

		$db    = \JFactory::getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName('rawdata'))
			->from($db->quoteName('#__jevents_vevent'))
			->where($db->quoteName('ev_id') . ' = ' . $db->quote($id));
		$db->setQuery($query);
		$result = $db->loadResult();

		if (!empty($result))
		{
			$result = unserialize($result);

			if (is_array($result))
			{
				$contentItem->data = $result;
			}
		}

		$contentItem  = $this->_contentItemPrepare($contentItem);

		$this->previousState = 'not determined';
		$this->onContentAfterSave($context, $contentItem, false);
	}

	return true;
}
