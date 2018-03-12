<?php
/**
 * A wrapper class to extend common joomla class with GJFields methods
 *
 * @package    GJFields
 * @author     Gruz <arygroup@gmail.com>
 * @copyright  Copyleft (є) 2016 - All rights reversed
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Short desc
 *
 * @author  Gruz <arygroup@gmail.com>
 * @since   0.0.1
 */
class JPluginGJFields extends JPlugin
{
	static public $debug;

	static public $vars;

	/**
	 * Constructor.
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *
	 * @since   1.6
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$jinput = JFactory::getApplication()->input;

		if ($jinput->get('option', null) == 'com_dump')
		{
			return;
		}

		// Load languge for frontend
		$this->plg_name = $config['name'];
		$this->plg_type = $config['type'];
		$this->plg_full_name = 'plg_' . $config['type'] . '_' . $config['name'];
		$this->plg_path_relative = '/plugins/' . $this->plg_type . '/' . $this->plg_name . '/';
		$this->plg_path = JPATH_PLUGINS . '/' . $this->plg_type . '/' . $this->plg_name . '/';

		// Is used for building joomfish links
		$this->langShortCode = null;
		$this->default_lang = JComponentHelper::getParams('com_languages')->get('site');
		$language = JFactory::getLanguage();

		$language->load($this->plg_full_name, $this->plg_path, 'en-GB', true);
		$language->load($this->plg_full_name, $this->plg_path, $this->default_lang, true);
	}

	/**
	 * Determines if the current plugin has been just saved or applied and stores result into $this->pluginHasBeenSavedOrApplied
	 *
	 * The call of the function must be placed into __construct  or at least onAfterRoute.
	 * When saving a plugin Joomla uses a redirect. So onAfterRoute (and surely __construct) is run twice,
	 * while onAfterRender and futher functions only once (..onAfterRoute - redirect - ..onAfterRoute->onAfterRender)
	 * At the first onAfterRoute I can get such variables as task and jform to determine what action is performed.
	 * At the secon onAfterRoute the variables are empty, as they have been used before the redirect.
	 * So I check the needed variables to determin if the plugin is saved and enabled before the redirect onAfterRoute and store
	 * some flags to the session.
	 * After the redirect I get the flags from the session, clear the session not to run the plugin twice and run the main function body.
	 *
	 * @return	void
	 */
	protected function _preparePluginHasBeenSavedOrAppliedFlag ()
	{
		$jinput = JFactory::getApplication()->input;

		if ($jinput->get('option', null) == 'com_dump')
		{
			return;
		}

		// CHECK IF THE PLUGIN WAS JUST SAVED AND STORE A FLAG TO SESSION
		$jinput = JFactory::getApplication()->input;
		$this->pluginHasBeenSavedOrApplied = false;

		$session = JFactory::getSession();
		$option = $jinput->get('option', null);
		$task = $jinput->get('task', null);

		if ($option == 'com_plugins' && in_array($task, array('plugin.save','plugin.apply')))
		{
			// If the plugin which is saved is our current plugin and it's enabled
			$session = JFactory::getSession();
			$jform = $jinput->post->get('jform', null, 'array');

			if (isset($jform['element']) && $jform['element'] == $this->plg_name && isset($jform['folder']) && $jform['folder'] == $this->plg_type)
			{
				if ($jform['enabled'] == '0')
				{
					$session->clear($this->plg_full_name);
				}
				else
				{
					$data = new stdClass;
					$data->runPlugin = true;
					$session->set($this->plg_full_name, $data);
				}
			}
		}
		else
		{
			$sessionInfo = $session->get($this->plg_full_name, array());
			$session->clear($this->plg_full_name);

			// If we do not have to run plugin - joomla is not saving the plugin
			if (empty($sessionInfo) || empty($sessionInfo->runPlugin))
			{
				return;
			}
			else
			{
				$this->pluginHasBeenSavedOrApplied = $sessionInfo->runPlugin;
			}
		}
	}

	/**
	 * Gets defaultAttion default value
	 *
	 * @param   string  $addition  defaultAddition field attribute value
	 *
	 * @return   string  Default addition script result
	 */
	public function getDefaultAddtion($addition)
	{
		$addition = explode(';$', $addition);
		$text = '';

		if (file_exists(JPATH_SITE . '/' . $addition[0]) && isset($addition[1]))
		{
			require JPATH_SITE . '/' . $addition[0];
			$additionVar = ${$addition[1]};

			if (!is_array($additionVar))
			{
				$text = $additionVar;
			}
			else
			{
				$text = implode('', $additionVar);
			}
		}

		return $text;
	}

	/**
	 * Parses parameters of gjfileds (variablefileds) into a convinient arrays
	 *
	 * @param   string  $group_name  Name of the group in the XML file
	 *
	 * @return   type  Description
	 */
	public function getGroupParams ($group_name)
	{
		$jinput = JFactory::getApplication()->input;

		if ($jinput->get('option', null) == 'com_dump')
		{
			return;
		}

		if (!isset($GLOBALS[$this->plg_name]['variable_group_name'][$group_name]))
		{
			$GLOBALS[$this->plg_name]['variable_group_name'][$group_name] = true;
		}
		else
		{
			return;
		}

		// Get defauls values from XML {
		$group_name_start = $group_name;
		$group_name_end = str_replace('{', '', $group_name) . '}';
		$xmlfile = $this->plg_path . '/' . $this->plg_name . '.xml';
		$xml = simplexml_load_file($xmlfile);

		$field = 'field';
		$xpath = 'config/fields/fieldset';

		$started = false;
		$defaults = array();

		foreach ($xml->xpath('//' . $xpath . '/' . $field) as $f)
		{
			$field_name = (string) $f['name'];

			if ($field_name == $group_name_start)
			{
				$started = true;
				continue;
			}

			if (!$started)
			{
				continue;
			}

			if (in_array($f['basetype'], array('toggler', 'blockquote', 'note')))
			{
				continue;
			}

			$defaults[$field_name] = '';

			$def = (string) $f['default'];

			if (!empty($f['defaultAddition']))
			{
				$def .= $this->getDefaultAddtion((string) $f['defaultAddition']);
			}

			if (!empty($def))
			{
				$defaults[$field_name] = $def;
			}
			elseif ($def == 0)
			{
				$defaults[$field_name] = $def;
			}

			if ($field_name == $group_name_end)
			{
				break;
			}
		}
		// Get defauls values from XML }

		// Get all parameters
		$params = $this->params->toObject();

		$pparams = array();
		/*
		if (empty($params->{$group_name})) {
			$override_parameters = array (
				'ruleEnabled'=>$this->paramGet('ruleEnabled'),
				'menuname'=>$this->paramGet('menuname'),
				'show_articles'=>$this->paramGet('show_articles'),
				'categories'=>$this->paramGet('categories'),
				'regeneratemenu'=>$this->paramGet('regeneratemenu')
			);
			$pparams[] = $override_parameters;
		}
		*/

		if (empty($params->{$group_name}))
		{
			$params->{$group_name} = array();
		}

		$pparams_temp  = $params->{$group_name};

		foreach ($pparams_temp as $fieldname => $values)
		{
			$group_number = 0;
			$values = (array) $values;

			foreach ($values as $n => $value)
			{
				if ($value == 'variablefield::' . $group_name)
				{
					$group_number++;
				}
				elseif (is_array($value) && $value[0] == 'variablefield::' . $group_name)
				{
					if (!isset($pparams[$group_number][$fieldname]))
					{
						$pparams[$group_number][$fieldname] = array();
					}

					$group_number++;
				}
				elseif (is_array($value) )
				{
					$pparams[$group_number][$fieldname][] = $value[0];
				}
				elseif ( $fieldname == $group_name )
				{
					$pparams[$group_number][$fieldname][] = $value;
				}
				else
				{
					if ($value !== '')
					{
						$pparams[$group_number][$fieldname] = $value;
					}
				}
			}
		}

		// Update params with default values if there are no stored in the DB. Usefull when adding a new XML field and a user don't resave settings {
		foreach ($pparams as $param_key => $param)
		{
			foreach ($defaults as $k => $v)
			{
				if (!isset($param[$k]))
				{
					$pparams[$param_key][$k] = $v;
				}
			}
		}

		// Update params with default values if there are no stored in the DB. Usefull when adding a new XML field and a user don't resave settings }

		$this->pparams = $pparams;
	}

	/**
	 * Sets some default values
	 *
	 * In J1.7+ the default values written in the XML file are not passed to the script
	 * till first time save the plugin options. The defaults are used only to show values when loading
	 * the setting page for the first time. And if a user just publishes the plugin from the plugin list,
	 * ALL the fields doesn't have values set. So this function
	 * is created to avoid duplicating the defaults in the code.
	 * Usage:
	 * Instead of
	 * <code>$this->params->get( 'some_field_name', 'default_value' )</code>
	 * use
	 * <code>$this->paramGet( 'some_field_name',[optional 'default_value'])</code>
	 *
	 * @param   string  $name     Field name to get the default value from XML
	 * @param   mixed   $default  Default value if no found
	 *
	 * @return  mixed  default value
	 */
	public function paramGet($name, $default=null)
	{
		$hash = get_class();
		$session = JFactory::getSession();

		// Get cached parameteres
		$params = $session->get('DefaultParams', false, $hash);

		if (empty($params) || empty($params[$name]))
		{
			// $xmlfile = dirname(__FILE__).'/'.basename(__FILE__,'.php').'.xml';
			$xmlfile = $this->plg_path . '/' . $this->plg_name . '.xml';

			$this->_parseXMLForDefautlValues($xmlfile);
		}

		if (!isset ($params[$name]))
		{
			$params[$name] = $default;
		}

		return $this->params->get($name, $params[$name]);
	}

	/**
	 * Get's default values from an XML file and stores it to the session
	 *
	 * If a subform found, then calls itself on the subform xml
	 *
	 * @param   string  $xmlfile  Absolute path to an XML form file
	 *
	 * @return   void
	 */
	public function _parseXMLForDefautlValues($xmlfile)
	{
			$hash = get_class();
			$session = JFactory::getSession();

			// Get cached parameteres
			$params = $session->get('DefaultParams', array(), $hash);

			$xpath = '//field[@type="subform"]';
			$xml = simplexml_load_file($xmlfile);
			$subforms = $xml->xpath($xpath);

			foreach ($subforms as $subform)
			{
				$path = JPATH_ROOT . '/' . $subform['formsource'];
				$this->_parseXMLForDefautlValues($path);
			}

			$fields = $xml->xpath('//field');

			foreach ($fields as $f)
			{
				if (isset($f['default']) )
				{
					if (preg_match('~[0-9]+,[0-9]*~', (string) $f['default']))
					{
						$params[(string) $f['name']] = explode(',', (string) $f['default']);
					}
					else
					{
						$params[(string) $f['name']] = (string) $f['default'];
					}
				}
			}

			$params_old = $session->get('DefaultParams', array(), $hash);
			$params = array_merge($params_old, $params);

			$session->set('DefaultParams', $params, $hash);
	}

	/**
	 * Checks if current view is a plugin edit view
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @return	bool			true if currentrly editing current plugin, false - if another plugin view
	 */
	public function checkIfNowIsCurrentPluginEditWindow()
	{
		$jinput = JFactory::getApplication()->input;

		$option = $jinput->get('option', null);

		if ($option !== 'com_plugins')
		{
			return false;
		}

		$view = $jinput->get('view', null);
		$layout = $jinput->get('layout', null);
		$current_extension_id = $jinput->get('extension_id', null);

		// Means we are editing a plugin
		if ($view == 'plugin' && $layout == 'edit')
		{
			$db = JFactory::getDBO();
			$db->setQuery('SELECT extension_id FROM #__extensions WHERE type ='
				. $db->quote('plugin') . ' AND element = '
				. $db->quote($this->plg_name)
				. ' AND folder = ' . $db->quote($this->plg_type)
			);
			$extension_id = $db->loadResult();

			if ($current_extension_id == $extension_id)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if plugin is published
	 *
	 * @param   string  $plugin_group  Plugin group
	 * @param   string  $plugin_name   Plugin name
	 * @param   bool    $show_message  If to show message
	 *
	 * @return   type  Description
	 */
	public function checkIfAPluginPublished ($plugin_group, $plugin_name, $show_message = true)
	{
		$plugin_state = JPluginHelper::getPlugin($plugin_group, $plugin_name);

		if (!$plugin_state)
		{
			if ($show_message)
			{
				$db = JFactory::getDBO();
				$db->setQuery('SELECT name FROM #__extensions WHERE type ='
					. $db->quote('plugin')
					. ' AND element = ' . $db->quote($plugin_name)
					. ' AND folder = ' . $db->quote($plugin_group)
				);
				$name = $db->loadResult();
				$plugin_name = JText::_($name);
				$application = JFactory::getApplication();
				$application->enqueueMessage(
					JText::sprintf(
						'LIB_GJFIELDS_PLUGIN_NOT_PUBLISHED',
						$plugin_name,
						$plugin_group,
						$plugin_name,
						$plugin_name,
						$plugin_group
					),
					'error'
				);
			}

			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	 * Adds a JS or CSS file using proper joomla way to let me be overridable
	 *
	 * Please note, that url passed to the function must be like plg_system_notificationary/ajax.js
	 * while the file itself must be in a folder like media/plg_system_notificationary/js/ajax.js
	 *
	 * @param   string  $path       JS or CSS file like ajax.js
	 * @param   string  $extension  Extension name in format like plg_system_notificationary or com_content
	 *                              to look for the media files in the media folder
	 * @param   bool    $debug      If to load min or non-min file
	 *
	 * @return   void
	 */
	public static function addJSorCSS($path, $extension, $debug = false)
	{
		$path = $extension . '/' . $path;

		$path_parts = pathinfo($path);

		if (!static::$debug && !$debug)
		{
			// Add .min to the file name
			$path = explode('.', $path);
			$ext = array_pop($path);
			$path[] = 'min';
			$path[] = $ext;
			$path = implode('.', $path);

			switch ($path_parts['extension'])
			{
				case 'css':
					JHtml::stylesheet($path, false, true, false);
					break;
				case 'js':
					JHtml::script($path, false, true);
					break;
				default :

					break;
			}

			return;
		}

		if (!$extension && isset(static::$vars['plg_path_relative']))
		{
			$path = static::$vars['plg_path_relative'];
		}
		else
		{
			$extension = explode('_', $extension);

			switch ($extension[0])
			{
				case 'plg':
					$path = '/plugins/' . $extension[1] . '/' . $extension[2];
					break;
				case 'lib':
					$path = '/libraries/' . $extension[1];
					break;
				default :
					break;
			}

			$path .= '/';

		}

		$path .= $path_parts['extension'] . '/' . $path_parts['basename'];

		$doc = JFactory::getDocument();
		$hash = md5_file(JPATH_ROOT . $path);
		$path .= '?h=' . $hash;

		switch ($path_parts['extension'])
		{
			case 'css':
				$doc->addStyleSheet($path);
				break;
			case 'js':
				$doc->addScriptVersion($path);
				break;
		}
	}

	/**
	 * Outputs JSON data
	 *
	 * Perofms additional actions like sending proper headers and closing apps
	 *
	 * @param   array   $data         Data array
	 * @param   string  $message      Message
	 * @param   bool    $task_failed  Task status
	 *
	 * @return   type  Description
	 */
	public static function _JResponseJson($data , $message, $task_failed = true)
	{
		// At least here in the plugin it's a must to send proper headers
		JFactory::getApplication()->setHeader('Content-Type', 'application/json', true)->sendHeaders();
		echo new JResponseJson($data, $message, $task_failed);

		// Closing app is a must here to return JSON immideately
		JFactory::getApplication()->close();
	}

	/**
	 * Used to replace core ajax token check
	 *
	 * Core JSession::checkToken() functions performs redirects
	 * for new/expired tokens. This doesn't allow for us to send
	 * AJAX responses to our ajax forms on bad token.
	 * So in the places where we need AJAX token check, we must use this
	 * function.
	 * Usage: <code>Teach::checkToken('post');</code>
	 *
	 * @param   string  $method  Method of token data passed
	 *
	 * @return  void
	 */
	public static function checkToken($method = 'post')
	{
		$token = JSession::getFormToken();
		$app = JFactory::getApplication();
		$jinput  = $app->input;

		if ($app->input->$method->get($token, '', 'alnum'))
		{
			// Token check passed
			return;
		}

		$session = JFactory::getSession();

		if ($session->isNew())
		{
			$message = JText::_('JLIB_ENVIRONMENT_SESSION_EXPIRED');

			if (!$jinput->$method->get('ajax', false))
			{
				// Redirect to login screen.
				$app->enqueueMessage($message, 'warning');
				$app->redirect(JRoute::_('index.php'));
			}

			$task_failed = true;
			$data = array ('status' => 'expired_session');
			self::_JResponseJson($data, $message, $task_failed);

			return true;
		}
		else
		{
			$message = JText::_('JINVALID_TOKEN');

			if (!$jinput->$method->get('ajax', false))
			{
				jexit($message);
			}

			$task_failed = true;
			$data = array ('status' => 'invalid_token');
			self::_JResponseJson($data, $message, $task_failed);

			return false;
		}
	}
}
