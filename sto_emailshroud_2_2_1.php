<?php
/*
Plugin Name: EmailShroud
Plugin URI: http://www.somethinkodd.com/emailshroud/
Description: Prevents email address harvesting by replacing mailto: references in anchor tags with obfuscated form.
Version: 2.2.1
Author: Somethinkodd.com Development Team
Author URI: http://oddthinking.somethinkodd.com/
*/

$sto_emailShroud_version = "2.2.1";

function sto_init_emailShroud () {
     load_plugin_textdomain('emailShroud','wp-content/plugins/emailShroud');
}
add_action('init', 'sto_init_emailShroud');


$sto_emailShroud_matches = array();
/* stores each matching email address as it is found, in order to generate the JavaScript at the bottom of the page. */

// *********************************************************************************
// Helper Functions
// *********************************************************************************

function sto_emailShroud_stripLeftAndRight($paddedString) {
    return substr($paddedString, 1, strlen($paddedString)-2);
}

function sto_emailShroud_shuffle_and_reverse_encrypt($clearTextEmailAddress)
	// Assumes text contains a valid mail address - particularly only one "@" and some time later a "."
	// If not, will return False;
{
	$atPosition = strpos($clearTextEmailAddress, "@");
	if($atPosition === false) return false;
	$dotPosition = strpos($clearTextEmailAddress, ".",$atPosition+1);
	if($dotPosition === false) return false;
	$part1 = substr($clearTextEmailAddress,0,$atPosition);
	$part2 = substr($clearTextEmailAddress,$atPosition+1,$dotPosition-$atPosition-1);
	$part3 = substr($clearTextEmailAddress,$dotPosition+1);
	$encryptedEmailAddress = strrev($part2.".".$part1."@@".$part3);
	return $encryptedEmailAddress;
}
	
function sto_emailShroud_3DES_encrypt($clearTextEmailAddress) {
    $td = mcrypt_module_open(MCRYPT_3DES, '', MCRYPT_MODE_CBC, '');
    $ks = mcrypt_enc_get_key_size($td);
	$randomString = sha1(get_bloginfo("url")+"Random extra padding in case it turns out the blog URL is too short.");
	$randomStringLen = strlen($randomString);
    $key = substr($randomString, 0, $ks); 
	$iv = substr($randomString, $randomStringLen-mcrypt_enc_get_iv_size($td), $randomStringLen-1);
		/*
			Note: This manner of producing a random string is unconventional from a 
			security perspective. However, the key here is not actually secret - it 
			is presented in the web-page. However, decrypting the email address has 
			a computational cost, and that is what is protecting the email address. 
			Replay attacks are not a concern. Having the key and the iv remain 
			relatively constant (ie. based on WordPress url) allows the possibility 
			of caching both locally (yet to be implemented) and at the web-service 
			remote end. 
		*/
    mcrypt_generic_init($td, $key, $iv);
    $encryptedText = mcrypt_generic($td, $clearTextEmailAddress);
    mcrypt_generic_deinit($td);
	
	return array('key' => $key,
	             'encryptedText' => $encryptedText,
				 'iv' => $iv);
}

function sto_emailShroud_stripEmailAddress($strToCheck, $replacementText)
{
/* This is not the main email searching function. It is a clean up function to cover any spurious secondary references to email addresses
     in an href.
	For example:
		<a href="mailto:mainAddress@example.com" title="title@example.com">text@example.com</a>
	The main algorithm will detect the reference to "mainAddress". This function will help replace "title" and "text" strings, if present.
	*/
	return preg_replace("#(?:(?:mailto:)?([\w-]+(?:\.[\w-]+)*)@((?:[\w-]+\.)+\w+))#",$replacementText, $strToCheck);
}	

// *********************************************************************************
// Main functionality 
// *********************************************************************************

/*  sto_emailShroud_mainFilter is the main engine to this plug-in.
    It detects mentions of email addresses in the text of a post, and triggers the code that replaces it.  */
function sto_emailShroud_mainFilter($content) {
	$pattern  = <<<EMAILADDRESS_REGEXP
#(?:(?:mailto:)?([\w-]+(?:\.[\w-]+)*)@((?:[\w-]+\.)+\w+))|<a([^>]*)href\s*=\s*[\"\']?mailto:([^"'>\s@]*)@([^"'>\?\s]*)(\?[^\"\']*)?[\"\']?([^>]*)>((?:[^<]|(?:<[^/])|(?:</[^a])|(?:</a[^/s>]))*)</a\s*>#i
EMAILADDRESS_REGEXP;
	/* 	Matches email addresses - here are some examples of matches:
			user.name@domain.name.tld
			mailto:user.name@domain.name.tld
			<a rel='external' href="mailto:user.name@domain.name.tld?subject=Subject Line" title="Tooltip">User's Full Name</a>
			
		Includes the following 'groups':
		1 : Simple address - user name
		2 : Simple address - domain name
		3 : Anchor tag - extra anchor tags between A and HREF (like rel='external nofollow')
		4 : Anchor tag - user name
		5 : Anchor tag - domain name
		6 : Anchor tag - extra fields of mailto (like "?subject=Test")
		7 : Anchor tag - extra anchor tags between HREF and end (like rel='external nofollow')
		8 : Anchor tag - subject - between <a> and </a>

		Note: When [1] is set, [4] is empty and vice-versa. Similarly for [2]/[5] and [1]/[8].
	*/
	return preg_replace_callback($pattern, 'sto_emailShroud_matchedEmailAddress', $content);
}

function sto_emailShroud_matchedEmailAddress($matchingText) {
/* Called by preg_replace_callback. See regexp in sto_emailShroud_mainFilter for definition of array. */

	global $sto_emailShroud_matches;
	global $sto_emailShroud_version;
	$result = "";
	
	$userName = $matchingText[1].$matchingText[4];
	// Remove any trace of remaining email addresses
	$matchingText[3] = sto_emailShroud_stripEmailAddress($matchingText[3],$userName);
	$matchingText[6] = sto_emailShroud_stripEmailAddress($matchingText[6],$userName);
	$matchingText[7] = sto_emailShroud_stripEmailAddress($matchingText[7],$userName);
	$matchingText[8] = sto_emailShroud_stripEmailAddress($matchingText[8],$userName);

	$sto_emailShroud_actionPlan = get_option("sto_emailShroud_actionPlan");
	$sto_emailShroud_encryption = get_option("sto_emailShroud_encryption");
	
	$id="sto_emailShroud".strval(count($sto_emailShroud_matches));
	$domain = $matchingText[2].$matchingText[5];
	$username = $matchingText[1].$matchingText[4];
	$emailAddress = $userName."@".$domain;
	
	// Determing whether it is a simple address (e.g. foo@example.com) or a complex anchor.
	$isSimpleEmailAddress = $matchingText[1] ? "true" : "false";

	// Push the match onto the list, based on encryption method. Used later to generate the Javascript.
	if("3DES"==$sto_emailShroud_encryption)
	{
		$encryptedData = sto_emailShroud_3DES_encrypt($emailAddress);
		array_push($sto_emailShroud_matches,array($id,urlencode($encryptedData['key']),urlencode($encryptedData['iv']),urlencode($encryptedData['encryptedText']),$isSimpleEmailAddress));
	}
	elseif ("shuffle_reverse"==$sto_emailShroud_encryption)	{
		$encryptedAddress = sto_emailShroud_shuffle_and_reverse_encrypt($emailAddress);
		array_push($sto_emailShroud_matches,array($id,$encryptedAddress,$isSimpleEmailAddress));
	}
	else
	{
		array_push($sto_emailShroud_matches,array($id,$domain,$username,$isSimpleEmailAddress));
	};

	// Generate the inline text, based on Action Plan.
	if ("replace" == $sto_emailShroud_actionPlan) {
		$sto_emailShroud_prefixString = sto_emailShroud_stripLeftAndRight(get_option("sto_emailShroud_prefixString"));
		$sto_emailShroud_replacementString = sto_emailShroud_stripLeftAndRight(get_option("sto_emailShroud_replacementString"));
		$sto_emailShroud_suffixString = sto_emailShroud_stripLeftAndRight(get_option("sto_emailShroud_suffixString"));
		$result .= "<span class=\"emailShroud_protectedAddress\" id=\"$id\" ";
		$result .=
			">"
			.$matchingText[1].$matchingText[8]
			."<span class=\"emailShroud_transformedAddress\">"
			.$sto_emailShroud_prefixString
			.$username
			.$sto_emailShroud_replacementString
			.$domain
			.$sto_emailShroud_suffixString
			."</span>"
			."</span>";
	} else { // Action Plan is to send to somethinkodd.com.
		$sto_emailShroud_redirectURLbase = "http://www.somethinkodd.com/emailshroud/emailaddress.php";
		$result .=
			"<a $matchingText[3] rel=\"nofollow\" ".
			"id=\"".$id."\" ";
		if("3DES"==$sto_emailShroud_encryption) {
			die("EmailShroud plug-in: Corrupted config. Combination not implemented.");
		} elseif ("shuffle_reverse"==$sto_emailShroud_encryption)	{
			$result .=
				"href=\"$sto_emailShroud_redirectURLbase?"
				."encryptedAddress=".urlencode($encryptedAddress)."&amp;"
				."ver=".$sto_emailShroud_version
				."\"";
		} else {
			$result .= 
				"href=\"$sto_emailShroud_redirectURLbase?domainName=$domain&amp;userName=$username&amp;ver=$sto_emailShroud_version\" ";
		}
		$result .= 
			"$matchingText[7]>"
			."$matchingText[1]$matchingText[8]</a>";
	}
	return $result;
}

add_filter('the_content', 'sto_emailShroud_mainFilter', 55); /* Ensures that it is run after wp_texturize. Priority must be greater than 50 to work with Markdown 1.0.1b. */
add_filter('comment_text', 'sto_emailShroud_mainFilter', 55); /* Priority ensures that it is run after the cleanup which adds line breaks everywhere. */
add_filter('comment_excerpt', 'sto_emailShroud_mainFilter',55);
add_filter('get_the_excerpt', 'sto_emailShroud_mainFilter',9); /* Need to beat the trim function to avoid double handling. Double handling may lead to munging if a @nospam action plan is used. */
add_filter('the_excerpt_rss', 'sto_emailShroud_mainFilter',55);

/* sto_emailShroud_insertScript()  generates the JavaScript that is inserted in the footer of each page.
     The JavaScript hunts for the email addresses that were modified in the body of the page, and changes them
	 back to the original text (or at least, as close to it as feasible.). */
function sto_emailShroud_insertScript() {
	global $sto_emailShroud_matches;
	if(count($sto_emailShroud_matches)>0)
	{
		$sto_emailShroud_encryption = get_option("sto_emailShroud_encryption");
		?>
<script type="text/javascript" src="<?php print get_bloginfo("url")?>/index.php?emailShroud=replace.js"></script>
<?php
		if("3DES"==$sto_emailShroud_encryption)
		{ ?>
<script type="text/javascript" src="<?php print get_bloginfo("url")?>/index.php?emailShroud=encode.js"></script>
<script type="text/javascript" src="<?php print get_bloginfo("url")?>/index.php?emailShroud=DES.js"></script>
<script type="text/javascript" src="<?php print get_bloginfo("url")?>/index.php?emailShroud=3DES_decrypt.js"></script>
<script type="text/javascript"><!--
<?php
	foreach ($sto_emailShroud_matches as $email_match)
	{
		print "		sto_emailShroud_3DES_decrypt(\"$email_match[0]\", \"$email_match[1]\", \"$email_match[2]\", \"$email_match[3]\", $email_match[4]);\n";
	};
?>
// --></script>				
<?php	}
		elseif ("shuffle_reverse"==$sto_emailShroud_encryption)
		{ ?>
<script type="text/javascript" src="<?php print get_bloginfo("url")?>/index.php?emailShroud=encode.js"></script>
<script type="text/javascript" src="<?php print get_bloginfo("url")?>/index.php?emailShroud=DES.js"></script>
<script type="text/javascript" src="<?php print get_bloginfo("url")?>/index.php?emailShroud=shuffle_reverse_decrypt.js"></script>
<script type="text/javascript"><!--
<?php
	foreach ($sto_emailShroud_matches as $email_match)
	{
		print "		sto_emailShroud_shuffle_and_reverse_decrypt(\"$email_match[0]\", \"$email_match[1]\", $email_match[2]);\n";
	};
?>
// --></script>				
<?php	}
		else
		{
		 ?>
<script type="text/javascript" src="<?php print get_bloginfo("url")?>/index.php?emailShroud=rearrangement_decrypt.js"></script>
<script type="text/javascript"><!--
<?php
	foreach ($sto_emailShroud_matches as $email_match)
	{
		print "		sto_emailShroud_rearrangement_decrypt(\"$email_match[0]\", \"$email_match[1]\", \"$email_match[2]\", $email_match[3]);\n";
	};
?>
// --></script>	
<?php	}
	}
}
add_action('wp_footer', 'sto_emailShroud_insertScript');

// *********************************************************************************
// JavaScript file redirects.
// Allows the retrieval of standard pieces of JavaScript from files to promote browser cacheing.
// *********************************************************************************

function sto_emailShroud_query_vars ( $vars ) {
	$vars[] = "emailShroud";
	return $vars;
}
add_filter('query_vars', 'sto_emailShroud_query_vars');

// Last-Modified is coarsely faked to approximate a long time, to encourage cacheing.
function sto_emailShroud_show_javascript() {
	$filenameParameter = get_query_var("emailShroud");
	if ($filenameParameter == "DES.js"
	    || $filenameParameter == "encode.js"
		|| $filenameParameter == "replace.js"
		|| $filenameParameter == "rearrangement_decrypt.js"
		|| $filenameParameter == "3DES_decrypt.js"
		|| $filenameParameter == "shuffle_reverse_decrypt.js")
	{
		header("HTTP/1.x 200 OK");
		header('Content-type: application/x-javascript');
		header('Cache-Control: max-age = 7776000');
		echo file_get_contents(dirname(__FILE__)."/".$filenameParameter);
		exit();
	}
}
add_action('parse_query', 'sto_emailShroud_show_javascript');

// *********************************************************************************
// Plugin Administration Code
// *********************************************************************************

function sto_emailShroud_options_page() {
    if (function_exists('add_options_page')) {
	add_options_page('EmailShroud', 'EmailShroud', 'manage_options', basename(__FILE__), 'sto_emailShroud_options_subpanel');
    }
}

add_action('admin_menu', 'sto_emailShroud_options_page');

function sto_emailShroud_options_subpanel() 
{
    if(get_option("sto_emailShroud_actionPlan")) {
        /* If one is set, blithely assume that all are set. */
	$sto_emailShroud_actionPlan = get_option("sto_emailShroud_actionPlan");
	$sto_emailShroud_prefixString = sto_emailShroud_stripLeftAndRight(get_option("sto_emailShroud_prefixString"));
	$sto_emailShroud_replacementString = sto_emailShroud_stripLeftAndRight(get_option("sto_emailShroud_replacementString"));
	$sto_emailShroud_suffixString = sto_emailShroud_stripLeftAndRight(get_option("sto_emailShroud_suffixString"));
	$sto_emailShroud_encryption = get_option("sto_emailShroud_encryption");

    } else {
	$sto_emailShroud_actionPlan = "sto";
	$sto_emailShroud_prefixString = " [Email address: ";
	$sto_emailShroud_replacementString = " #AT# ";
	$sto_emailShroud_suffixString = " - replace #AT# with @ ]";
	$sto_emailShroud_encryption = "rearrangement";
    };
    if (isset($_POST['info_update'])) {
        ?><div class="updated"><p><strong><?php 
        /* Note: At first blush, it appears there is insufficient security checking of the data here.
		For example, the code should not ontain anything that will fool the preg_match script (e.g. $ signs).
		There should also not be any code that can't appear in a value tag (e.g. double-quote marks)
	         While this may confuse the user, the risk of hostile attack is negligible. You need admin access to see this page. */
        if(isset($_POST['sto_emailShroud_actionPlan'])) {
           $sto_emailShroud_actionPlan = $_POST["sto_emailShroud_actionPlan"];
		}
        if(isset($_POST['sto_emailShroud_encryption'])) {
            $sto_emailShroud_encryption = $_POST["sto_emailShroud_encryption"];
		}
		$compatible = ("3DES"!=$sto_emailShroud_encryption) || ("sto"!= $sto_emailShroud_actionPlan);
		if (!$compatible)
		{
			_e("WARNING: 3DES security not permitted with this Action Plan. Security lowered to Shuffle/Reverse.", "emailShroud");
			echo "<br/>";
			$sto_emailShroud_encryption = "shuffle_reverse";
		}
        if(isset($_POST['sto_emailShroud_actionPlan'])) {
           update_option("sto_emailShroud_actionPlan", $sto_emailShroud_actionPlan);
           _e("Successfully set field: ", "emailShroud");
           echo "sto_emailShroud_actionPlan = '".$sto_emailShroud_actionPlan."'<br/>";
        };
        if(isset($_POST['sto_emailShroud_encryption']) || !$compatible) {
            update_option("sto_emailShroud_encryption", $sto_emailShroud_encryption);
           _e("Successfully set field: ", "emailShroud");
           echo "sto_emailShroud_encryption = '".$sto_emailShroud_encryption."'<br/>";
        };
        if(isset($_POST['sto_emailShroud_prefixString'])) {
            $sto_emailShroud_prefixString = $_POST["sto_emailShroud_prefixString"];
            update_option("sto_emailShroud_prefixString", "~".$sto_emailShroud_prefixString."~");
            _e("Successfully set field: ", "emailShroud");
            echo "sto_emailShroud_prefixString = '".$sto_emailShroud_prefixString."'<br/>";
        }
        if(isset($_POST['sto_emailShroud_replacementString'])) {
           $sto_emailShroud_replacementString = $_POST["sto_emailShroud_replacementString"];
           update_option("sto_emailShroud_replacementString", "~".$sto_emailShroud_replacementString."~");
           _e("Successfully set field: ", "emailShroud");
           echo "sto_emailShroud_replacementString = '".$sto_emailShroud_replacementString."'<br/>";
        }
        if(isset($_POST['sto_emailShroud_suffixString'])) {
           $sto_emailShroud_suffixString = $_POST["sto_emailShroud_suffixString"];
           update_option("sto_emailShroud_suffixString", "~".$sto_emailShroud_suffixString."~");
           _e("Successfully set field: ", "emailShroud");
           echo "sto_emailShroud_suffixString = '".$sto_emailShroud_suffixString."'<br/>";
        }
        _e('Successfully updated.', 'emailShroud')?></strong></p></div><?php
    } /* Info Updated */ ?>
 <div class=wrap>
	<form method="post">
		<h2>EmailShroud Options</h2>
		 <fieldset name="ActionPlan">
			<legend><?php _e('No JavaScript Action Plan', 'emailShroud'); ?></legend>
			<?php _e("If the reader's browser does not handle JavaScript, what action should be taken?", 'emailShroud'); ?><br/>
			<input type="radio" <?php if("sto" == $sto_emailShroud_actionPlan){echo "CHECKED ";}?>name="sto_emailShroud_actionPlan" value="sto"/><?php _e("Divert to decoder page on EmailShroud site.", 'emailShroud'); /* Note to potential translators: This site is in English only. Mention that in the translated version! */?><br/>
			<input type="radio" <?php if("replace" == $sto_emailShroud_actionPlan){echo "CHECKED ";}?>name="sto_emailShroud_actionPlan" value="replace"/><?php _e("Transform address:", 'emailShroud'); ?><br/>
					<ul>
						<li><?php _e("Add prefix:", 'emailShroud');?><input name="sto_emailShroud_prefixString" type="text" size="30" value="<?php echo $sto_emailShroud_prefixString?>"/></li>
						<li><?php _e("Replace '@' with: ", 'emailShroud');?><input name="sto_emailShroud_replacementString" type="text" size="10" value="<?php echo $sto_emailShroud_replacementString?>"/></li>
						<li><?php _e("Add suffix:", 'emailShroud');?><input name="sto_emailShroud_suffixString" type="text" size="30" value="<?php echo $sto_emailShroud_suffixString?>"/></li>
					</ul>
		</fieldset>
		<fieldset name="SecuritySettings">
			<legend><?php _e('Security Settings', 'emailShroud'); ?></legend>
			<?php _e("How would you like to trade-off between security and speed?", 'emailShroud'); ?>
			<select name="sto_emailShroud_encryption">
				<option <?php if("rearrangement" == $sto_emailShroud_encryption){echo "SELECTED ";} ?> value="rearrangement">Rearrangement: Somewhat secure, very fast</option>
				<option <?php if("shuffle_reverse" == $sto_emailShroud_encryption){echo "SELECTED ";} ?> value="shuffle_reverse">Shuffle/Reverse: Fairly secure, pretty fast</option>				
				<option <?php if("3DES" == $sto_emailShroud_encryption){echo "SELECTED ";} ?> value="3DES">3DES: Very secure, very slow</option>
			</select><br/>
			<?php _e("Note: The '3DES' security setting is unavailable when using the 'Divert to the decoder page' Action Plan.", 'emailShroud'); ?>
		</fieldset>
		<div class="submit">
			<input type="submit" name="info_update" value="<?php _e('Update options'); ?>" />
		</div>
	</form>
 </div> <?php
} /* sto_emailShroud_options_subpanel */	
?>
