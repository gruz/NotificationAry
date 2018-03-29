<?php
/**
 * A helper trait with small functions
 *
 * @package     NotificationAry
 *
 * @author      Gruz <arygroup@gmail.com>
 * @copyright   Copyleft (є) 2018 - All rights reversed
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */


namespace NotificationAry\Traits;

/**
 * Small helper functions
 *
 * @since 0.2.17
 */
trait SmallFunctions
{
	/**
	 * Check if item is an instance of \JForm
	 *
	 * @param   any $form A form instance
	 *
	 * @return boolean
	 */
	public function checkIsForm($form)
	{
		if (($form instanceof \JForm) || ($form instanceof Joomla\CMS\Form\Form))
		{
			return true;
		}
		else
		{
			return false;
		}
	}


	/**
	 * Checks if current page is a content item edit page
	 *
	 * @param   string  &$context  Context
	 *
	 * @return   bool
	 */
	public function _isContentEditPage(&$context )
	{
		$this->_prepareParams();

		if (!empty($context))
		{
			if (in_array($context, $this->allowedContexts))
			{
				return true;
			}
		}

		return false;
	}


	/**
	 * Returns a variable from a link
	 *
	 * @param   string  $link     Url
	 * @param   string  $varName  Name of the url query parameter
	 *
	 * @return   string
	 */
	static public function getVarFromQuery($link, $varName)
	{
		$parsed_url = parse_url($link);

		parse_str($parsed_url['query'], $query_params);

		if (!empty($query_params[$varName]))
		{
			return $query_params[$varName];
		}

		return null;
	}

		/**
	 * Checks if it's the first run of the function. The plugin is executed twice  - as system and as content.
	 *
	 * Is needed i.e. to properly add notification switch, to run the add routine only once
	 *
	 * @param   string  $name  Test
	 *
	 * @return  bool  True is the functions is rub not the first time
	 */
	static public function isFirstRun($name = 'unknown')
	{
		global  $NotificationAryFirstRunCheck;

		if (empty($NotificationAryFirstRunCheck[$name]))
		{
			$NotificationAryFirstRunCheck[$name] = 'not first';

			return true;
		}

		return false;
	}

	/**
	 * Hides full email
	 *
	 * Used when sending in ajax mode
	 * not to show real emails registred at the web-site
	 *
	 * @param   string  $email  Email address
	 *
	 * @return   string  Hiddedn email address
	 */
	static public function obfuscate_email($email)
	{
		$em   = explode("@", $email);
		$name = implode(array_slice($em, 0, count($em) - 1), '@');
		$len  = floor(strlen($name) / 2);

		return substr($name, 0, $len) . str_repeat('*', $len) . "@" . end($em);
	}


}
