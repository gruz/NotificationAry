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

/**
 * Method is called before user data is stored in the database
 *
 * @param   array    $user   Holds the old user data.
 * @param   boolean  $isNew  True if a new user is stored.
 * @param   array    $data   Holds the new user data.
 *
 * @return  boolean
 *
 * @since   3.9.0
 * @throws  InvalidArgumentException on missing required data.
 */

defined('_JEXEC') or die('Restricted access');

function onUserBeforeSave($user, $isNew, $data)
{

// dump ('onBeforeSaveEvent');
	// $dataModel = new JEventsDataModel;

	// foreach ($vevent as $k => $v)
	// {
	// 	$dataModel->$k = $v;
	// }

	// $dataModel->id = $dataModel->evid;
	// $contentItem = $dataModel;

	// $jinput = JFactory::getApplication()->input;
	// $evid = $jinput->post->get('evid');
	// $isNew = true;
	
	// if ($evid > 0 )
	// {
	// 	$isNew = false;
	// }
	
	$context = 'com_users.users';
	$this->isNew = $isNew;

	$user = (object) $user;
	$user->state = ! $user->block;
	$user->created_by = null;
	$user->modified_by = JFactory::getUser()->id;;
	$user->catid = null;
	$user->created = $user->registerDate;

// dump($user);
	return $this->onContentBeforeSave($context, $user, $isNew);
	// $this->onContentChangeState($context, $contentItem, $data->block);
}
