=== Twitter Tools ===
Contributors: alexkingorg, crowdfavorite
Tags: twitter, tweet, integration, post, notify, integrate, archive, widget, shortcode, social
Requires at least: 3.8
Tested up to: 3.8
Stable tag: 3.1
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Twitter Tools is a plugin that creates a complete integration between your WordPress blog and your Twitter account.

== Description ==

Twitter Tools integrates with Twitter by giving you the following functionality:

* Connect multiple Twitter accounts (via Social)
* Archive the tweets from your Twitter accounts (downloaded every 10 minutes)
* Create a blog post from each of your tweets
* Create a tweet on Twitter whenever you post in your blog, with a link to the blog post (via Social)
* Browse your tweets locally by @mention, #hashtag or user account (optionally display these publicly)

Twitter Tools leverages Social's connection to Twitter so that you don't have to create an app and copy keys around. It supports multiple accounts (must be authorized as "global" accounts in Social) with settings on a per-account basis.

**Support Level:** Product Support (we want to fix bugs and make the product great, but do not provide individual support).

**Developers:** [Fork and contribute on GitHub](https://github.com/crowdfavorite/wp-twitter-tools
).

== Installation ==

_Twitter Tools relies on the <a href="http://wordpress.org/extend/plugins/social/">Social</a> plugin to connect to Twitter. If you aren't already using this plugin please install it before installing Twitter Tools._

1. Download the plugin archive and expand it (you've likely already done this).
2. Put the 'twitter-tools' directory into your wp-content/plugins/ directory.
3. Go to the Plugins page in your WordPress Administration area and click 'Activate' for Twitter Tools.
4. Go to the Twitter Tools Options page (Settings > Twitter Tools) to set up your Twitter information and preferences.


== Upgrading ==

If you have upgraded from an older version of Twitter Tools, your data will need to be converted to the new Twitter Tools format. On the Twitter Tools Options page you will see a prompt to upgrade if appropriate. Follow the steps to convert your data.

Twitter Tools now stores complete Twitter data along with your basic tweet content. Over time, Twitter Tools will request this data for upgraded tweets. This process make take a few days, as only 10 tweets are requested per hour (to avoid egatively impacting your rate limit).


== Connecting Accounts ==

Any Twitter accounts connected on the Social settings page are available for Twitter Tools. You can enable them on a per-account account basis, as well as specifying per-account preferences for creating blog posts, etc. (on the Twitter Tools options screen).


== Managing your Tweets ==

You can view, edit and delete (or unpublish) the local copy of your Tweets right in your WordPress admin. Navigate the tweets from the "Tweets" menu item and manage them just as you would any other post type. Twitter Tools does not know if you've deleted a tweet on Twitter, so you'll need to also delete the copy of the tweet from the admin to remove it from your WordPress site.


== Displaying your Tweets ==

Twitter Tools include options to create URLs for your local tweets using the following scheme:

- single tweet: http://alexking.org/tweets/253580615113400321
- account archive: http://alexking.org/tweet-accounts/alexkingorg
- @mention archive: http://alexking.org/tweet-mentions/sogrady
- #hashtag archive: http://alexking.org/tweet-hashtags/monktoberfest

You can enable public URLs for your tweets in your Twitter Tools settings. If you choose not to enable public URLs for your tweets, you can still vuew and manage them from within the admin screens.

= Shortcode =

You can use a shortcode to display a list of tweets.

	[aktt_tweets account="alexkingorg"] 

If you want, you can specify some additional parameters to control how many tweets are displayed:

	[aktt_tweets account="alexkingorg" count="5" offset="0"]

You can also choose to explicitly include or exclude replies and retweets:

	[aktt_tweets account="alexkingorg" include_rts="0" include_replies="1"]

If you want to limit the tweets to specific @mentions or #hashtags, you can to that as well:

	[aktt_tweets account="alexkingorg" mentions="crowdfavorite,twittertools" hashtags="wordpress,plugin,twittertools"]

= Widget =

The options for the shortcode are also available for the Twitter Tools widget via a few settings.

= Create Blog Posts =

Twitter Tools can create a blog post from each of your Tweets. This feature can be enabled on a per-account basis. If there is an image included in the media data of the tweet Twitter Tools will try to save that image as the featured image for the post and append it to the blog post content.

Please note that this will take effect for all future tweets, it does not retroactively create posts for older tweets (though you could pretty easily script it to do so if you desired).


== Customization ==

Twitter Tools is designed to be customizable via the standard hook/filter API. If you find you need additional hooks (or to suggest other bug fixes and enhancements) please create a pull request on GitHub.

https://github.com/crowdfavorite/wp-twitter-tools

Get creative! Here are some examples of ways to use more of the full Twitter data to create links back into Twitter where appropriate:

- linking to the original tweet on Twitter
- linking to "in reply to" tweets


== Frequently Asked Questions ==

= What if I don't want to use Social's comment display? =

All of Social's features (broadcasting, comment display, looking for responses on Twitter and Facebook and the ability to log in with Twitter or Facebook) can be disabled on Social's settings screen.

= Will Twitter Tools pull in my entire tweet archive from Twitter? =

Twitter Tools starts archiving from the time you enable it. It does not try to download your entire tweet history. However, there is code in Twitter Tools that can be scripted to download and import tweets. You can put together the pieces with your own code to create the combination of features you desire. Here's an Gist to get you started:

https://gist.github.com/3470627

= What happened to the digest features? =

The digest features never worked reliably and were removed in version 3.0. Another developer is welcome to make a plugin that uses the underlying features of Twitter Tools to implement digest features.

= What happened to the default hashtags feature? =

These are no longer needed in 3.0+ since the default broadcast message is now fully customizable in the Social settings. Add your hashtags to your default broadcast message template.

= How do I use a URL-shortener like bit.ly? =

Since Twitter Tools no longer does broadcasting, this is really a [question for Social](http://wordpress.org/extend/plugins/social/faq/). As noted in that FAQ, Social uses the built-in "short URL" feature of WordPress that supports any number of services via their plugins.

== Screenshots ==

1. Show your tweets on your site (optional).
2. Tweets can be viewed by account, @mention or #hashtag.
3. Manage your tweets in the standard WordPress admin interface.
4. View tweets by @mention, #hashtag, etc.
5. Easy interface to for per-account settings.


== Upgrade Notice ==

Version 3.1 brings support for Social 2.10's CRON action names and requires Social 2.10 and WordPress 3.8. It also adds a setting for the publish/draft status of blog posts created from tweets. Now you can set your blog posts to be created as drafts, then publish only the ones you choose to. We've also improved native RT support.


== Changelog ==

= 3.1 =

* (new) Requires Social v2.10
* (new) Setting for post status when creating blog posts from tweets (thanks <a href="https://github.com/crowdfavorite/wp-twitter-tools/pull/22">ShawnDrew</a>)
* (change) Twitter has ended @anywhere so go ahead and link usernames all the time
* (fix) Support for Social v2.10's CRON actions - tweets automatically download again
* (fix) Handle native RTs better (thanks <a href="https://github.com/crowdfavorite/wp-twitter-tools/issues/19">trustin</a>)


= 3.0.4 =

* Support for Twitter API v1.1.


= 3.0.3 =

* Fix a typo that could prevent proper backfilling of tweet data


= 3.0.2 =

* Add `aktt_tweet_create_blog_post` filter to allow other plugins/code to make programatic decisions about when to create blog posts from tweets
* Add `aktt_tweet_create_blog_post_format` filter to allow post format to changed or omitted
* Properly apply title prefix when creating blog posts
* Address misc. multi-byte string issues
* Fix GMT/local time issues and set time properly for tweets and posts
* Properly enable featured image for tweet post type by merging with existing enabled post types


= 3.0.1 =

* Set categories and post tags properly on posts created from tweets
* Set GMT date explicitly for blog posts created from tweets (fixes time offset issue)
* Make enabled/disabled accounts more explicit visually


= 3.0 =

* Complete rewrite!
* Integrates with Social (required) to provide a vastly improved set-up experience
* Broadcasting features are now handled via the Social plugin
* Tweets are stored as a custom post type, providing easy admin access to edit or delete tweets as needed
* Tweets are cross-linked and browsable via custom taxonomies for accounts, @mentions and #hashtags
* Full Twitter is now stored with each tweet
* Comprehensive upgrade routine to migrate existing data and backfill upgraded tweets with full Twitter data
* Additional control over which tweets are displayed via shortcode and widget
* Daily and weekly digest functionality has been removed


= 2.4 =

* Replaced 401 authentication with OAuth.
* Now relies on WordPress to provide JSON encode/decode functions.
* WP 3.0 compatibility fix for hashtags plugin (set default hashtags properly).
* WP 3.0 compatibility fix for creating duplicate post meta.
* Added support form to settings page.


= 2.3.1 =

* Fixed a typo that was breaking the latest tweet template tag.


= 2.3 =

* Added nonces
* Patched several potential security issues (thanks Mark Jaquith)
* Load JS and CSS in separate process to possibly avoid some race conditions


= 2.2.1 =

* Typo-fix that should allow resetting digests properly (not sure when this broke, thanks lionel_chollet).


= 2.2 =

* The use of the native `json_encode()` function, required by the changes in WordPress 2.9 (version 2.1) created a problem for users with servers running 32-bit PHP. the `json_decode()` function treats the tweet ID field as an integer instead of a string, which causes the issues. Thanks to Joe Tortuga and Ciaran Walsh for sending in the fix.


= 2.1.2 =

* Missed one last(?) instance of Services_JSON


= 2.1.1 =

* Missed replacing a couple of instances of Services_JSON


= 2.1 =

* Make install code a little smarter
* Add unique index on tweet ID columns, remove duplicates and optimize table
* Track the currently installed version for easier upgrades in the future
* Cleanup around login test code
* Add action on Update Tweets (aktt_update_tweets)
* Add a shortcode to display recent tweets
* Exclude replies in aktt_latest_tweet() function (if option selected)
* Better RegEx for username and hashtag linking
* Use site_url() and admin_url(), losing backward compatibility but gaining SSL compatibility
* Added WordPress HelpCenter contact info to settings page
* Use standard meta boxes (not backwards compatible) for post screen settings
* Change how Services_JSON is included to be compatible with changes in WP 2.9 and PHP < 5.2
* Digest functionality is marked as experimental, they need to be fundamentally rewritten to avoid race conditions experienced by some users 
* Misc code cleanup and bug fixes
* Added language dir and .pot file

Bit.ly plugin

* Changed RegEx for finding URLs in tweet content (thanks Porter Maus)
* Added a j.mp option
* Cleaned up the settings form
* Added a trim() on the API Key for people that struggle with copy/paste
* Use admin_url(), losing backward compatibility but gaining SSL compatibility

Exclude Category plugin

* Use admin_url(), losing backward compatibility but gaining SSL compatibility

Hashtags plugin

* Use admin_url(), losing backward compatibility but gaining SSL compatibility


= 2.0 =

* Added various hooks and filters to enable other plugins to interact with Twitter Tools.
* Added option to set blog post tweet prefix
* Added CSS classes for elements in tweet list
* Initial release of Bit.ly for Twitter Tools - enables shortening your URLs and tracking them on your Bit.ly account.
* Initial release of #hashtags for Twitter Tools - enables adding hashtags to your blog post tweets.
* Initial release of Exclude Category for Twitter Tools - enables not tweeting posts in chosen categories.
