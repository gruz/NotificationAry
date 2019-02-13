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
 * Toggler field
 *
 * @author  Gruz <arygroup@gmail.com>
 * @since   0.0.1
 */
class GJFieldsFormFieldToggler extends GJFieldsFormField
{
	/**
	 * The form field type
	 *
	 * @var		string
	 */
	public $type = 'toggler';

	/**
	 * Get toggler label doesn't return a label, but initializes some assets
	 *
	 * @return   void
	 */
	protected function getLabel()
	{
		/*
		if (!isset($GLOBALS[$this->type . '_initialized']))
		{
			$GLOBALS[$this->type . '_initialized'] = true;

			$path_to_assets = JURI::root() . 'libraries/gjfields/';

			$doc = JFactory::getDocument();

			$cssname = $path_to_assets . 'css/' . $this->type . '.css';
			$doc->addStyleSheet($cssname);

			$jversion = new JVersion;
			$common_script = $path_to_assets . 'js/script.js?v=' . $jversion->RELEASE;
			$doc->addScript($common_script);

			// $scriptname = $path_to_assets.'js/' . $this->type.'.js';
			// $doc->addScript($scriptname);
		}
		*/
	}

	/**
	 * Input field HTML
	 *
	 * @return   string
	 */
	public function getInput()
	{
		$param = $this->def('param');
		$value = $this->def('value');
		$class = $this->def('class');
		$group_name = $this->def('name');

		if (!empty($this->element['clean_name']))
		{
			$comment = $this->element['clean_name'];
		}
		else
		{
			$comment = $this->element['name'];
		}

		// ~ $param = preg_replace( '#^\s*(.*?)\s*$#', '\1', $param);
		// ~ $param = preg_replace( '#\s*\|\s*#', '|', $param);

		$html = '';

		if ( $param != '' )
		{
			// ~ $param = preg_replace( '#[^a-z0-9-\.\|\@]#', '_', $param);
			$set_groups = explode('|', $param);
			$set_values = explode('|', $value);
			$ids = array();

			foreach ( $set_groups as $i => $group )
			{
				$count = $i;

				if ( $count >= count($set_values) )
				{
					$count = 0;
				}

				$value = explode(',', $set_values[$count]);

				foreach ($value as $val)
				{
					// ~ $ids[] = $group.'.' . $val;
					$ids[$group][] = $val;
				}
			}

			if (!empty($this->element['label']))
			{
				$class .= ' blockquote';
			}

			$toggler_data = 'data-toggler=\'' . json_encode($ids) . '\'';

			$group_name = $this->def('name');
			$group_name = explode('][', $group_name, 3);

			if (isset($group_name[1]))
			{
				$group_name = $group_name[1];
			}
			else
			{
				$group_name = '';
			}

			if (trim($group_name) != '')
			{
				$toggler_data .= ' data-rules-group=\'' . $group_name . '\'';
			}

			// ~ $id = '___'.implode( '___', $ids);
			// ~ $html .= '<div id="'.rand( 1000000, 9999999 ).$id.'" class="gjtoggler options' . $id;
			$html .= '<div id="gjtoggler_' . rand(1000000, 9999999) . '" ' . $toggler_data . ' class="gjtoggler options';

			// $html .= '" style="visibility: hidden;">';
			$html .= ' ' . $class . '" >';

			if (!empty($this->element['label']))
			{
				$html .= '<div class="title">' . JText::_($this->element['label']) . '</div>';
			}

			if (version_compare(JVERSION, '3.0', 'ge') && $this->HTMLtype == 'div')
			{
				$html = '</div>' . PHP_EOL . '<!-- controls !-->' . PHP_EOL . $html . PHP_EOL . '<div><div>';
			}
			else
			{
				// $html = '</div>' . PHP_EOL . '<!-- controls !-->' . PHP_EOL . $html . PHP_EOL . '<div><div>';

				$html = '</li></ul>' . '<!-- close core ' . $comment . ' -->'
					. PHP_EOL . '									'
					. $html
					. PHP_EOL . '		' . '<ul><li><!-- continue core flow ' . $comment . ' -->' . PHP_EOL;
			}
		}
		else
		{
			if (version_compare(JVERSION, '3.0', 'ge') && $this->HTMLtype == 'div')
			{
				$html .= '</div><!-- controls !-->' . PHP_EOL;
				$html .= '</div><!-- control-group !-->' . PHP_EOL;
			}
			else
			{
				$html .= "\n" . '</li></ul></div><ul><li><!-- ' . $comment . ' -->';

				// $html .= "\n".'<!-- start --></li></ul><!-- end -->';
			}
			// $html .= '<div style="clear: both;"></div>' . PHP_EOL;
		}

		return $html;
	}
}

// Preserve compatibility
if (!class_exists('JFormFieldToggler'))
{
	/**
	 * Old-fashioned field name
	 *
	 * @since  1.2.0
	 */
				class JFormFieldToggler extends GJFieldsFormFieldToggler
				{
				}
}
