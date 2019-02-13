<?php
/**
 * @package    GJFileds
 *
 * @copyright  0000 Copyright (C) All rights reversed.
 * @license    http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL or later
 */

// No direct access
defined('_JEXEC') or die();

if (!class_exists('JPluginGJFields'))
{
	include 'helper/plugin.php';
}

/** ##mygruz20180410015059 { Add Fabrik compatibility
 *  Fabrik overrides basic joomla Joomla\CMS\Form\FormField class with own.
 * GJFields, if loaded before fabrik, loads the core class.
 * And then Fabrik tries to load own, causing redeclare fatal error.
 * The code below is copied from libraries/gjfields/helper/plugin.php
 */
$app     = JFactory::getApplication();
$version = new JVersion;
$base    = 'components.com_fabrik.classes.' . str_replace('.', '', $version->RELEASE);
$loaded = JLoader::import($base . '.FormField', JPATH_SITE . '/administrator', 'administrator.');
/** ##mygruz20180410015059 } */

/**
 * Base class to extend with gjfields fileds
 *
 * @since  0.0.1
 */
class GJFieldsFormField extends Joomla\CMS\Form\FormField
{
	static public $debug = false; // phpcs:ignore
	static public $libName = 'lib_gjfields'; // phpcs:ignore
	/**
	 * Constructor
	 *
	 * Loads some
	 *
	 * @param   object  $form  XML form
	 */
	public function __construct($form = null)
	{
		parent::__construct($form);
		JHTML::_('jquery.framework');
		JHTML::_('behavior.framework', true);
		$app = JFactory::getApplication();

		if (!$app->get($this->type . '_initialized', false))
		{
			$app->set($this->type . '_initialized', true);

			$pathToAssets = JPATH_ROOT . '/libraries/gjfields/';
			$doc = JFactory::getDocument();

			$cssNamePath = $pathToAssets . 'css/common.css';

			if (file_exists($cssNamePath))
			{
				JPluginGJFields::addJSorCSS('common.css', static::$libName, static::$debug);
			}

			$this->type = JString::strtolower($this->type);

			$cssNamePath = $pathToAssets . 'css/' . $this->type . '.css';

			if (file_exists($cssNamePath))
			{
				JPluginGJFields::addJSorCSS($this->type . '.css', static::$libName, static::$debug);
			}

			JPluginGJFields::addJSorCSS('script.js', static::$libName, static::$debug);

			$scriptNamePath = $pathToAssets . 'js/' . $this->type . '.js';

			if (file_exists($scriptNamePath))
			{
				JPluginGJFields::addJSorCSS($this->type . '.js', static::$libName, static::$debug);
			}
		}

		$this->HTMLtype = 'div';

		if (JFactory::getApplication()->isAdmin() && JFactory::getApplication()->getTemplate() !== 'isis')
		{
			$this->HTMLtype = 'li';
		}

		$varName = basename(__FILE__, '.php') . '_HTMLtype';

		if (!$app->get($varName, false))
		{
			$app->set($varName, true);
			$doc = JFactory::getDocument();
			$doc->addScriptDeclaration('var ' . $varName . ' = "' . $this->HTMLtype . '";');
			$doc->addScriptDeclaration('var lang_reset = "' . JText::_('JSEARCH_RESET') . '?";');
		}
	}

	/**
	 * A cap
	 *
	 * @return   void
	public function getInput()
	{
	}
	 */

	/**
	 * Gets a default value for a field
	 *
	 * A very old NN legacy function
	 *
	 * @param   mixed  $val      Element name
	 * @param   mixed  $default  Default value
	 *
	 * @return   mixed  Either current or default value of an XML field
	 */
	public function def($val, $default = '')
	{
		return ( isset( $this->element[$val] ) && (string) $this->element[$val] != '' ) ? (string) $this->element[$val] : $default;
	}

	/**
	 * Gets GJFields version numbed from the library .xml file
	 *
	 * @return   string  Version number
	 */
	static public function _getGJFieldsVersion ()
	{
		$gjfieldsVersion = file_get_contents(dirname(__FILE__) . '/gjfields.xml');
		preg_match('~<version>(.*)</version>~Ui', $gjfieldsVersion, $gjfieldsVersion);
		$gjfieldsVersion = $gjfieldsVersion[1];

		return $gjfieldsVersion;
	}

	/**
	 * A cap for older joomla versions. Is a must.
	 *
	 * @return   string
	 */
	public function getInput()
	{
		return parent::getInput();
	}
}

// Preserve compatibility
if (!class_exists('JFormFieldGJFields'))
{
	/**
	 * Old-fashioned field name
	 *
	 * @since  1.2.0
	 */
	class JFormFieldGJFields extends GJFieldsFormField
	{
	}
}
