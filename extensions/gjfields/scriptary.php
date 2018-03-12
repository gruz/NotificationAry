<?php
/**
 * ScriptAry helper base class. I used by all gruz.org.ua extensions
 *
 * @package    ScriptAry
 *
 * @author     Gruz <arygroup@gmail.com>
 * @copyright  Copyleft (є) 2016 - All rights reversed
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access
defined('_JEXEC') or die;

/**
 * Class to be extended by
 *
 * @author  Gruz <arygroup@gmail.com>
 * @since   0.0.1
 */
class ScriptAry
{
	/**
	 * On install operations
	 *
	 * @param   object  $parent  Is the class calling this method
	 *
	 * @return   void
	 */
	public function install($parent)
	{

		$manifest = $parent->getParent()->getManifest();

		if (!$this->_installAllowed($manifest))
		{
			return false;
		}
		// $parent is the class calling this method
		// $parent->getParent()->setRedirectURL('index.php?option=com_helloworld');
	}

	/**
	 * On uninstall operations
	 *
	 * @param   object  $parent  Is the class calling this method
	 *
	 * @return   void
	 */
	public function uninstall($parent)
	{
		// Nothing yet
	}

	/**
	 * On update operations
	 *
	 * @param   object  $parent  Is the class calling this method
	 *
	 * @return   void
	 */
	public function update($parent)
	{
		$manifest = $parent->getParent()->getManifest();

		if (!$this->_installAllowed($manifest))
		{
			return false;
		}

		return true;
		// $parent is the class calling this method
		// E.g. echo '<p>' . JText::_('COM_HELLOWORLD_UPDATE_TEXT') . '</p>';
	}

	/**
	 * Parses scriptfile.php class name to find the name of the extension beign installed
	 *
	 * @return   mixed  Extension name or false
	 */
	static public function _getExtensionName()
	{
		$className = get_called_class();
		preg_match('~(?:plg|mod_|Plg|Mod_)(.*)InstallerScript~Ui', $className, $matches);

		if (isset($matches[1]))
		{
			return strtolower($matches[1]);
		}

		return false;
	}

	/**
	 * Checks is installtion is allowed
	 *
	 * @param   object  $manifest  Install XML manifest object
	 *
	 * @return   bool
	 */
	function _installAllowed($manifest)
	{
		$this->minimumJoomla = (string) $manifest['version'];

		// Check for the minimum Joomla version before continuing
		if (!empty($this->minimumJoomla) && !version_compare(JVERSION, $this->minimumJoomla, '>'))
		{
			$msg = JText::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomla);

			// Older Joomlas don't have this line
			if ($msg == 'JLIB_INSTALLER_MINIMUM_JOOMLA')
			{
				$msg = JText::sprintf("You don't have the minimum Joomla version requirement of J%s", $this->minimumJoomla);
			}

			JLog::add($msg, JLog::WARNING, 'jerror');

			return false;
		}

		return true;
	}


	/**
	 * Prepares some variables to be used later in the class, loads languages
	 *
	 * @param   string  $type    Is the type of change (install, update or discover_install)
	 * @param   object  $parent  Is the class calling this method
	 *
	 * @return   void
	 */
	public function preflight($type, $parent)
	{
		$manifest = $parent->getParent()->getManifest();

		if ($type != 'uninstall' && !$this->_installAllowed($manifest))
		{
			return false;
		}

		$this->ext_name = $this->_getExtensionName();
		$this->ext_group = (string) $manifest['group'];
		$this->ext_type = (string) $manifest['type'];
		$className = get_called_class();
		$ext = strtolower(substr($className, 0, 3));

		switch ($ext)
		{
			case 'plg':
				$this->ext_name = substr($this->ext_name, strlen($this->ext_group));
				$this->ext_full_name = $ext . '_' . $this->ext_group . '_' . $this->ext_name;
				break;
			case 'mod':
			case 'com':
			case 'lib':
			default :
				$this->ext_full_name = $ext . '_' . $this->ext_name;
				break;
		}

		$this->langShortCode = null;
		$this->default_lang = JComponentHelper::getParams('com_languages')->get('admin');
		$language = JFactory::getLanguage();

		$language->load($this->ext_full_name, dirname(__FILE__), 'en-GB');

		if ($this->default_lang != 'en-GB')
		{
			$language->load($this->ext_full_name, dirname(__FILE__), $this->default_lang);
		}

		return true;
	}

	/**
	 * Install extensions (placed in /extension folders, published the plugin)
	 *
	 * @param   string  $type           Is the type of change (install, update or discover_install)
	 * @param   object  $parent         Is the class calling this method
	 * @param   bool    $publishPlugin  If to publish the being installed plugin
	 *
	 * @return   void
	 */
	public function postflight($type, $parent, $publishPlugin = true)
	{
		$manifest = $parent->getParent()->getManifest();

		if ($type != 'uninstall' && !$this->_installAllowed($manifest))
		{
			return false;
		}

		if ($type != 'uninstall')
		{
			$this->_installExtensions($parent);
		}

		if ($publishPlugin && $type == 'install' && $this->ext_type == 'plugin')
		{
			$this->_publishPlugin($this->ext_name, $this->ext_group, $this->ext_full_name);
		}

		if ($this->ext_type == 'plugin')
		{
			// Remove min js and css
			foreach (array('js', 'css') as $ftype)
			{
				$path = JPATH_ROOT . '/plugins/' . $this->ext_group . '/' . $this->ext_name . '/';
				$pattern = '.*min\.' . $ftype . '';
				$files = JFolder::files($path, $pattern, true, true);

				foreach ($files as $fll)
				{
					JFile::delete($files);
				}
			}

			$extensionTable = JTable::getInstance('extension');

			// Find plugin id
			$pluginId = $extensionTable->find(array('element' => $this->ext_name, 'type' => 'plugin'));
			$extensionTable->load($pluginId);

			$this->messages[] = JText::_('JOPTIONS')
					. ': <a class="menu-' . $this->ext_name . ' " href="index.php?option=com_plugins&task=plugin.edit&extension_id=' . $pluginId . '">'
					. JText::_($this->ext_full_name) . '</a>';
		}

		if (!empty($this->messages))
		{
			echo '<ul><li>' . implode('</li><li>', $this->messages) . '</li></ul>';
		}

		return true;

		// E.g.: echo '<p>' . JText::_('COM_HELLOWORLD_POSTFLIGHT_' . $type . '_TEXT') . '</p>';
	}

	/**
	 * Publishes a plugin
	 *
	 * @param   string  $plg_name       Plugin name, like notificationary
	 * @param   string  $plg_type       Plugin group, like system
	 * @param   string  $plg_full_name  Plugin full name, like olg_system_notificationary
	 *
	 * @return   void
	 */
	private function _publishPlugin($plg_name,$plg_type, $plg_full_name = null)
	{
		$plugin = JPluginHelper::getPlugin($plg_type, $plg_name);
		$success = true;

		if (empty($plugin))
		{
			// Get the smallest order value
			$db = jfactory::getdbo();

			// Publish plugin
			$query = $db->getquery(true);

			// Fields to update.
			$fields = array(
				$db->quotename('enabled') . '=' . $db->quote('1')
			);

			// Conditions for which records should be updated.
			$conditions = array(
				$db->quotename('type') . '=' . $db->quote('plugin'),
				$db->quotename('folder') . '=' . $db->quote($plg_type),
				$db->quotename('element') . '=' . $db->quote($plg_name),
			);
			$query->update($db->quotename('#__extensions'))->set($fields)->where($conditions);
			$db->setquery($query);
			$result = $db->execute();
			$getaffectedrows = $db->getAffectedRows();
			$success = $getaffectedrows;
		}

		if (empty($plg_full_name))
		{
			$plg_full_name = $plg_name;
		}

		$msg = jtext::_('jglobal_fieldset_publishing') . ': <b style="color:blue;"> ' . JText::_($plg_full_name) . '</b> ... ';

		if ($success)
		{
			$msg .= '<b style="color:green">' . jtext::_('jpublished') . '</b>';
		}
		else
		{
			$msg .= '<b style="color:red">' . jtext::_('error') . '</b>';
		}

		$this->messages[] = $msg;
	}

	/**
	 * Installs all extensions
	 *
	 * Full description (multiline)
	 *
	 * @param   object  $parent  Is the class calling this method
	 *
	 * @return   void
	 */
	private function _installExtensions ($parent)
	{
		jimport('joomla.filesystem.folder');
		jimport('joomla.installer.installer');

		JLoader::register('LanguagesModelInstalled', JPATH_ADMINISTRATOR . '/components/com_languages/models/installed.php');
		$lang = new LanguagesModelInstalled;
		$current_languages = $lang->getData();
		$locales = array();

		foreach ($current_languages as $lang)
		{
			$locales[] = $lang->language;
		}

		$extpath = dirname(__FILE__) . '/extensions';

		if (!is_dir($extpath))
		{
			return;
		}

		$folders = JFolder::folders($extpath);

		foreach ($folders as $folder)
		{
			$folder_temp = explode('_', $folder, 2);

			if (isset ($folder_temp[0]))
			{
				$check_if_language = $folder_temp[0];

				if (preg_match('~[a-z]{2}-[A-Z]{2}~', $check_if_language))
				{
					if (!in_array($folder_temp[0], $locales))
					{
						continue;
					}
				}
			}

			$installer = new JInstaller;

			if ($installer->install($extpath . '/' . $folder))
			{
				$manifest = $installer->getManifest();
				$this->messages[] = JText::sprintf(
						'COM_INSTALLER_INSTALL_SUCCESS',
						'<b style="color:#0055BB;">[' . $manifest->name . ']<span style="color:green;">'
					)
					. '</span></b>';
			}
			else
			{
				$this->messages[] = '<span style="color:red;">' . $folder . ' ' . JText::_('JERROR_AN_ERROR_HAS_OCCURRED') . '</span>';
			}
		}
	}
}
