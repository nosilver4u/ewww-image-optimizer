=== EWWW Image Optimizer ===
Contributors: nosilver4u
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=MKMQKCBFFG3WW
Tags: image, compress, optimize, optimization, lossless, lossy, seo, tinyjpg, tinypng, webp, wp-cli
Requires at least: 4.6
Tested up to: 4.9
Requires PHP: 5.3
Stable tag: 4.1.3
License: GPLv3

Speed up your website and improve your visitors' experience by automatically compressing and resizing images and PDFs. Boost SEO and improve sales.

== Description ==

The EWWW Image Optimizer will increase your page speeds by way of image optimization. Increased page speeds can result in better search engine rankings, and will also improve conversion rates (increased sales and signups). It will also save you storage space and bandwidth. While EWWW I.O. will automatically optimize new images that you upload, it can also optimize all the images that you have already uploaded, and optionally convert your images to the best file format. You can choose pixel perfect compression or high compression options that are visually lossless.

EWWW I.O. will optimize images uploaded and created by any plugin, and features special integrations with many popular plugins, detailed below.

**Why use EWWW Image Optimizer?**

1. **No Speed Limits** and [unlimited file size](https://ewww.io/unlimited-file-size/). Using automatic Background Optimization and optional Parallel Optimization, get rid of upload delays and get back to doing what you love.
1. **Smooth Handling** with pixel-perfect optimization using industry-leading tools and progressive rendering.
1. **High Torque** as we bring you the best compression/quality ratio available with our lossy options for JPG, PNG, and PDF files.
1. **Adaptive Steering** with intelligent conversion options to get the right image format for the job (JPG, PNG, or GIF).
1. **Free Parking** The core plugin is free and always will be. Additionally, if you choose the API, you never pay for an image we can’t compress, you are never billed for a month you do not use the API, and pre-paid credits never expire. Plus, get WebP image generation at no extra cost: any JPG or PNG can be converted to Google’s next-generation image format.
1. **Comprehensive Coverage:** no image gets left behind, optimize everything on your site, beyond just the WordPress Media Library.
1. **Safety First:** all communications are secured with top SSL encryption.
1. **Roadside Assistance:** top-notch support is in our DNA. While API customers get top priority, we answer [every single support question with care](https://ewww.io/contact-us/).
1. **Pack a Spare:** free image backups store your original images for 30 days.

Images can be optimized using tools on your own server for free (jpegtran, optipng, pngout, pngquant, gifsicle, cwebp), or can be optimized via specialized servers that utilize the best tools available in lossless or lossy mode. Our lossy compression uses unique algorithms to gain maximum compression while remaining visually lossless. Your images can even be converted to the most suitable file format using the appropriate options. Using the EWWW I.O. API will allow the plugin to work on any hosting platform, and can also be desirable if you cannot, or do not want to use the exec() function on your server, or prefer to offload the resource demands of optimization.

If you need a version of this plugin for API use only, see [EWWW Image Optimizer Cloud](https://wordpress.org/plugins/ewww-image-optimizer-cloud/). It is much more compact as it does not contain any binaries or any mention of the exec() function.

= Automatic Resizing =

With ExactDN support, images will be automatically resized to fit the page and device size. ExactDN also enables basic retina support and automatic lossless compression.

= Support =

If you need assistance using the plugin, please visit our [Support Page](https://ewww.io/contact-us/). The forums are community supported only.
The EWWW Image Optimizer is developed at https://github.com/nosilver4u/ewww-image-optimizer

= Bulk Optimize =

Optimize all your images from a single page using the Bulk Scanner. This includes the Media Library, your theme, and a handful of pre-configured folders (see Optimize Everything Else below). Officially supported galleries (GRAND FlaGallery, NextCellent and NextGEN) have their own Bulk Optimize pages.

= Optimize Everything Else =

Configure any folder within your WordPress folder to be optimized. The Bulk Scan under Media->Bulk Optimize will optimize theme images, BuddyPress avatars, BuddyPress Activity Plus images, Meta Slider slides, WP Symposium Pro avatars, GD bbPress attachments, Grand Media Galleries, and any user-specified folders. Additionally, this tool can run on an hourly basis via wp_cron to keep newly uploaded images optimized. Scheduled optimization should not be used for any plugin that uses the built-in Wordpress image functions.

= Skips Previously Optimized Images =

All optimized images are stored in the database so that the plugin does not attempt to re-optimize them unless they are modified. On the Bulk Optimize page you can view a list of already optimized images. You may also remove individual images from the list, or use the Force optimize option to override the default behavior. The re-optimize links on the Media Library page also force the plugin to ignore the previous optimization status of images.

= WP Image Editor =

All images created by the built-in WP_Image_Editor class will be automatically optimized. Current implementations are GD, Imagick, and Gmagick. Images optimized via this class include Animated GIF Resize, BuddyPress Activity Plus (thumbs), Easy Watermark, Hammy, Imsanity, MediaPress, Meta Slider, MyArcadePlugin, OTF Regenerate Thumbnails, Regenerate Thumbnails, Simple Image Sizes, WP Retina 2x, WP RSS Aggregator and probably countless others. If you are not sure if a plugin uses WP_Image_Editor, [just ask](https://ewww.io/contact-us/).

= WebP Images =

Can generate WebP versions of your images, and enables you to serve even smaller images to supported browsers. Several methods are available for serving WebP images, including Apache-compatible rewrite rules and our Alternative WebP Rewriting option compatible with caches and CDNs. Also works with the WebP option in the Cache Enabler plugin from KeyCDN.

= WP-CLI =

Allows you to run all Bulk Optimization processes from your command line, instead of the web interface. It is much faster, and allows you to do things like run it in 'screen' or via regular cron (instead of wp-cron, which can be unpredictable on low-traffic sites). Install WP-CLI from wp-cli.org, and run 'wp-cli.phar help ewwwio optimize' for more information or see the [Docs](https://docs.ewww.io/article/25-optimizing-with-wp-cli).

= FooGallery =

All images uploaded and cached by FooGallery are automatically optimized. Previous uploads can be optimized by running the Media Library Bulk Optimize. Previously cached images can be optimized by entering the wp-content/uploads/cache/ folder under Folders to Optimize and running a Scan & Optimize from the Bulk Optimize page.

= NextGEN Gallery =

Features optimization on upload capability, re-optimization, and bulk optimizing. The NextGEN Bulk Optimize function is located near the bottom of the NextGEN menu, and will optimize all images in all galleries. It is also possible to optimize groups of images in a gallery, or multiple galleries at once.

= NextCellent Gallery =

Features all the same capability as NextGEN, and is the continuation of legacy (1.9.x) NextGEN support.

= GRAND Flash Album Gallery =

Features optimization on upload capability, re-optimization, and bulk optimizing. The Bulk Optimize function is located near the bottom of the FlAGallery menu, and will optimize all images in all galleries. It is also possible to optimize groups of images in a gallery, or multiple galleries at once.

= Image Store =

Uploads are automatically optimized. Look for Optimize under the Image Store (Galleries) menu to see status of optimization and for re-optimization and bulk-optimization options. Using the Bulk Optimization tool under Media Library automatically includes all Image Store uploads.

= CDN Support =

Uploads to Amazon S3, Azure Storage, Cloudinary, and DreamSpeed CDN are optimized. All pull mode CDNs like Cloudflare, KeyCDN, MaxCDN, and Sucuri CloudProxy are also supported.

= WPML Compatible =

Tested regularly to ensure compatibility with multilingual sites. Learn more at https://wpml.org/plugin/ewww-image-optimizer/

= Translations =

Huge thanks to all our translators! See the full list here: https://translate.wordpress.org/projects/wp-plugins/ewww-image-optimizer/contributors

If you would like to help translate this plugin (new or existing translations), you can do so here: https://translate.wordpress.org/projects/wp-plugins/ewww-image-optimizer
To receive updates when new strings are available for translation, you can signup here: https://ewww.io/register/

== Installation ==

1. Upload the "ewww-image-optimizer" plugin to your /wp-content/plugins/ directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Ensure jpegtran, optipng, pngout and gifsicle are installed on your Linux server (basic installation instructions are below if they are not). You will receive a warning when you activate the plugin if they are not present. This message will go away once you have them installed.
1. The plugin will attempt to install jpegtran, optipng, and gifsicle automatically for you. This requires that the wp-content folder is writable by the user running the web server.
1. If the automatic install did not work, find the appropriate binaries for your system in the ewww-image-optimizer plugin folder, copy them to wp-content/ewww/ and remove the OS "tag" (like -linux or -fbsd). No renaming is necessary on Windows, just copy the .exe files to the wp-content/ewww folder. IMPORTANT: Do not symlink or modify the binaries in any way, or they will not pass the security checks. If you transfer files via FTP, be sure to transfer in binary mode, not ascii or text.
1. If the binaries don't run locally, you can sign up for the EWWW IO cloud service to run them via our optimization servers: https://ewww.io/plans/
1. *Recommended* Visit the settings page to enable/disable specific tools and turn on advanced optimization features.
1. Done!

If these steps do not work, additional documentation is available at https://docs.ewww.io. If you need further assistance using the plugin, please visit our [Support Page](https://ewww.io/contact-us/). The forums are community supported only.

= Webhosts =

To find out if your webhost works with the EWWW Image Optimizer, you can check the [official list](https://docs.ewww.io/article/43-supported-web-hosts).

== Frequently Asked Questions ==

= Google Pagespeed says my images need compressing or resizing, but I already optimized all my images. What do I do? =

Try this for starters: https://docs.ewww.io/article/5-pagespeed-says-my-images-need-more-work

= The plugin complains that I'm missing something, what do I do? =

This article will walk you through installing the required tools (and the alternatives if installation does not work): https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something

= Does the plugin replace existing images? =

Yes, but only if the optimized version is smaller. The plugin should NEVER create a larger image.

= Can I resize my images with this plugin? =

Yes, you can, set it up on the Resize tab.

= Can I lower the compression setting for JPGs to save more space? =

The lossy JPG optimization using the API will determine the ideal quality setting and give you the best results, but you can also adjust the default quality for conversion and resizing. More information: https://docs.ewww.io/article/12-jpq-quality-and-wordpress

= The bulk optimizer doesn't seem to be working, what can I do? =

If it doesn't seem to work at all, check for javascript problems using the developer console in Firefox or Chrome. If it is not working just on some images, you may need to increase the setting max_execution_time in your php.ini file. There are also other timeouts with Apache, and possibly other limitations of your webhost. If you've tried everything else, the last thing to look for is large PNG files. In my tests on a shared hosting setup, "large" is anything over 300 KB. You can first try decreasing the PNG optimization level in the settings. If that doesn't work, perhaps you ought to convert that PNG to JPG or set a max PNG optimization size. Screenshots are often done as PNG files, but that is a poor choice for anything with photographic elements.
[youtube https://www.youtube.com/watch?v=vAC1SVlh7o0]

= What are the supported operating systems? =

I've tested it on Windows (with Apache), Linux, Mac OSX, FreeBSD 9, and Solaris (v10). The cloud API will work on any OS.

= How are JPGs optimized? =

Lossless optimization is done with the command *jpegtran -copy all -optimize -progressive -outfile optimized-file original-file*. Optionally, the -copy switch gets the 'none' parameter if you choose to strip metadata from your JPGs on the options page.

= How are PNGs optimized? =

There are three parts (and all are optional). First, using the command *pngquant original-file*, then using the commands *pngout-static -s2 original-file* and *optipng -o2 original-file*. You can adjust the optimization levels for both tools on the settings page. Optipng is an automated derivative of pngcrush, which is another widely used png optimization utility. EWWW I.O. Cloud uses TinyPNG for 10% better lossy compression than standalone pngquant.

= How are GIFs optimized? =

Using the command *gifsicle -b -O3 --careful original file*. This is particularly useful for animated GIFs, and can also streamline your color palette. That said, if your GIF is not animated, you should strongly consider converting it to a PNG. PNG files are almost always smaller, they just don't do animations. The following command would do this for you on a Linux system with imagemagick: *convert somefile.gif somefile.png*

= I want to know more about image optimization, and why you chose these options/tools. =

That's not a question, but since I made it up, I'll answer it. See these resources:
https://developers.google.com/speed/docs/insights/OptimizeImages
http://developer.yahoo.com/performance/rules.html#opt_images

== Screenshots ==

1. Plugin settings page.
2. Additional optimize column added to media listing. You can see your savings, manually optimize individual images, and restore originals (converted only).
3. Bulk optimization page. You can optimize all your images at once and resume a previous bulk optimization. This is very useful for existing blogs that have lots of images.

== Changelog ==

* Feature requests can be submitted via https://ewww.io/contact-us/ and commented on here: https://trello.com/b/Fp81dWof/ewww-image-optimizer
* If you would like to help translate this plugin in your language, get started here: https://translate.wordpress.org/projects/wp-plugins/ewww-image-optimizer/

= 4.1.3 =
* fixed: infinite loop when removing invalid API key
* fixed: img elements with incorrect attachment ID being replaced with wrong image src
* fixed: ExactDN CSS and JS parsing incompatible with Autoptimize

= 4.1.2 =
* added: detect WP Fastest Cache WebP rewrite rules
* added: notice if WebP conversion enabled but mod_rewrite or mod_headers is missing
* added: better debugging when background/async mode is blocked
* changed: CSS/JS files are filtered pre-emptively by ExactDN to avoid quirks with emoji scripts
* fixed: warning during wp_cron for undefined constant
* fixed: invalid or expired keys would still attempt optimization
* fixed: WebP files are orphaned when using Media File Renamer
* deprecated: PHP 5.3 will no longer be supported in 4.2
* deprecated: PHP 5.4 support will be removed by July 2018
* deprecated: PHP 5.5 support will be removed by October 2018

= 4.1.1 =
* added: reduce ExactDN load time by suppressing db queries with EXACTDN_PREVENT_DB_QUERIES
* added: $fullsize indicator added to pre/post optimization hooks, props Schweinepriester
* fixed: missing www preventing rewrites for ExactDN
* fixed: Alt WebP compatibility with Tatsu page builder
* fixed: relative path support not working properly for Pantheon users
* fixed: missing directories prevent optimization of S3 files

= 4.1.0 =
* SECURITY: gifsicle and optipng have been updated to address security flaws
* added: full compatibility with Image Watermark plugin
* added: dummy images for Essential Grid and Layer Slider whitelisted with ExactDN
* added: compatibility with Visual Composer and Essential Grid async/AJAX loaders
* added: compatibility with Media File Renamer
* changed: ExactDN rewrites all wp-content and wp-includes urls by default
* changed: mime-type detection function does not rely on fileinfo extension anymore
* changed: Solaris/SunOS binary builds use OpenIndiana 2017.10, let me know if they break
* fixed: wp-emoji script not rewritten by EXACTDN_ALL_THE_THINGS
* fixed: resize detection script throws error when admin bar is hidden
* fixed: warnings when WP Offload S3 set to delete local files, props ianmjones
* updated: pngquant version 2.11.7

= 4.0.6 =
* changed: dummy images have no args appended with exactdn except for ssl flag
* fixed: resize_detection.js being combined with other scripts by Autoptimize
* fixed: retina optimization not deferred in async mode
* fixed: PDF files could trigger license exceeded message
* fixed: binary detection not fully functional with MacOS and PHP 7.2
* fixed: compatibility with Regenerate Thumbnails version 3

= 4.0.5 =
* added: enable lossy compression with ExactDN: https://docs.ewww.io/article/47-getting-more-from-exactdn
* added: CSS/JS minification with ExactDN, see https://docs.ewww.io/article/47-getting-more-from-exactdn
* added: disable WebP for specific files with ewww_image_optimizer_bypass_webp filter
* added: ExactDN obeys focus point from Theia Smart Thumbnails plugin
* added: admin-ajax requests for eddvbugm loader work with ExactDN
* fixed: multisite settings would not save in certain circumstances
* fixed: compression levels reset for API users on multisite after toggling single-site override on and off
* fixed: media library items with non-local images rewritten incorrectly by ExactDN
* fixed: restoring images throws errors on PHP 7.1
* fixed: has_cap with invalid argument not recognizing utf8-mb4 v5.2

= 4.0.4 =
* fixed: ExactDN domain validation failing on length check for some domains
* updated: PEL for better EXIF preservation

= 4.0.3 =
* added: support for additional ExactDN root domains
* added: button to remove WebP rewrite rules
* added: informational notice on thumbnail rebuild pages of how the plugins interact
* changed: WebP rewrite rules removed automatically when ExactDN is enabled, use Alt WebP instead
* changed: ExactDN now removes metadata if option is enabled
* fixed: multisite settings set to defaults when single-site resize settings are submitted

= 4.0.2 =
* fixed: WooCommerce images still not working with Alt WebP in all cases
* fixed: ob_clean() breaks AJAX actions when there is no buffer to clean
* fixed: notice on NextCellent gallery management pages
* fixed: missing JS for AJAX actions in NextCellent

= 4.0.1 =
* fixed: ExactDN option not disabled when verification fails too many times
* fixed: theme scanner sometimes skipped images on PHP 5.3
* fixed: invalid (float) width parameters for srcset attributes
* fixed: Jetpack lightbox and carousel were not fully working with Alt WebP
* fixed: WooCommerce lightbox and gallery not working with Alt WebP
* fixed: incorrect message about scanning scope when selecting images from media library for bulk optimization
* security: fixed wildcard LIKE queries to allow proper escaping

= 4.0.0 =
* added: ExactDN with CDN and automatic image resizing
* added: image resize detection for admin users
* changed: WP core, theme, and plugin images are excluded from lossy optimization
* fixed: files fetched from S3 not detected by PHP in some cases
* fixed: option override conflict preventing webp conversion
* fixed: Alt WebP breaks Draw Attention image maps
* fixed: customized WP_Background_Process class conflicts with other plugins using the same class
* fixed: image deletion could cause deletion of images on source site after cloning database
* fixed: WebP .htaccess rules using REQUEST_FILENAME instead of REQUEST_URI does not work on some servers
* fixed: per-site resize settings hidden when API is active network-wide
* fixed: network-wide settings not saving properly
* fixed: notice of undefined index with some configurations of the Shield security plugin
* deprecated: PHP 5.3 support will be removed by March 2018

= Earlier versions =
Please refer to the separate changelog.txt file.

== Upgrade Notice ==

= 4.1.0 =
* Security update: gifsicle and optipng have been updated to resolve security flaws.
* ExactDN now processes JS/CSS/Fonts for even more speed.

= 4.0.0 =
* Introduced new ExactDN with CDN and automatic image resizing.

= 3.6.0 =
* API functions have been rewritten to use core WP detection for https capability, please report any errors right away.
* Several options have been removed from the user interface, see the changelog for details.

= 3.4.0 =
* Multisite change: disabling resizes must be done on individual sites even when network activated, as those settings are heavily theme-specific.

= 3.3.0 =
* Requires PHP 5.3+. All sites hosted on Pantheon will now use "relative" paths. Existing Pantheon sites will need to update the ewwwio_images table to match (contact support for help), or disable this function by setting EWWW_IMAGE_OPTIMIZER_RELATIVE to false in wp-config.php.

== Contact and Credits ==

Written by [Shane Bishop](https://ewww.io). Based upon CW Image Optimizer, which was written by [Jacob Allred](http://www.jacoballred.com/) at [Corban Works, LLC](http://www.corbanworks.com/). CW Image Optimizer was based on WP Smush.it. Jpegtran is the work of the Independent JPEG Group. PEL is the work of Martin Geisler, Lars Olesen, and Erik Oskam. ExactDN class based upon the Photon module from Jetpack.

= optipng =

Copyright (C) 2001-2017 Cosmin Truta and the Contributing Authors.
For the purpose of copyright and licensing, the list of Contributing
Authors is available in the accompanying AUTHORS file.

This software is provided 'as-is', without any express or implied
warranty.  In no event will the author(s) be held liable for any damages
arising from the use of this software.

= pngquant.c =

   © 1989, 1991 by Jef Poskanzer.

   Permission to use, copy, modify, and distribute this software and its
   documentation for any purpose and without fee is hereby granted, provided
   that the above copyright notice appear in all copies and that both that
   copyright notice and this permission notice appear in supporting
   documentation.  This software is provided "as is" without express or
   implied warranty.

= pngquant.c and rwpng.c/h =

   © 1997-2002 by Greg Roelofs; based on an idea by Stefan Schneider.
   © 2009-2017 by Kornel Lesiński.

   All rights reserved.

   Redistribution and use in source and binary forms, with or without modification,
   are permitted provided that the following conditions are met:

   1. Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

   2. Redistributions in binary form must reproduce the above copyright notice,
      this list of conditions and the following disclaimer in the documentation
      and/or other materials provided with the distribution.

   THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
   AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
   IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
   DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
   FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
   DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
   SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
   CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
   OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
   OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

= WebP =

Copyright (c) 2010, Google Inc. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

  * Redistributions of source code must retain the above copyright
    notice, this list of conditions and the following disclaimer.

  * Redistributions in binary form must reproduce the above copyright
    notice, this list of conditions and the following disclaimer in
    the documentation and/or other materials provided with the
    distribution.

  * Neither the name of Google nor the names of its contributors may
    be used to endorse or promote products derived from this software
    without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
