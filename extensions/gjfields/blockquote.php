<?php
/**
 * @package    GJFileds
 *
 * @copyright  0000 Copyright (C) All rights reversed.
 * @license    http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL or later
 */

// No direct access
defined('_JEXEC') or die();

/**
 * Toggler Element
 *
 * To use this, make a start xml param tag with the param and value set
 * And an end xml param tag without the param and value set
 * Everything between those tags will be included in the slide
 *
 * Available extra parameters:
 * param			The name of the reference parameter
 * value			a comma separated list of value on which to show the framework
 */
if (!class_exists('GJFieldsFormField'))
{
	include 'gjfields.php';
}

/**
 * Blockquote field
 *
 * @author  Gruz <arygroup@gmail.com>
 * @since   0.0.1
 */
class GJFieldsFormFieldBlockquote extends GJFieldsFormField
{
	/**
	 * The form field type
	 *
	 * @var		string
	 */
	public $type = 'blockquote';

	/**
	 * Cap
	 *
	 * @return   void
	 */
	protected function getLabel()
	{
		return '';
	}

	/**
	 * Html output
	 *
	 * @return   string
	 */
	public function getInput()
	{
		$class = $this->def('class');
		$html = '';

		if (!empty($this->element['clean_name']))
		{
			$comment = $this->element['clean_name'];
		}
		else
		{
			$comment = $this->element['name'];
		}

		if (version_compare(JVERSION, '3.0', 'ge') && $this->HTMLtype == 'div')
		{
			$html .= '</div><!-- controls !-->' . PHP_EOL;
			$html .= '</div><!-- control-group !--><!-- blockquote -->' . PHP_EOL;
		}
		else
		{
			$html .= "\n" . '</li></ul><br style="clear:both" /><!-- blockquote  ' . $comment . ' -->';
		}

		$name = (string) $this->element['clean_name'];

		if (empty($name))
		{
			$name = (string) $this->element['name'];
		}

		if (strpos($name, 'jform[params]') !== false)
		{
			$name = explode('][', $name);
			$name = $name[count($name) - 2];
		}

		if (strpos($name, '{') === 0 || strpos($name, 'jform[params][{') === 0 )
		{
			$html .= PHP_EOL . '	<div class="blockquote ' . $class . '">' . '<!-- { div blockquote !-->' . PHP_EOL;

			if (!empty($this->element['label']))
			{
				// ~ $html .= '		<div class="title">' . JText::_($this->element['label']) . '</div>';
				$html .= '		<div class="title">' . parent::getLabel() . '</div>';
			}
		}
		else
		{
			$html .= PHP_EOL . '	</div><!-- div blockquote } !-->' . PHP_EOL;
		}

		if (version_compare(JVERSION, '3.0', 'ge') && $this->HTMLtype == 'div')
		{
			$html .= '<!-- blockquote --><div><div>';
		}
		else
		{
			$html .= PHP_EOL . '			<ul><li><!-- blockquote ' . $name . ' -->' . PHP_EOL;
		}

		return $html;
	}
}

// Preserve compatibility
if (!class_exists('JFormFieldBlockquote'))
{
	/**
	 * Old-fashioned field name
	 *
	 * @since  1.2.0
	 */
				class JFormFieldBlockquote extends GJFieldsFormFieldBlockquote
				{
				}
}
