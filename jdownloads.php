<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Finder.Contacts
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\Registry\Registry;

require_once JPATH_ADMINISTRATOR . '/components/com_finder/helpers/indexer/adapter.php';

/**
 * Finder adapter for Joomla Contacts.
 *
 * @since  2.5
 */
class PlgFinderJdownloads extends FinderIndexerAdapter
{
	protected $context = 'JDownloads';
	protected $extension = 'com_jdownloads';
	protected $layout = 'download';
	protected $type_title = 'jdownloads';
	protected $table = '#__jdownloads_files';
	protected $state_field = 'published';
	protected $autoloadLanguage = true;

	
	protected function index(FinderIndexerResult $item, $format = 'html')
	{
		// Check if the extension is enabled
		if (JComponentHelper::isEnabled($this->extension) == false)
		{
			return;
		}

		$item->setLanguage();

		// Initialize the item parameters.
		$registry = new Registry;
		$registry->loadString($item->params);
		$item->params = $registry;

		// Build the necessary route and path information.
		$item->url = $this->getUrl($item->file_id, $this->extension, $this->layout);
		$item->route = JdownloadsHelperRoute::getDownloadRoute($item->slug, $item->catslug, $item->language);
		$item->path = FinderIndexerHelper::getContentPath($item->route);

		// Get the menu title if it exists.
		$title = $this->getItemMenuTitle($item->url);

		// Adjust the title if necessary.
		if (!empty($title) && $this->params->get('use_menu_title', true))
		{
			$item->title = $title;
		}

		
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'title');
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'metakey');
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'metadesc');
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'metaauthor');
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'author');
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'date_added');

		// Add the type taxonomy data.
		$item->addTaxonomy('Type', 'JDownloads');

		// Get content extras.
		FinderIndexerHelper::getContentExtras($item);

		// Index the item.
		$this->indexer->index($item);
	}

	protected function setup()
	{
		// Load dependent classes.
		require_once JPATH_SITE . '/components/com_jdownloads/helpers/route.php';

		// This is a hack to get around the lack of a route helper.
		FinderIndexerHelper::getContentPath('index.php?option=com_jdownloads');

		return true;
	}

	protected function getListQuery($query = null)
	{
		$db = JFactory::getDbo();

            $query = $query instanceof JDatabaseQuery ? $query : $db->getQuery(true)
            ->select('a.file_id, a.file_title as title, a.file_alias, a.description AS summary')
            ->select('a.file_pic, a.release, a.date_added, a.publish_from, a.access, a.ordering')
            ->from('#__jdownloads_files AS a')
            ->where('a.published = 1');

		return $query;
	}
}