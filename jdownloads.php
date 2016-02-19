<?php

defined('JPATH_BASE') or die;

use Joomla\Registry\Registry;

require_once JPATH_ADMINISTRATOR . '/components/com_finder/helpers/indexer/adapter.php';

class PlgFinderjdownloads extends FinderIndexerAdapter {

    protected $context = 'JDownloads';
    protected $extension = 'com_jdownloads';
    protected $layout = 'download';
    protected $type_title = 'jdownloads';
    protected $table = '#__jdownloads_files';
    protected $state_field = 'published';
    protected $autoloadLanguage = true;

    public function onFinderCategoryChangeState($extension, $pks, $value) {
        // Make sure we're handling com_contact categories
        if ($extension == 'com_jdownloads') {
            $this->categoryStateChange($pks, $value);
        }
    }

    public function onFinderAfterDelete($context, $table) {
        if ($context == 'com_jdownloads.download') {
            $id = $table->file_id;
        } elseif ($context == 'com_finder.index') {
            $id = $table->link_id;
        } else {
            return true;
        }
        // Remove the items.
        return $this->remove($id);
    }

    public function onFinderAfterSave($context, $row, $isNew) {
        // We only want to handle contacts here
        if ($context == 'com_jdownloads.download') {
            // Check if the access levels are different
            if (!$isNew && $this->old_access != $row->access) {
                // Process the change.
                $this->itemAccessChange($row);
            }

            // Reindex the item
            $this->reindex($row->file_id);
        }

        // Check for access changes in the category
        if ($context == 'com_jdownloads.category') {
            // Check if the access levels are different
            if (!$isNew && $this->old_cataccess != $row->access) {
                $this->categoryAccessChange($row);
            }
        }

        return true;
    }

    public function onFinderBeforeSave($context, $row, $isNew) {
        // We only want to handle contacts here
        if ($context == 'com_jdownloads.download') {
            // Query the database for the old access level if the item isn't new
            if (!$isNew) {
                $this->checkItemAccess($row);
            }
        }

        // Check for access levels from the category
        if ($context == 'com_jdownloads.category') {
            // Query the database for the old access level if the item isn't new
            if (!$isNew) {
                $this->checkCategoryAccess($row);
            }
        }

        return true;
    }

    public function onFinderChangeState($context, $pks, $value) {
        // We only want to handle podcast feeds here
        if ($context == 'com_jdownloads.download') {
            $this->itemStateChange($pks, $value);
        }
        // Handle when the plugin is disabled
        if ($context == 'com_plugins.plugin' && $value === 0) {
            $this->pluginDisable($pks);
        }
    }

    protected function index(FinderIndexerResult $item, $format = 'html') {
        // Check if the extension is enabled
        if (JComponentHelper::isEnabled($this->extension) == false) {
            return;
        }

        $item->setLanguage();

        // Initialize the item parameters.
        $registry = new Registry;
        $registry->loadString($item->params);
        $item->params = $registry;

        // Initialize the item parameters.
        $registry = new Registry;
        $registry->loadString($item->info);
        $item->info = $registry;

        // Build the necessary route and path information.
        $item->url = $this->getUrl($item->file_id, $this->extension, $this->layout);
        $item->route = JdownloadsHelperRoute::getDownloadRoute($item->slug, 0, $item->language);
        $item->path = FinderIndexerHelper::getContentPath($item->route);


        // Get the menu title if it exists.
        $title = $this->getItemMenuTitle($item->url);

        // Adjust the title if necessary.
        if (!empty($title) && $this->params->get('file_title', true)) {
            $item->title = $title;
        }

        // Handle the contact position.                
        $item->addInstruction(FinderIndexer::META_CONTEXT, 'secretary');

        // Add the type taxonomy data.
        $item->addTaxonomy('Type', 'JDownloads');

        // Get content extras.
        FinderIndexerHelper::getContentExtras($item);


        // Index the item.
        $test = $this->indexer->index($item);
    }

    protected function setup() {
        // Load dependent classes.
        require_once JPATH_SITE . '/components/com_jdownloads/helpers/route.php';

        // This is a hack to get around the lack of a route helper.
        FinderIndexerHelper::getContentPath('index.php?option=com_jdownloads');

        return true;
    }

    protected function getListQuery($query = null) {
        $db = JFactory::getDbo();

        // Check if we can use the supplied SQL query.
        $query = $query instanceof JDatabaseQuery ? $query : $db->getQuery(true)
                        ->select('a.file_id, a.file_title as title, a.file_alias, a.description, a.url_download AS info')
                        ->select('a.published as state, a.cat_id')
                        ->select('a.created_by, a.modified_date, a.modified_by')
                        ->select('a.metakey, a.metadesc, a.file_language, a.access')
                        ->select('a.publish_from AS publish_start_date, a.publish_to AS publish_end_date')
                        ->select('c.title AS category, c.published AS cat_state, c.access AS cat_access');

        // Handle the alias CASE WHEN portion of the query
        $case_when_item_alias = ' CASE WHEN ';
        $case_when_item_alias .= $query->charLength('a.file_alias', '!=', '0');
        $case_when_item_alias .= ' THEN ';
        $a_id = $query->castAsChar('a.file_id');
        $case_when_item_alias .= $query->concatenate(array($a_id, 'a.file_alias'), ':');
        $case_when_item_alias .= ' ELSE ';
        $case_when_item_alias .= $a_id . ' END as slug';
        $query->select($case_when_item_alias);

        $case_when_category_alias = ' CASE WHEN ';
        $case_when_category_alias .= $query->charLength('c.alias', '!=', '0');
        $case_when_category_alias .= ' THEN ';
        $c_id = $query->castAsChar('c.id');
        $case_when_category_alias .= $query->concatenate(array($c_id, 'c.alias'), ':');
        $case_when_category_alias .= ' ELSE ';
        $case_when_category_alias .= $c_id . ' END as catslug';
        $query->select($case_when_category_alias)
                ->select('us.name AS secretary')
                ->from('#__jdownloads_files AS a')
                ->join('LEFT', '#__categories AS c ON c.id = a.cat_id')
                ->join('LEFT', '#__users AS us ON us.id = a.file_title');

        return $query;
    }

    protected function getURL($id, $extension, $view) {
        return 'index.php?option=' . $extension . '&view=' . $view . '&id=' . $id;
    }

    protected function getStateQuery() {
        $sql = $this->db->getQuery(true);
        $sql->select($this->db->quoteName('a.file_id'));
        $sql->select($this->db->quoteName('a.' . $this->state_field, 'state'));
        $sql->select('NULL AS cat_state');
        $sql->from($this->db->quoteName($this->table, 'a'));
        return $sql;
    }

}
