=== Role Permissions for WP Document Revisions ===
Contributors: nwjames
Tags: documents, document management, access, roles
Requires at least: 4.9
Requires PHP: 7.4
Requires Plugins: wp-document-revisions, members
Tested up to: 6.5
Stable tag: 1.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Add-on to WP Document Revisions and Members plugins. Restrict access to roles defined on document posts.

== Description ==

This plugin is an integration between WP Document Revisions and Members plugins.

By default all published documents are available to everyone.

This plugin will enable you to use the Members Content Permissions functionality for your Documents.

When editing a document, this will provide an additional metabox to allow you to optionally one or more role values

If roles have been entered here, then a user will need to have at least one of those roles assigned to them to be able to access the document.

Note that
1. Administrators and users with `restrict_content` are allowed to see all content.
2. Standard WP Document Revisions permission controls are always applied.
This means that it is always necessary to have the Document Revisions permission necessary to do an operation. The Members permission control may then hide certain documents from these operations.

== Installation ==

1. Download the Cookbook code from GitHub
2. Copy the `wpdr-role-permissions` directory to your plugins directory. This can be done by creating a zip file of the directory and loading that as a plugin.
3. Activate the plugin through the 'Plugins' menu in WordPress

== Role Permissions for WP Document Revisions Filters ==

None are currently defined,

== Frequently Asked Questions ==

None so far.

== Changelog ==

= 1.0 =
Release date: December 6, 2023

