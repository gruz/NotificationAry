<?php
/**
 * NotificationaryCore helper class
 *
 * @package    Notificationary

 * @author     Gruz <arygroup@gmail.com>
 * @copyright  0000 Copyleft (є) 2017 - All rights reversed
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace NotificationAry\HelperClasses\Traits;

use NotificationAry\HelperClasses\NotificationAryHelper;
// use NotificationAry\HelperClasses\FakeMailerClass;

// No direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use JText,
	JTable,
	JURI,
	JApplication,
	JLoader,
	JPath,
	JCategories,
	JRoute
;

/**
 * Plugin code
 *
 * @author  Gruz <arygroup@gmail.com>
 * @since   0.0.1
 */
trait BuildMail
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
		if ($lang == null && $this->langShortCode != null) {
			$lang = '&lang=' . $this->langShortCode;
		}

		// Do not run every time, only once
		if (isset($this->link[$zone][$task][$lang])) {
			return $this->link[$zone][$task][$lang];
		}

		$link = '';
		$curr_root = parse_url(JURI::root());
		$live_site_host = $curr_root['scheme'] . '://' . $curr_root['host'] . '/';
		$live_site = JURI::root();

		switch ($task) {
			case 'edit':
				$link = 'index.php?option=' . $this->context['option'] . '&task=' . $this->context['task'] . '.edit';

				if ($zone == 'site') {
					switch ($this->context['option']) {
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
						default:
							if ($this->rule->extension_info['Frontend edit link'] === false) {
								$link = false;
							} elseif (empty($this->rule->extension_info['Frontend edit link'])) {
								// Try to use some default link form
								$link = $link . '&' . $this->context['task'][0] . '_id=' . $this->contentItem->id;
							} else {
								$link = str_replace('##ID##', $this->contentItem->id, $this->rule->extension_info['Frontend edit link']);

								/* For ZOO frontend edit link
									* Check /administrator/components/com_zoo/helpers/route.php line 394
									* /administrator/components/com_zoo/helpers/submission.php line 62
									* /administrator/components/com_zoo/framework/helpers/system.php line 56
									*/
								if (strpos($this->rule->extension_info['Frontend edit link'], '##SUBMISSION_HASH##') !== false) {
									$submission_id = null;
									$type_id = 'article';
									$item_id = $this->contentItem->id;
									$edit = 1;
									$seed = $submission_id . $type_id . $item_id . $edit;

									// index.php?option=com_zoo&view=submission&layout=submission&submission_id=&type_id=article&item_id=##ID##&redirect=itemedit
									$seed = JApplication::getHash($seed);
									$link = str_replace('##SUBMISSION_HASH##', $seed, $link);
								}
							}

							break;
					}

					// Get previously stored Itemid and attach to the current FE link if needed
					if ($link) {
						$checkIfItemIdExists = NotificationAryHelper::getVarFromQuery($link, 'Itemid');

						if (empty($checkIfItemIdExists) && !empty($this->link_itemid[$zone]['view'][$lang])) {
							$link .= '&Itemid=' . $this->link_itemid[$zone]['view'][$lang];
						}

						$link = $this->_makeSEF($link);
					}
				} elseif ($zone == 'admin') {
					switch ($this->context['option']) {
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
						default:
							// ~ if (isset($this->rule->extension_info)) {
							if ($this->rule->extension_info['Backend edit link'] === false) {
								$link = false;
							} elseif (empty($this->rule->extension_info['Backend edit link'])) {
								$link = $link . '&id=' . $this->contentItem->id;
							} else {
								$link = str_replace('##ID##', $this->contentItem->id, $this->rule->extension_info['Backend edit link']);
							}
							break;
					}

					$link = 'administrator/' . $link;
					$link = $live_site . $link;
				}
				break;
			case 'view':
				$app = Factory::getApplication();
				$break = false;

				$catid = (is_array($this->contentItem->catid)) ? $this->contentItem->catid[0] : $this->contentItem->catid;

				switch ($this->context['option']) {
					case 'com_users':
					case 'com_banners':
						$link = false;
						$break = true;
						break;
					default:
						$extension_info = $this->rule->extension_info;

						if ($extension_info['View link'] === false) {
							$link = false;
						} elseif (!empty($extension_info['View link'])) {
							$link = str_replace('##ID##', $this->contentItem->id, $extension_info['View link']);

							// Need to find Itemid at backend
							if ($app->isAdmin() && strpos($link, 'Itemid=') === false) {
								if (!empty($extension_info['RouterClass::RouterMethod'])) {
									$parts = explode('::', $extension_info['RouterClass::RouterMethod']);
									JLoader::register($parts[0], JPATH_ROOT . '/components/' . $this->context['option'] . '/helpers/route.php');
									$link = $parts[0]::{$parts[1]}($this->contentItem->id, $catid);
								} else {
									$db = Factory::getDBO();
									$query = $db->getQuery(true);
									$query->select('id')->from('#__menu')->where($db->quoteName('link') . " = " . $db->Quote($link));
									$query->where($db->quoteName('menutype') . " <> " . $db->Quote('main'));
									$query->where($db->quoteName('published') . " = " . $db->Quote('1'));
									$db->setQuery((string) $query);
									$Itemid = $db->loadResult();

									if (!empty($Itemid)) {
										$link	.= '&Itemid=' . $Itemid;
									} else {
										// Do nothing
									}
								}
							} else {
								$link = str_replace('##ID##', $this->contentItem->id, $this->rule->extension_info['View link']);
							}
						} else {
							$break = false;

							switch ($this->context['option']) {
								case 'com_users':
								case 'com_banners':
									$link = false;
									$break = true;
									break;
								default:
									$this->contentItem->slug = $this->contentItem->id . ':' . $this->contentItem->alias;
									break;
							}

							if ($break) {
								break;
							}

							// $this->contentItem->slug = $this->contentItem->id.':'.$this->contentItem->alias;
							$routerClass = $this->context['extension'] . 'HelperRoute';
							$routerMethod = 'get' . $this->context['task'] . 'Route';

							JLoader::register($routerClass, JPATH_ROOT . '/components/' . $this->context['option'] . '/helpers/route.php');

							if (class_exists($routerClass) && method_exists($routerClass, $routerMethod)) {
								if ($this->real_context == "com_categories.category") {
									$catid = $this->contentItem->id;
								} else {
									$catid = (is_array($this->contentItem->catid)) ? $this->contentItem->catid[0] : $this->contentItem->catid;
								}

								switch ($this->context['full']) {
										/*
									case "com_dpcalendar.category":
										$link	= $routerClass::$routerMethod($this->contentItem->id);
										break;
									case "com_k2.item":
										$link	= $routerClass::$routerMethod($this->contentItem->id);
										$link2 = K2HelperRoute::getItemRoute($item->id.':'.urlencode($item->alias), $item->catid.':'.urlencode($item->category->alias));
										break;
									*/
									default:
										$link	= $routerClass::$routerMethod($this->contentItem->id, $catid, $this->contentItem->language);
										break;
								}
								// $link	= ContentHelperRoute::getArticleRoute($this->contentItem->slug, $this->contentItem->catid, $this->contentItem->language);
							} else {
								switch ($this->context['extension']) {
										/*
									case 'k2':
										if ( $app->isAdmin() ) {
											$db = Factory::getDBO();
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
									default:
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

				if ($break) {
					break;
				}

				if ($link) {
					$this->link_itemid[$zone][$task][$lang] = NotificationAryHelper::getVarFromQuery($link, 'Itemid');
					$link = $this->_makeSEF($link);
				}
				break;
		}

		if ($link) {
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
		$conf = Factory::getConfig();

		if ($conf->get('sef') != 1) {
			$live_site_host = JURI::root();

			return $live_site_host . $link;
		}

		$curr_root = parse_url(JURI::root());

		// Add non-standard port if needed
		$port = isset($curr_root['port']) ? ':' . $curr_root['port'] : '';

		$live_site_host = $curr_root['scheme'] . '://' . $curr_root['host'] . $port . '/';

		$app = Factory::getApplication();

		if ($app->isAdmin()) {
			// After struggling much with getting proper SEF link from Backend, I had to use this remote call
			$user = Factory::getUser();

			// I create a fake session as a flag to show onAjaxNotificationAryGetFEURL that it's a call from myself
			$session = Factory::getSession();
			$sessionId = $session->getId();

			// Get current user password hash to later restore it
			$db		= Factory::getDBO();
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

			if (function_exists('curl_version') && false) {
				$curlSession = curl_init();
				curl_setopt($curlSession, CURLOPT_URL, $url_ajax_plugin);
				curl_setopt($curlSession, CURLOPT_BINARYTRANSFER, true);
				curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
				$data = curl_exec($curlSession);
				curl_close($curlSession);
				$res_link = $data;
			} elseif (ini_get('allow_url_fopen')) {
				$res_link = file_get_contents($url_ajax_plugin);
			}

			if (trim($res_link) == '') {
				$res_link = $link;
			}

			$query	= $db->getQuery(true);

			$query->update('#__users');
			$query->set('password = ' . $db->Quote($origPass));
			$query->where('id=' . $db->Quote($user->id));
			$db->setquery($query);
			$db->execute();

			if (strpos($res_link, 'http:/') === false && strpos($res_link, 'https:/') === false) {
				$link = $live_site_host . '/' . $res_link;
			} else {
				$link = $res_link;
			}

			$link = JPath::clean($link);
		} else {
			$app = JApplication::getInstance('site');
			$router = $app->getRouter();
			$url = $router->build($link);
			$url->setHost($live_site_host);
			$url = $url->toString();
			$url = JPath::clean($url);
			$link = $url;

			if ($this->isNew) {
				$jinput = Factory::getApplication()->input;
				$submit_url = JRoute::_('index.php?Itemid=' . $jinput->get('Itemid', null));
				$submit_url = JPath::clean($live_site_host . $submit_url);
				$link = str_replace($submit_url, $live_site_host, $link);
			}
		}

		return $link;
	}

	/**
	 * Builds mail body and subject
	 *
	 * @param   JUserObject  $user  Joomla use object
	 *
	 * @return   mixed  False or array with mail parts (subject and body)
	 */
	protected function _buildMail($user)
	{
		// Need this for authors and modifiers as they are not checked anywhere else
		if ($user->block == 1) {
			return false;
		}

		if ($user->id == 0) {
			// If it's an added directly email, then make it clear to later IF-ELSE statements
			$user->id = -1;
		}

		$userGroupToCache = $user->groups;
		sort($userGroupToCache);
		$userGroupToCache = implode(',', $userGroupToCache);
		$hash = $this->contentItem->id . '|' . $userGroupToCache;

		if ($user->id == $this->author->id || $user->id == $this->modifier->id) {
			// Do not cache for author or modifier
		} else {
			if (!$this->rule->personalize) {
				if (isset($this->rule->cachedMailBuilt[$hash])) {
					$mail = $this->rule->cachedMailBuilt[$hash];
					$mail['email'] = $user->email;

					return $mail;
				}
			}
		}

		static $user_language_loaded = false;
		$app = Factory::getApplication();

		if ($app->isAdmin()) {
			$lang_code = $user->getParam('admin_language');
		} else {
			$lang_code = $user->getParam('language');
		}

		$language = Factory::getLanguage();

		if (!empty($lang_code) && $lang_code != $this->default_lang) {
			$language->load($this->plg_base_name, JPATH_ADMINISTRATOR, $lang_code, true);
			$language->load($this->plg_full_name, JPATH_ADMINISTRATOR, $lang_code, true);
			$user_language_loaded = true;
		} elseif ($user_language_loaded) {
			$language->load($this->plg_base_name, JPATH_ADMINISTRATOR, 'en-GB', true);
			$language->load($this->plg_full_name, JPATH_ADMINISTRATOR, 'en-GB', true);

			if ($this->default_lang != 'en-GB') {
				$language->load($this->plg_base_name, JPATH_ADMINISTRATOR, $this->default_lang, true);
				$language->load($this->plg_full_name, JPATH_ADMINISTRATOR, $this->default_lang, true);
			}

			$user_language_loaded = false;
			$lang_code = $this->default_lang;
		} else {
			$user_language_loaded = false;
			$lang_code = $this->default_lang;
		}

		$canView = false;

		// User has back-end access
		$canEdit = $user->authorise('core.edit', $this->context['full'] . '.' . $this->contentItem->id);
		$canLoginBackend = $user->authorise('core.login.admin');

		// This workaround is needed becasue $user->getAuthorisedViewLevels() fails on $user->id == -1
		$setAgain = false;

		if ($user->id == -1) {
			$setAgain = true;
			$user->id = 0;
		}

		if (empty($this->contentItem->access) || in_array($this->contentItem->access, $user->getAuthorisedViewLevels())) {
			$canView = true;
		}

		if ($setAgain) {
			$user->id = -1;
		}


		$notifyonlyifcanview = $this->rule->ausers_notifyonlyifcanview;

		// Just in case. Such users should be already removed before
		if ($notifyonlyifcanview == 1 && !$canView) {
			return false;
		}

		$this->_loadMailPlaceholders();
		$place_holders_subject = $this->place_holders_subject;
		$place_holders_body = $this->place_holders_body;

		$place_holders_subject['%SITENAME%'] = $this->sitename;
		$place_holders_subject['%SITELINK%'] = JURI::root();

		if ($this->rule->personalize) {
			$place_holders_subject['%TO_NAME%'] = $user->name;
			$place_holders_subject['%TO_USERNAME%'] = $user->username;
			$place_holders_subject['%TO_EMAIL%'] = $user->email;
		}


		if ($this->isNew) {
			$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_NEW_CONTENT_ITEM');

			if ($user->id == $this->contentItem->created_by) {
				if ($this->contentItem->created_by != $this->contentItem->modified_by) {
					$place_holders_subject['%ACTION%'] = $this->modifier->username . ' '
						. JText::_('PLG_SYSTEM_NOTIFICATIONARY_HAS_ADDED_A_CONTENT_ITEM_WITH_YOU_SET_AS_AUTHOR');
				} else {
					$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_YOU_HAVE_JUST_ADDED_A_CONTENT_ITEM');
				}
			}
			// Current user is not the article's author, but the article's author is set to anoter user
			elseif ($user->id == $this->contentItem->modified_by) {
				$place_holders_subject['%ACTION%'] = JText::sprintf(
					'PLG_SYSTEM_NOTIFICATIONARY_YOU_HAVE_JUST_ADDED_A_CONTENT_ITEM_WITH_AUTHOR',
					$this->author->username
				);
			}
		}
		// Article not new
		else {
			if ($this->publish_state_change == 'publish') {
				// Current user have changed article state
				if ($user->id == $this->contentItem->modified_by) {
					$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_YOU_HAVE_JUST_PUBLISHED_A_CONTENT_ITEM');
				}
				// The article was not modifed by the user, but the user is the article's author
				elseif ($user->id == $this->contentItem->created_by && $user->id != $this->contentItem->modified_by) {
					$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_YOUR_CONTENT_ITEM_HAS_BEEN_PUBLISHED');
					$place_holders_body['%ACTION%'] = $this->modifier->username . ' ' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_HAS_PUBLISHED_YOUR_CONTENT_ITEM');
				}
				// User neither modifier nor author
				else {
					$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_A_CONTENT_ITEM_HAS_BEEN_PUBLISHED');
				}
			} elseif ($this->publish_state_change == 'unpublish') {
				// Current user have changed article state
				if ($user->id == $this->contentItem->modified_by) {
					$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_YOU_HAVE_JUST_UNPUBLISHED_A_CONTENT_ITEM');
				}
				// The article was not modifed by the user, but the user is the article's author
				elseif ($user->id == $this->contentItem->created_by && $user->id != $this->contentItem->modified_by) {
					$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_YOUR_CONTENT_ITEM_HAS_BEEN_UNPUBLISHED');
					$place_holders_body['%ACTION%'] = $this->modifier->username . ' ' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_HAS_UNPUBLISHED_YOUR_CONTENT_ITEM');
				}
				// User neither modifier nor author
				else {
					$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_A_CONTENT_ITEM_HAS_BEEN_UNPUBLISHED');
				}
			} else {
				if ($user->id == $this->contentItem->modified_by) {
					$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_YOU_HAVE_JUST_UPDATED_A_CONTENT_ITEM');
				} elseif ($user->id == $this->contentItem->created_by && $user->id != $this->contentItem->modified_by) {
					$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_YOUR_CONTENT_ITEM_HAS_BEEN_MODIFIED');
					$place_holders_body['%ACTION%'] = $this->modifier->username . ' ' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_HAS_MODIFIED_YOUR_CONTENT_ITEM');
				} else {
					$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_A_CONTENT_ITEM_HAS_BEEN_CHANGED');
				}
			}
		}

		switch ($this->contentItem->state) {
			case '1':
				$place_holders_subject['%STATUS%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_JPUBLISHED');
				break;
			case '0':
				$place_holders_subject['%STATUS%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_JUNPUBLISHED');
				break;
			case '2':
				$place_holders_subject['%STATUS%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_JARCHIVED');
				break;
			case '-2':
				$place_holders_subject['%STATUS%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_JTRASHED');
				break;
		}

		if ($this->rule->messagebodysource == 'hardcoded') {
			$place_holders_subject['%STATUS%'] = '<b>'
				. JText::_('PLG_SYSTEM_NOTIFICATIONARY_JSTATUS') . ':</b> ' . $place_holders_subject['%STATUS%'];
			$include = '';

			$isAuthor = false;
			$isModifier = false;

			if ($user->id == $this->contentItem->created_by) {
				$isAuthor = true;
				$include = 'author';
			} elseif ($user->id == $this->contentItem->modified_by) {
				$isModifier = true;
				$include = 'modifier';
			}

			$IncludeIntroText = $this->rule->{'ausers_' . $include . 'includeintrotext'};
			$IncludeFullText = $this->rule->{'ausers_' . $include . 'includefulltext'};
			$IncludeFrontendViewLink = $this->rule->{'ausers_' . $include . 'includefrontendviewlink'};
			$IncludeFrontendEditLink = $this->rule->{'ausers_' . $include . 'includefrontendeditlink'};
			$IncludeBackendEditLink = $this->rule->{'ausers_' . $include . 'includebackendeditlink'};
			/*
			$IncludeFrontendViewLink = $this->rule->ausers_includefrontendviewlink;
			$IncludeFrontendEditLink = $this->rule->ausers_includefrontendeditlink;
			$IncludeBackendEditLink = $this->rule->ausers_includebackendeditlink;
			*/

			if (!isset($this->contentItem->introtext)) {
				$IncludeIntroText = false;
			}

			if (!isset($this->contentItem->fulltext)) {
				$IncludeFullText = false;
			}

			$IncludeArticleTitle = $this->rule->ausers_includearticletitle;
			$IncludeCategoryTree = $this->rule->ausers_includecategorytree;
			$IncludeAuthorName = $this->rule->ausers_includeauthorname;
			$IncludeModifierName = $this->rule->ausers_includemodifiername;
			$IncludeCreatedDate = $this->rule->ausers_includecreateddate;
			$IncludeModifiedDate = $this->rule->ausers_includemodifieddate;
			$IncludeContenttype = $this->rule->ausers_includecontenttype;

			$place_holders_subject['%TITLE%']  = $this->contentItem->title;
			$place_holders_subject['%MODIFIER%']  = "<b>" . JText::_('PLG_SYSTEM_NOTIFICATIONARY_MODIFIER') . '</b>: ' . $this->modifier->username;

			// $place_holders_subject['%CONTENT_TYPE%'] = $this->rule->type_title;

			$this->rule->manage_subscription_link = trim($this->rule->manage_subscription_link);

			if (!empty($this->rule->manage_subscription_link)) {
				$link = str_replace(JURI::root(), '', $this->rule->manage_subscription_link);
				$link = JURI::root() . $link;

				if ($this->rule->emailformat == 'plaintext') {
					$place_holders_body['%MANAGE SUBSCRIPTION LINK%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_FIELD_MANAGE_SUBSCRIPTION')
						. ': ' . $link;
				} else {
					$place_holders_body['%MANAGE SUBSCRIPTION LINK%'] = '<a href="' . $link . '">'
						. JText::_('PLG_SYSTEM_NOTIFICATIONARY_FIELD_MANAGE_SUBSCRIPTION') . '</a>';
				}
			}

			// ---------------- body ---------------- //
			if ($IncludeArticleTitle) {
				$place_holders_body['%TITLE HARDCODED%']  = "<b>" . JText::_('PLG_SYSTEM_NOTIFICATIONARY_TITLE') . '</b>: ' . $this->contentItem->title;
			}

			if ($IncludeModifierName) {
				$place_holders_body['%MODIFIER HARDCODED%'] = $place_holders_subject['%MODIFIER%'];
			}

			if ($IncludeAuthorName) {
				$place_holders_body['%AUTHOR%']  = "<b>" . JText::_('PLG_SYSTEM_NOTIFICATIONARY_AUTHOR') . '</b>: ' . $this->author->username;
			}

			if ($IncludeCreatedDate && !empty($this->contentItem->created)) {
				$place_holders_body['%CREATED DATE%']  = "<b>" . JText::_('PLG_SYSTEM_NOTIFICATIONARY_CREATED') . '</b>: '
					. NotificationAryHelper::getCorrectDate($this->contentItem->created);
			}

			if ($IncludeModifiedDate) {
				if (is_null($this->contentItem->modified)) {
					$place_holders_body['%MODIFIED DATE%']  = "<b>" . JText::_('PLG_SYSTEM_NOTIFICATIONARY_MODIFIED') . '</b>: ' . JText::_('JNO');
				} else {
					$place_holders_body['%MODIFIED DATE%']  = "<b>" . JText::_('PLG_SYSTEM_NOTIFICATIONARY_MODIFIED') . '</b>: '
						. NotificationAryHelper::getCorrectDate($this->contentItem->modified) . '<br/>';
				}
			}

			if ($IncludeCategoryTree) {
				$this->_buildCategoryTree();
				$place_holders_body['%CATEGORY PATH%'] = "<b>"
					. JText::_('PLG_SYSTEM_NOTIFICATIONARY_JCATEGORY') . '</b>: ' . implode(' > ', $this->categoryTree);
			}

			if ($IncludeContenttype) {
				$place_holders_body['%CONTENT_TYPE%'] = "<b>" . JText::_('PLG_SYSTEM_NOTIFICATIONARY_CONTENT_TYPE') . '</b>: ' . $this->rule->contenttype_title;
			}

			if ($IncludeFrontendViewLink && $this->contentItem->state == 1 && $canView) {
				if ($link = $this->_buildLink($zone = 'site', $task = 'view')) {
					$place_holders_body['%FRONT VIEW LINK%']  = "<b>" . JText::_('PLG_SYSTEM_NOTIFICATIONARY_VIEW_CONTENT_ITEM') . '</b>: <br/>' . PHP_EOL;
					$place_holders_body['%FRONT VIEW LINK%'] .= '<a href="' . $link . '">' . $link . '</a>';
				}
			} elseif ($IncludeFrontendViewLink && $this->contentItem->state == 1 && !$canView) {
				if ($link = $this->_buildLink($zone = 'site', $task = 'view')) {
					$place_holders_body['%FRONT VIEW LINK%']  = "<b>" . JText::_('PLG_SYSTEM_NOTIFICATIONARY_VIEW_CONTENT_ITEM') . '</b>: <br/>' . PHP_EOL;
					$place_holders_body['%FRONT VIEW LINK%'] .= JText::_('PLG_SYSTEM_NOTIFICATIONARY_JERROR_ALERTNOAUTHOR') . '<br/>' . PHP_EOL;
					$place_holders_body['%FRONT VIEW LINK%'] .= '<a href="' . $link . '">' . $link . '</a>';
				}
			} elseif ($this->contentItem->state != 1) {
				$place_holders_body['%FRONT VIEW LINK%'] = "<b>"
					. JText::_('PLG_SYSTEM_NOTIFICATIONARY_THIS_CONTENT_ITEM_MUST_BE_REVIEWED_AND_MAY_BE_PUBLISHED_BY_AN_ADMINISTRATOR_USER') . "</b>.";

				if ($link = $this->_buildLink($zone = 'site', $task = 'view')) {
					$place_holders_body['%FRONT VIEW LINK%'] .= '<br>' . PHP_EOL . $link;
				}
			}

			if ($isModifier || $isAuthor) {
				$place_holders_body['%CONTENT ID%'] = "<b>"
					. JText::_('PLG_SYSTEM_NOTIFICATIONARY_YOUR_CONTENT_ITEM_ID_FOR_FUTHER_REFERENCE_IS') . "</b> " . $this->contentItem->id;
			}

			// Add FE edit link
			if ($IncludeFrontendEditLink && $canEdit) {
				if ($link = $this->_buildLink($zone = 'site', $task = 'edit')) {
					$place_holders_body['%FRONT EDIT LINK%']  = "<b>"
						. JText::_('PLG_SYSTEM_NOTIFICATIONARY_IF_YOU_ARE_LOGGED_IN_TO_FRONTEND_USE_THIS_LINK_TO_EDIT_THE_CONTENT_ITEM')
						. '</b>' . '<br/>' . PHP_EOL;
					$place_holders_body['%FRONT EDIT LINK%']  .= '<a href="' . $link . '">' . $link . '</a>';
				}
			}

			// Add BE edit link
			if ($IncludeBackendEditLink && $canLoginBackend) {
				$place_holders_body['%BACKEND EDIT LINK%']  = "<b>"
					. JText::_('PLG_SYSTEM_NOTIFICATIONARY_IF_YOU_ARE_LOGGED_IN_TO_BACKEND_USE_THIS_LINK_TO_EDIT_THE_CONTENT_ITEM')
					. '</b>' . '<br/>' . PHP_EOL;

				$place_holders_body['%BACKEND EDIT LINK%'] .= '<a href="' . $this->_buildLink($zone = 'admin', $task = 'edit') . '">'
					. $this->_buildLink($zone = 'admin', $task = 'edit') . '</a>';
			}
		} else {
			$place_holders_subject['%TITLE%']  = $this->contentItem->title;
			$place_holders_subject['%MODIFIER%']  = $this->modifier->username;
			$place_holders_subject['%CONTENT_TYPE%'] = $this->rule->contenttype_title;

			// ---------------- body ---------------- //
			$place_holders_body['%AUTHOR%']  = $this->author->username;
			$place_holders_body['%CREATED DATE%']  = NotificationAryHelper::getCorrectDate($this->contentItem->created);

			if (!isset($this->contentItem->modified) || is_null($this->contentItem->modified)) {
				$place_holders_body['%MODIFIED DATE%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_JNO');
			} else {
				$place_holders_body['%MODIFIED DATE%']  = NotificationAryHelper::getCorrectDate($this->contentItem->modified);
			}

			if (strpos($this->rule->messagebodycustom, '%CATEGORY PATH%') !== false) {
				$this->_buildCategoryTree();
				$place_holders_body['%CATEGORY PATH%'] = implode(' > ', $this->categoryTree);
			}

			$place_holders_body['%FRONT VIEW LINK%'] = '';

			if ($this->contentItem->state == 1 && $canView) {
				if ($link = $this->_buildLink($zone = 'site', $task = 'view')) {
					$place_holders_body['%FRONT VIEW LINK%'] .= $this->_buildLink($zone = 'site', $task = 'view');
				} else {
					$place_holders_body['%FRONT VIEW LINK%'] .= JText::_('PLG_SYSTEM_NOTIFICATIONARY_NO_FE_LINK');
				}
			} elseif ($this->contentItem->state == 1 && !$canView) {
				$place_holders_body['%FRONT VIEW LINK%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_JERROR_ALERTNOAUTHOR');
			} elseif ($this->contentItem->state != 1 && $canEdit) {
				if ($link = $this->_buildLink($zone = 'site', $task = 'view')) {
					$place_holders_body['%FRONT VIEW LINK%'] = "<b>"
						. JText::_('PLG_SYSTEM_NOTIFICATIONARY_THIS_CONTENT_ITEM_MUST_BE_REVIEWED_AND_MAY_BE_PUBLISHED_BY_AN_ADMINISTRATOR_USER') . "</b>.";

					$place_holders_body['%FRONT VIEW LINK%'] .= PHP_EOL . $link;
				} else {
					$place_holders_body['%FRONT VIEW LINK%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_NO_FE_LINK');
				}
			} elseif ($this->contentItem->state != 1) {
				if ($link = $this->_buildLink($zone = 'site', $task = 'view')) {
					$place_holders_body['%FRONT VIEW LINK%'] = "<b>"
						. JText::_('PLG_SYSTEM_NOTIFICATIONARY_THIS_CONTENT_ITEM_MUST_BE_REVIEWED_AND_MAY_BE_PUBLISHED_BY_AN_ADMINISTRATOR_USER') . "</b>.";
					$place_holders_body['%FRONT VIEW LINK%'] .= PHP_EOL . $link;
				} else {
					$place_holders_body['%FRONT VIEW LINK%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_NO_FE_LINK');
				}
			}

			if ($canEdit) {
				if ($link = $this->_buildLink($zone = 'site', $task = 'edit')) {
					$place_holders_body['%FRONT EDIT LINK%'] = $link;
				} else {
					$place_holders_body['%FRONT EDIT LINK%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_NO_FE_LINK');
				}
			} else {
				$place_holders_body['%FRONT EDIT LINK%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_JERROR_ALERTNOAUTHOR');
			}

			if ($canLoginBackend && $canEdit) {
				$place_holders_body['%BACKEND EDIT LINK%'] = $this->_buildLink($zone = 'admin', $task = 'edit');
			} else {
				$place_holders_body['%BACKEND EDIT LINK%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_JERROR_ALERTNOAUTHOR');
			}

			$place_holders_body['%CONTENT ID%']  = $this->contentItem->id;
		}

		// Strip plugin tags
		if ($this->rule->strip_plugin_tags) {
			if (!empty($this->contentItem->introtext)) {
				$this->contentItem->introtext = NotificationAryHelper::stripPluginTags($this->contentItem->introtext);
			}

			if (!empty($this->contentItem->fulltext)) {
				$this->contentItem->fulltext = NotificationAryHelper::stripPluginTags($this->contentItem->fulltext);
			}
		}

		if ($this->rule->make_image_path_absolute == 'absolute') {
			$domain = JURI::root();

			if (!empty($this->contentItem->introtext)) {
				$this->contentItem->introtext = str_replace('href="mailto:', '##mygruz20161114125806', $this->contentItem->introtext);
				$this->contentItem->introtext = str_replace('href=\'mailto:', '##mygruz20161114125807', $this->contentItem->introtext);

				$this->contentItem->introtext = preg_replace("/(href|src)\=\"([^(http)])(\/)?/", "$1=\"$domain$2", $this->contentItem->introtext);

				$this->contentItem->introtext = str_replace('##mygruz20161114125806', 'href="mailto:', $this->contentItem->introtext);
				$this->contentItem->introtext = str_replace('##mygruz20161114125807', 'href=\'mailto:', $this->contentItem->introtext);
			}

			if (!empty($this->contentItem->fulltext)) {
				$this->contentItem->fulltext = str_replace('href="mailto:', '##mygruz20161114125806', $this->contentItem->fulltext);
				$this->contentItem->fulltext = str_replace('href=\'mailto:', '##mygruz20161114125807', $this->contentItem->fulltext);

				$this->contentItem->fulltext = preg_replace("/(href|src)\=\"([^(http)])(\/)?/", "$1=\"$domain$2", $this->contentItem->fulltext);

				$this->contentItem->fulltext = str_replace('##mygruz20161114125806', 'href="mailto:', $this->contentItem->fulltext);
				$this->contentItem->fulltext = str_replace('##mygruz20161114125807', 'href=\'mailto:', $this->contentItem->fulltext);
			}
		}

		// *** prepare introtext and fulltext {

		// Instantiate a new instance of the class. Passing the string
		// variable automatically loads the HTML for you.
		if ($this->rule->emailformat == 'plaintext') {
			if (!class_exists('\Html2Text\Html2Text')) {
				require_once NotificationAry_DIR . '/helpers/Html2Text.php';
			}
		}

		if (empty($this->rule->introtext)) {
			if ($this->rule->emailformat == 'plaintext') {
				$h2t = new \Html2Text\Html2Text($this->contentItem->introtext, array('show_img_link' => 'yes'));
				$h2t->width = 120;

				// Simply call the get_text() method for the class to convert
				// the HTML to the plain text. Store it into the variable.
				$this->rule->introtext = $h2t->get_text();
				unset($h2t);
			} else {
				$this->rule->introtext = $this->contentItem->introtext;
			}
		}

		if (empty($this->rule->fulltext)) {
			if ($this->rule->emailformat == 'plaintext') {
				// Instantiate a new instance of the class. Passing the string
				// variable automatically loads the HTML for you.
				$h2t = new \Html2Text\Html2Text($this->contentItem->fulltext);
				$h2t->width = 120;

				// Simply call the get_text() method for the class to convert
				// the HTML to the plain text. Store it into the variable.
				$this->rule->fulltext = $h2t->get_text();
				unset($h2t);
			} else {
				$this->rule->fulltext = $this->contentItem->fulltext;
			}
		}
		// *** prepare introtext and fulltext }

		if (empty($this->rule->fulltext)) {
			$fulltext = '[' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_FIELD_NO_CONTENT') . ']';
		} else {
			$fulltext = $this->rule->fulltext;
		}

		if ($this->rule->messagebodysource == 'hardcoded') {
			if ($IncludeIntroText) {
				$place_holders_body['%INTRO TEXT%'] = "\n\n<br/><br/>...........<b>"
					. JText::_('PLG_SYSTEM_NOTIFICATIONARY_INTRO_TEXT') . "</b>:...........\n<br/>" . $this->rule->introtext;
			}

			if ($IncludeFullText) {
				$place_holders_body['%FULL TEXT%'] = "\n\n<br/>...........<b>"
					. JText::_('PLG_SYSTEM_NOTIFICATIONARY_FULL_TEXT') . "</b>:...........\n<br/>" . $fulltext;
			}

			$diffType = 'none';

			if ($this->rule->emailformat == 'plaintext') {
				$diffType = $this->rule->includediffinfo_text;
			} else {
				$diffType = $this->rule->includediffinfo_html;
			}

			if (!$this->onContentChangeStateFired && isset($this->rule->attachdiffinfo) && $this->noDiffFound) {
				$diffContents = PHP_EOL . '<br /><span style="color:red;">' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_NO_DIFF_FOUND') . '</span><br/>' . PHP_EOL;

				$place_holders_body['%DIFF ' . $diffType . '%'] = $diffContents;
			} else {
				if ($diffType != 'none' && isset($this->diffs[$diffType])) {
					$diffContents = PHP_EOL . '<hr><center>					.....Diff.......</center>' . PHP_EOL;
					$diffContents .= $this->diffs[$diffType];
					$place_holders_body['%DIFF ' . $diffType . '%'] = $diffContents;
				}
			}
		} else {
			// Custom mailbody
			$place_holders_body['%INTRO TEXT%'] = $this->rule->introtext;
			$place_holders_body['%FULL TEXT%'] = $fulltext;

			if (!$this->onContentChangeStateFired) {
				$noDiffEchoed = false;

				foreach ($this->availableDIFFTypes as $diffType) {
					if ($this->rule->emailformat == 'plaintext' && in_array($diffType, array('Html/SideBySide', 'Html/Inline'))) {
						continue;
					}

					if ($this->rule->emailformat == 'html' && !in_array($diffType, array('Html/SideBySide', 'Html/Inline'))) {
						continue;
					}

					if (strpos($this->rule->messagebodycustom, '%DIFF ' . $diffType . '%') !== false) {
						if ($this->noDiffFound) {
							if (!$noDiffEchoed) {
								$place_holders_body['%DIFF ' . $diffType . '%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_NO_DIFF_FOUND');
								$noDiffEchoed = true;
							} else {
								$place_holders_body['%DIFF ' . $diffType . '%'] = '';
							}
						} else {
							$place_holders_body['%DIFF ' . $diffType . '%'] = $this->diffs[$diffType];
						}
					}
				}
			} else {
				foreach ($this->availableDIFFTypes as $diffType) {
					$place_holders_body['%DIFF ' . $diffType . '%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_NO_DIFF_FOUND');
					break;
				}
			}
		}

		// Just a symlink to be compatible for ReL, as he may already have used the %ACTION BODY% tag
		$place_holders_body['%ACTION BODY%'] = &$place_holders_body['%ACTION%'];

		// Just a symlink to be compatible for ReL, as he may already have used the %ACTION BODY% tag
		$place_holders_body['%STATE%'] = &$place_holders_body['%STATUS%'];

		// Switched to Content item
		$place_holders_body['%ARTICLE ID%'] = &$place_holders_body['%CONTENT ID%'];

		foreach ($place_holders_subject as $k => $v) {
			if (!isset($place_holders_body[$k]) || empty($place_holders_body[$k])) {
				$place_holders_body[$k] = $v;
			}
		}

		if ($this->rule->messagebodysource == 'hardcoded') {
			$place_messagesubject = '%ACTION% %SITENAME% (%SITELINK%): %TITLE%';
			$place_messagebody = array(
				'%TITLE HARDCODED%',
				'%CONTENT_TYPE%',
				'%CATEGORY PATH%',
				'%STATUS%',

				// Empty line
				'',
				'%AUTHOR%',
				'%MODIFIER HARDCODED%',
				'%CREATED DATE%',
				'%MODIFIED DATE%',
				'%CONTENT ID%',
				'',
				'%FRONT VIEW LINK%',
				'',
				'%FRONT EDIT LINK%',
				'',
				'%BACKEND EDIT LINK%',
				'',
				'%INTRO TEXT%',
				'',
				'%FULL TEXT%',
				'',
				'%MANAGE SUBSCRIPTION LINK%',
				'%DIFF Html/SideBySide%',
				'%DIFF Html/Inline%',
				'%DIFF Text/Unified%',
				'%DIFF Text/Context%'
			);

			// Clear empty fields from the template
			foreach ($place_messagebody as $k => $v) {
				if ($v === '') {
					continue;
				}

				if (empty($place_holders_body[$v])) {
					unset($place_messagebody[$k]);
					continue;
				}
			}

			// Clear double empty lines {
			$prev_line_empty = false;
			$place_messagebody_temp = array();

			foreach ($place_messagebody as $line) {
				if (!empty($line)) {
					$place_messagebody_temp[] = $line;
					$prev_line_empty = false;
					continue;
				}

				if (empty($line)) {
					if ($prev_line_empty) {
						continue;
					} elseif (!$prev_line_empty) {
						$place_messagebody_temp[] = $line;
						$prev_line_empty = true;
					}
				}
			}

			$place_messagebody = $place_messagebody_temp;

			unset($place_messagebody_temp);

			// Clear double empty lines }

			$glue = '<br/>' . PHP_EOL;

			if ($this->rule->emailformat == 'plaintext') {
				$glue = PHP_EOL;
			}

			$place_messagebody = '%ACTION% %SITENAME% (%SITELINK%)' . $glue . implode($glue, $place_messagebody);
		} else {
			$place_messagesubject = JText::_($this->rule->messagesubjectcustom);
			$place_messagebody = $this->rule->messagebodycustom;
		}

		foreach ($place_holders_subject as $k => $v) {
			$place_messagesubject = str_replace($k, $v, $place_messagesubject);
		}

		$place_messagesubject = $this->_replaceRunPHPOnPlaceHolder($place_messagesubject);
		$place_messagesubject = $this->_replaceObjectPlaceHolders($place_messagesubject);

		$mail['subj'] = strip_tags($place_messagesubject);

		// Preserve placeholder
		$place_messagebody = str_replace('%UNSUBSCRIBE LINK%', '##mygruz20160414053402', $place_messagebody);

		foreach ($place_holders_body as $k => $v) {
			if ($this->rule->emailformat == 'plaintext') {
				$v = strip_tags($v);
			}

			$place_messagebody = str_replace($k, $v, $place_messagebody);
		}

		$place_messagebody = $this->_replaceRunPHPOnPlaceHolder($place_messagebody);
		$place_messagebody = $this->_replaceObjectPlaceHolders($place_messagebody);

		// Place back
		$place_messagebody = str_replace('##mygruz20160414053402', '%UNSUBSCRIBE LINK%', $place_messagebody);

		$mail['body'] = $place_messagebody;
		$this->rule->cachedMailBuilt[$hash] = $mail;
		$mail['email'] = $user->email;

		return $mail;
	}


		/**
	 * Run in-message PHP code, but do not run PHP code
	 *
	 * @param   string  $place_messagebody  Body text with php placeholders
	 *
	 * @return   string  Text with PHP placeholders evaled
	 */
	public function _replaceRunPHPOnPlaceHolder($place_messagebody)
	{
		preg_match_all("/(<\s*\?php)(.*?)(\?\s*>)/msi", $place_messagebody, $php_codes);

		foreach ($php_codes[0] as $ck => $code_line) {
			$code_line = $this->_replaceObjectPlaceHolders($code_line);
			ob_start();
			eval('?>' . $code_line);
			$code_line = ob_get_contents();
			ob_end_clean();
			$place_messagebody = str_replace($php_codes[0][$ck], $code_line, $place_messagebody);
		}

		return $place_messagebody;
	}

	/**
	 * Replaces placeholders by the object variables
	 *
	 * @param   string  $text  Mail body text
	 *
	 * @return   type  Description
	 */
	protected function _replaceObjectPlaceHolders($text)
	{
		/* // ##mygruz20200718185712 {
		It was:
		preg_match_all('/##([^#]*)#([^#]*)##([^#]*)/Ui',$text,$matches);
		preg_match_all('/##([^#]*)#([^#]*)##([^#]*)#{0,2}/Ui', $text, $matches);
		preg_match_all('/##([^ \n]*)##/i', $text, $matches);
		It became: */
		preg_match_all('/(["\']{0,1})##([^ \n]*)##(["\']{0,1})/i', $text, $matches);

		/* ##mygruz20160705172743 } */
		$varNameBag = $matches[2];
		$quotesBag = $matches[1];

		if (!empty($varNameBag)) {
			foreach ($varNameBag as $k => $v) {
				$path = [];
				$tmp = explode('##', $v);

				if (!empty($tmp[1])) {
					$path[3] = $tmp[1];
				}

				$tmp = explode('#', $tmp[0]);

				$path[1] = $tmp[0];
				$path[2] = $tmp[1];

				switch ($path[1]) {
					case 'Content':
						if (empty($path[3])) {
							if (isset($this->contentItem->{$path[2]})) {
								$value = $this->contentItem->{$path[2]};

								if (is_array($value)) {
									$value = implode(',', $value);
								}
							}
						} else {
							if (isset($this->contentItem->{$path[2]}) && is_array($this->contentItem->{$path[2]}) && isset($this->contentItem->{$path[2]}[$path[3]])) {
								$value = $this->contentItem->{$path[2]}[$path[3]];

								if (is_array($value)) {
									$value = implode(',', $value);
								}
							}
						}
						break;
					case 'User':
						$user = Factory::getUser();

						if (isset($user->{$path[2]})) {
							$value = $user->{$path[2]};

							if (is_array($value)) {
								$value = implode(',', $user->{$path[2]});
							}
						}
						break;
				}

				if (empty($value)) {
					$value = '';
				}

				$value = (string) $value;
				if ($quotesBag[$k]) {
					$value = str_replace($quotesBag[$k], '\\'. $quotesBag[$k], $value);
					$value = $quotesBag[$k] . $value . $quotesBag[$k];
				}
				$text = str_replace($matches[0][$k], $value, $text);
			}
		}

		return $text;
	}

	/**
	 * Builds content item category tree
	 *
	 * @return   void
	 */
	protected function _buildCategoryTree()
	{
		if (!empty($this->categoryTree)) {
			return;
		}

		$this->categoryTree = array();

		// ~ $category_table = JTable::getInstance( 'category');
		// ~ $category_table->load($this->contentItem->catid);

		// Need to pass the $options array with access false to get categroies with all access levels
		$options = array();
		$options['access'] = false;

		$catid = (is_array($this->contentItem->catid)) ? $this->contentItem->catid[0] : $this->contentItem->catid;

		switch ($this->context['extension']) {
			case 'k2':
			case 'zoo':
				// ~ $context = 'com_'.$this->context['extension'].'.category';

				$context = $this->context['full'];
				$contentItem = $this->_getContentItemTable($context, $category = true);
				$contentItem->load($catid);
				array_unshift($this->categoryTree, $contentItem->name);

				// $this->categoryTree[] = $contentItem->name;
				while ($contentItem->parent != 0) {
					$contentItem->load($contentItem->parent);
					array_unshift($this->categoryTree, $contentItem->name);
				}

				return;
				break;
			case 'jdownloads':
				$cat = JTable::getInstance('category', 'jdownloadsTable');
				$cat->load($catid);
				$path = $cat->getParentCategoryPath($catid);
				$this->categoryTree = explode('/', $path);

				return;
				break;
			default:
				break;
		}

		if (isset($this->contentItem->extension)) {
			$scope = explode('_', $this->contentItem->extension);
			$cat = JCategories::getInstance($scope[1], $options);
			$cat_id = $this->contentItem->id;
		} else {
			$cat = JCategories::getInstance($this->context['extension'], $options);

			// ~ JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_categories'.DS.'tables');
			// ~ $cat = JCategories::getInstance('users',$options);

			$catid = (is_array($this->contentItem->catid)) ? $this->contentItem->catid[0] : $this->contentItem->catid;
			$cat_id = $catid;
		}

		$this->categoryTree = array();

		// If current extension which item is saved, doesn't use Joomla native category system
		if (!$cat) {
			return;
		}

		if (method_exists($cat, 'get')) {
			$cat = $cat->get($cat_id);
		}

		if (method_exists($cat, 'hasParent')) {
			while ($cat->hasParent()) {
				array_unshift($this->categoryTree, $cat->title);
				$cat = $cat->getParent();
			}

			if ($cat->title !== 'ROOT') {
				array_unshift($this->categoryTree, $cat->title);
			}
		}
	}

	/**
	 * Loads available mail placeholders from a special file
	 *
	 * @return   type  void
	 */
	public function _loadMailPlaceholders()
	{
		// Load placeholders, but only once, no need to to load file each time building a email
		static $placeholders_loaded = false;

		if (!$placeholders_loaded) {
			require JPATH_SITE . '/plugins/system/notificationary/helpers/field_mailbodyHelper.php';

			foreach ($ph_body as $k => $v) {
				if (strpos($v, '%') !== 0) {
					unset($ph_body[$k]);
					continue;
				}
			}

			foreach ($ph_subject as $k => $v) {
				$this->place_holders_subject[$v] = null;
			}

			foreach ($ph_body as $k => $v) {
				$this->place_holders_body[$v] = null;
			}

			$placeholders_loaded = true;
		}
	}

}
