function sto_emailShroud_trimNulls(sString)
{
	while (sString.substring(sString.length-1, sString.length) == '\0')
	{
		sString = sString.substring(0,sString.length-1);
	}
	return sString;
};

function sto_emailShroud_3DES_decrypt(idToFind, key, iv, encryptedAddress, isSimple)
{
	var plaintextAddress = sto_emailShroud_trimNulls(des(urlDecode(key), urlDecode(encryptedAddress), 0, 1, urlDecode(iv)));
	sto_emailShroud_replaceNode(idToFind, plaintextAddress,isSimple);
};

