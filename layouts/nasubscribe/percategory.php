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
				<option value="all">JALL</option>
				<option value="none">JNONE</option>
				<option value="selected">PLG_SYSTEM_NOTIFICATIONARY_SELECT</option>
		</field>
	');

// ~ $formfield->setup($element, '', $ruleUniqID);
$formfield->setup($element, $selectAllValue);

switch ($selectAllValue) {
	case 'all':
	case 'none':
		$disabled = ' disabled="disabled" ';
		break;
	default :
		$disabled = null;
		break;
}

$form[] = $formfield->renderField();

// We render checkboxes with previously prepared options
$field_type = 'checkboxes';
$formfield = \JFormHelper::loadFieldType($field_type);
$element = simplexml_load_string(
	'
		<field
			name="categoriesToSubscribe_' . $rule->__ruleUniqID . '"
			id="categoriesToSubscribe_' . $rule->__ruleUniqID . $hash .'"
			type="' . $field_type . '"
			label="PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIBE_TO_CATEGORY" ' . $disabled . '
		>
				' . implode(PHP_EOL, $options) . '
				<!-- just to show we can add something manuall
				<option value="anch">Anchovies</option>
				<option value="chor">Chorizo</option>
				<option value="on">Onions</option>
				<option value="mush">Mushrooms</option>
				!-->
		</field>
	');

// ~ $formfield->setup($element, '', $ruleUniqID);
$formfield->setup($element, $values);


$form[] = '<span class="categories">';
$form[] = $formfield->renderField();
$form[] = '</span>';

$form = implode(PHP_EOL, $form);

echo $form;