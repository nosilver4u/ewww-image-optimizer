=== EWWW Image Optimizer ===
Contributors: nosilver4u
Donate link: https://ewww.io/donate/
Tags: compress, convert, webp, resize, lazy load
Tested up to: 6.9
Stable tag: 8.3.1
License: GPLv3

Comprehensive image optimization that doesn't require a rocket science degree. Optimize images automatically for Faster Sites and Happy Visitors.

== Description ==

Are you frustrated by a slow website? Do over-sized images make you say “ewww”... Image optimization with EWWW Image Optimizer helps you make your site faster, improve your bounce rate, and boost your SEO. But most importantly, make your visitors happier so they keep coming back for more.

= Why use EWWW Image Optimizer? =

**Get all this for free:**

* Unlimited image optimization to compress images of any size
* Local image optimization mode compatible with [most web hosts](https://docs.ewww.io/article/43-supported-web-hosts)
* Lossless JPG, PNG, GIF, and SVG image optimization (8% average savings)
* WebP conversion compatible with all web hosts (60% average savings)
* Optimize images from [any plugin](https://docs.ewww.io/article/84-plugin-compatibility)
* Resize images at upload or in bulk
* Lazy Load with auto-scaling for responsive images–uses properly-sized placeholders to prevent layout shift (CLS)
* Sharpen thumbnail images for better quality
* Adjust JPG and WebP quality (AVIF quality configurable in premium)
* Control creation and optimization of individual WordPress thumbnails
* Convert images to the best format (GIF to PNG, PNG to JPG or vice versa)
* Local image backups
* Preserve GIF animations in thumbnails
* [Free email support](https://ewww.io/contact-us/)

EWWW Image Optimizer is the only plugin that lets you optimize images using tools on your own web server (jpegtran, optipng, pngout, pngquant, gifsicle, cwebp). This requires the PHP exec() function and a [compatible](https://docs.ewww.io/article/43-supported-web-hosts) Linux, Windows, MacOS, or FreeBSD web server. [If your web server is not compatible, we offer unlimited lossless JPG image optimization and WebP conversion via our Compress API **for free*](https://docs.ewww.io/article/29-what-is-exec-and-why-do-i-need-it).

**Upgrade to [Premium](https://ewww.io/plans/) for:**

* 5x premium image optimization
* PDF optimization
* Automatic scaling for all images, even those in external CSS
* One-click WebP & AVIF conversion and delivery
* Enhanced responsive images that use correct dimensions for all devices
* WebP image optimization
* Deliver High-DPI images to devices with 2x and 3x screens (retina)
* Watermark images
* CDN delivery for images, CSS, JS, and fonts with custom domain name option
* 30-day cloud-based backups
* [Premium support](https://ewww.io/about/)

[Premium plans](https://ewww.io/plans/) include SWIS Performance plugin with:

* Page caching
* Enable browser caching with long cache lifetimes
* Defer JS/CSS to eliminate render blocking requests
* Minify JS/CSS
* Critical CSS generation to prevent layout shifting (CLS)
* Optimize font display/self-host Google fonts
* Preload assets like fonts and LCP images
* Reduce unused JS/CSS
* Manage speculative loading


= Automatic Everything =

Optimize images on your entire site with a single click. With [Easy IO CDN](https://ewww.io/plans/), images are automatically compressed, scaled to fit the page and device size, lazy loaded, and converted to next-gen WebP and AVIF formats.

= Support =

[We provide free one-on-one email support to everyone](https://ewww.io/contact-us/).
Do you have an idea to make EWWW Image Optimizer even better? [Share it and vote on future features](https://feedback.ewww.io/b/features)!

Found a bug? Report the issue on [GitHub](https://github.com/nosilver4u/ewww-image-optimizer), and we'll get it fixed!

You may report security issues through our Patchstack Vulnerability Disclosure Program. The Patchstack team helps validate, triage and handle any security vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/vdp/ewww-image-optimizer)

= Bulk Optimize =

Optimize images, all from a single page. This includes the Media Library, your theme, and a handful of pre-configured folders (see Optimize Everything Else below). GRAND FlaGallery, NextCellent and NextGEN have their own Bulk Optimize pages.

= Optimize Everything Else =

Configure any folder within your WordPress install to optimize images. The Bulk Optimizer will compress theme images, BuddyPress avatars, BuddyPress Activity Plus images, Meta Slider slides, WP Symposium Pro avatars, GD bbPress attachments, Grand Media Galleries, and any user-specified folders. You can also use Scheduled optimization or run the optimizer from WP-CLI if that's more your thing.

= Plugin Compatibility =

EWWW Image Optimizer has been tested with thousands of [plugins and themes](https://docs.ewww.io/article/84-plugin-compatibility), here are just a few of the most common ones: BuddyPress (Activity Plus add-on too), Cloudinary, Easy Watermark, FileBird, FooGallery, GD bbPress Attachments, GRAND FlAGallery, Gmedia Photo Gallery, MediaPress, Meta Slider, Microsoft Azure Storage, MyArcadePlugin, NextGEN Gallery, Regenerate Thumbnails, [Weglot](https://weglot.com/integrations/wordpress-translation-plugin/demo/), WP Offload Media, [WPML](https://wpml.org/plugin/ewww-image-optimizer/), WP Retina 2x, WP RSS Aggregator, WP Symposium, [and more...](https://docs.ewww.io/article/84-plugin-compatibility)

= WebP Images =

If you want simple, get automatic WebP conversion with Easy IO, and be done with it! Otherwise, you can generate WebP versions of unlimited images with the Bulk Optimizer. Deliver them to supported browsers with Apache-style rewrite rules, JS WebP Rewriting, or Picture WebP Rewriting. EWWW Image Optimizer even works with the WebP option in the Cache Enabler plugin from KeyCDN.

= AVIF Images = 

AVIF conversion is built into the Easy IO CDN. Once your site is setup with Easy IO, edit the site settings to enable AVIF, and you're done!

= WP-CLI =

Allows you to run all batch image processes from the command line, instead of the web interface. Optimize images even faster, run it in 'screen' or via regular cron (instead of wp-cron, which can be unpredictable on low-traffic sites). Install WP-CLI from wp-cli.org, and run 'wp-cli.phar help ewwwio optimize' for more information or see the [Docs](https://docs.ewww.io/article/25-optimizing-with-wp-cli).

= CDN Support =

[WP Offload Media](https://wordpress.org/plugins/amazon-s3-and-cloudfront/) is the officially supported (and recommended) plugin for uploads to Amazon S3, Digital Ocean Spaces, and Google Cloud Storage. [Check our compatibility list for details on other plugins](https://docs.ewww.io/article/84-plugin-compatibility). All pull mode CDNs like Cloudflare, KeyCDN, Bunny CDN and Sucuri work automatically, but you will need to purge the CDN cache after you optimize images with bulk optimization.

= Translations =

Huge thanks to all our translators, [see the full list](https://translate.wordpress.org/projects/wp-plugins/ewww-image-optimizer/contributors)!

If you would like to help translate this plugin, [join the team](https://translate.wordpress.org/projects/wp-plugins/ewww-image-optimizer).
[Signup to receive updates when new strings are available for translation](https://ewww.io/register/).

== Installation ==

1. The plugin will attempt to install jpegtran, optipng, and gifsicle automatically for you. This requires that the wp-content folder is writable by the user running the web server.
1. If the binaries don't run locally, you may sign up for cloud-based optimization: https://ewww.io/plans/
1. Visit the settings page to customize the plugin for your site.
1. *Recommended* Activate the [Easy IO CDN](https://ewww.io/plans/) and/or run the bulk process to compress your existing images.
1. Done!

If these steps do not work, [see the additional documentation](https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something). If you need further assistance using the plugin, please visit our [Support Page](https://ewww.io/contact-us/).

= Webhosts =

To find out if your webhost works with the EWWW Image Optimizer, you can check the [official list](https://docs.ewww.io/article/43-supported-web-hosts).

== Frequently Asked Questions ==

= Does the plugin remove EXIF and/or IPTC metadata?

EWWW Image Optimizer will remove metadata by default, but if you need to keep the EXIF/IPTC data for copyright purposes, you can disable the Remove Metadata option.
EXIF data does not impact SEO, and it is recommended by Google (and just about everyone else) to remove EXIF data.

= Google Pagespeed says my images need compressing or resizing, but I already optimized all my images. What do I do? =

Try this for starters: [https://docs.ewww.io/article/5-pagespeed-says-my-images-need-more-work](https://docs.ewww.io/article/5-pagespeed-says-my-images-need-more-work)

= The plugin complains that I'm missing something, what do I do? =

This article will walk you through installing the required tools (and the alternatives if installation does not work): [https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something](https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something)

= Does the plugin replace existing images? =

Yes, but only if the optimized version is smaller. The plugin should NEVER create a larger image.

= Can I lower the compression setting for JPGs to save more space? =

Our premium compression can determine the ideal quality setting and give you the best results, but you may also adjust the default quality for conversion and resizing. [Read more...](https://docs.ewww.io/article/12-jpq-quality-and-wordpress)

= The bulk optimizer doesn't seem to be working, what can I do? =

See [https://docs.ewww.io/article/39-bulk-optimizer-failure](https://docs.ewww.io/article/39-bulk-optimizer-failure) for full troubleshooting instructions.

= What are the supported operating systems? =

Free mode using local server compression is supported on Windows, Linux, MacOS, and FreeBSD. The Compress API and Easy IO CDN will work on any OS.

= I want to know more about image optimization, and which options I should choose. =

That's not a question, but since I made it up, I'll answer it. See this resource:
[https://ewww.io/2026/01/12/ewww-whose-images-are-those/](https://ewww.io/2026/01/12/ewww-whose-images-are-those/)

== Screenshots ==

1. Plugin settings page.
2. Additional optimize column added to media listing. You can see your savings, manually optimize individual images, and restore originals (converted only).
3. Bulk optimization page. You can optimize all your images at once and resume a previous bulk optimization. This is very useful for existing blogs that have lots of images.

== Changelog ==

* Feature requests can be viewed and submitted on our [feedback portal](https://feedback.ewww.io/b/features)
* If you would like to help translate this plugin in your language, [join the team](https://translate.wordpress.org/projects/wp-plugins/ewww-image-optimizer/)

= 8.3.2 =
*Release Date - TBD*

* added: ability to choose append or replace for naming of WebP images, props @adamewww
* improved: WebP renaming tool for converting from replacement naming to append and vice versa
* fixed: Lazy Load setting does not detect presence of Easy IO plugin
* fixed: Easy IO domain not reset after site URL is updated
* fixed: PHP warnings and notices

= 8.3.1 =
*Release Date - December 4, 2025*

* changed: prevent use of deprecated seems_utf8() function on WP 6.9+
* fixed: Lazy Load auto-sizing makes images too small when screen size changes
* fixed: failure to decode CSS background images contained in encoded quotes (&apos;)

= 8.3.0 =
*Release Date - November 19, 2025*

* added: Lazy Load support for background images in external CSS files
* added: View CDN bandwidth usage on settings page
* changed: Lazy Load checks parent element for skip-lazy class
* changed: Lazy Load auto-sizing honors High DPI setting
* changed: Easy IO fills in 450px wide image when responsive (srcset) images have a gap
* changed: Easy IO premium setting moved to zone configuration at https://ewww.io/manage-sites/
* improved: Lazy Load performance when searching for img elements
* improved: Lazy Load placeholder generation is faster and works better with Safari
* fixed: Lazy Load for iframes breaks WP Remote Users Sync plugin
* fixed: PHP warning when attempting conversion of custom thumbnails from certain themes

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
