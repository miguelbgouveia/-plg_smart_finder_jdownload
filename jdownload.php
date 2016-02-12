<?php

defined('_JEXEC') or die;

use Joomla\Registry\Registry;

// Load the base adapter.
require_once JPATH_ADMINISTRATOR . '/components/com_finder/helpers/indexer/adapter.php';

class PlgFinderJdownloads extends FinderIndexerAdapter
{
	protected $context = 'Jdownloads';
	protected $extension = 'com_jdownloads';
	protected $layout = 'downloads';
	protected $type_title = 'Jdownloads';
	protected $table = '#__jdownloads_files';
	protected $autoloadLanguage = true;
	protected $state_field = 'published';
        
        public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}
        
        public function onFinderCategoryChangeState($extension, $pks, $value)
	{
		// Make sure we're handling com_contact categories
		if ($extension == 'com_jdownloads')
		{
			$this->categoryStateChange($pks, $value);
		}
	}

	public function onFinderAfterDelete($context, $table)
	{
		if ($context == 'com_jdownloads.downloads')
		{
			$id = $table->id;
		}
		elseif ($context == 'com_finder.index')
		{
			$id = $table->link_id;
		}
		else
		{
			return true;
		}

		// Remove the item from the index.
		return $this->remove($id);
	}

	
	public function onFinderAfterSave($context, $row, $isNew)
	{
		// We only want to handle web links here. We need to handle front end and back end editing.
		if ($context == 'com_jdownloads.downloads' )
		{
			// Check if the access levels are different.
			if (!$isNew && $this->old_access != $row->access)
			{
				// Process the change.
				$this->itemAccessChange($row);
			}

			// Reindex the item.
			$this->reindex($row->id);
		}

		return true;
	}
        
	public function onFinderBeforeSave($context, $row, $isNew)
	{
		// We only want to handle web links here.
		if ($context == 'com_jdownloads.downloads' )
		{
			// Query the database for the old access level if the item isn't new.
			if (!$isNew)
			{
				$this->checkItemAccess($row);
			}
		}

		return true;
	}
        
	public function onFinderChangeState($context, $pks, $value)
	{
		// We only want to handle web links here.
		if ($context == 'com_jdownloads.downloads' )
		{
			$this->itemStateChange($pks, $value);
		}

		// Handle when the plugin is disabled.
		if ($context == 'com_plugins.plugin' && $value === 0)
		{
			$this->pluginDisable($pks);
		}
	}

        protected function index(FinderIndexerResult $item, $format = 'html')
	{
		// Check if the extension is enabled
		if (JComponentHelper::isEnabled($this->extension) == false)
		{
			return;
		}		
		
                // Initialize the item parameters.
		$registry = new JRegistry;
		$registry->loadString($item->params);
		$item->params = $registry;

		// Build the necessary route and path information.
		$item->url = $this->getURL($item->id, $this->extension, $this->layout);
		//$item->route = EDocmanHelperRoute::getDocumentRoute($item->slug, $item->catslug);
		$item->route = JdownloadsHelperRoute::getDownloadRoute($item->slug, $item->catslug);
		$item->path = $item->route;

		// Get the menu title if it exists.
		$title = $this->getItemMenuTitle($item->url);

		// Adjust the title if necessary.
		if (!empty($title) && $this->params->get('use_menu_title', true))
		{
			$item->title = $title;
		}

		/*
		 * Add the meta-data processing instructions based on the contact
		 * configuration parameters.
		 */
		// Handle the contact user name.
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'title');
		// Add the meta-data processing instructions.
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'metakey');
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'metadesc');
		//$item->addInstruction(FinderIndexer::META_CONTEXT, 'metaauthor');
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'author');
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'date_added');
		
		$item->state = $this->translateState($item->state);
		// Add the type taxonomy data.
		$item->addTaxonomy('Type', 'Jdownloads');
		
		// Add the category taxonomy data.
		$item->addTaxonomy('Downloads', $item->body, $item->state, $item->access);

		// Add the language taxonomy data.
		$item->addTaxonomy('Language', $item->language);
		
		// Get content extras.
		FinderIndexerHelper::getContentExtras($item);

		// Index the item.
		FinderIndexer::index($item);
	}

	protected function setup()
	{
		// Load dependent classes.
		require_once JPATH_SITE . '/components/com_jdownloads/helpers/route.php';

		return true;
	}

	protected function getListQuery($sql = null)
	{
		$db = JFactory::getDbo();
                $sql = $sql instanceof JDatabaseQuery ? $sql : $db->getQuery(true);
                
                // Check if we can use the supplied SQL query.
		//$sql = is_a($query, 'JDatabaseQuery') ? $query : $this->db->getQuery(true);
		//$sql->select($this->db->quoteName('a.*'));
                $sql->select('a.file_id, a.file_title, a.file_alias, a.description AS summary, a.url_download AS body');
		$sql->select('a.published AS state, a.access AS access, a.date_added AS start_date');
		//$query->select($this->db->quoteName('a.file_title', 'title'));
		//$query->select($this->db->quoteName('a.description', 'summary'));
		//$query->select($this->db->quoteName('a.published', 'state'));
		//$query->select('0 AS publish_start_date');
		//$query->select('0 AS publish_end_date');
		//$query->select('1 AS access');
                $sql->from($this->db->quoteName('#__jdownloads_files AS a'));
                //$sql->where($this->db->quoteName('a.file_title').' LIKE '.$db->quote('%'.$text.'%'));
		//$sql->where($this->db->quoteName('a.state').'=1');
           
                return $sql;

	}

	/*protected function getStateQuery()
	{
		$query = $this->db->getQuery(true);
		$query->select($this->db->quoteName('a.file_id'))
			->select($this->db->quoteName('a.' . $this->state_field, 'state') . ', ' . $this->db->quoteName('a.access'))
			->from($this->db->quoteName($this->table, 'a'));

		return $query;
	}

	protected function getUpdateQueryByTime($time)
	{
		// Build an SQL query based on the modified time.
		$query = $this->db->getQuery(true)
			->where('a.date >= ' . $this->db->quote($time));

		return $query;
	}*/
	
}
