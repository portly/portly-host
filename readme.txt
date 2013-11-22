=== Portly Router ===
Contributors: kellymartinv
Tags: hostname, domain, routing, address
Requires at least: 2.8
Tested up to: 3.7.1
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Zero-config plugin that integrates with Portly for accessing your local
installation at a public URL.

== Description ==

Portly Router is a plugin designed to be used with [Portly](https://getportly.com)
to access your local WordPress setup via a public URL.

The plugin alters all WordPress-generated URLs according to the server's
current hostname and handles reverse-proxy HTTPS connections. It essentially
allows using a public domain without configuring VirtualHosts in Apache or
altering the Site URL in the WordPress installation.

== Installation ==

Portly Router is designed to be plug-and-play.

1. Upload the plugin to the `/wp-content/plugins/` directory in its own
folder.
2. Activate the plugin through the 'Plugins' menu in WordPress.
