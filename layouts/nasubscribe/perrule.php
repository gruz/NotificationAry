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
 * 	$hash            : (string) An unique hash to make the fields id unique
 */

// We render checkboxes with previously prepared options
$field_type = 'list';
$formfield = \JFormHelper::loadFieldType($field_type);
$element = simplexml_load_string(
	'
		<field
			name="subscribetoall_' . $rule->__ruleUniqID . '"
			id="subscribetoall_' . $rule->__ruleUniqID . $hash . '"
			type="' . $field_type . '"
			label="PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIBE_SELECT_LABEL"
		>
				<option value="all">JYES</option>
				<option value="none">JNO</option>
		</field>
	');

// ~ $formfield->setup($element, '', $ruleUniqID);
$formfield->setup($element, $selectAllValue);

$form[] = $formfield->renderField();


$form = implode(PHP_EOL, $form);

echo $form;