<?php
/**
 * BackendInerface
 *
 * @package     NotificationAry
 *
 * @author      Gruz <arygroup@gmail.com>
 * @copyright   Copyleft (є) 2018 - All rights reversed
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */


namespace NotificationAry\Traits;

/**
 * A helper trait
 *
 * @since 0.2.17
 */
trait BackendInerface
{

	/**
	 * Prepares a test object output
	 *
	 * @param   object  $contentObject              A content object to be parsed
	 * @param   array   &$place_holders_body_input  Array to place HTML output
	 *
	 * @return   void
	 */
	static public function buildExampleObject($contentObject, &$place_holders_body_input)
	{
		foreach ($contentObject as $key => $value)
		{
			if (is_array($value))
			{
				foreach ($value as $kf => $vf)
				{
					$place_holders_body_input[] = ''
						. '<span style="color:red;">##Content#' . $key . '##' . $kf . '##</span> => ' . htmlentities((string) $vf) . '<br/>';
				}
			}
			else
			{
				if (is_object($value))
				{
					continue;
				}

				$place_holders_body_input[] = ''
					. '<span style="color:red;">##Content#' . $key . '##</span> => ' . htmlentities((string) $value) . '<br/>';
			}
		}
	}


	/**
	 * Prepares a test object output
	 *
	 * @param   JUser  $user                       A content object to be parsed
	 * @param   array  &$place_holders_body_input  Array to place HTML output
	 *
	 * @return   void
	 */
	public static function buildExampleUser($user, &$place_holders_body_input)
	{
		foreach ($user as $key => $value)
		{
			if ($key == 'password')
			{
				continue;
			}

			if (is_array($value))
			{
				$value = implode(',', $value);
			}

			if ( is_object( $value ) ) 
			{
				continue;
			}
			
			$place_holders_body_input[] = '<span style="color:red;">##User#' . $key . '##</span> => ' . htmlentities((string) $value) . '<br/>';
		}
	}

}
