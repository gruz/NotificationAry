<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  Layout
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

extract($displayData);
/**
 * Layout variables
 * -----------------
 * @var   string   $autocomplete    Autocomplete attribute for the field.
 * @var   boolean  $autofocus       Is autofocus enabled?
 * @var   string   $class           Classes for the input.
 * @var   string   $description     Description of the field.
 * @var   boolean  $disabled        Is this field disabled?
 * @var   string   $group           Group the field belongs to. <fields> section in form XML.
 * @var   boolean  $hidden          Is this field hidden in the form?
 * @var   string   $hint            Placeholder for the field.
 * @var   string   $id              DOM id of the field.
 * @var   string   $label           Label of the field.
 * @var   string   $labelclass      Classes to apply to the label.
 * @var   boolean  $multiple        Does this field support multiple values?
 * @var   string   $name            Name of the input field.
 * @var   string   $onchange        Onchange attribute for the field.
 * @var   string   $onclick         Onclick attribute for the field.
 * @var   string   $pattern         Pattern (Reg Ex) of value of the form field.
 * @var   boolean  $readonly        Is this field read only?
 * @var   boolean  $repeat          Allows extensions to duplicate elements.
 * @var   boolean  $required        Is this field required?
 * @var   integer  $size            Size attribute of the input.
 * @var   boolean  $spellcheck      Spellcheck state for the form field.
 * @var   string   $validate        Validation rules to apply.
 * @var   string   $value           Value attribute of the field.
 * @var   array    $checkedOptions  Options that will be set as checked.
 * @var   boolean  $hasValue        Has this field a value assigned?
 * @var   array    $options         Options available for this field.
 *
 * @var   string   $userName        The user name
 * @var   mixed    $groups          The filtering groups (null means no filtering)
 * @var   mixed    $exclude         The users to exclude from the list of users
 */

// Set the link for the user selection page
$link = 'index.php?option=com_users&amp;view=users&amp;layout=modal&amp;tmpl=component&amp;required='
	. ($required ? 1 : 0) . '&amp;field={field-user-id}'
	. (isset($groups) ? ('&amp;groups=' . base64_encode(json_encode($groups))) : '')
	. (isset($excluded) ? ('&amp;excluded=' . base64_encode(json_encode($excluded))) : '');

//~ // Invalidate the input value if no user selected
//~ if (JText::_('JLIB_FORM_SELECT_USER') == htmlspecialchars($userName, ENT_COMPAT, 'UTF-8'))
//~ {
	//~ $userName = "";
//~ }

/*##mygruz20160510033514 { Also below replaced al field-user with field-users
It was:
JHtml::script('jui/fielduser.min.js', false, true, false, false, true);
It became:*/
JPluginGJFields::addJSorCSS('fieldusers.js', 'lib_gjfields', $debug = false);
// ~ JHtml::script(Juri::root() .'/libraries/gjfields/js/fieldusers.js', false, true, false, false, true);
/*##mygruz20160510033514 } */
?>
<?php // Create a dummy text field with the user name. ?>
<div class="field-users-wrapper"
	data-url="<?php echo $link; ?>"
	data-modal=".modal"
	data-modal-width="100%"
	data-modal-height="400px"
	data-input=".field-users-input"
	data-input-name=".field-users-input-name"
	data-button-select=".button-select"
	>
	<div class="input-append">
		<?php /*##mygruz20160510085552 {
		It was:
		<input
			type="text" id="<?php echo $id; ?>"
			value="<?php echo  htmlspecialchars($userName, ENT_COMPAT, 'UTF-8'); ?>"
			placeholder="<?php echo JText::_('JLIB_FORM_SELECT_USER'); ?>"
			readonly
			class="field-users-input-name <?php echo $class ? (string) $class : ''?>"
			<?php echo $size ? ' size="' . (int) $size . '"' : ''; ?>
			<?php echo $required ? 'required' : ''; ?>/>
		It became:*/?>
		<?php
			if (!is_array($value)) {
				$value = array_map('trim',explode(',',$value));
			}
			$value = (array)$value;
//~ $value = array(854,876);
		if ($simple) {
			?>
			<input type="text" id="<?php echo $id; ?>_id" name="<?php echo $name; ?>" value="<?php if(!empty($value)) echo (implode(',',$value)); ?>"
				class="field-users-input <?php echo $class ? (string) $class : ''?>"
				data-onchange="<?php echo $this->escape($onchange); ?>"/>
		<?php }
		else  {
			$name .= '[]';
		?>
		<select multiple="true" id="<?php echo $id; ?>_id" name="<?php echo $name; ?>"
			class="field-users-input <?php echo $class ? (string) $class : ''?>"
			data-onchange="<?php echo $this->escape($onchange); ?>">
			<?php
			foreach ($value as $k=>$v) {
				if (empty($v)) {continue; }
				$user = JFactory::getUser($v);
				if ($user->guest)
				{
					echo '<option  selected="selected" value="'.$v.'" >' . JText::_('JLIB_HTML_BATCH_USER_NOUSER') . ' ID: ' . $v . '</option>';
				}
				else
				{
					echo '<option  selected="selected" value="'.$v.'" >'.$user->username.'</option>';
				}
			}

			?>
		</select>
		<?php } ?>
		<?php/*##mygruz20160510085552 } */?>

		<?php if (!$readonly) : ?>
			<?php echo JHtml::_(
				'bootstrap.renderModal',
				'userModal_' . $id,
				array(
					'title'  => JText::_('JLIB_FORM_CHANGE_USER'),
					'closeButton' => true,
					'footer' => '<button class="btn" data-dismiss="modal">' . JText::_('JLIB_HTML_BEHAVIOR_CLOSE') . '</button>'
				)
			); ?>
		<?php endif; ?>
			<a class="btn btn-success button-select input-append" style="vertical-align:top;" title="<?php echo JText::_('JLIB_FORM_CHANGE_USER') ?>"><span class="icon-user"></span></a>
	</div>
	<?php // Create the real field, hidden, that stored the users ids. ?>
</div>
