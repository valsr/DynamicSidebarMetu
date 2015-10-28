/**
 * This code pre-processes trees and menus to integrate the third-party code into mediawiki without changing it
 */
$(document).ready(function()
{

	$('#DynamicSidebarMenu').fancytree
	({
		activate: function(event, data)
		{
        		var node = data.node;
		        // Use <a> href and target attributes to load the content:
	        	if( node.data.href )
	        	{
				window.open(node.data.href, "_self");
	        	}
		}
	});
});

// Preload the tree icons and loader
var path = mw.config.get('fancytree_path');
(new Image()).src = path + '/loading.gif';
(new Image()).src = path + '/icons.gif';
