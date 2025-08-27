=== EWWW Image Optimizer ===
Contributors: nosilver4u
Donate link: https://ewww.io/donate/
Tags: compress, convert, webp, resize, lazy load
Tested up to: 6.8
Stable tag: 8.2.1
License: GPLv3

Smaller Images, Faster Sites, Happier Visitors. Comprehensive image optimization that doesn't require a degree in rocket science.

== Description ==

Are you frustrated by a slow website? Do over-sized images make you say "ewww"... Let EWWW Image Optimizer help you make your site faster, improve your bounce rate, and boost your SEO. But most importantly, make your visitors happier so they keep coming back for more.

With EWWW IO you can optimize all your existing images, [from any plugin](https://docs.ewww.io/article/84-plugin-compatibility), and then let EWWW IO take care of new image uploads automatically.

**Why use EWWW Image Optimizer?**

1. **No Speed Limits** and [unlimited file size](https://ewww.io/unlimited-file-size/).
1. **Smooth Handling** with pixel-perfect optimization using industry-leading tools and progressive rendering.
1. **High Torque** as we bring you the best compression/quality ratio available with our Premium compression for JPG, PNG, SVG, WebP, and PDF files.
1. **Adaptive Steering** with intelligent conversion options to get the right image format for the job (JPG, PNG, GIF, AVIF, or WebP).
1. **Free Parking** The core plugin is free and always will be. However, our paid services offer up to 80% compression, and a [host of other features](https://ewww.io/plans/)!
1. **Comprehensive Coverage:** no image gets left behind, optimize everything on your site, not just the WordPress Media Library.
1. **Safety First:** all communications are secured with top SSL encryption.
1. **Roadside Assistance:** top-notch support is in our DNA. While API customers get top priority, we answer [every single support question with care](https://ewww.io/contact-us/).
1. **Pack a Spare:** free image backups store your original images for 30 days.

EWWW IO is the only plugin that lets you optimize images using tools on your own server (jpegtran, optipng, pngout, pngquant, gifsicle, cwebp). If you feel the need for more speed, get more compression and offload the CPU-intensive process of optimization to [our specialized servers](https://ewww.io/plans/).

= Automatic Everything =

With Easy IO, images are automatically compressed, scaled to fit the page and device size, lazy loaded, and converted to next-gen WebP and AVIF formats.

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

= AVIF Images = 

AVIF conversion is built into the Easy IO CDN. Once your site is setup with Easy IO, edit the site settings to enable AVIF, and you're done!

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

= 8.2.1 =
*Release Date - August 27, 2025*

* added: filters for cloud storage plugin integration
* fixed: Easy IO misses some preload links
* fixed: PHP error when API quota is exhausted during single image optimization

= 8.2.0 =
*Release Date - July 15, 2025*

* added: JS WebP support for HTML elements/tags added via eio_allowed_background_image_elements filter
* added: Easy IO support for dynamic cropping (crop=1) on WordPress.com sites
* changed: use native ImageMagick methods to detect, and correct, paletted PNG thumbnails
* changed: use authoritative classmap via composer to speed up autoloader, props @nlemoine
* changed: streamlined admin notices
* fixed: indexed PNG thumbnails with 8-bit alpha are distorted by quantization
* fixed: PHP warnings related to HTML parsing
* fixed: missing ImageMagick information on Site Health screen
* fixed: PHP warnings when link URLs contain special regex characters

= 8.1.4 =
*Release Date - May 15, 2025*

* added: customize lossy PDF compression by defining EWWW_IMAGE_OPTIMIZER_PDF_IMAGE_DPI and/or EWWW_IMAGE_OPTIMIZER_PDF_IMAGE_QUALITY
* fixed: WebP thumbnails have incorrect orientation when created from the original unoptimized image

= 8.1.3 =
*Release Date - March 26, 2025*

* added: exclude private BuddyBoss media from Easy IO with page:buddyboss exclusion
* changed: WebP Only mode no longer requires Force Re-optimize for already optimized images
* fixed: Easy IO rewriting some URLs when full page exclusions are used
* fixed: WebP rewriters alter PNG URLs when PNG to WebP conversion is unavailable
* fixed: regression in compatibility with plugins that recreate images via WP_Image_Editor
* fixed: previous fix to avoid translation notices caused errors with other plugins calling background processes earlier than 'init'

= 8.1.2 =
*Release Date - March 6, 2025*

* changed: WebP Conversion mode configurable for API users
* changed: combine metadata queries for faster async scanning
* changed: Bulk Optimization no longer requires Force Re-optimization to create WebP images for previously optimized images
* fixed: background processes trigger notice from loading translations too early
* fixed: WooCommerce thumb regen still runs when WC sizes are disabled
* fixed: Easy IO fails to refresh CDN domain when site URL has changed
* fixed: Force and WebP Only options not applied when scanning additional folders in async mode
* fixed: PDF and SVG images queued in WebP Only mode

= 8.1.1 =
*Release Date - February 26, 2025*

* changed: added handling of HTTP errors to processes on Tools page
* changed: added nonce-renewal for long-running processes on Tools page
* changed: improved output for WebP Cleanup tool and Delete Converted Originals tool
* fixed: queue table upgrade fails to add 'id' column

= 8.1.0 =
*Release Date - February 18, 2025*

* added: Preserve Originals option to keep pre-scaled images for WebP and thumbnail generation
* added: ability for 3rd party plugins to hook into Lazy Load and WebP HTML parsers
* changed: ImageMagick is default WebP conversion method on supported servers
* changed: improved performance of custom *_option functions on multisite
* changed: Max Image Dimensions always override WP big_image threshold
* changed: local image backups not removed on plugin deactivation
* fixed: Sharpen Images not applied to new WebP Conversion process
* fixed: WebP Quality not applied to ImageMagick WebP Conversion for thumbnails
* fixed: WebP resizing overrides custom crop set by Crop Thumbnails
* fixed: pre-scaled original cannot be found if attachment metadata is incomplete
* fixed: PHP error in bulk image scanner

= 8.0.0 =
*Release Date - December 11, 2024*

* added: WebP Optimization via API, existing customers may enable it on the Local tab in Ludicrous Mode
* added: improved WebP Conversion quality by using full-size/original source for thumbs
* added: Above the Fold setting for Lazy Load (previously EIO_LAZY_FOLD override)
* added: High-DPI option for Easy IO
* changed: gravatar images excluded from Above the Fold/EIO_LAZY_FOLD counts
* fixed: Picture WebP ignores images with skip-lazy when it should not
* fixed: image records not reset after image restore
* fixed: several PHP warnings from bulk processes
* fixed: paths for thumbs were broken on Windows
* fixed: Easy IO adding images to srcset combined with broken WooCommerce gallery thumbnails causes oversized image sizes to be loaded
* fixed: Easy IO srcset filler using incorrect width for calculations
* fixed: PHP warning during bulk scan

= 7.9.1 =
*Release Date - October 31, 2024*

* changed: bulk optimizer links point to async bulk tool, if available
* fixed: Lazy Load for iframes results in empty src attribute
* fixed: debug actions on bulk optimizer missing nonces
* fixed: bulk optimize scanner queries are too long for some hosts
* fixed: Lazy Load breaks --background CSS variable

= 7.9.0 =
*Release Date - September 12, 2024*

* added: conversion of BMP images to JPG format
* changed: allow folders outside of WordPress install to be optimized via Folders to Optimize
* changed: improve performance of ewwwio_is_file(), props @rmpel
* changed: improve exceeded credit messages for sub-keys
* changed: warn when db connection is not using UTF-8
* changed: ensure all db statements are properly prepared/sanitized
* fixed: bulk async shows start optimizing instead of resume when queues are paused
* fixed: bulk async status refresh does not handle errors properly
* fixed: some strings with i18n had incorrect text domain

= 7.8.0 =
*Release Date - July 25, 2024*

* added: agency mode available by defining EWWWIO_WHITELABEL or using the ewwwio_whitelabel filter
* changed: skip lazy load for LCP images based on fetchpriority when auto-scaling is disabled
* fixed: JS WebP alters img srcset when src is non-WebP but srcset is already WebP
* fixed: Lazy Load and Easy IO fail to decode URLs with HTML-encoded characters, which causes esc_url to break the URL
* fixed: Easy IO fails to update CDN domain if site is re-registered while still active

= 7.7.0 =
*Release Date - June 6, 2024*

* added: improved resizing of paletted PNG images in WP_Image_Editor using pngquant or API
* added: warning when hiding query strings with Hide My WP
* changed: apply async loading to lazyload JS using WP core functionality
* fixed: missing srcset when using JS WebP rewriting
* fixed: multisite deactivate for Easy IO fails nonce verification
* fixed: some strings were missing i18n (props @DAnn2012)

= 7.6.0 =
*Release Date - April 24, 2024*

* added: Easy IO delivery for JS/CSS assets from additional domains
* added: Lazy Load can use dominant color placeholders via Easy IO
* added: ability to filter/parse admin-ajax.php requests via eio_filter_admin_ajax_response filter
* added: Easy IO support for Divi Pixel image masks
* changed: improved smoothing of LQIP for Lazy Load when using Easy IO
* changed: after editing an image in WordPress, optimization results for backup sizes will be hidden from Media Library list mode
* changed: Lazy Load checks for auto-scale exclusions on ancestors of lazyloaded element
* fixed: async bulk interface does not show Start Optimizing when image queue is already visible
* fixed: bulk process appears to have completed after clearing queue
* fixed: storing resize/webp results for new images fails with MySQL strict mode
* fixed: database records not cleaned after thumbs are removed by Force Regenerate Thumbnails
* fixed: JPG to PNG conversion on 8-bit PNGs sometimes uses incorrect black background
* fixed: Help links broken in Firefox's Strict mode
* fixed: async queue status not properly checked on multi-site

= 7.5.0 =
*Release Date - March 26, 2024*

* added: Easy IO support for upcoming Slider Revolution 7 rendering engine
* added: Easy IO updates existing image preload URLs
* added: Lazy Load automatically excludes preloaded images
* changed: async process locking uses unique key on disk to avoid duplicate processes
* fixed: Easy IO skipping Slider Revolution 6 URLs
* fixed: Lazy Load incorrectly auto-scales fixed group background images
* fixed: uncaught errors when attempting svgcleaner install on FreeBSD
* fixed: optimized images list links to WebP thumbnail for all sizes
* fixed: optimized images list shows wrong thumbnail for non-media library images
* fixed: quirks with new bulk interface and optimized images list
* updated: cwebp to version 1.3.2
* updated: gifsicle to version 1.95
* updated: optipng to version 0.7.8

= 7.4.0 =
*Release Date - March 6, 2024*

* added: async bulk optimizer on settings page
* added: store WebP results/errors for display in Media Library, and in optimization table/results
* added: ability to view pending/queued images, remove images from queue, and sort queue by original image size
* fixed: restoring images from optimization table
* fixed: attempting to install x64 binaries on arm64 servers

= Earlier versions =
Please refer to the separate changelog.txt file.

== Credits ==

Written by [Shane Bishop](https://ewww.io) with special thanks to my [Lord and Savior](https://www.iamsecond.com/). Based upon CW Image Optimizer, which was written by [Jacob Allred](http://www.jacoballred.com/) at [Corban Works, LLC](http://www.corbanworks.com/). CW Image Optimizer was based on WP Smush.it. Jpegtran is the work of the Independent JPEG Group. PEL is the work of Martin Geisler, Lars Olesen, and Erik Oskam. Easy IO and HTML parsing classes based upon the Photon module from Jetpack.
