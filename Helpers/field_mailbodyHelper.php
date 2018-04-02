<?php
/**
 * This is a helper file used to output email template in the plugin settings.
 * Tries to load an content item object based on the selected source content type
 *
 * @package    NotificationAry
 * @author     Gruz <arygroup@gmail.com>
 * @copyright  0000 Copyleft - All rights reversed
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

// Available placeholder for mails
$ph_subject = array(
	'%SITENAME%',
	'%SITELINK%',
	'%ACTION%', // Published
	'%STATUS%',
	'%TITLE%',
	'%MODIFIER%',
	'%CONTENT_TYPE%',

	'%TO_NAME%',
	'%TO_USERNAME%',
	'%TO_EMAIL%',
);

$ph_body = array(
	'%CONTENT ID%',
	'%AUTHOR%',
	'%CATEGORY PATH%',
	'%CREATED DATE%',
	'%MODIFIED DATE%',
	'%FRONT VIEW LINK%',
	'%FRONT EDIT LINK%',
	'%BACKEND EDIT LINK%',
	'%UNSUBSCRIBE LINK%',
	'%MANAGE SUBSCRIPTION LINK%',
	'%INTRO TEXT%',
	'%FULL TEXT%',
	'%DIFF Text/Unified%',
	'%DIFF Text/Context%',

	// This line is used at the plugin settings form only, not in mailbody
	'</b>' . \JText::_('PLG_SYSTEM_NOTIFICATIONARY_FIELD_MESSAGE_HTML_BODY_ONLY') . '<b>',
	'%DIFF Html/SideBySide%',
	'%DIFF Html/Inline%'
);

$place_holders_subject_label = array();

foreach ($ph_subject as $k => $v)
{
	$place_holders_subject_label[$k] = '<br/><b>' . $v . '</b>';
}

$place_holders_body_label = array();

foreach ($ph_body as $k => $v)
{
	$place_holders_body_label[$k] = '<br/><b>' . $v . '</b>';
}

$place_holders_body_label = array_merge($place_holders_subject_label, $place_holders_body_label);

$default_body = JText::_('JSITE') . ':  %SITELINK% :: %SITENAME%
' . JText::_('JGLOBAL_TITLE') . ': %TITLE%
' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_CONTENT_TYPE') . ': %CONTENT_TYPE%
' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_ACTION') . ': %ACTION%
' . JText::_('JCATEGORY') . ': %CATEGORY PATH%
' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_VIEW_LINK') . ': %FRONT VIEW LINK%

' . JText::_('JGLOBAL_CREATED_DATE') . ': %CREATED DATE%
' . JText::_('JGLOBAL_FIELD_MODIFIED_LABEL') . ': %MODIFIED DATE%

' . JText::_('JGLOBAL_INTRO_TEXT') . ':
----
%INTRO TEXT%
----
';

$placeHoldersBodyInput = array();

if (get_class($this) == 'GJFieldsFormFieldTextareafixed')
{
	while (true)
	{
		$contextOrContenttype = $this->element['context_or_contenttype'];

		if (empty($contextOrContenttype))
		{
			break;
		}

		$extension = $this->element[$contextOrContenttype] ? (string) $this->element[$contextOrContenttype] : (string) $this->element['scope'];

		switch ($contextOrContenttype)
		{
			case 'context':
				break;
			case 'content_type':
			default :
				$category = JTable::getInstance('contenttype');
				$category->load($extension);
				$extension = $category->type_alias;
				break;
		}

		JPluginHelper::importPlugin('notificationary');
		$app = JFactory::getApplication();

		$scriptAdded = $app->get('##mygruz20160216061544', false);

		if (!$scriptAdded)
		{
			$document = JFactory::getDocument();
			$js = "
				jQuery(document).ready(function(){
					jQuery('small.object_values').toggle('hide');
						jQuery('button.object_values').live('click', function(event) {
							jQuery(this).nextAll('small.object_values:first').toggle('show');
						});
				});
			";
			$document->addScriptDeclaration($js);
			$app->set('##mygruz20160216061544', true);
			$scriptAdded = true;
		}

		$contentObject = $app->triggerEvent('_getContentItemTable', array($extension));

		// If a rule is disabled, then an empty result is returned. Not sence to handle in this case
		if (!empty($contentObject) && !empty($contentObject[0]))
		{
			$contentObject = $contentObject[0];

			$tbl = $contentObject->get('_tbl');
			$tbl_key = $contentObject->get('_tbl_key');
			$db = JFactory::getDBO();
			$query = $db->getQuery(true);
			$query->select($tbl_key);
			$query->from($tbl);
			$query->order($tbl_key . ' DESC');
			$query->setLimit('1');
			$db->setQuery((string) $query);
			$id = $db->loadResult();

			$contentObject->load($id);
		}
		else
		{
			break;
		}

		$placeHoldersBodyInput = array();

		$placeHoldersBodyInput[] = '<br/>'
			. JText::_('PLG_SYSTEM_NOTIFICATIONARY_SHOW_EXAMPLE_OBJECT')
			. ' <button type="button" class="btn btn-warning btn-small object_values" >
						<i class="icon-plus"></i>
					</button><br/>
					<small class="object_values" >
					<pre style="clear:both;float:left;width:46%;margin-right:1%;"><b>----'
						. get_class($contentObject) . '----</b><br/>';

		// JLoader::register('NotificationAry', dirname(__FILE__) . '/helper.php');

		NotificationAry\PlgSystemNotificationaryCore::buildExampleObject($contentObject, $placeHoldersBodyInput);

		// Free some memory
		unset($contentObject);

		$user = JFactory::getUser();
		$placeHoldersBodyInput[] = '</pre>';
		$placeHoldersBodyInput[] = '<pre style="float:left;width:46%;"><b>----' . get_class($user) . '----</b><br/>';
		NotificationAry\PlgSystemNotificationaryCore::buildExampleUser($user, $placeHoldersBodyInput);

		// Free some memory
		unset($user);

		$placeHoldersBodyInput[] = '</pre></small>';
		break;
	}
}
