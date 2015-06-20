function sto_emailShroud_shuffle_and_reverse_decrypt(idToFind, encryptedEmailAddress, isSimple)
{
			if (!encryptedEmailAddress) return;
			var unreversed='';
			for (i = encryptedEmailAddress.length-1; i>=0; i--)
				unreversed+=encryptedEmailAddress.charAt(i)
			var dotPosition = unreversed.indexOf(".");
			if (dotPosition == -1) return;
			var part2 = unreversed.substring(0,dotPosition);
			var atPosition = unreversed.indexOf("@");
			if (atPosition == -1) return;
			var part1 = unreversed.substring(dotPosition+1,atPosition);
			var part3 = unreversed.substring(atPosition+2);
			if(!part3) return;
			var clearTextEmailAddress = part1+"@"+part2+"."+part3;
			
			sto_emailShroud_replaceNode(idToFind, clearTextEmailAddress, isSimple);
}
