-plg_smart_finder_jdownload
Joomla plug in to add jdownloads contents to the smart search.

To install it you just proceed as normal for installing plugins in Joomla. The plugin is ready to work in joomla 2.5 and 3.x versions.

There some bug that we not resolve yet:

Change the category state doesn't propagate the changes to the jdownload content. If we have a jdownload file that is published in a category that are unpublished the file will appear in the result of a search when it shouldn't.
The access permissions for the jdonwload content is not taken in account when showing the results of a search.
When working with multi language in the results of a search shows all items for all the defined languages. The links of the files that don't belong to the defined language are wrong.
This problems have to be correct in the Joomla base code. You can following this problems in this pull request in Joomla open source repository: https://github.com/joomla/joomla-cms/pull/9346.
