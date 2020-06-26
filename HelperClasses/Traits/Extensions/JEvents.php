<?php
/**
 * JEvents events :-)
 *
 * @package    Notificationary

 * @author     Gruz <arygroup@gmail.com>
 * @copyright  0000 Copyleft (є) 2020 - All rights reversed
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace NotificationAry\HelperClasses\Traits\Extensions;

use Joomla\CMS\Factory;

trait JEvents
{
	// No need to redefine, but VScode marks as undefined since it's defined in the parent class
	static protected $shouldShowSwitchCheckFlag = false;

	function onAfterSaveEvent (&$vevent, $dryrun)
	{
		if ($dryrun)
		{
			return;
		}

		$contentItem = clone $vevent;
		$context = 'jevents.edit.icalevent';
		$this->_prepareParams();

		$rules = $this->_leaveOnlyRulesForCurrentItem($context, $contentItem, 'showSwitch');

		if (!empty($rules))
		{
			static::$shouldShowSwitchCheckFlag = true;

			if (!empty($vevent->data['custom_runnotificationary']))
			{
				$jform['params']['runnotificationary'] = $vevent->data['custom_runnotificationary'];
				$jinput = Factory::getApplication()->input;
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

		$jinput = Factory::getApplication()->input;
		$evid = $jinput->post->get('evid');
		$isNew = true;

		if ($evid > 0)
		{
			$isNew = false;
		}

		$this->isNew = $isNew;

		return $this->onContentAfterSave($context = 'jevents.edit.icalevent', $contentItem, $isNew);
	}

	function onBeforeSaveEvent ($vevent, $dryrun)
	{
		if ($dryrun)
		{
			return;
		}

	// ~ dump ('onBeforeSaveEvent');
		$dataModel = new \JEventsDataModel;

		foreach ($vevent as $k => $v)
		{
			$dataModel->$k = $v;
		}

		$dataModel->id = $dataModel->evid;
		$contentItem = $dataModel;

		$context = 'jevents.edit.icalevent';
		$jinput = Factory::getApplication()->input;
		$evid = $jinput->post->get('evid');
		$isNew = true;

		if ($evid > 0 )
		{
			$isNew = false;
		}

		$this->isNew = $isNew;

		return $this->onContentBeforeSave($context, $contentItem, $isNew);
	}

	function onEventEdit ($extraTabs, $row, $params)
	{
		//~ dump ('onEventEdit');
		 $db = Factory::getDbo();
		 $query = $db->getQuery(true)
			  ->select($db->quoteName('rawdata'))
			  ->from($db->quoteName('#__jevents_vevent'))
			  ->where($db->quoteName('ev_id') . ' = ' . $db->quote($row->_ev_id));
		 $db->setQuery($query);
		 $result = $db->loadResult();

		 if (!empty($result))
	   {
			$result = unserialize($result);

		if (is_array($result) && isset($result['custom_runnotificationary']))
		{
				$row->params = array();
				$row->params['runnotificationary'] = $result['custom_runnotificationary'];
			}
		 }

	  return $this->onContentPrepareForm($this->form, $contentItem = $row);
	}

	function onPublishEvent ($pks, $state) {
		$jinput =  Factory::getApplication()->input; if ($jinput->get('option',null) == 'com_dump') { return; }

		$this->_prepareParams();
		$context = 'jevents.edit.icalevent';

		if (!in_array($context, $this->allowed_contexts)) { return true; }

		$this->onContentChangeStateFired = true;


		foreach ($pks as $id) {
			$dataModel = new \JEventsDataModel();
			$contentItem = $dataModel->queryModel->getEventById(intval($id), 1, "icaldb");
			$contentItem->modified_by = Factory::getUser()->id;

			 $db = Factory::getDbo();
			 $query = $db->getQuery(true)
				  ->select($db->quoteName('rawdata'))
				  ->from($db->quoteName('#__jevents_vevent'))
				  ->where($db->quoteName('ev_id') . ' = ' . $db->quote($id));
			 $db->setQuery($query);
			 $result = $db->loadResult();
			 if (!empty($result)) {
				$result = unserialize($result);
				if (is_array($result)) {
					$contentItem->data = $result;
				}
			 }
			$contentItem  = $this->_contentItemPrepare($contentItem);

			$this->previous_state = 'not determined';
			$this->onContentAfterSave($context, $contentItem, false);

		}
		return true;
	}
}
