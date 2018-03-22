<?php
/**
 * This is a helper file used to output email template in the plugin settings.
 * Tries to load an content item object based on the selected source content type
 *
 * @package		NotificationAry
 * @author Gruz <arygroup@gmail.com>
 * @copyright	Copyleft - All rights reversed
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
// No direct access
defined('_JEXEC') or die('Restricted access');

	$extensionTable = JTable::getInstance('extension');
	// Find plugin id, in my case it was plg_ajax_ajaxhelpary
	$pluginId = $extensionTable->find( array('element' => 'notificationary', 'type' => 'plugin') );
	$extensionTable->load($pluginId);

	// Get joomla default object
	$params = new JRegistry;
	$params->loadString($extensionTable->params, 'JSON'); // Load my plugin params.
	$group_params = $params->get('{notificationgroup');
	unset($group_params->{'{notificationgroup'});
	//dump ($group_params,'$group_params before');
	//~ dump($group_params->author_mailbody,'$group_params->author_mailbody');
	//~ $group_params->{'{notificationgroup'} = array();
	//$number_of_rule
	//~ $meet_end_number_of_times = 0;
	//~ $array = $group_params->author_mailbody ;
	//~ dump ($number_of_rule,'$number_of_rule');
	//~ foreach ($array as $krule=>$rule) {
		//~ if ($meet_end_number_of_times != $number_of_rule) {
			//~ unset($array[$krule]);
		//~ }
		//~ if ($rule[0] == 'variablefield::{notificationgroup') {
			//~ $meet_end_number_of_times++;
		//~ }
	//~ }
	//~ dump ($array,'$array');
	//~ return;
	$app = JFactory::getApplication();
	$number_of_rule = $app->get ('number_of_rule',0,$pluginId);
	$app->set ('number_of_rule',$number_of_rule+1,$pluginId);
	$number_of_rule_index = $app->get ('number_of_rule_index',0,$pluginId);
	$app->set ('number_of_rule_index',$number_of_rule_index+2,$pluginId);
//return;
	foreach ($group_params as $key=>$array) {
//echo $key.var_dump($array);
		$meet_end_number_of_times = 0;
		foreach ($array as $krule=>$rule) {
			if (!is_array($rule)) {
				if ($key == '{notificationgroup') {
					// do nothing
				}
				else if ($krule==$number_of_rule_index || $krule == $number_of_rule_index+1) {
					// is ok, we need this
				} else {
//~ echo '<pre> Line: '.__LINE__.' '.PHP_EOL;
//~ print_r($key);
//~ echo PHP_EOL.'</pre>'.PHP_EOL;
//~ echo '<pre> Line: '.__LINE__.' '.PHP_EOL;
//~ print_r($array);
//~ echo PHP_EOL.'</pre>'.PHP_EOL;
					unset($array[$krule]);
				}

			} else {
				$remove_other = false;
					if ($meet_end_number_of_times != $number_of_rule) {
						unset($array[$krule]);
					}
					if ($rule[0] == 'variablefield::{notificationgroup') {
						$meet_end_number_of_times++;
					}
			}
		}
		$group_params->$key = $array;

	}

	$output = "<textarea class=\"pull-left span6 jsonsource\" style='width:55%;height:6rem;cursor:text' class=\"readonly\" readonly='true' >".base64_encode(json_encode($group_params)).'</textarea>
	'.'<i class="icon jsoncopy icon-copy" style="cursor:pointer"></i>';

	$app->get('js added ##mygruz20160506172158',false);
	if (!$app->get('js added ##mygruz20160506172158',false)) {

		$app    = JFactory::getApplication();
		$js = "
			jQuery( document ).ready(function( $ ) {
				$('textarea[name=\"jform[params][{notificationgroup][use_json_template][]\"]').val('');
				var clipboard =  new Clipboard('.jsoncopy', {
					 text: function(trigger) {
						  return $(trigger).parent().find('.jsonsource').val();
					 }
				});
				clipboard.on('success', function(e) {
					$(e.trigger).delay(100).fadeIn(100).fadeOut(100).fadeIn(100).fadeOut(100).fadeIn(100);
					//$(e.trigger).fadeTo(1000, 0.5, function() { $(e.trigger).fadeTo(800, 1); });
					 //~ console.info('Action:', e.action);
					 //~ console.info('Text:', e.text);
					 //~ console.info('Trigger:', e.trigger);

					 //e.clearSelection();
				});

				clipboard.on('error', function(e) {
					 //~ console.error('Action:', e.action);
					 //~ console.error('Trigger:', e.trigger);
				});
			});
		";
		$document = JFactory::getDocument();
		$document->addScriptDeclaration($js);

		JPluginGJFields::addJSorCSS('clipboard.js', 'plg_system_notificationary', false);

		$app->set('js added ##mygruz20160506172158',true);

	}

	return;
?>
