=== EWWW Image Optimizer ===
Contributors: nosilver4u
Donate link: https://ewww.io/donate/
Tags: optimize, image, convert, webp, resize, compress, lazy load, optimization, lossless, lossy, seo, scale
Requires at least: 5.8
Tested up to: 6.0
Requires PHP: 7.2
Stable tag: 6.7.0
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

= 6.6.0 =
* added: AVIF support via Easy IO, enable on site management at ewww.io
* added: sharpening setting to improve WP resizing via ImageMagick and WebP conversion
* added: AVIF quality setting on Advanced tab
* added: ability for Easy IO to get full-size path when using offloaded media
* changed: JPG quality and WebP quality moved to Advanced tab
* changed: allow .htaccess rules on Cloudways with notice about WebP Redirection toggle
* fixed: front-end HTML parsers running within Bricks editor
* fixed: Easy IO not finding scaled full-size for dynamic size generation
* fixed: cover images not cropped properly by Easy IO
* fixed: Easy IO URLs leaking into post editor with WP 6.0

= 6.5.2 =
* added: automatic optimization for Crop Thumbnails plugin
* added: filters to adjust sharpening parameters for core WP (ImageMagick) image resizing
* changed: Easy IO WebP quality can be defined separately from the JPG quality used for resizing operations
* fixed: Picture WebP rewriting disabled on embeds
* fixed: Lazy Load integration with WooCommerce Product Recommendations handling AJAX output incorrectly
* fixed: PHP notice when checking for presence of mod_rewrite/mod_headers

= 6.5.1 =
* fixed: LQIP option not available on sub-domain multisite install unless Easy IO is active on site 1
* fixed: API quota function doesn't handle expired status correctly

= 6.5.0 =
* added: Lazy Load and Easy IO support for multiple background images and mixing with gradients
* changed: all Easy IO settings available to all plan levels
* changed: Easy IO CDN image links constrained to 2560px, use EIO_PRESERVE_LINKED_IMAGES to override
* fixed: database upgrade fails on MySQL 5.6
* fixed: LQIP and SVG placeholder options not auto-loaded correctly
* fixed: regression in legacy translation loader

= Earlier versions =
Please refer to the separate changelog.txt file.

== Credits ==

Written by [Shane Bishop](https://ewww.io) with special thanks to my [Lord and Savior](https://www.iamsecond.com/). Based upon CW Image Optimizer, which was written by [Jacob Allred](http://www.jacoballred.com/) at [Corban Works, LLC](http://www.corbanworks.com/). CW Image Optimizer was based on WP Smush.it. Jpegtran is the work of the Independent JPEG Group. PEL is the work of Martin Geisler, Lars Olesen, and Erik Oskam. Easy IO and HTML parsing classes based upon the Photon module from Jetpack.
