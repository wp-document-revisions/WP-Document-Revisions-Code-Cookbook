# WP Document Revisions - WPML Support

## Simplified Data Structure of WP Document Revisions

WP Document Revisions makes use of a custom post type `document` that be associated with a document file (e.g. pdf) that can be uploaded and linked to the document.

The upload process makes use of the standard media attachment process that creates an `attachment` post type and is the actual linkage to the document file.

To support the concept of revisions, the plugin puts the id of the attachment in the `post_content` field and revisions are automatically created when the `post_content` field is changed.

Hence an accessible history of all the versions of the attached document file can be maintained.

## WPML Processing

WPML will create translated post records (either documents (and so its revisions) and its attachments. It does not address the document files themselves.

## Role of this Plugin  

WPML does not know of the internal linkages of the `post_content` field holding the attachment identifier. So this plugin tries to ensure that this field is maintained correctly.

## Type of Support

If WPML is implemented, there are two modes of operation that can be used with Document posts:
1. The attached document file is not translated, that is, although the post is translated, the actual document file is shared between the translations; or
2. Each translated post is to have its own version of the document.

This mode of operation can be set for each document when the document post is first created.

In the Media Attachments sub-section within the Languages metabox there is a tickbox labelled "Duplicate uploaded media to translations". If ticked when first saving a document, then the first mode is used, otherwise it will be the second.

This choice is made for the lifetime of the document post. However it can be changed up to the time that a translated post is created. Once created, the corresponding checkbox on the screen will be disabled.

The original (untranslated) post is known here as the Main post. 

Any posts translated from this post is referred to here as a Translated post.

## Impact of this Option

### Shared Document Files

New uploads of the document file can only be uploaded by editing the Main post.

Thus Translated posts will not support uploading of revision documents.

The Main post cannot be deleted if there are any Translated posts. That is, all Translated posts need to be deleted before the Main post can be trashed or deleted.

### Unique Document Files

New uploads of the document file have to be uploaded via the Translated post.

Logically the Main and Translated posts are tied together for WPML processing, they are independent as far as the document attachments are concerned.

## Debug capability

Since the role of the plugin is to make sure that the linkages are set correctly, to help know the data relationships the document post structure is made available.

This is held in the help pull-down from the document edit screen where additional tab areas have been created.
