=== Twitter Tools ===
Contributors: alexkingorg, crowdfavorite
Tags: twitter, tweet, integration, post, notify, integrate, archive, widget, shortcode, social
Requires at least: 3.4
Tested up to: 3.4.1
Stable tag: 3.0

Twitter Tools is a plugin that creates a complete integration between your WordPress blog and your Twitter account.

== Details ==

Twitter Tools integrates with Twitter by giving you the following functionality:

* Archive the tweets from your Twitter accounts (downloaded every 10 minutes)
* Create a blog post from each of your tweets
* Create a tweet on Twitter whenever you post in your blog, with a link to the blog post (via Social)
* Browse your tweets locally by @mention, #hashtag or user account (optionally display these publicly)

Twitter Tools leverages Social's connection to Twitter so that you don't have to create an app and copy keys around. It supports multiple accounts (must be authorized as "global" accounts in Social) with settings on a per-account basis.


== Installation ==

_Twitter Tools relies on the Social plugin to connect to Twitter. If you aren't already using this plugin please install it before installing Twitter Tools._

1. Download the plugin archive and expand it (you've likely already done this).
2. Put the 'twitter-tools' directory into your wp-content/plugins/ directory.
3. Go to the Plugins page in your WordPress Administration area and click 'Activate' for Twitter Tools.
4. Go to the Twitter Tools Options page (Settings > Twitter Tools) to set up your Twitter information and preferences.


== Upgrading ==

If you have upgraded from an older version of Twitter Tools, your data will need to be converted to the new Twitter Tools format. On the Twitter Tools Options page you will see a prompt to upgrade if appropriate. Follow the steps to convert your data.

Twitter Tools now stores complete Twitter data along with your basic tweet content. Over time, Twitter Tools will request this data for upgraded tweets. This process make take a few days, as only 10 tweets are requested per hour (to avoid egatively impacting your rate limit).


== Managing your Tweets ==

You can view, edit and delete (or unpublish) your Tweets

== Displaying your Tweets ==




## Rough Outline

- managing tweets using UI


- public URLs for tweets, and by taxonomy

- shortcode syntax
- widget options

- create blog posts


- outline a few customization points


### FAQ

- What if I don't want to use Social's comment display?