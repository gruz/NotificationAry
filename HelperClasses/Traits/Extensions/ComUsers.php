<?php

/**
 * Catch com_users events
 *
 * @package    Notificationary

 * @author     Gruz <arygroup@gmail.com>
 * @copyright  0000 Copyleft (є) 2020 - All rights reversed
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace NotificationAry\HelperClasses\Traits\Extensions;

use Joomla\CMS\Factory;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Language\Text;

trait ComUsers
{
    function onUserBeforeSave($user, $isNew, $data)
    {
        $db = Factory::getDbo();

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('email') . ' = ' . $db->quote(Factory::getUser()->email));

        $db->setQuery($query, 0, 1);

        $user_id = $db->loadResult();

        $context = 'com_users.users';
        $this->isNew = $isNew;

        $user = (object) $user;
        $user->state = !$user->block;
        $user->created_by = null;
        $user->modified_by = $user_id;
        $user->modified = new Date();
        $user->catid = null;
        $user->created = $user->registerDate;

        $fields = \FieldsHelper::getFields("com_users.user", $user);

        // Loading the model
        // $model = \Joomla\CMS\MVC\Model\BaseDatabaseModel::getInstance('Field', 'FieldsModel', array('ignore_request' => true));


        // Loop over the fields
        foreach ($fields as $field) {

            // Determine the value if it is (un)available from the data
            if (key_exists($field->name, $data['com_fields'])) {
                $value = $data['com_fields'][$field->name] === false ? null : $data['com_fields'][$field->name];
            }
            // Field not available on form, use stored value
            else {
                $value = $field->rawvalue;
            }

            // If no value set (empty) remove value from database
            if (is_array($value) ? !count($value) : !strlen($value)) {
                $value = null;
            }

            // JSON encode value for complex fields
            if (is_array($value) && (count($value, COUNT_NORMAL) !== count($value, COUNT_RECURSIVE) || !count(array_filter(array_keys($value), 'is_numeric')))) {
                $value = json_encode($value);
            }

            $field->new_value = $value;
            $field->old_value = $field->value;

            // Setting the value for the field and the item
            // $model->setFieldValue($field->id, $item->id, $value);
        }

        $names = [
            'name' => Text::_('COM_USERS_PROFILE_NAME_LABEL'),
            'username' => Text::_('JGLOBAL_USERNAME'),
            'email' => Text::_('JGLOBAL_EMAIL'),
        ];

        foreach ($names as $name => $label) {

            $field = new \stdClass;

            $field->old_value = $user->$name;
            $field->new_value = $data[$name];
            $field->label = $label;

            array_unshift($fields, $field);
        }

        if ($fields) {
            $user->com_fields = $fields;
        }
        // dump($user->com_fields, 'before');


        // dump($user);
        return $this->onContentBeforeSave($context, $user, $isNew);
        // $this->onContentChangeState($context, $contentItem, $data->block);
    }

    function onUserAfterSave($user, $isNew, $success, $msg)
    {
        // dump ('onBeforeSaveEvent');
        $db = Factory::getDbo();

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('email') . ' = ' . $db->quote(Factory::getUser()->email));

        $db->setQuery($query, 0, 1);

        $user_id = $db->loadResult();

        $context = 'com_users.users';

        $user = (object) $user;
        $user->state = !$user->block;
        $user->created_by = null;
        $user->modified_by = $user_id;
        $user->modified = new Date();
        $user->catid = null;
        $user->created = $user->registerDate;

        // dump($user, 'after');
        // dump($this->previous_article->com_fields, 'after3');

        return $this->onContentAfterSave($context, $user, $isNew);
    }
}
