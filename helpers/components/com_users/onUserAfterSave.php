<?php
/**
 * Bridge to tie NotificationAry and JEvents
 *
 * @package    NotificationAry
 * @author     Gruz <arygroup@gmail.com>
 * @copyright  0000 Copyleft - All rights reversed
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Utility method to act on a user after it has been saved.
 *
 * @param   array    $user     Holds the new user data.
 * @param   boolean  $isNew    True if a new user is stored.
 * @param   boolean  $success  True if user was successfully stored in the database.
 * @param   string   $msg      Message.
 *
 * @return  boolean
 *
 * @since   3.9.0
 */

 // No direct access
defined('_JEXEC') or die('Restricted access');

function onUserAfterSave($user, $isNew, $success, $msg)
{
	// dump ('onBeforeSaveEvent');
	$db = \JFactory::getDbo();

	$query = $db->getQuery(true)
		->select($db->quoteName('id'))
		->from($db->quoteName('#__users'))
		->where($db->quoteName('email') . ' = ' . $db->quote(\JFactory::getUser()->email));

	$db->setQuery($query, 0, 1);

	$user_id = $db->loadResult();

	$context = 'com_users.users';

	$user = (object) $user;
	$user->state = ! $user->block;
	$user->created_by = null;
	$user->modified_by = $user_id;
	$user->modified = new \JDate();
	$user->catid = null;
	$user->created = $user->registerDate;
	
	// dump($user, 'after');
	// dump($this->previous_article->com_fields, 'after3');

	return $this->onContentAfterSave($context, $user , $isNew);
}
