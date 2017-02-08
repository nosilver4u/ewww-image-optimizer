=== EWWW Image Optimizer ===
Contributors: nosilver4u
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=MKMQKCBFFG3WW
Tags: image, compress, optimize, optimization, lossless, lossy, photo, picture, seo, jpegmini, tinyjpg, tinypng, webp, wp-cli 
Requires at least: 4.4
Tested up to: 4.7.2
Stable tag: 3.2.5
License: GPLv3

Reduce image sizes in WordPress including NextGEN, GRAND FlAGallery, FooGallery and more using lossless/lossy methods and image format conversion.

== Description ==

The EWWW Image Optimizer is a WordPress plugin that will automatically optimize your images as you upload them to your blog. It can optimize the images that you have already uploaded, convert your images automatically to the file format that will produce the smallest image size (make sure you read the WARNINGS), and optionally apply lossy compression to achieve huge savings for PNG and JPG images.

**Why use EWWW Image Optimizer?**

1. **Your pages will load faster.** Smaller image sizes means faster page loads. This will make your visitors happy, and can increase revenue.
1. **Faster backups.** Smaller image sizes also means faster backups.
1. **Less bandwidth usage.** Optimizing your images can save you hundreds of KB per image, which means significantly less bandwidth usage.
1. **Super fast.** The plugin can run on your own server, so you don’t have to wait for a third party service to receive, process, and return your images. You can optimize hundreds of images in just a few minutes. PNG files take the longest, but you can adjust the settings for your situation.
1. **Best JPG optimization.** With TinyJPG integration, nothing else comes close (requires an API subscription).
1. **Best PNG optimization.** You can use pngout, optipng, and pngquant in conjunction. And if that isn't enough, try the powerful TinyPNG option.
1. **Root access not needed** Pre-compiled binaries are made available to install directly within the Wordpress folder, and cloud optimization is provided for those who cannot run the binaries locally.
1. **Optimize everything** With the wp_image_editor class extension, and the ability to specify your own folders for scanning, any image in Wordpress can be optimized.

By default, EWWW Image Optimizer uses lossless optimization techniques, so your image quality will be exactly the same before and after the optimization. The only thing that will change is your file size. The one small exception to this is GIF animations. While the optimization is technically lossless, you will not be able to properly edit the animation again without performing an --unoptimize operation with gifsicle. The gif2png and jpg2png conversions are also lossless but the png2jpg process is not lossless. The lossy optimization for JPG and PNG files uses sophisticated algorithms to minimize perceptual quality loss, which is vastly different than setting a static quality/compression level.

The tools used for optimization are [jpegtran](http://jpegclub.org/jpegtran/), [TinyJPG](http://www.tinyjpg.com), [JPEGmini](http://www.jpegmini.com), [optipng](http://optipng.sourceforge.net/), [pngout](http://advsys.net/ken/utils.htm), [pngquant](http://pngquant.org/), [TinyPNG](http://www.tinypng.com), and [gifsicle](http://www.lcdf.org/gifsicle/). Most of these are freely available except TinyJPG/TinyPNG and JPEGmini. Images are converted using the above tools and one of the following: GMagick, IMagick, or GD.

EWWW Image Optimizer calls optimization utilities directly which is well suited to shared hosting situations where these utilities may already be installed. Pre-compiled binaries/executables are provided for optipng, gifsicle, pngquant, cwebp, and jpegtran. Pngout can be installed with one-click from the settings page. If none of that works, there is a cloud option that will work for any site.

If you need a version of this plugin for API use only, see [EWWW Image Optimizer Cloud](https://wordpress.org/plugins/ewww-image-optimizer-cloud/). It is much more compact as it does not contain any binaries or any mention of the exec() function.

= Support =

If you need assistance using the plugin, please visit our [Support Page](https://ewww.io/contact-us/). The forums are community supported only.
The EWWW Image Optimizer is developed at https://github.com/nosilver4u/ewww-image-optimizer

= Bulk Optimize =

Optimize all your images from a single page using the Bulk Scanner. This includes the Media Library, your theme, and a handful of pre-configured folders (see Optimize Everything Else below). Officially supported galleries (GRAND FlaGallery, NextCellent and NextGEN) have their own Bulk Optimize pages. 

= Skips Previously Optimized Images =

All optimized images are stored in the database so that the plugin does not attempt to re-optimize them unless they are modified. On the Bulk Optimize page you can view a list of already optimized images. You may also remove individual images from the list, or use the Force optimize option to override the default behavior. The re-optimize links on the Media Library page also force the plugin to ignore the previous optimization status of images.

= WP Image Editor = 

All images created by the built-in WP_Image_Editor class will be automatically optimized. Current implementations are GD, Imagick, and Gmagick. Images optimized via this class include Animated GIF Resize, BuddyPress Activity Plus (thumbs), Easy Watermark, Hammy, Imsanity, MediaPress, Meta Slider, MyArcadePlugin, OTF Regenerate Thumbnails, Regenerate Thumbnails, Simple Image Sizes, WP Retina 2x, WP RSS Aggregator and probably countless others. If you are not sure if a plugin uses WP_Image_Editor, send an inquiry on the support page.

= Optimize Everything Else =

Site admins can specify any folder within their WordPress folder to be optimized. The Bulk Scan under Media->Bulk Optimize will optimize theme images, BuddyPress avatars, BuddyPress Activity Plus images, Meta Slider slides, WP Symposium images, GD bbPress attachments, Grand Media Galleries, and any user-specified folders. Additionally, this tool can run on an hourly basis via wp_cron to keep newly uploaded images optimized. Scheduled optimization should not be used for any plugin that uses the built-in Wordpress image functions.

= WebP Images =

Can generate WebP versions of your images, and enables you to serve even smaller images to supported browsers. Several methods are available for serving WebP images, including Apache-compatible rewrite rules and our Alternative WebP Rewriting option compatible with caches and CDNs. Also works with the WebP option in the Cache Enabler plugin from KeyCDN.

= WP-CLI =

Allows you to run all Bulk Optimization processes from your command line, instead of the web interface. It is much faster, and allows you to do things like run it in 'screen' or via regular cron (instead of wp-cron, which can be unpredictable on low-traffic sites). Install WP-CLI from wp-cli.org, and run 'wp-cli.phar help ewwwio optimize' for more information. 

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

= Translations =

Huge thanks to all our translators! See the full list here: https://translate.wordpress.org/projects/wp-plugins/ewww-image-optimizer/contributors

If you would like to help translate this plugin (new or existing translations), you can do so here: https://translate.wordpress.org/projects/wp-plugins/ewww-image-optimizer
To receive updates when new strings are available for translation, you can signup here: https://ewww.io/register/

== Installation ==

1. Upload the 'ewww-image-optimizer' plugin to your '/wp-content/plugins/' directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Ensure jpegtran, optipng, pngout and gifsicle are installed on your Linux server (basic installation instructions are below if they are not). You will receive a warning when you activate the plugin if they are not present. This message will go away once you have them installed.
1. The plugin will attempt to install jpegtran, optipng, and gifsicle automatically for you. This requires that the wp-content folder is writable by the user running the web server.
1. If the automatic install did not work, find the appropriate binaries for your system in the ewww-image-optimizer plugin folder, copy them to wp-content/ewww/ and remove the OS 'tag' (like -linux or -fbsd). No renaming is necessary on Windows, just copy the .exe files to the wp-content/ewww folder. IMPORTANT: Do not symlink or modify the binaries in any way, or they will not pass the security checks. If you transfer files via FTP, be sure to transfer in binary mode, not ascii or text.
1. If the binaries don't run locally, you can sign up for the EWWW IO cloud service to run them via our optimization servers: https://ewww.io/plans/
1. *Recommended* Visit the settings page to enable/disable specific tools and turn on advanced optimization features.
1. Done!

If these steps do not work, additional documentation is available at https://docs.ewww.io. If you need further assistance using the plugin, please visit our [Support Page](https://ewww.io/contact-us/). The forums are community supported only.

= Webhosts =

In general, these lists only apply to shared hosting services. If the providers below have VPS or dedicated server options, those will likely work just fine. If you have any contributions or corrections to these lists, please contact me via the form at https://ewww.io

Webhosts where things work (mostly) out of the box:

* [A2 Hosting](https://www.a2hosting.com/): EWWW IO is installed automatically for A2 Optimized sites.
* [aghosted](https://aghosted.com/)
* [Arvixe](http://www.arvixe.com)
* [Bluehost](https://www.bluehost.com)
* [DigitalBerg](https://www.digitalberg.com)
* [Dreamhost](https://www.dreamhost.com)
* [GoDaddy](https://www.godaddy.com) (only with PHP 5.3+)
* [gPowerHost](https://gpowerhost.com/)
* [HostGator](http://www.hostgator.com)
* [Hetzner Online](https://www.hetzner.de)
* [Hosterdam](http://www.hosterdam.com) (FreeBSD)
* [HostMonster](https://www.hostmonster.com)
* [iFastNet](https://ifastnet.com/portal/) (with custom php.ini from customer support)
* [inmotion](http://www.inmotionhosting.com)
* [Liquid Web](https://www.liquidweb.com)
* [Namecheap](https://www.namecheap.com)
* [OVH](https://www.ovh.co.uk)
* [Site5] (https://www.site5.com) (tools must be built manually, or contact Site5 support for assistance)
* [SiteGround](https://www.siteground.com)
* [Spry Servers](https://www.spryservers.net) (even with PHP 7)
* [WebFaction](https://www.webfaction.com)
* [1&1](https://www.1and1.com) (pngout requires manual upload and permissions fix)

Webhosts where the plugin will only work in cloud mode or only some tools are installed locally:

* Cloudways
* Flywheel
* Gandi
* Hostwinds
* ipage (JPG only)
* ipower
* one.com - may not even work in cloud mode
* WP Engine - use EWWW Image Optimizer Cloud fork: https://wordpress.org/plugins/ewww-image-optimizer-cloud/


== Frequently Asked Questions ==

= Google Pagespeed says my images need compressing or resizing, but I already optimized all my images. What do I do? =

Try this for starters: http://docs.ewww.io/article/5-pagespeed-says-my-images-need-more-work

= The plugin complains that I'm missing something, what do I do? =

This article will walk you through installing the required tools (and the alternatives if installation does not work): http://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something

= Does the plugin replace existing images? =

Yes, but only if the optimized version is smaller. The plugin should NEVER create a larger image.

= Can I resize my images with this plugin? =

Yes, you can, set it up on the Advanced tab.

= Can I lower the compression setting for JPGs to save more space? =

The lossy JPG optimization using TinyJPG and JPEGmini will determine the ideal quality setting and give you the best results, but you can also adjust the default quality for conversion and resizing. More information here: http://docs.ewww.io/article/12-jpq-quality-and-wordpress

= The bulk optimizer doesn't seem to be working, what can I do? =

If it doesn't seem to work at all, check for javascript problems using the developer console in Firefox or Chrome. If it is not working just on some images, you may need to increase the setting max_execution_time in your php.ini file. There are also other timeouts with Apache, and possibly other limitations of your webhost. If you've tried everything else, the last thing to look for is large PNG files. In my tests on a shared hosting setup, "large" is anything over 300 KB. You can first try decreasing the PNG optimization level in the settings. If that doesn't work, perhaps you ought to convert that PNG to JPG or set a max PNG optimization size. Screenshots are often done as PNG files, but that is a poor choice for anything with photographic elements.
[youtube https://www.youtube.com/watch?v=V4U2hQlmkvQ]

= What are the supported operating systems? =

I've tested it on Windows (with Apache), Linux, Mac OSX, FreeBSD 9, and Solaris (v10). The cloud API will work on any OS.

= How are JPGs optimized? =

Lossless optimization is done with the command *jpegtran -copy all -optimize -progressive -outfile optimized-file original-file*. Optionally, the -copy switch gets the 'none' parameter if you choose to strip metadata from your JPGs on the options page. Lossy optimization is done using the outstanding TinyJPG and JPEGmini utilities.

= How are PNGs optimized? =

There are three parts (and all are optional). First, using the command *pngquant original-file*, then using the commands *pngout-static -s2 original-file* and *optipng -o2 original-file*. You can adjust the optimization levels for both tools on the settings page. Optipng is an automated derivative of pngcrush, which is another widely used png optimization utility. EWWW I.O. Cloud uses TinyPNG for 10% better lossy compression than standalone pngquant.

= How are GIFs optimized? =

Using the command *gifsicle -b -O3 --careful original file*. This is particularly useful for animated GIFs, and can also streamline your color palette. That said, if your GIF is not animated, you should strongly consider converting it to a PNG. PNG files are almost always smaller, they just don't do animations. The following command would do this for you on a Linux system with imagemagick: *convert somefile.gif somefile.png*

= I want to know more about image optimization, and why you chose these options/tools. =

That's not a question, but since I made it up, I'll answer it. See these resources:  
http://developer.yahoo.com/performance/rules.html#opt_images  
https://developers.google.com/speed/docs/insights/OptimizeImages

Pngout, TinyJPG/TinyPNG, JPEGmini, and Pngquant were recommended by EWWW IO users. Pngout (usually) optimizes better than Optipng, and best when they are used together. TinyJPG is the best lossy compression tool that I have found for JPG images. Pngquant is an excellent lossy optimizer for PNGs, and is one of the tools used by TinyPNG.

== Screenshots ==

1. Plugin settings page.
2. Additional optimize column added to media listing. You can see your savings, manually optimize individual images, and restore originals (converted only).
3. Bulk optimization page. You can optimize all your images at once and resume a previous bulk optimization. This is very useful for existing blogs that have lots of images.

== Changelog ==

* I no longer have a Mac with which to build new binaries. Until I can raise enough funds, there will be no more updates for the Mac OS X tools, and I may have to temporarily remove Mac support if any security issues arise in the meantime: https://www.gofundme.com/ewww-image-optimizer-mac-dev-laptop
* Feature requests can be submitted via https://ewww.io/contact-us/ and commented on here: https://trello.com/b/Fp81dWof/ewww-image-optimizer
* If you would like to help translate this plugin in your language, get started here: https://translate.wordpress.org/projects/wp-plugins/ewww-image-optimizer/

= 3.2.5 =
* fixed: converting PNG to JPG with GD did not properly convert resizes
* fixed: broken attachment metadata could halt the bulk scanner
* fixed: background optimization running when sleep is disabled

= 3.2.4 =
* changed: when license has been exceeded, visiting the settings page flushes the license cache
* fixed: warnings for illegal string offsets
* fixed: regression with the dreaded duplicate key name
* fixed: scheduled optimization could run during bulk optimization, causing unexpected results

= 3.2.3 =
* added: image linker for media images optimized using scheduled optimizer or the old Scan and Optimize
* added: low memory mode for bulk scanner with notice to user
* added: ability to manually configure how much memory is available using EWWW_MEMORY_LIMIT constant
* added: variable query counts depending on available memory
* added: ability to view and remove debug.log from settings page
* added: ability to manually disable background optimization using EWWW_DISABLE_ASYNC constant
* changed: check every 100 images during scan to avoid timeouts and memory errors
* changed: additional folder scanner can stop & resume mid-folder
* fixed: bulk scanner updates timestamps when it should not
* fixed: special characters are mangled during database insert on some systems
* fixed: pending images that were already optimized were not cleared from queue
* fixed: images with invalid updated dates in database corrected
* fixed: images that should be excluded from optimization were still queued even though they would not be optimized
* fixed: results column was too short, causing bulk optimization to get stuck on an image that was previously optimized
* fixed: if two different attachments reference the same image, duplicate records could be inserted into database during media scan

= 3.2.2 =
* added: estimated time remaining on bulk optimize
* added: 'ewww_image_optimizer_image_resized' hook added right after resizing, before original is overwritten
* changed: image resizing is performed before any thumbnails are generated for reduced resource usage
* fixed: compatibility with Azure storage plugin
* fixed: bulk optimization not playing nice with WP Offload S3
* fixed: optimization results for resized original not displayed when using Imsanity
* fixed: bulk optimization not working for utf-8 filenames - credit to devsporadic on github
* fixed: retina paths not tested correctly in some odd cases
* notice: FreeBSD 9 is EOL, version 10.3 is now the currently supported version
* notice: RHEL 5 and CentOS 5 will be EOL at the end of March, at that point version 6 will be the lowest supported version
* removed: generating full-size retina image automatically when resizing images and WP Retina 2x Pro detected

= 3.2.1 =
* fixed: really old versions of PHP (less than 5.5) cannot cope with using empty() on a function return value
* fixed: queue of images not reset when reloading bulk page

= 3.2.0 =
* added: option to ignore folders when optimizing
* added: ability to disable optimization or creation for any or all previews of PDF files in WordPress 4.7
* added: optimization results detail for all resizes of an image in media library list view
* added: automatic metadata rebuilding for broken image attachments in media library during bulk scan
* changed: bulk optimizers for media library and everything else have been merged
* changed: bulk optimization processes images in batches for fewer AJAX requests to your server
* changed: tool locations saved for the duration of a request to improve optimization speed
* changed: optimization results no longer stored in attachment metadata
* changed: populating list of optimized images during scan uses less memory
* changed: obsolete options removed from database
* changed: if scan is interrupted, it will automatically retry
* changed: excessive re-optimization warning ignores theme and plugin images
* changed: if full-size image is converted, all resizes, custom sizes, and retina images will be converted
* changed: conversion will not inject extra numbers if possible
* changed: image results message generated on demand to avoid stale results
* removed: ability to use the ImageMagick 'convert' binary, use Imagick extension for PHP instead
* removed: unoptimized images page, bulk scanner is now able to accomplish the job more accurately
* fixed: parallel mode prevents successful conversion
* fixed: removing API key on multisite did not fallback to local mode properly
* fixed: pngout enabled after API key removed
* fixed: image paths with special characters stored incorrectly in database
* fixed: parallel optimization for retina and custom sizes was missing parameters
* fixed: bulk optimizing a single image was broken, but who does that anyway?
* fixed: notice when LIBXML_VERSION is undefined and alt webp is enabled
* fixed: invalid default value for timestamp in db records
* fixed: one-click optimization returns no error when running out of API credits
* fixed: background mode was not checked properly in nextgen and flagallery functions
* fixed: incorrect mimetype set after image conversion for PNG2JPG
* fixed: using getimagesize on pdf files

== Upgrade Notice ==

= 3.2.3 =
* The bulk scanner will now attempt to auto-detect how much memory is available to avoid exceeding memory limits within PHP. Some webhosts do not allow the ini_get() function, so the plugin will fall back to the current memory usage plug 16MB. If you need to set the memory limit for EWWW IO manually, you can do so with the EWWW_MEMORY_LIMIT constant in wp-config.php.

= 2.9.0 =
* changed: JPG quality setting applies to conversion AND image editing (but not regular optimization), so that you can override the WP default of 82 (it is NOT recommended to increase the quality)
* added: parallel optimization for Media uploads (original and resizes are done concurrently), turn off under Advanced if it affects site performance

= 2.8.4 =
* security fix: remote command execution, please update immediately

= 2.8.1 =
* KeyCDN added support for WebP images generated by EWWW I.O. into the Cache Enabler plugin. If you are using Cache Enabler, you may wish to use their WebP option instead of Alt WebP Rewriting. Works very nicely with CDNs and is a nice simple caching plugin.

= 2.8.0 =
* added: resizing for uploaded images, set max width and height and optionally resize all existing images
* changed: settings have been revamped, please check to make sure your settings were migrated properly

== Contact and Credits ==

Written by [Shane Bishop](https://ewww.io). Based upon CW Image Optimizer, which was written by [Jacob Allred](http://www.jacoballred.com/) at [Corban Works, LLC](http://www.corbanworks.com/). CW Image Optimizer was based on WP Smush.it. Jpegtran is the work of the Independent JPEG Group.  
[Hammer](http://thenounproject.com/noun/hammer/#icon-No1306) designed by [John Caserta](http://thenounproject.com/johncaserta) from The Noun Project.  
[Images](http://thenounproject.com/noun/images/#icon-No22772) designed by [Simon Henrotte](http://thenounproject.com/Gizmodesbois) from The Noun Project.

= optipng =

Copyright (C) 2001-2014 Cosmin Truta and the Contributing Authors.
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
   © 2009-2014 by Kornel Lesiński.

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

