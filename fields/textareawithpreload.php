<?php
/**
 * @package    NotificationAry
 *
 * @copyright  0000 Copyleft (є) 2017 - All rights reversed
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

if (!class_exists('GJFieldsFormField'))
{
	include JPATH_ROOT . '/libraries/gjfields/gjfields.php';
}

if (!class_exists('GJFieldsFormFieldTextareafixed'))
{
	include JPATH_ROOT . '/libraries/gjfields/textareafixed.php';
}

/**
 * Textarea field which allows to load additional data from files
 *
 * @author  Gruz <arygroup@gmail.com>
 * @since   0.0.1
 */
class NAFormFieldTextareawithpreload extends GJFieldsFormFieldTextareafixed
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  11.1
	 */
	protected $type = 'Textareawithpreload';

	/**
	 * Method to get the textarea field input markup.
	 * Use the rows and columns attributes to specify the dimensions of the area.
	 *
	 * @return  string  The field input markup.
	 */
	public function getInput()
	{
		$output = parent::getInput();
		$addition = $this->Addition('preloadTemplates');

		return $addition . $output;
	}
}
