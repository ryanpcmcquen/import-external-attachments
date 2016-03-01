=== Import external attachments ===
Contributors: ryanpcmcquen
Donate link: https://ryanpcmcquen.org
Tags: images, gallery, photobloggers, attachments, photo, links, external, photographers, Flickr, save, download, pdf
Requires at least: 3.2
Tested up to: 4.4
Stable tag: trunk

Makes local copies of all the linked images and pdfs in a post, adding them as gallery attachments.

== Description ==

Makes local copies of all the linked images and pdfs in a post, adding them as gallery attachments.

= Features =

= Credits =

This plugin is baszed on the work done in the "Import External Images" plugin by MartyThornley.

== Installation ==

1. Download the "Import external attachments" zip file.
2. Extract the files to your WordPress plugins directory.
3. Activate the plugin via the WordPress Plugins tab.

== Frequently Asked Questions ==

= How does this plugin work? =

The plugin examines the HTML source of your post when you save it, inspecting each IMG tag, and processing them according to the options you have selected.

Under the default settings, it will find IMG tags with links to images on other web sites and copy those images to your web site, updating the IMG src to point to your copy.

PDF functionality was added by bengreeley.

= Does it work with MultiSite? =

Yes! It was developed and built ( and is used everyday ) at PhotographyBlogSites.com - a multisite install.

= What if i don't want to import images from a third party image hosting site? =

You can make it ignore any domain you want on the settings page, in case you work with a CDN or photo hosting site and want to keep those images where they are.

== Changelog ==

= 1.5.6 =

Fix naming.

= 1.5.5 =

Make import_images_start_time a function. Hopefully it works now.  :^)

= 1.5.4 =

Make import_images_start_time globally accessible.

= 1.5.3 =

General cleanup.

= 1.5.1 =

Merged upstream pull requests from bengreeley and IvanDoomer and changed to 'Import external attachments'.
Now supports PDFs and HTTPS!

= 1.3 =

Fixed case sensitivity, thanks to https://github.com/SidFerreira
Fixed duplicate EXTERNAL_IMAGES_DIR notice

= 1.1 =

fixed title in readme.
