<?php
/**
 * @package    NotificationAry
 *
 * @copyright  0000 Copyleft (є) 2017 - All rights reversed
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('JPATH_BASE') or die;

extract($displayData);

/**
 * Layout variables
 * ---------------------
 * 	$options         : (array)  List of categories to be subscribed to
 * 	$title           : (string) NA rule name
 * 	$rule            : (object) NA Rule itself
 * 	$values          : (array)  Selected category ids
 * 	$selectAllValue  : (array)  Subscribe to all/none/selected list value
 * 	$layout          : (object) JLayout object to later be able to call a sublayout
 * 	$sublayout       : (string) Sublayout name
 * 	$debug           : (bool)   If to output layout debug
 * 	$user           :  (JUser)  User object
 */

$form = array();
$app = JFactory::getApplication();
$class_add = '';

// For user edit page we use Bootstap2 class to have the lists in 3 columns
if ($app->isAdmin())
{
	$class_add = 'span4';
}

$form[] =  '<span id="' . $rule->__ruleUniqID . $hash . '" class="nasubscribe form ' . $class_add . '">';
// ~ $form[] =  '<form id="' . $rule->__ruleUniqID . $hash . '" class="nasubscribe form">';
$form[] = '<h1 class="na subscribe form">' . $title . '</h1>';

$form[] = $layout->sublayout($sublayout, $displayData, null, array('debug' => $debug, 'client' => 1));

$form[] = '<input type="hidden" name="ruleUniqID" value="' . $rule->__ruleUniqID . '">';
$form[] = '<input type="hidden" name="userid" value="' . $user->id . '">';
$form[] = JHtml::_('form.token');

// ~ $form[] = '<input class="submit" type="submit" name="subscribe" value="' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIBE_UPDATE') . '">';
// ~ $form[] = '</form>';
$form[] = '</span><!-- closet !-->';

$form = implode(PHP_EOL, $form);

echo $form;