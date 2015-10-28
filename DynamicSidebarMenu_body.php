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
		global $wgOut, $wgExtensionAssetsPath, $wgAutoloadClasses, $IP, $wgResourceModules;

		// This gets the remote path even if it's a symlink (MW1.25+)
		$path = str_replace( "$IP/extensions", '', __DIR__ );

		// Fancytree script and styles
		$wgResourceModules['ext.fancytree']['localBasePath'] = __DIR__ . '/fancytree';
		$wgResourceModules['ext.fancytree']['remoteExtPath'] = "$path/fancytree";
		$wgOut->addModules( 'ext.fancytree' );
		$wgOut->addStyle( "$wgExtensionAssetsPath/$path/fancytree/fancytree.css" );
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
		foreach ( $res as $row )
		{
			self::log("Got row from result:". $row->page_title);
			$title = Title::makeTitleSafe( $row->page_namespace, $row->page_title );
			if( $title ) 
				$titles[] = $title;
		}

		return $titles;
	}
	/**
	 * Create a link item (anchor link) of given title
	 * @param $title Title the title of a page
	 * @return string the prepared string
	 */
	function createItemLink( $title) {
		self::log("Making title '$title'");			
		return '<a href="'.$title->getFullURL().'">'.$title->getText().'</a>';
	}
	/**
	 * Make the given list of titles into a menu
	 * @param $titles Array all page titles
	 * @return string the whole list
	 */
	function menufy( $titles ) 
	{
		$menu_list = array();
		$menu = array();
		
		# creates a list of all the menu levels we need making sure all items can be connected
		self::log("Creating menu structure");
		foreach( $titles as $title )
		{
			self::log("Processing: $title");
			$levels = explode('/', $title);
			$title_path="";
			
			foreach ($levels as $level)
			{
				$title_path = trim("$title_path/$level", '/');
				if(!in_array($title_path, $menu_list) && strlen($title_path) > 0)
				{
					$menu_list[]= Title::makeTitle($title->getNamespace(), $title_path);
				}
			}
		}
		
		self::log("Creating menu");
		$prev_level = 0;
		$level = 0;
		$out = '<ul id="DynamicSidebarMenuData">';
		foreach( $menu_list as $menu_title )
		{
			self::log("Processing: $menu_title");
			$level = substr_count($menu_title, '/');
					
			if($level < $prev_level)
			{
				$out  .= '</ul></li>';
			}				
			else if ($level > $prev_level)
			{
				$out .= '<ul>';
			}
			
			$out .= '<li>'.$this->createItemLink($menu_title); // closing is done by the next item
			
			$prev_level = $level;
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
