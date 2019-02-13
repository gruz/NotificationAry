<?php
/**
 * A function to fool Joomla mailer
 *
 * @package		NotificationAry
 * @subpackage	site
 * @author Gruz <arygroup@gmail.com>
 * @copyright	 0000 Copyleft - All rights reversed
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access
defined('_JEXEC') or die;

class fakeMailerClass
{

	function isHTML($var) { $this->{__FUNCTION__} = $var; }
	function Encoding($var) { $this->{__FUNCTION__} = $var; }
	function setSubject($var) { $this->{__FUNCTION__} = $var; }

	function setSender($var) { $this->{__FUNCTION__} = $var; }

	function addRecipient($var1,$var2) { $this->{__FUNCTION__}[] = array($var1,$var2); }
	function addReplyTo($var1,$var2) { $this->{__FUNCTION__}[] = array($var1,$var2); }


	function addAttachment($var) { $this->{__FUNCTION__}[] = $var; }
	function setBody($var) { $this->{__FUNCTION__} = $var; }
}
