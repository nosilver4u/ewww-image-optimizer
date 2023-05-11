=== EWWW Image Optimizer ===
Contributors: nosilver4u
Donate link: https://ewww.io/donate/
Tags: optimize, image, convert, webp, resize, compress, lazy load, optimization, lossless, lossy, seo, scale
Requires at least: 5.8
Tested up to: 6.2
Requires PHP: 7.2
Stable tag: 7.0.0
License: GPLv3

Smaller Images, Faster Sites, Happier Visitors. Comprehensive image optimization that doesn't require a degree in rocket science.

== Description ==

Are you frustrated by a slow website? Do over-sized images make you say "ewww"... Let EWWW Image Optimizer help you make your site faster, improve your bounce rate, and boost your SEO. But most importantly, make your visitors happier so they keep coming back for more.

With EWWW IO you can optimize all your existing images, [from any plugin](https://docs.ewww.io/article/84-plugin-compatibility), and then let EWWW IO take care of new image uploads automatically.

**Why use EWWW Image Optimizer?**

1. **No Speed Limits** and [unlimited file size](https://ewww.io/unlimited-file-size/).
1. **Smooth Handling** with pixel-perfect optimization using industry-leading tools and progressive rendering.
1. **High Torque** as we bring you the best compression/quality ratio available with our Premium compression for JPG, PNG, and PDF files.
1. **Adaptive Steering** with intelligent conversion options to get the right image format for the job (JPG, PNG, GIF, or WebP).
1. **Free Parking** The core plugin is free and always will be. However, our paid services offer up to 80% compression, and a [host of other features](https://ewww.io/plans/)!
1. **Comprehensive Coverage:** no image gets left behind, optimize everything on your site, not just the WordPress Media Library.
1. **Safety First:** all communications are secured with top SSL encryption.
1. **Roadside Assistance:** top-notch support is in our DNA. While API customers get top priority, we answer [every single support question with care](https://ewww.io/contact-us/).
1. **Pack a Spare:** free image backups store your original images for 30 days.

EWWW IO is the only plugin that lets you optimize images using tools on your own server (jpegtran, optipng, pngout, pngquant, gifsicle, cwebp). If you feel the need for more speed, get more compression and offload the CPU-intensive process of optimization to [our specialized servers](https://ewww.io/plans/).

= Automatic Everything =

With Easy IO, images are automatically compressed, scaled to fit the page and device size, lazy loaded, and converted to the next-gen WebP format.

= Support =

Stuck? Feeling like maybe you DO need that rocket science degree? [We provide free one-on-one email support to everyone](https://ewww.io/contact-us/).
Do you have an idea to make EWWW IO even better? [Share it and vote on future features](https://feedback.ewww.io/b/features)!
Found a bug? Report the issue on [GitHub](https://github.com/nosilver4u/ewww-image-optimizer), and we'll get it fixed!

= Bulk Optimize =

Optimize all your images from a single page. This includes the Media Library, your theme, and a handful of pre-configured folders (see Optimize Everything Else below). GRAND FlaGallery, NextCellent and NextGEN have their own Bulk Optimize pages.

= Optimize Everything Else =

Configure any folder within your WordPress install to be optimized. The Bulk Optimizer will compress theme images, BuddyPress avatars, BuddyPress Activity Plus images, Meta Slider slides, WP Symposium Pro avatars, GD bbPress attachments, Grand Media Galleries, and any user-specified folders. You can also use Scheduled optimization or run the optimizer from WP-CLI if that's more your thing.

= Plugin Compatibility =

EWWW IO has been tested with hundreds (if not thousands) of [plugins and themes](https://docs.ewww.io/article/84-plugin-compatibility), here are just a few of the most common ones: BuddyPress (Activity Plus add-on too), Cloudinary, Easy Watermark, FileBird, FooGallery, GD bbPress Attachments, GRAND FlAGallery, Gmedia Photo Gallery, MediaPress, Meta Slider, Microsoft Azure Storage, MyArcadePlugin, NextGEN Gallery, Regenerate Thumbnails, [Weglot](https://weglot.com/integrations/wordpress-translation-plugin/demo/), WP Offload Media, [WPML](https://wpml.org/plugin/ewww-image-optimizer/), WP Retina 2x, WP RSS Aggregator, WP Symposium. [Read more...](https://docs.ewww.io/article/84-plugin-compatibility)

= WebP Images =

If you want simple, get automatic WebP conversion with Easy IO, and be done with it! Otherwise, you can generate WebP versions of your images with the Bulk Optimizer, and deliver them to supported browsers. Take your pick between Apache-style rewrite rules, JS WebP Rewriting, and <picture> WebP Rewriting. EWWW IO even works with the WebP option in the Cache Enabler plugin from KeyCDN.

= WP-CLI =

Allows you to run all Bulk Optimization processes from your command line, instead of the web interface. It is much faster, and allows you to do things like run it in 'screen' or via regular cron (instead of wp-cron, which can be unpredictable on low-traffic sites). Install WP-CLI from wp-cli.org, and run 'wp-cli.phar help ewwwio optimize' for more information or see the [Docs](https://docs.ewww.io/article/25-optimizing-with-wp-cli).

= CDN Support =

[WP Offload Media](https://wordpress.org/plugins/amazon-s3-and-cloudfront/) is the officially supported (and recommended) plugin for uploads to Amazon S3, Digital Ocean Spaces, and Google Cloud Storage. [Check our compatibility list for details on other plugins](https://docs.ewww.io/article/84-plugin-compatibility). All pull mode CDNs like Cloudflare, KeyCDN, MaxCDN, and Sucuri CloudProxy work automatically, but will require you to purge the cache after a bulk optimization.

= Translations =

Huge thanks to all our translators, [see the full list](https://translate.wordpress.org/projects/wp-plugins/ewww-image-optimizer/contributors)!

If you would like to help translate this plugin, [join the team](https://translate.wordpress.org/projects/wp-plugins/ewww-image-optimizer).
[Signup to receive updates when new strings are available for translation](https://ewww.io/register/).

== Installation ==

1. Upload the "ewww-image-optimizer" plugin to your /wp-content/plugins/ directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. The plugin will attempt to install jpegtran, optipng, and gifsicle automatically for you. This requires that the wp-content folder is writable by the user running the web server.
1. If the binaries don't run locally, you may sign up for cloud-based optimization: https://ewww.io/plans/
1. *Recommended* Visit the settings page to enable/disable specific tools and turn on advanced optimization features.
1. Done!

If these steps do not work, [see the additional documentation](https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something). If you need further assistance using the plugin, please visit our [Support Page](https://ewww.io/contact-us/).

= Webhosts =

To find out if your webhost works with the EWWW Image Optimizer, you can check the [official list](https://docs.ewww.io/article/43-supported-web-hosts).

== Frequently Asked Questions ==

= Does the plugin remove EXIF and/or IPTC metadata?

EWWW IO will remove metadata by default, but if you need to keep the EXIF/IPTC data for copyright purposes, you can disable the Remove Metadata option.
EXIF data does not impact SEO, and it is recommended by Google (and just about everyone else) to remove EXIF data.

= Google Pagespeed says my images need compressing or resizing, but I already optimized all my images. What do I do? =

Try this for starters: [https://docs.ewww.io/article/5-pagespeed-says-my-images-need-more-work](https://docs.ewww.io/article/5-pagespeed-says-my-images-need-more-work)

= The plugin complains that I'm missing something, what do I do? =

This article will walk you through installing the required tools (and the alternatives if installation does not work): [https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something](https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something)

= Does the plugin replace existing images? =

Yes, but only if the optimized version is smaller. The plugin should NEVER create a larger image.

= Can I resize my images with this plugin? =

Yes, you can, set it up on the Resize tab.

= Can I lower the compression setting for JPGs to save more space? =

Our premium compression can determine the ideal quality setting and give you the best results, but you may also adjust the default quality for conversion and resizing. [Read more...](https://docs.ewww.io/article/12-jpq-quality-and-wordpress)

= The bulk optimizer doesn't seem to be working, what can I do? =

See [https://docs.ewww.io/article/39-bulk-optimizer-failure](https://docs.ewww.io/article/39-bulk-optimizer-failure) for full troubleshooting instructions.

= What are the supported operating systems? =

I've tested it on Windows (with Apache), Linux, Mac OSX, FreeBSD, and Solaris. The Compress API and Easy IO CDN will work on any OS.

= I want to know more about image optimization, and why you chose these options/tools. =

That's not a question, but since I made it up, I'll answer it. See this resource:
[https://developers.google.com/web/tools/lighthouse/audits/optimize-images](https://developers.google.com/web/tools/lighthouse/audits/optimize-images)

== Screenshots ==

1. Plugin settings page.
2. Additional optimize column added to media listing. You can see your savings, manually optimize individual images, and restore originals (converted only).
3. Bulk optimization page. You can optimize all your images at once and resume a previous bulk optimization. This is very useful for existing blogs that have lots of images.

== Changelog ==

* Feature requests can be viewed and submitted on our [feedback portal](https://feedback.ewww.io/b/features)
* If you would like to help translate this plugin in your language, [join the team](https://translate.wordpress.org/projects/wp-plugins/ewww-image-optimizer/)

= 7.0.0 =
* breaking: namespaced and reorganized several classes, third party integrations should check for compatibility
* added: allow video files to go through Easy IO CDN (pass through)
* added: support for WP_Image_Editor_Imagick::set_imagick_time_limit() method added in WP 6.2
* added: ewwwio_inline_webp_script_attrs filter to add custom data-* attributes to the JS WebP inline scripts
* added: Easy IO support for BuddyBoss images, video, and documents
* added: Bulk Optimizer and Scheduled Optimizer include BuddyBoss profile and cover image folders automatically
* added: backup images post-resize but pre-compression with the ewww_image_optimizer_backup_post_resize filter
* added: improved support for Hide My WP Ghost in Lazy Load, and WebP rewriting engine 
* added: update attachment metadata for WPML replicas after image conversion
* changed: improved Auto Scaling when using full-width layout in Elementor
* changed: use fread to check mimetype of files for better performance
* changed: style tag search/regex cleaned up to prevent excess markup
* fixed: WebP images are added to WP Offload Media queue multiple times
* fixed: PHP 8.1 deprecation notices from usage of add_submenu_page and add_query_arg
* fixed: debug notice cannot be dismissed on sub-sites for network-activated installs
* fixed: PHP notice when cleaning attachment metadata
* fixed: error when certain options have been stored as strings rather than serialized arrays
* fixed: tool path and content dir functions don't resolve symlinks
* fixed: Easy IO image URLs leaking into image gallery block via post editor
* fixed: JS WebP issues when body tag has script attributes
* fixed: clearing debug log does not redirect back to settings page in rare cases

= 6.9.3 =
* changed: improved Brizy Builder compatibility
* changed: async optimization defers processing by WP Offload Media until after optimization is complete, fixes issues with WP Offload Media 3.1+
* fixed: converting an image with the same base name as a previous upload (image.png vs. image.jpg) could cause naming conflict when using WP Offload Media with Remove Local Media option
* fixed: Bulk Optimize encounters unrecoverable error when a GIF or PDF file takes too long to optimize
* fixed: Easy IO fails to apply crop for custom size in some cases
* fixed: Picture WebP rewriter uses mixed single/double quotes
* fixed: PHP warnings when bulk optimizing images on cloud storage with no local copies
* improved: ensure originals are removed from local storage after conversion when using WP Offload Media with Remove Local Media option
* improved: ensure originals are queued for removal from remote storage after conversion and subsequent deletion when using WP Offload Media

= 6.9.2 =
* changed: improved Easy IO detection for site URL changes
* changed: load backup class earlier to prevent issues with custom image uploaders
* fixed: and improved the ewwwio_translated_media_ids filter, props @ocean90
* fixed: Lazy Load JS throws error if inline script vars are missing
* fixed: Easy IO + Lazy Load auto-scale produces invalid URL if an image with no query string is constrained by height

= 6.9.1 =
* changed: default syntax for MySQL 8.x to use faster upgrade query
* fixed: bulk action parameter was not validated properly when selecting attachments for optimization
* fixed: undefined function ewww_image_optimizer_get_primary_wpml_id
* fixed: PHP notices when Easy IO filters srcset URLs

= 6.9.0 =
* added: allow translation plugins to filter attachment IDs for retrieving Media Library results via ewwwio_primary_translated_media_id/ewwwio_translated_media_ids
* changed: include upstream lazysizes unveilhooks for use by developers, props @saas786
* fixed: Easy IO compatibility with S3 Uploads 3.x
* fixed: better compatibility with S3 Uploads when using autoload
* fixed: PHP notices when removing images and backups are disabled
* fixed: trailing comma after parameters in WP-CLI remove_originals function
* fixed: Easy IO srcset URL construction not accounting for object versioning with S3 (or other cloud storage)

= 6.8.0 =
* added: ability to store image backups on local storage
* added: tool to bulk restore images under Tools menu and WP-CLI
* added: WebP cleanup tool can be resumed and run via WP-CLI
* added: Delete Originals can be run via WP-CLI
* added: remove originals after conversion (like PNG to JPG) via WP-CLI
* added: exclude by page for Easy IO, Lazy Load, and WebP delivery methods
* changed: ensure full-size image is optimized after resizing with Imsanity
* fixed: incorrect cfasync attribute used for JS WebP scripts

= 6.7.0 =
* added: API keys can be used to auto-register sites for Easy IO, including sub-keys
* changed: expose legacy resize dimensions with removal option
* fixed: Lazy Load not using EWWWIO_CONTENT_DIR
* fixed: Easy IO Premium/WebP compression disabled incorrectly when in Easy Mode
* fixed: JS WebP body script throws error if wp_head script missing
* fixed: Lazy Load Auto-scale adds query parameters to SVG images
* fixed: JS WebP and Lazy Load prevent image loading in GiveWP iframe
* fixed: Auto Scale crops too much for object-* images in Oxygen
* fixed: trailing space on image URL handled incorrectly
* updated: Gifsicle to version 1.93 and Pngquant to 2.17
* removed: free binaries for SunOS, may use free cloud-based JPG compression instead

= Earlier versions =
Please refer to the separate changelog.txt file.

== Credits ==

Written by [Shane Bishop](https://ewww.io) with special thanks to my [Lord and Savior](https://www.iamsecond.com/). Based upon CW Image Optimizer, which was written by [Jacob Allred](http://www.jacoballred.com/) at [Corban Works, LLC](http://www.corbanworks.com/). CW Image Optimizer was based on WP Smush.it. Jpegtran is the work of the Independent JPEG Group. PEL is the work of Martin Geisler, Lars Olesen, and Erik Oskam. Easy IO and HTML parsing classes based upon the Photon module from Jetpack.
