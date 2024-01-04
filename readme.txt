=== EWWW Image Optimizer ===
Contributors: nosilver4u
Donate link: https://ewww.io/donate/
Tags: optimize, image, convert, webp, resize, compress, lazy load, optimization, lossless, lossy, seo, scale
Requires at least: 6.1
Tested up to: 6.4
Requires PHP: 7.3
Stable tag: 7.2.3
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

You may report security issues through our Patchstack Vulnerability Disclosure Program. The Patchstack team helps validate, triage and handle any security vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/vdp/ewww-image-optimizer)

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

Free mode using local server compression is supported on Windows, Linux, MacOS, and FreeBSD. The Compress API and Easy IO CDN will work on any OS.

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

= 7.2.3 =
*Release Date - January 4, 2024*

* fixed: Easy IO incorrectly modifies JS/CSS URLs when using S3 on multisite
* fixed: regression with WP Offload Media compatibility and incorrect ContentType for WebP images
* fixed: local backup folder not protected from optimization

= 7.2.2 =
*Release Date - December 12, 2023*

* fixed: Lazy Load compatibility with X/Pro themes and Cornerstone builder
* fixed: JPG quality level ignored during PNG to JPG conversion
* fixed: too much scaling for Visual Composer background images with zoom effect
* fixed: Perfect Images compatibility function broken during image upload
* fixed: Easy IO strips extra sub-folders in non-image URLs
* fixed: compatibility with NextGEN Gallery 3.50+
* fixed: optimization of dynamic thumbs for NextGEN Gallery

= 7.2.1 =
*Release Date - September 7, 2023*

* changed: Scheduled Optimizer skips image errors faster
* changed: use updated coding standards, and restructure code for async/background functions
* removed: legacy image editor extensions for unmaintained plugins
* security: randomize filename of debug log

= 7.2.0 =
*Release Date - July 20, 2023*

* added: Easy IO rewrites poster/thumbnail image URLs for video elements
* changed: Easy IO + Auto Scale checks images on load and resize events to reduce browser upscaling
* changed: prevent Easy IO font substitution when OMGF is active
* fixed: Auto Scale downscales too much for landscape images displayed in portrait containers
* fixed: Easy IO compatibility with Brizy thumbnail generation endpoint

= 7.1.0 =
*Release Date - June 29, 2023*

* added: deliver Google Fonts via Easy IO or Bunny Fonts for improved user privacy
* fixed: PHP error trying to save EXIF data to JPG after resizing
* fixed: could not disable auto-scaling
* fixed: prevent errors when using legacy Animated GIF Resizing plugin
* fixed: prevent WP Offload Media from prematurely re-offloading when using bulk optimizer

= Earlier versions =
Please refer to the separate changelog.txt file.

== Credits ==

Written by [Shane Bishop](https://ewww.io) with special thanks to my [Lord and Savior](https://www.iamsecond.com/). Based upon CW Image Optimizer, which was written by [Jacob Allred](http://www.jacoballred.com/) at [Corban Works, LLC](http://www.corbanworks.com/). CW Image Optimizer was based on WP Smush.it. Jpegtran is the work of the Independent JPEG Group. PEL is the work of Martin Geisler, Lars Olesen, and Erik Oskam. Easy IO and HTML parsing classes based upon the Photon module from Jetpack.
