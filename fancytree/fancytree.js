/**
 * This code pre-processes trees and menus to integrate the third-party code into mediawiki without changing it
 */
$(document).ready(function()
{

	$('#DSM').fancytree
	({
		clickFolderMode:2,
		click: function(event, data)
		{
        	var node = data.node;
			
			if (node.hasChildren() && event.toElement.nodeName != "A")
			{
				node.toggleExpanded();
				return false;
			}
		},
		createNode: function(event, data)
		{
			if(data.node.hasChildren())
			{
				var span = $(data.node.span);
				
				if(span.hasClass("DSMRed"))
					$(data.node.span).append("<span class=\"fancy-tree-link\">[<a href=\""+data.node.data.href+"?action=edit\">Create</a>]</span>");
				else
					$(data.node.span).append("<span class=\"fancy-tree-link\">[<a href=\""+data.node.data.href+"\">Open</a>]</span>");
			}
			else
			{
				$(data.node.span).find(".fancytree-title").replaceWith("<span class=\"fancytree-title fancy-tree-link\"><a href=\""+data.node.data.href+"\">"+data.node.title+"</a></span>");
			}
		}
	});
});

// Preload the tree icons and loader
var path = mw.config.get('fancytree_path');
(new Image()).src = path + '/loading.gif';
(new Image()).src = path + '/icons.gif';