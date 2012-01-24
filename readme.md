Sample code to help customize WP Document Revisions. Once adapted to your organizations needs the files in the cookbook should be dropped into your /wp-content/plugins/ directory (or a sub-directory thereof) and activated like any plugin would.

1. **Third Party Encryption** - example of how to integrate at rest encryption using third-party tools
1. **Audit Trail** - creates check in / check out audit trail
1. **Bulk Import** - how to batch import a directory (or other list) of files as documents
1. **Post Parent** - Creates UI for selecting document's post parent (useful for querying on the front-end like attachments)
1. **Filetype Taxonomy** - Adds support to filter by filetype
1. **Remove Workflow States** - Completely removes Workflow state taxonomy backend and UI
1. **Rename Documents** - changes all references to "Documents" in the interface to any label of your choosing
1. **State Change Notification** - how to use document api to allow the author to receive notification 
1. **State Permissions** - allows setting user level permissions based on a custom taxonomy such as workflow state or other document status
whenever his or her document changes workflow states
1. **Change Tracker** - Auto-generates and appends revision summaries for changes to taxonomies, title, and visibility'

Code Cookbook Alumni (Deprecated functions now included with the plugin by default):

1. **Recently Revised Widget** - example of how to list recently revised documents in a widget
1. **Revision Shortcode** - Code sample to demonstrate short code to list revisions
1. **Edit Flow Support** - detect and integrate with Edit Flow, when present

*See also*, the [full set up custom plugins used](https://github.com/benbalter/PCLJ-Members-Workspace) to power a peer reviewed scholarly publication's workflow.