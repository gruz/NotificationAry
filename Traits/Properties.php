<?php
/**
 * A helper trait with properties
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
trait Properties {

	public static $helpersFolder = __DIR__ . '/../Helpers/';
	public static $predefinedContentsFile = __DIR__ . '/../Helpers/predefined_contexts.php';
	public static $componentBridgesFolder = __DIR__ . '/../Helpers/components/';

	/**
	 * Stores previous content object state
	 *
	 * @var string
	 */
	protected $previousState;

	/**
	 * TODO Comment
	 *
	 * @var boolean
	 */
	protected $onContentChangeStateFired = false;

	/**
	 * Stores content item object
	 *
	 * @var object
	 */
	protected $contentItem;

	/**
	 * Sitename
	 *
	 * @var string
	 */
	protected $sitename;

	/**
	 * Language short code
	 *
	 * @var string
	 */
	protected $langShortCode;

	/**
	 * Lits of available diff types
	 *
	 * @var array
	 */
	protected $availableDIFFTypes = array('Text/Unified','Text/Context','Html/SideBySide','Html/Inline');

	/**
	 * Here will be stored which previos article versions need to be attached
	 *
	 * @var array
	 */
	protected $preparePreviousVersionsFlag = array();

	/**
	 * This flag is used to determine if DIFF info is needed at least once. Otherwise, DIFF library is not loaded.
	 *
	 * @var boolean
	 */
	protected $includeDiffInBody = false;

	/**
	 * Flag to know what diffs should be prepared gloablly.
	 *
	 * @var array
	 */
	protected $DIFFsToBePreparedGlobally = array();

	/**
	 * Broken email sends
	 *
	 * @var array
	 */
	protected $brokenSends = array();

	/**
	 * Article is New
	 *
	 * @var boolean
	 */
	protected $isNew = false;

	/**
	 * TODO comment
	 *
	 * @var string
	 */
	protected $publishStateChange = 'not determined';

	/**
	 * Contains all contexts to run the plugin at
	 *
	 * @var array
	 */
	protected $allowedContexts = array();

	/**
	 * Contains all components to run the plugin at
	 *
	 * @var array
	 */
	protected $allowedComponents = array();

	// Contains context=>jtableclass (manually entered) ties
	// ~ protected $jtableClasses = array();

	/**
	 * A variable to pass current context between functions.
	 *
	 * Currenlty used to determine if to show a notification switch.
	 * Is set in onContentPrepareForm to and used in onAfterRender to know if onAfterRender should run
	 *
	 * @var array
	 */
	protected $context = array();

	protected $shouldShowSwitchCheckFlag = false;

	protected $context_aliases = array(
					'com_content.category' => 'com_categories.category' ,
					"com_banners.category" => 'com_categories.category',
					// ~ "com_content.form" => 'com_content.article',
					// ~ "com_jdownloads.form" => 'com_jdownloads.download',

					// "com_categories.categorycom_content" => 'com_categories.category',
					// "com_categories.categorycom_banners" => 'com_categories.category',
					'com_categories.categories' => 'com_categories.category',
				);

	/**
	 * Joomla article object differs from i.e. K2 object. Make them look the same for some variables
	 *
	 * @var array
	 */
	protected $object_variables_to_replace = array (
			// State in com_contact means not status, but a state (region), so use everywhere published
			array ('published','state'),
			array ('fulltext','description'),
			array ('title','name'),

			// User note
			array ('title','subject'),

			// JEvents
			array ('title','_title'),
			array ('fulltext','_content'),
			array ('published','_state'),
			array ('id','_ev_id'),

			// Banner client
			array ('fulltext','extrainfo'),

			// Contact
			array ('fulltext','misc'),

			// User note
			array ('fulltext','body'),

			// Banner category
			array ('created_by','created_user_id'),

			// Jdownloads. Third parameter means force to override created_by with created_id even if created_by exists
			array ('created_by','created_id', true),

			// Banner category
			array ('modified_by','modified_user_id'),
			array ('created','created_time'),
			array ('modified','modified_time'),

			// PhocaDownload
			array ('created_by', 'owner_id'),
			array ('modified_by', 'owner_id'),
			array ('modified', 'date'),
			array ('created', 'date'),

			// JDownloads
			array ('id','file_id'),
			array ('catid','cat_id'),
			array ('title','file_title'),

			array ('introtext', false),
			array ('title', false),
			array ('alias', false),
			array ('fulltext', false),
		);

	public static $CustomHTMLReplacementRules;
}
