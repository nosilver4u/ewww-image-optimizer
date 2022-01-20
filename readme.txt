=== EWWW Image Optimizer ===
Contributors: nosilver4u
Donate link: https://ewww.io/donate/
Tags: optimize, image, convert, webp, resize, compress, lazy load, optimization, lossless, lossy, seo, scale
Requires at least: 5.6
Tested up to: 5.9
Requires PHP: 7.2
Stable tag: 6.4.0
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

= 6.4.0 =
* added: free API-based WebP generation for servers that cannot generate WebP images locally
* added: detection for Jetpack Boost lazy load function
* added: JS WebP handling for WooCommerce product variations
* changed: SVG placeholder setting removed from UI as PNG placeholders can now provide the same benefits (and better).
* changed: Lazy Load no longer excludes first image in a page due to potential CLS issues and auto-scaling suppression
* fixed: PNG thumbnails skipped from WebP conversion when using exec-free mode
* fixed: SVG placeholders broken when existing img src is single-quoted
* fixed: Lazy Loader incorrectly parses fall-back iframe from Google Tag Manager, triggering 403 errors in some WAF systems
* fixed: error when disabling Easy IO
* fixed: Easy IO misses some image URLs on multi-site when using domain-mapping
* fixed: SVG level cannot be set when using API if svgcleaner was not installed previously
* fixed: Easy IO URL rewriter changing links if they matched a custom upload folder
* fixed: Easy IO incompatible with Toolset Blocks
* fixed: Easy IO incorrectly sizing wide/full width cover blocks
* fixed: SWIS CDN compat called too early in some cases
* updated: PHP EXIF library dependency updated to 0.9.9
* removed: PHP 7.1 is no longer supported

= 6.3.0 =
* added: EIO_LAZY_FOLD override to configure number of images above-the-fold that will be skipped by Lazy Load
* added: Easy IO URLs for custom (non-WP) srcset markup
* added: Easy IO support for CSS background images with relative URLs
* changed: Lazy Load excludes first image in a page as above-the-fold
* fixed: Easy IO scaling not working on full-size images without srcset/responsive markup
* fixed: WebP and Lazy Load function skip images dynamically created by Brizy builder
* fixed: Easy IO conflict on Elementor preview pages
* fixed: EXACTDN_CONTENT_WIDTH not effective at overriding $content_width during image_downsize filter

= 6.2.5 =
* added: Easy IO and Lazy Load support for AJAX responses from FacetWP
* changed: Vimeo videos excluded from iframe lazy load
* changed: use 'bg-image-crop' class on elements with CSS background images that need to be cropped by auto-scaling
* fixed: sub-folder multi-site installs which use separate domains could not activate Easy IO, define EXACTDN_SUB_FOLDER to override
* fixed: Lazy Load PNG placeholders cannot be cached if the WP_CONTENT_DIR location is read-only (notably on Pantheon servers)
* fixed: is_amp() called too early
* fixed: Fusion Builder (Avada) does not load when Lazy Load, WebP, or Easy IO options are enabled
* fixed: png_alpha() check uses more memory than is available, causing some uploads to fail

= 6.2.4 =
* added: Multi-site domain-based installs can activate/register sites en masse, and directly upon site creation
* changed: improved db upgrade routine for updated column
* changed: JS WebP script moved back to page head
* fixed: local PNG placeholders enabled with Easy IO when placeholder folder is not writable
* fixed: WebP Rewriters not detecting upload URL correctly for CDN support
* fixed: iframe lazy loading breaks Gravity Forms and FacetWP when parsing JSON
* fixed: SQL error when running "wp-cli ewwwio optimize media" - props @komsitr
* fixed: local savings query sometimes returns no results
* fixed: PHP warnings when local tools are disabled

= 6.2.3 =
* fixed: db error when MariaDB 10.1 does not permit ALTER for setting default column value
* fixed: Lazy Load missing placeholder folder when Easy IO is enabled

= 6.2.2 =
* added: disable Easy IO's "deep" integration with image_downsize filter via EIO_DISABLE_DEEP_INTEGRATION override
* added: integration with JSON/AJAX respones from Spotlight Social Media Feeds plugin
* changed: PNG placeholders are now inlined for less HTTP requests and better auto-scaling
* changed: Bulk Optimizer processes images from oldest to newest for the Media Library
* changed: Resize Detection uses minified JS and console logging suppressed unless using SCRIPT_DEBUG
* fixed: Easy IO does not rewrite image (href) links if image_downsize integration has rewritten the img tag
* fixed: Lazy Load throws error when ewww_webp_supported not defined in edge cases
* fixed: front-end scripts loading for page builders when they shouldn't be
* fixed: when using WP/LR Sync, EWWWIO_WPLR_AUTO does not trigger optimization for new images
* fixed: img element search parsing JSON incorrectly
* fixed: WebP uploads not resized to max dimensions

= 6.2.1 =
* fixed: Lazy Load regression prevents above-the-fold CSS background images from loading
* fixed: WebP Conversion for CMYK images leaves empty color profile attached

= 6.2.0 =
* added: PHP-based WebP Conversion via GD/Imagick in free mode when exec() is disabled
* added: enable -sharp_yuv option for WebP conversion with the EIO_WEBP_SHARP_YUV override
* added: WebP Conversion for CMYK images
* added: webp-supported conditional class added to body tag when JS WebP is active
* added: WP-CLI command can be run with --webp-only option
* added: Lazy Load for iframes, add 'iframe' in exclusions to disable
* added: compatibility with S3 Uploads 3.x
* added: preserve metadata and apply lossless compression to linked versions of images via Easy IO with EIO_PRESERVE_LINKED_IMAGES constant
* added: Easy IO rewrites URLs in existing picture elements
* changed: JS WebP scripts moved to beginning of page footer
* changed: native lazy loading is now enabled for right-sized PNG placeholders, override with EIO_DISABLE_NATIVE_LAZY constant
* changed: add resume ability to Delete Originals tool
* changed: move Easy IO check-in to wp_cron
* fixed: empty .webp images sometimes produced when cwebp encounters an error
* fixed: Bulk Optimizer for NextGEN loading incorrect script
* fixed: Bulk Optimizer for NextGEN fails to verify nonce for selective optimization
* fixed: Last Optimized times for Optimized Images table were incorrect
* fixed: Add Missing Dimensions overwrites smaller width/height attribute if only one is set
* fixed: replacing an existing attribute (like width) with a numeric value is broken

= Earlier versions =
Please refer to the separate changelog.txt file.

== Credits ==

Written by [Shane Bishop](https://ewww.io) with special thanks to my [Lord and Savior](https://www.iamsecond.com/). Based upon CW Image Optimizer, which was written by [Jacob Allred](http://www.jacoballred.com/) at [Corban Works, LLC](http://www.corbanworks.com/). CW Image Optimizer was based on WP Smush.it. Jpegtran is the work of the Independent JPEG Group. PEL is the work of Martin Geisler, Lars Olesen, and Erik Oskam. Easy IO and HTML parsing classes based upon the Photon module from Jetpack.
