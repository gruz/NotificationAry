<?php
/*
 * @author Gruz <arygroup@gmail.com>
 * @copyright	Copyleft - All rights reversed
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
*/
// No direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Script file
 */

class uk_UA_gjfields_language_packInstallerScript {
	function __construct() {
	}
	/**
	 * method to install the component
	 *
	 * @return void
	 */
	function install($parent) {
		// $parent is the class calling this method
		//$parent->getParent()->setRedirectURL('index.php?option=com_helloworld');
	}

	/**
	 * method to uninstall the component
	 *
	 * @return void
	 */
	function uninstall($parent) {
		// $parent is the class calling this method
		//echo '<p>' . JText::_('COM_HELLOWORLD_UNINSTALL_TEXT') . '</p>';
	}

	/**
	 * method to update the component
	 *
	 * @return void
	 */
	function update($parent) {
		// $parent is the class calling this method
		//echo '<p>' . JText::_('COM_HELLOWORLD_UPDATE_TEXT') . '</p>';
	}

	/**
	 * method to run before an install/update/uninstall method
	 *
	 * @return void
	 */
	function preflight($type, $parent) {
		// $parent is the class calling this method
		// $type is the type of change (install, update or discover_install)
		//echo '<p>' . JText::_('COM_HELLOWORLD_PREFLIGHT_' . $type . '_TEXT') . '</p>';
	}

	/**
	 * method to run after an install/update/uninstall method
	 *
	 * Remove old language pack bad installations
	 *
	 * @return void
	 */
	function postflight($type, $parent) {
		$name = get_class($this);
		$extension = JTable::getInstance('extension');
		$manifest = $parent->getParent()->getManifest();
		$element_to_del = str_replace(' ','',(string)$manifest->description);
		$eid = $extension->find(
			 array(
			'element' => strtolower($element_to_del)
			// , 'type' => strtolower('file'),
			//~ 'client_id' => strtolower($current_update->get('client_id')),
			//~ 'folder' => strtolower($current_update->get('folder'))
		));
		if (!empty($eid)) {
			$extension->delete($eid);
		}
		// $parent is the class calling this method
		// $type is the type of change (install, update or discover_install)
		//echo '<p>' . JText::_('COM_HELLOWORLD_POSTFLIGHT_' . $type . '_TEXT') . '</p>';
	}
	private function installExtensions ($parent) {

	}

}
?>