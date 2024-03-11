# WP Document Revisions - WPML Support

## Type of Support

If WPML is implemented, there are two modes of operation that can be used with Document posts:
1. The attached document file is not translated, that is, although the post is translated, the actual document file is shared between the translations; or
2. Each translated post is to have its own version of the document.

This mode of operation can be determined for each document when the document post is first created.

In the Media Attachments sub-section on the Languages metabox there is a tickbox labelled "Duplicate uploaded media to translations". If ticked on creating a translated document, then the first mode is used, otherwise it will be the second.

This choice is made for the lifetime of the document post. It can be changed up to the time that a translated post is created. Once created, the corresponding checkbox on the screen will be disabled.

The original (untranslated) post is known here as the Main post. 

Any posts translated from this post is referred to here as a Translated post.

## Impact of this Option

### Shared Document Files

New uploads of the document file can only be uploaded by editing the Main post.

Thus Translated posts will not support uploading of revision documents.

The Main post cannot be deleted if there are any Translated posts. That is, the Translated posts need to be deleted before the Main post can be trashed or deleted.

### Unique Document Files

New uploads of the document file have to be uploaded via the Translated post.

Logically the Main and Translated posts are tied together for WPML processing, they are independent as far as the document attachments are concerned.

## Debug Support

This is an initial release and it may need debug support. Some functionality has been included into the plugin to display various data on the screen.

This is to be found in the Help Pulldown on Document editing screen. 