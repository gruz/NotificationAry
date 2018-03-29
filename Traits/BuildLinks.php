<?php
/**
 * BuildLinks
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
trait BuildLinks
{
		/**
	 * Builds content item link
	 *
	 * It's important that site/view is called before site/edit, as Itemid is stored after site/view run
	 *
	 * @param   string  $zone  Site scope: admin/site
	 * @param   string  $task  Task: view/edit
	 * @param   string  $lang  Language code
	 *
	 * @return   string  SEF(if possible) link
	 */
	protected function _buildLink($zone = 'site', $task = 'edit', $lang = null)
	{
		if ($lang == null && $this->langShortCode != null )
		{
			$lang = '&lang=' . $this->langShortCode;
		}

		// Do not run every time, only once
		if (isset($this->link[$zone][$task][$lang]))
		{
			return $this->link[$zone][$task][$lang];
		}

		$link = '';
		$curr_root = parse_url(JURI::root());
		$live_site_host = $curr_root['scheme'] . '://' . $curr_root['host'] . '/';
		$live_site = JURI::root();

		switch ($task)
		{
			case 'edit':
				$link = 'index.php?option=' . $this->context['option'] . '&task=' . $this->context['task'] . '.edit';

				if ($zone == 'site')
				{
					switch ($this->context['option'])
					{
						/*
						case 'com_k2':
							* // $link = false;
							$link = 'index.php?option='.$this->context['option']
									.'&view='.$this->context['task'].'&task=edit&cid='.$this->contentItem->id.'&tmpl=component';
							* // index.php?option=com_k2&view=item&task=edit&cid=1&tmpl=component&Itemid=510
							break;
						case 'com_newsfeed':
						case 'com_banners':
						*/
						case 'com_newsfeeds':
						case 'com_tags':
						case 'com_users':
							$link = false;
							break;
						/*
						case 'com_jdownloads':
							$link = $link . '&'.'a_id='.$this->contentItem->id;
							break;
						*/
						default :
							if ($this->rule->extension_info['Frontend edit link'] === false)
							{
								$link = false;
							}
							elseif (empty($this->rule->extension_info['Frontend edit link']))
							{
								// Try to use some default link form
								$link = $link . '&' . $this->context['task'][0] . '_id=' . $this->contentItem->id;
							}
							else
							{
								$link = str_replace('##ID##', $this->contentItem->id, $this->rule->extension_info['Frontend edit link']);

								/* For ZOO frontend edit link
									* Check /administrator/components/com_zoo/Helpers/route.php line 394
									* /administrator/components/com_zoo/Helpers/submission.php line 62
									* /administrator/components/com_zoo/framework/Helpers/system.php line 56
									*/
								if (strpos($this->rule->extension_info['Frontend edit link'], '##SUBMISSION_HASH##') !== false)
								{
									$submission_id = null;
									$type_id = 'article';
									$item_id = $this->contentItem->id;
									$edit = 1;
									$seed = $submission_id.$type_id.$item_id.$edit;

									// index.php?option=com_zoo&view=submission&layout=submission&submission_id=&type_id=article&item_id=##ID##&redirect=itemedit
									$seed = JApplication::getHash($seed);
									$link = str_replace('##SUBMISSION_HASH##', $seed, $link);
								}
							}

							break;
					}

					// Get previously stored Itemid and attach to the current FE link if needed
					if ($link)
					{
						$checkIfItemIdExists = self::getVarFromQuery($link, 'Itemid');

						if (empty($checkIfItemIdExists) && !empty($this->link_itemid[$zone]['view'][$lang]))
						{
							$link .= '&Itemid=' . $this->link_itemid[$zone]['view'][$lang];
						}

						$link = $this->_makeSEF($link);
					}
				}
				elseif ($zone == 'admin')
				{
					switch ($this->context['option'])
					{
						/* /administrator/index.php?option=com_k2&view=item&cid=1
						case 'com_k2':
							$link = 'index.php?option='.$this->context['option'].'&view='.$this->context['task'].'&cid='.$this->contentItem->id;
							break;
						*/
						/* /administrator/index.php?option=com_jdownloads&task=download.edit&file_id=36
						case 'com_jdownloads':
							$link = $link . '&file_id='.$this->contentItem->id;
							$link = "administrator/index.php?option='.$this->context['option'].'&task=article.edit&id=".$this->contentItem->id;
							break;
						*/
						default :
							// ~ if (isset($this->rule->extension_info)) {
							if ($this->rule->extension_info['Backend edit link'] === false )
							{
								$link = false;
							}
							elseif (empty($this->rule->extension_info['Backend edit link']) )
							{
								$link = $link . '&id=' . $this->contentItem->id;
							}
							else
							{
								$link = str_replace('##ID##', $this->contentItem->id, $this->rule->extension_info['Backend edit link']);
							}
							break;
					}

					$link = 'administrator/' . $link;
					$link = $live_site . $link;
				}
				break;
			case 'view':
				$app = \JFactory::getApplication();
				$break = false;

				$catid = (is_array($this->contentItem->catid)) ? $this->contentItem->catid[0] : $this->contentItem->catid;

				switch ($this->context['option'])
				{
					case 'com_users':
					case 'com_banners':
						$link = false;
						$break = true;
						break;
					default :
						$extension_info = $this->rule->extension_info;

						if ($extension_info['View link'] === false)
						{
							$link = false;
						}
						elseif (!empty($extension_info['View link']))
						{
							$link = str_replace('##ID##', $this->contentItem->id, $extension_info['View link']);

							// Need to find Itemid at backend
							if ($app->isAdmin() && strpos($link, 'Itemid=') === false)
							{
								if (!empty($extension_info['RouterClass::RouterMethod']))
								{
									$parts = explode('::', $extension_info['RouterClass::RouterMethod']);
									JLoader::register($parts[0], JPATH_ROOT . '/components/' . $this->context['option'] . '/helpers/route.php');
									$link = $parts[0]::{$parts[1]}($this->contentItem->id, $catid);
								}
								else
								{
									$db = \JFactory::getDBO();
									$query = $db->getQuery(true);
									$query->select('id')->from('#__menu')->where($db->quoteName('link') . " = " . $db->Quote($link));
									$query->where($db->quoteName('menutype') . " <> " . $db->Quote('main'));
									$query->where($db->quoteName('published') . " = " . $db->Quote('1'));
									$db->setQuery((string) $query);
									$Itemid = $db->loadResult();

									if (!empty($Itemid))
									{
										$link	.= '&Itemid=' . $Itemid;
									}
									else
									{
										// Do nothing
									}
								}
							}
							else
							{
								$link = str_replace('##ID##', $this->contentItem->id, $this->rule->extension_info['View link']);
							}
						}
						else
						{
							$break = false;

							switch ($this->context['option'])
							{
								case 'com_users':
								case 'com_banners':
									$link = false;
									$break = true;
									break;
								default :
									$this->contentItem->slug = $this->contentItem->id . ':' . $this->contentItem->alias;
									break;
							}

							if ($break)
							{
								break;
							}

							// $this->contentItem->slug = $this->contentItem->id.':'.$this->contentItem->alias;
							$routerClass = $this->context['extension'] . 'HelperRoute';
							$routerMethod = 'get' . $this->context['task'] . 'Route';

							JLoader::register($routerClass, JPATH_ROOT . '/components/' . $this->context['option'] . '/helpers/route.php');

							if (class_exists($routerClass) && method_exists($routerClass, $routerMethod))
							{
								if ( $this->real_context == "com_categories.category")
								{
									$catid = $this->contentItem->id;
								}
								else
								{
									$catid = (is_array($this->contentItem->catid)) ? $this->contentItem->catid[0] : $this->contentItem->catid;
								}

								switch ($this->context['full'])
								{
									/*
									case "com_dpcalendar.category":
										$link	= $routerClass::$routerMethod($this->contentItem->id);
										break;
									case "com_k2.item":
										$link	= $routerClass::$routerMethod($this->contentItem->id);
										$link2 = K2HelperRoute::getItemRoute($item->id.':'.urlencode($item->alias), $item->catid.':'.urlencode($item->category->alias));
										break;
									*/
									default :
										$link	= $routerClass::$routerMethod($this->contentItem->id, $catid, $this->contentItem->language);
										break;
								}
								// $link	= ContentHelperRoute::getArticleRoute($this->contentItem->slug, $this->contentItem->catid, $this->contentItem->language);
							}
							else
							{
								switch ($this->context['extension'])
								{
									/*
									case 'k2':
										if ( $app->isAdmin() ) {
											$db = \JFactory::getDBO();
											$query = $db->getQuery(true);
											$query->select('id')->from('#__menu')->where($db->quoteName('link')." = ".$db->Quote($link));
											$db->setQuery((string)$query);
											$Itemid = $db->loadResult();
											if (!empty($Itemid)) {
												$link	.= '&Itemid=' . $Itemid;
											}
											$link2 = ContentHelperRoute::getArticleRoute($this->contentItem->slug, $this->contentItem->catid, $this->contentItem->language);
										}
										break;
									*/
									default :
										$link = 'index.php?option=' . $this->context['option'];
										$link .= '&view=' . $this->context['task'] . '&id=' . $this->contentItem->id;
										break;
								}
							}

							$link = str_replace($this->contentItem->slug, $this->contentItem->id, $link);
							$link = str_replace($live_site, '', $link);
						}
						break;
				}

				if ($break)
				{
					break;
				}

				if ($link)
				{
					$this->link_itemid[$zone][$task][$lang] = self::getVarFromQuery($link, 'Itemid');

					if(self::getVarFromQuery($link, 'option') === 'com_jevents' && self::getVarFromQuery($link, 'task') === 'icalevent.detail')
					{
						$jevents_params = JComponentHelper::getParams('com_jevents');
						$jevents_itemid = $jevents_params->get('permatarget', 0);
						$link .= '&Itemid=' . $jevents_itemid;
						$link = JURI::ROOT() . JRoute::_($link);
					}
					else {
						$link = $this->_makeSEF($link);
					}
				}

				break;
		}

		if ($link)
		{
			$link = JPath::clean($link);
			$link = str_replace(':/', '://', $link);
			$link = str_replace('&amp;', '&', $link);
		}

		$this->link[$zone][$task][$lang] = $link;

		return $this->link[$zone][$task][$lang];
	}

	/**
	 * Tries to get properly sef link, especially needed when working in backend
	 *
	 * @param   string  $link  Non-sef Joomla link
	 *
	 * @return   string  SEF joomla link if is possible or needed to be SEF
	 */
	private function _makeSEF($link)
	{
		$conf = \JFactory::getConfig();

		if ($conf->get('sef') != 1)
		{
			$live_site_host = JURI::root();

			return $live_site_host . $link;
		}

		$curr_root = parse_url(JURI::root());

		// Add non-standard port if needed
		$port = isset($curr_root['port']) ? ':' . $curr_root['port'] : '';

		$live_site_host = $curr_root['scheme'] . '://' . $curr_root['host'] . $port . '/';

		$app = \JFactory::getApplication();

		if ($app->isAdmin())
		{
			// After struggling much with getting proper SEF link from Backend, I had to use this remote call
			$user = \JFactory::getUser();

			// I create a fake session as a flag to show onAjaxNotificationAryGetFEURL that it's a call from myself
			$session = \JFactory::getSession();
			$sessionId = $session->getId();

			// Get current user password hash to later restore it
			$db		= \JFactory::getDBO();
			$query	= $db->getQuery(true);
			$query->select(array('password'));
			$query->from('#__users');
			$query->where('id=' . $db->Quote($user->id));
			$db->setQuery($query);
			$origPass = $db->loadResult();

			// Build remote link
			$url_ajax_plugin = JRoute::_(
						JURI::ROOT()
						// It's a must
						. '?option=com_ajax&format=raw'
						. '&group=' . $this->plg_type
						. '&plugin=notificationAryGetFEURL'
						// . '&' . JSession::getFormToken() . '=1'
						. '&userid=' . $user->id
						. '&sid=' . $sessionId
						// Pass your data if needed
						. '&serialize=' . base64_encode(serialize($link))
				);

			$res_link = $link;

			if (function_exists('curl_version') && false)
			{
				$curlSession = curl_init();
				curl_setopt($curlSession, CURLOPT_URL, $url_ajax_plugin);
				curl_setopt($curlSession, CURLOPT_BINARYTRANSFER, true);
				curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
				$data = curl_exec($curlSession);
				curl_close($curlSession);
				$res_link = $data;
			}
			elseif (ini_get('allow_url_fopen'))
			{
				$res_link = file_get_contents($url_ajax_plugin);
			}

			if (trim($res_link) == '')
			{
				$res_link = $link;
			}

			$query	= $db->getQuery(true);

			$query->update('#__users');
			$query->set('password = ' . $db->Quote($origPass));
			$query->where('id=' . $db->Quote($user->id));
			$db->setquery($query);
			$db->execute();

			if (strpos($res_link, 'http:/') === false && strpos($res_link, 'https:/') === false)
			{
				$link = $live_site_host . '/' . $res_link;
			}
			else
			{
				$link = $res_link;
			}

			$link = JPath::clean($link);
		}
		else
		{
			$app = JApplication::getInstance('site');
			$router = $app->getRouter();
			$url = $router->build($link);
			$url->setHost($live_site_host);
			$url = $url->toString();
			$url = JPath::clean($url);
			$link = $url;

			if ($this->isNew)
			{
				$jinput = \JFactory::getApplication()->input;
				$submit_url = JRoute::_('index.php?Itemid=' . $jinput->get('Itemid', null));
				$submit_url = JPath::clean($live_site_host . $submit_url);
				$link = str_replace($submit_url, $live_site_host, $link);
			}
		}

		return $link;
	}

}
