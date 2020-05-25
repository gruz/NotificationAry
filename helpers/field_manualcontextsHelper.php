<?php
/**
 * This is a helper file used to output a select box with predefined component templates for a custom component
 *
 * @package		NotificationAry
 * @author Gruz <arygroup@gmail.com>
 * @copyright	Copyleft - All rights reversed
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

if (!isset($predefined_context_templates)) { include 'predefined_contexts.php';}

$output = array();
$output[] = '<option data-template="" >'.\JText::_('JNONE').'</option>';

foreach ($predefined_context_templates as $context=>$array) {
	$tmp = array();
	$tmp[] = $context;

	$first = true;
	foreach ($rows as $template_item) {
		if ($first) {$first = false; continue; }
		if (!isset($array[$template_item])) {
			$item = '';
		}
		elseif ($array[$template_item] === false) {
			$item = 'false';
		}
		else {
			$item = $array[$template_item];
		}
		$tmp[] = $item;
	}
	$component = explode('.',$context);
	$component = explode('_',$component[0]);

	if ($component[0] == 'com') {
		$option = $component[1];
	} else {
		$option = $component[0];
	}

	$option_style='';
	$option_text = $array['Title'];

	// Check if component is installed
	//~ if (!JComponentHelper::getComponent($component[0], true)->enabled){
	$file = JPATH_ADMINISTRATOR . '/components/com_'.$option.'/'.$option.'.php';
	if (file_exists($file) && \JComponentHelper::isEnabled('com_'.$option, true)) {
	} else {
		$option_style=' style="color:gray" ';
		$option_text = \JText::sprintf('LIB_GJFIELDS_NOT_INSTALLED',$array['Title'].' :: ');
	}

	$output[] = '<option data-template="'.implode(PHP_EOL,$tmp).'" '.$option_style.'>'.$option_text.'</option>';
}
$output_templates = '<div class="select_templates">'.\JText::_('PLG_SYSTEM_NOTIFICATIONARY_PREDEFINED_MANUAL_CONTEXT')." <select >" .implode('',$output).'</select> </div>';

$output_label = "<br/>
<textarea class='helpertextarea' readonly >".
implode(PHP_EOL,$rows)
."</textarea>";


$height = (count($rows)+1)*18;

$app = \JFactory::getApplication();

$app->get('css added ##mygruz20160408015751',false);
if (!$app->get('css added ##mygruz20160408015751',false)) {
	$css = '.textareainbunch {
		border-radius: 0;
		float: left;
		/*min-width: 80%;*/
		overflow-x: scroll;
		overflow-y: hidden;
		padding: 0;
		resize: horizontal;
		white-space: pre;
		/* width: auto !important; */
		height:'.$height.'px;
		margin:0 0 0 -5px !important;
		font-family:monospace;
		font-size:11px;
		line-height:18px;
	}
	.helpertextarea {
		background: #efefef none repeat scroll 0 0;
		border: 1px solid #efefef;
		box-shadow: none;
		height: '.$height.'px;
		margin: 5px 0 0;
		overflow: hidden;
		padding: 0;
		text-align: right;
		resize:none;
		font-family:monospace;
		font-size:11px;
		white-space: pre;
		line-height:18px;
	}
	.showhidehelperinfo {
		clear:both;
		display:block;
		cursor:pointer;
	}
	.content_types_template > .control-group > .controls
	{
		margin-left:221px;
	}
	.content_types_template .control-group .control-label {
		width:auto;
	}
	';
	$app    = \JFactory::getApplication();
	if ($app->getTemplate() == 'hathor') {
		$css .= '
		/* Special for hathor */
		a.modal img {
			width:inherit;
		}
		.custom_context_help_info ul,
		.custom_context_help_info ol,
		.custom_context_help_info li {
			clear:both;
			list-style-type:inherit;
			list-style-position:inherit;
			list-style-image:inherit;
			padding: inherit;

		}
		';
	}
	$js = "
		jQuery( document ).ready(function( $ ) {
			var helpertemplates = $('div.select_templates select');
			helpertemplates.change(function() {
				var data = $('option:selected', this).data('template');
				var target = $(this).closest('.control-group,li').find('textarea').not('.helpertextarea');
				target.val(data);

			});
			var target = $('textarea.textareainbunch');
			target.bind('paste', function(e) {
				 var elem = $(this);

				 setTimeout(function() {
					// gets the copied text after a specified time (100 milliseconds)
					var text = elem.val().split(\"\\n\");
					for (i = 0; i < text.length; i++) {
						var temp_text = text[i].split('index.php?');
						if (temp_text.length == 2) {
							text[i] = 'index.php?'+temp_text[1];
						}
					}
					text = text.join(\"\\n\");
					elem.val(text);

				 }, 100);
			});

			var helpertemplates = $('.showhidehelperinfo');
			helpertemplates.click(function() {
				jQuery(this).parent().find('div.custom_context_help_info').toggleClass('hide');
			});

		});

	";
	$document = \JFactory::getDocument();
	$document->addStyleDeclaration($css);
	$document->addScriptDeclaration($js);
	$app->set('css added ##mygruz20160408015751',true);

}
