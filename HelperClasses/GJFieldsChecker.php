<?php
/**
 * GJFieldsChecker helper class
 *
 * @package    Notificationary

 * @author     Gruz <arygroup@gmail.com>
 * @copyright  0000 Copyleft (є) 2017 - All rights reversed
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace NotificationAry\HelperClasses;

// No direct access
defined('_JEXEC') or die('Restricted access');

use JFactory;

class GJFieldsChecker
{
	private $version;
	private $message;

	public function __construct($version)
	{
		$this->version = $version;
		return $this;
	}

	public function check() 
	{
		$error_msg = 'Install the latest GJFields plugin version <span style="color:black;">'
		. __FILE__ . '</span>: <a href="http://www.gruz.org.ua/en/extensions/gjfields-sefl-reproducing-joomla-jform-fields.html">GJFields</a>';
	
		$isOk = true;
	
		while (true) {
			$isOk = false;
		
			if (!class_exists('JPluginGJFields')) {
				$error_msg = 'Strange, but missing GJFields library for <span style="color:black;">'
					. __FILE__ . '</span><br> The library should be installed together with the extension... Anyway, reinstall it:
					<a href="http://www.gruz.org.ua/en/extensions/gjfields-sefl-reproducing-joomla-jform-fields.html">GJFields</a>';
				break;
			}
		
			$gjfields_version = file_get_contents(JPATH_ROOT . '/libraries/gjfields/gjfields.xml');
			preg_match('~<version>(.*)</version>~Ui', $gjfields_version, $gjfields_version);
			$gjfields_version = $gjfields_version[1];
		
			if (version_compare($gjfields_version, $this->version, '<')) {
				break;
			}
		
			$isOk = true;
			break;
		}

		if (!$isOk) {
			$this->setMessage($error_msg);
		}
		return $isOk;
	}

	/**
	 * Get the value of message
	 */ 
	public function getMessage()
	{
		return $this->message;
	}

	/**
	 * Set the value of message
	 *
	 * @return  self
	 */ 
	public function setMessage($message)
	{
		$this->message = $message;

		return $this;
	}
}
