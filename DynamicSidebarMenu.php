<?php
if( !defined( 'MEDIAWIKI' ) ) 
{
	echo( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
	die( 1 );
}


class DynamicSidebarMenu 
{
	private $debug = false; ///< Debug option - true to show debug statements, false to not
	private $title; ///< Title of the current page (Title object)
	private $namespace = ''; ///< Title current namespace
	/**
	 * Options array
	 */
	private $options = array();
	
	/**
	 * Hook function to draw the wenu
	 */
	static function createMenu(  Skin $skin, &$bar ) 
	{
		global $dsmDebug;
			
		$menu = new DynamicSidebarMenu( $skin->getRelevantTitle() );
		$options = array( "parent" => "/");
		$menu->options( $options );

		$text = $menu->render();

		$bar["DynamicSidebarMenu"]="<div id=\"DynamicSidebarMenu\">$text</div>";
		return true;
	}
	/**
	 * Called when the extension is first loaded
	 */
	public static function onRegistration() 
	{
		global $wgExtensionFunctions;
		$wgExtensionFunctions[] = 'DynamicSidebarMenu::setup';
	}
	/**
	 * Called at extension setup time, install hooks and module resources
	 */
	public static function setup() 
	{
		global $wgOut, $wgExtensionAssetsPath, $wgAutoloadClasses, $IP, $wgResourceModules, $dsmExpandSidebar;

		// This gets the remote path even if it's a symlink (MW1.25+)
		$path = str_replace( "$IP/extensions", '', __DIR__ );
		$path = str_replace( "$IP\\extensions", '', __DIR__ ); // Windows uses forward slash

		// Fancytree script and styles
		$wgResourceModules['ext.fancytree']['localBasePath'] = __DIR__ . '/fancytree';
		$wgResourceModules['ext.fancytree']['remoteExtPath'] = "$path/fancytree";
		$wgOut->addModules( 'ext.fancytree' );
		$wgOut->addStyle( "$wgExtensionAssetsPath/$path/fancytree/fancytree.css" );
		$wgOut->addStyle( "$wgExtensionAssetsPath/$path/fancytree/dynamicsidebarmenu.css" );
		if($dsmExpandSidebar)
			$wgOut->addStyle( "$wgExtensionAssetsPath/$path/fancytree/dynamicsidebarmenu-expandsidebar.css" );
		$wgOut->addJsConfigVars( 'fancytree_path', "$wgExtensionAssetsPath/$path/fancytree" );
	}

	function __construct( $title ) 
	{
		global $wgContLang;
		
		$this->title = $title;
		if (!is_null($title))
			$this->namespace = $title->getNamespace();
		else
			$this->namespace = 0;
			
		self::log("Created a DynamicSideMenu with title => '$title' and namespace => '".$this->namespace."'");
	}
	function options( $options ) 
	{
		self::log("Setting options: ", $options);
		if( isset( $options['debug'] ) ) 
			if( $options['debug'] == 'true' || intval( $options['debug'] ) == 1 ) 
				$this->debug = true;
	}

	function render() 
	{
		$pages = $this->queryDatabase();
		
		if( $pages != null && count( $pages ) > 0 ) 
		{
			$html = $this->menufy( $pages );
		} else {
			$html = "''" . $this->title->getText() . "''\n";
		}
	
		return $html;
	}
	/**
	 * Query the database to obtain all titles.
	 * @return array all titles
	 */
	function queryDatabase() 
	{
		self::log("Querying the database");
		$dbr = wfGetDB( DB_SLAVE );

		$conditions = array();
		$options = array();

		$options['ORDER BY'] = 'page_title ASC';

		$conditions['page_namespace'] = $this->namespace; // don't let list the streams(namespaces) cross
		$conditions['page_is_redirect'] = 0; // do not list redirects (for now)
		
		$fields = array('page_title', 'page_namespace');
		$res = $dbr->select( 'page', $fields, $conditions, __METHOD__, $options );
		
		$titles = array();
		$i = 0;
		foreach ( $res as $row )
		{
			$i++;
			$title = Title::makeTitle( $row->page_namespace, $row->page_title );
			if( $title ) 
				$titles[] = $title;
		}
		
		self::log("Processed ".count($titles)." out of $i results");
		return $titles;
	}
	/**
	 * Create a link item (anchor link) of given title
	 * @param $title Title the title of a page
	 * @return string the prepared string
	 */
	function createItemLink($title) 
	{
		$a = '<a href="'.$title->getFullURL().'">'.$title->getSubpageText().'</a>';
		self::log("a =".$a);
		return '<a href="'.$title->getFullURL().'">'.$title->getSubpageText().'</a>';
	}
	/**
	 * Make the given list of titles into a menu
	 * @param $titles Array all page titles
	 * @return string the whole list
	 */
	function menufy( $titles ) 
	{
		$menuList = array(); // list in Namespace:Path/to/Title (string)
		$menu = array();
		
		// creates a list of all the menu levels we need making sure all items can be connected
		self::log("Sorting array");
		asort($titles);
		self::log("Creating menu structure");
		foreach( $titles as $title )
		{
			$levels = explode('/', $title->getFullText());
			$titlePath="";
			
			foreach ($levels as $level)
			{
				$titlePath = trim("$titlePath/$level", '/');
				if(!in_array($titlePath, $menuList) && strlen($titlePath) > 0)
				{
					$menuList[] = $titlePath;
				}
			}
		}
		
		self::log("Creating menu");
		$prevLevel = 0;
		$level = 0;
		$out = '<ul id="DynamicSidebarMenuData">';
		foreach( $menuList as $menuTitle )
		{
			self::log("Processing: $menuTitle");
			$level = substr_count($menuTitle, '/');
					
			if($level < $prevLevel)
			{
				$out  .= '</ul></li>';
			}				
			else if ($level > $prevLevel)
			{
				$out .= '<ul>';
			}
			
			$out .= '<li>'.$this->createItemLink(Title::newFromText($menuTitle)); // closing is done by the next item
			
			$prevLevel = $level;
		}
		
		return $out.'</li></ul>';
	}	
	/**
	 * Prints debugging information.
	 * @param string $debugText Text to print
	 * @param array $debugArgs Argument to show. Will be expanded into argItem::argItem2::argItem3::...
	 */
	static function log( $debugText, $debugArgs = null ) 
	{
		global $dsmDebug;
		
		if($dsmDebug)
		{
			if ( isset( $debugArgs ) )
			{
				$text = $debugText . " " . implode( "::", $debugArgs );
				wfDebugLog( 'dynamicsidebarmenu', $text );
			} 
			else 
			{
				wfDebugLog( 'dynamicsidebarmenu', $debugText );
			}
		}
	}
}
