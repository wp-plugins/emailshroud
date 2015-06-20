function sto_emailShroud_replaceNode(idToFind, emailAddress, isSimple)
{
	var nodeToReplace = document.getElementById(idToFind);	
	// Check it actually appears on this page.
	if (nodeToReplace)
	{
		// Create new subtree
		var replacementAnchorNode = document.createElement('A');
		replacementAnchorNode.setAttribute('href',"mailto:"+emailAddress);
		// Copy any text as the target text
		var possibleTextNodes = nodeToReplace.childNodes;
		for (var i = 0; i < possibleTextNodes.length; i++)
		{
			if (possibleTextNodes[i].nodeType == 3) //NODE_TEXT)
			{
				if (isSimple)
				{
					// Replace what was taken out of the text by the main plug-in.
					possibleTextNodes[i].nodeValue = emailAddress;
				}
				replacementAnchorNode.appendChild(possibleTextNodes[i]);
			}
		}
		// Replace old subtree
		var parentNode = nodeToReplace.parentNode;
		parentNode.insertBefore(replacementAnchorNode,nodeToReplace);
		parentNode.removeChild(nodeToReplace);
	}
}
