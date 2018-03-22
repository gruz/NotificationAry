<?php
// No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

// Available placeholder for mails
	$ph_subject = array(
		'%SITENAME%',
		'%SITELINK%',
		'%ACTION%', // Published
		'%STATUS%',
		'%TITLE%',
		'%MODIFIER%',
		'%CONTENT_TYPE%'
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
		'%INTRO TEXT%',
		'%FULL TEXT%',
		'%DIFF Text/Unified%',
		'%DIFF Text/Context%',
		'</b>'.\JText::_('PLG_SYSTEM_NOTIFICATIONARY_FIELD_MESSAGE_HTML_BODY_ONLY').'<b>', // This line is used at the plugin settings form only, not in mailbody
		'%DIFF Html/SideBySide%',
		'%DIFF Html/Inline%'
	);


	$place_holders_subject_label = array();
	foreach ($ph_subject as $k=>$v) {
		$place_holders_subject_label[$k] = '<br/><b>'.$v.'</b>';
	}
	$place_holders_body_label = array();
	foreach ($ph_body as $k=>$v) {
		$place_holders_body_label[$k] = '<br/><b>'.$v.'</b>';
	}
	$place_holders_body_label = array_merge($place_holders_subject_label,$place_holders_body_label);

	$default_body =
JText::_('JSITE').':  %SITELINK% :: %SITENAME%
'.JText::_('JGLOBAL_TITLE').': %TITLE%
'.JText::_('PLG_SYSTEM_NOTIFICATIONARY_CONTENT_TYPE').': %CONTENT_TYPE%
'.JText::_('PLG_SYSTEM_NOTIFICATIONARY_ACTION').': %ACTION%
'.JText::_('JCATEGORY').': %CATEGORY PATH%
'.JText::_('PLG_SYSTEM_NOTIFICATIONARY_VIEW_LINK').': %FRONT VIEW LINK%

'.JText::_('JGLOBAL_CREATED_DATE').': %CREATED DATE%
'.JText::_('JGLOBAL_FIELD_MODIFIED_LABEL').': %MODIFIED DATE%

'.JText::_('JGLOBAL_INTRO_TEXT').':
----
%INTRO TEXT%
----
';

	if (get_class($this) == 'JFormFieldTextareafixed') {
		while (true) {
			$context_or_contenttype = $this->element['context_or_contenttype'];
			if (empty ($context_or_contenttype)) { break; }
//~ dump ((string)$this->name);
	//~ dump ($context_or_contenttype,'$context_or_contenttype');

			$extension = $this->element[$context_or_contenttype] ? (string) $this->element[$context_or_contenttype] : (string) $this->element['scope'];
	//~ dump ($extension,'$extension');
			switch ($context_or_contenttype) {
				case 'context':
					break;
				case 'content_type':
				default :
					$category = JTable::getInstance( 'contenttype' );
					$category->load( $extension );
					$extension = $category->type_alias;
					break;
			}

			JPluginHelper::importPlugin('notificationary');
			$app = JFactory::getApplication();

			$scriptAdded = $app->get('##mygruz20160216061544',false);
			if (!$scriptAdded) {
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
				$app->set('##mygruz20160216061544',true);
				$scriptAdded = true;

			}

			$contentObject = $app->triggerEvent('_getContentItemTable', array($extension,false,true));

			// If a rule is disabled, then an empty result is returned. Not sence to handle in this case
			if (!empty($contentObject) && !empty($contentObject[0])) {
				$contentObject = $contentObject[0];
			} else { break; }

			$place_holders_body_label[] = '<br/><button type="button" class="btn btn-warning btn-small object_values" ><i class="icon-plus"></i></button><br/>
			<small class="object_values">
				<pre style="clear:both;float:left;"><b>----'.get_class($contentObject).'----</b><br/>';
			foreach ($contentObject as $key=>$value) {
				$place_holders_body_label[] = '##Content#'.$key.'##<br/>';
			}
			$user =  JFactory::getUser();
			$place_holders_body_label[] = '
			</pre>';
			$place_holders_body_label[] = '
			<pre style="float:left;"><b>----'.get_class($user).'----</b><br/>';
			foreach ($user as $key=>$value) {
				$place_holders_body_label[] = '##User#'.$key.'##<br/>';
			}
			$place_holders_body_label[] = '
			</pre>
			</small>';
			break;
		}
	}

