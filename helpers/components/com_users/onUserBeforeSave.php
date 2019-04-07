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
	
	$db = JFactory::getDbo();

	$query = $db->getQuery(true)
		->select($db->quoteName('id'))
		->from($db->quoteName('#__users'))
		->where($db->quoteName('email') . ' = ' . $db->quote(JFactory::getUser()->email));

	$db->setQuery($query, 0, 1);

	$user_id = $db->loadResult();

	$context = 'com_users.users';
	$this->isNew = $isNew;

	$user = (object) $user;
	$user->state = ! $user->block;
	$user->created_by = null;
	$user->modified_by = $user_id;
	$user->modified = new JDate();
	$user->catid = null;
	$user->created = $user->registerDate;

	$fields = FieldsHelper::getFields("com_users.user", $user);

	// Loading the model
	// $model = JModelLegacy::getInstance('Field', 'FieldsModel', array('ignore_request' => true));

	
	// Loop over the fields
	foreach ($fields as $field)
	{
		
		// Determine the value if it is (un)available from the data
		if (key_exists($field->name, $data['com_fields']))
		{
			$value = $data['com_fields'][$field->name] === false ? null : $data['com_fields'][$field->name];
		}
		// Field not available on form, use stored value
		else
		{
			$value = $field->rawvalue;
		}

		// If no value set (empty) remove value from database
		if (is_array($value) ? !count($value) : !strlen($value))
		{
			$value = null;
		}

		// JSON encode value for complex fields
		if (is_array($value) && (count($value, COUNT_NORMAL) !== count($value, COUNT_RECURSIVE) || !count(array_filter(array_keys($value), 'is_numeric'))))
		{
			$value = json_encode($value);
		}

		$field->new_value = $value;
		$field->old_value = $field->value;

		// Setting the value for the field and the item
		// $model->setFieldValue($field->id, $item->id, $value);
	}

	if ($fields)
	{
		$user->com_fields = $fields;
	}
	// dump($user->com_fields, 'before');


// dump($user);
	return $this->onContentBeforeSave($context, $user, $isNew);
	// $this->onContentChangeState($context, $contentItem, $data->block);
}
