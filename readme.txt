=== Plugin Name ===
Contributors: juliano
Tags: email, spam, mailto
Requires at least: 2.0.3
Tested up to: 4.2.2
Stable tag: trunk
License: Gnu Artistic Licence
License URI: http://somethinkodd.com/emailshroud/emailshroud_licence.txt

Prevents email address harvesting by replacing mailto: references in anchor tags with obfuscated form.

== Description ==

In order for spammers to send email to millions of people, they need millions of email addresses. One way to get these addresses is to automatically search the web, harvesting email addresses from unsuspecting web-sites. EmailShroud helps to protect email addresses that are published on a WordPress Blog.

*Note: EmailShroud is not like most of the anti-spam plugins for WordPress. EmailShroud does not protect the blog against Comment Spam. EmailShroud helps to protect the owner, authors and other people mentioned on a blog from receiving email spam.*

EmailShroud does more than just use “escape codes”, which is a poor-man’s solution to this problem.

It uses JavaScript to “obfuscate” the email address. Spammers don’t run JavaScript during their harvesting, as it would take too much effort and is unlikely to help produce many more email addresses. Almost all browsers used to actually read blogs do run JavaScript – the browser transparently decodes the email address without the reader even noticing.

EmailShroud gracefully handles browsers that are not running JavaScript.

== Installation ==

1. Upload the extracted files into a directory called `emailshroud` under the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress.

The system is now installed and activated. It will handle almost all of the situations and almost all of your readers’ browsers.

Learn more about [the advanced settings you can tweak](http://www.somethinkodd.com/oddthinking/emailshroud-wordpress-plugin/emailshroud-advanced-settings/).

== Frequently Asked Questions ==

= What will EmailShroud detect and protect? =

EmailShroud will search for email addresses in the following places:

* The contents of WordPress pages.
* The contents of posts.
* The contents of post excerpts.
* The contents of RSS feeds.

It will search for:

* Links to email addresses (i.e. anchor tags with mailto addresses.)
* Email addresses written in the content of a post with the text mailto: in front of it.
* Email addresses simply written in the content of a post.

= What *won't* EmailShroud detect and protect? =

For almost all typical uses, EmailShroud works painless out of the box.

In the following rare circumstances, EmailShroud may pass through the email addresses, unprotected:

* Domain names with multiple consecutive dashes.
* Email addresses in WordPress page titles and post titles.
* Where the anchor tag is malformed, so it is not recognized as an anchor tag.
* Where the email tag appears outside of the pages, posts, excerpts and RSS feeds. In particular, in a list of links in a side-bar or in templates.

In the following rare circumstances, EmailShroud may *damage* existing links:

* Where a user-name and password is included in a URL.
  * i.e. using the userinfo subcomponent of a URL.
  * This is rarely used outside of phishing attempts.
* Where cc, bcc and subjects are provided in an anchor tag, they may be stripped out.
* Where email addresses are included in title or similar attributes inside an anchor tag, they will be replaced with the user name.
  * There has been a report of this happening in input forms.
* Where the anchor tag is malformed, so it is not recognized as an anchor tag.
* Automatically generated excerpts may have their email addresses stripped.
* Email addresses in Category Descriptions.

More [information about the limitations](http://www.somethinkodd.com/oddthinking/emailshroud-wordpress-plugin/emailshroud-features-and-limitations/#limitations) is available.

= Is it XHTML compliant? =

EmailShroud should work in themes that use XHTML Strict and Transitional.

== Screenshots ==

No screenshots are provided.

== Changelog ==

= 2.2.1 = 
June 2015

No change to functionality. Revamp packaging to make it hostable on WordPress.org, and thus easier to install.

= 2.2.0 = 
Live, 29 Dec 2007

This release involved a substantial change to the way the the Javascript is triggered. This should have no impact on the blog reader or the WordPress admin, but should make the theme-writers happy. The code now should support XHTML-compliant themes without warnings.

(If you have EmailShroud 2.0 or 2.1 installed, I would suggest that you upgrade, but it isn’t mandatory.)

* Features Added
  * EmailShroud now checks for cases where the email address is included more than once in an anchor tag, and replaces the offending data with the username.
    * Note: This will affect cc: and bcc: addresses parameters in anchor tags.
  * JavaScript now has (very long) expiry time to promote your users caching the JavaScript. That helps the page load faster and lowers the load on your server
  * Email addresses directly in the text will now be “de-shrouded” to the entire email address rather than just the user name part; this makes EmailShroud less intrusive on your text for JavaScript-running users.
* Bugs Fixed
  * Rearranged code to remove need for non-standard attributes; this should help XHTML-validation.

= 2.1.0 =

Live, mid April 2007.

This release fixed a small number of trivial errors. (If you have an EmailShroud 2.0 and you are happy with it, I recommend you don’t bother upgrading.)

* Bugs Fixed
  * Plugin now initialises only after all plugins are loaded. This isn’t a visible improvement, but should aid localisation efforts.
  * A broken URL link fixed. (In EmailShroud 2.0, WordPress would guess the right location anyway, so this isn’t a visible improvement.
  * Avoids use of <? short-cuts for PHP, which are configured to be not supported on a small minority of installations. If your installation is working for EmailShroud 2.0, this will have no visible effect.
  * UI now correctly displays default configuration of Action Plan. If you ever updated the Action Plan, this won’t make a difference.

= 2.0.0 =

Released: 14 September 2006

This release was a substantial increase in functionality from the previous release.

* Features Added
  * Ensure no email addresses slip through when one anchor tag contains multiple email addresses – problem observed in field.
  * Support for XHTML Strict themes – problem observed in field. Changed Javascript from inline Document.write to DOM edits. Removes one set of complaints from XHTML validators, but adds another.
  * No follow tags added when redirecting to EmailShroud web-service. Prevents my site being found when searching the web for your name – problem observed in field.
  * Support for new obfuscation options: Shuffle/Replace and 3DES – response to user demand.
  * Published EULA: Artistic License – response to user demand.
  * Support for WordPress 2.0 Roles. No visible change to user.
  * Added version number information.
  * Include Javascript files via redirect, which you don’t care about but was a breakthrough for me.

 * Features Removed
  * Support for link back to own template file. I asked anyone who managed to get it working to contact me. No-one did. Simplified system by removing it. Could be added back if anyone cares.

 * Bugs Fixed
  * If the transformation rules resulted in a still-valid email address, and the excerpt was auto-generated, and the theme showed excerpts, then the transformation would be applied twice.
  * Garbage character removed from Update Options button.

= 2.0.0 Beta =
Live, but not released, 9 September 2006.

= 1.0.1 =
Lowered filter priorities to avoid clash with PHP Markdown 1.0.1b, and later.

= 1.0.0 =

First version to go live.

= 0.91 =
* Beta test version

== Upgrade Notice ==

= 2.2.0 =

No need to upgrade, except that you will be notified if new versions are available.

= 2.1.0 =

No strong need to upgrade, except that you will be notified if new versions are available, and slight performance improvements.

= 1.0.1 =

A revamp making it faster, more flexible and less buggy. You may as well upgrade - it is pretty painless.
