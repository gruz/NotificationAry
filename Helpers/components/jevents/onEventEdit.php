<?php
/**
 * Bridge to tie NotificationAry and JEvents
 *
 * @package		NotificationAry
 * @author Gruz <arygroup@gmail.com>
 * @copyright	0000 Copyleft - All rights reversed
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

function onEventEdit($extraTabs, $row, $params)
{
	// ~ dump ('onEventEdit');
	$db    = \JFactory::getDbo();
	$query = $db->getQuery(true)
		->select($db->quoteName('rawdata'))
		->from($db->quoteName('#__jevents_vevent'))
		->where($db->quoteName('ev_id') . ' = ' . $db->quote($row->{'_ev_id'}));
	$db->setQuery($query);
	$result = $db->loadResult();

	if (!empty($result))
	{
		$result = unserialize($result);

		if (is_array($result) && isset($result['custom_runnotificationary']))
		{
			$row->params                       = array();
			$row->params['runnotificationary'] = $result['custom_runnotificationary'];
		}
	}

	return $this->onContentPrepareForm($this->form, $contentItem = $row);
}
