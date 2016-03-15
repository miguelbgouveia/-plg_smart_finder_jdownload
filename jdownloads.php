<?php

defined('JPATH_BASE') or die;

use Joomla\Registry\Registry;

require_once JPATH_ADMINISTRATOR . '/components/com_finder/helpers/indexer/adapter.php';

class PlgFinderjdownloads extends FinderIndexerAdapter {

    private $category_table = '#__jdownloads_categories';

    protected $context = 'JDownloads';
    protected $extension = 'com_jdownloads';
    protected $layout = 'download';
    protected $type_title = 'jdownloads';
    protected $table = '#__jdownloads_files';
    protected $state_field = 'published';
    protected $identifier_field = 'file_id';
    protected $autoloadLanguage = true;

    public function onFinderCategoryChangeState($extension, $pks, $value) {
        if ($extension == 'com_jdownloads.category') {
            $this->categoryStateChange($pks, $value);
        }
    }

    public function onFinderAfterDelete($context, $table) {
        if ($this->isInJdownloadsContext($context)) {
            $id = $table->file_id;
        } elseif ($context == 'com_finder.index') {
            $id = $table->link_id;
        } else {
            return true;
        }
        return $this->remove($id);
    }

    public function onFinderAfterSave($context, $row, $isNew) {
        if ($this->isInJdownloadsContext($context)) {
            if (!$isNew && $this->old_access != $row->access) {
                $this->itemAccessChange($row);
            }

            $this->reindex($row->file_id);
        }

        if ($context == 'com_jdownloads.category') {
            if (!$isNew && $this->old_cataccess != $row->access) {
                $this->categoryAccessChange($row);
            }
        }

        return true;
    }

    public function onFinderBeforeSave($context, $row, $isNew) {

        if ($this->isInJdownloadsContext($context)) {
            if (!$isNew) {
                $this->checkItemAccess($row);
            }
        }

        if ($context == 'com_jdownloads.category') {
            if (!$isNew) {
                $this->checkCategoryAccess($row);
            }
        }

        return true;
    }

    public function onFinderChangeState($context, $pks, $value) {

        if ($this->isInJdownloadsContext($context)) {
            $this->itemStateChange($pks, $value);
        }
        if ($context == 'com_plugins.plugin' && $value === 0) {
            $this->pluginDisable($pks);
        }
    }

    protected function index(FinderIndexerResult $item, $format = 'html') {

        if (JComponentHelper::isEnabled($this->extension) == false) {
            return;
        }

        $item->setLanguage();

        $registry = new Registry;
        $registry->loadString($item->params);
        $item->params = $registry;

        $registry = new Registry;
        $registry->loadString($item->info);
        $item->info = $registry;

        $item->url = $this->getUrl($item->file_id, $this->extension, $this->layout);
        $item->route = JdownloadsHelperRoute::getDownloadRoute($item->slug, 0, $item->language);
        $item->path = FinderIndexerHelper::getContentPath($item->route);


        $title = $this->getItemMenuTitle($item->url);

        if (!empty($title) && $this->params->get('file_title', true)) {
            $item->title = $title;
        }

        $item->addInstruction(FinderIndexer::META_CONTEXT, 'secretary');

        // Translate the state. Downloads should only be published if the category is published.
        $item->state = $this->translateState($item->state, $item->cat_state);

        $item->addTaxonomy('Type', 'JDownloads');
        $item->addTaxonomy('Category', $item->category, $item->cat_state, $item->cat_access);

        FinderIndexerHelper::getContentExtras($item);

        $test = $this->indexer->index($item);
    }

    protected function setup() {

        require_once JPATH_SITE . '/components/com_jdownloads/helpers/route.php';

        FinderIndexerHelper::getContentPath('index.php?option=com_jdownloads');

        return true;
    }

    protected function getListQuery($query = null) {

        $db = JFactory::getDbo();

        $query = $query instanceof JDatabaseQuery ? $query : $db->getQuery(true)
                        ->select('a.file_id, a.file_title as title, a.file_alias, a.description, a.url_download AS info')
                        ->select('a.published as state, a.cat_id, a.description as summary')
                        ->select('a.created_by, a.modified_date, a.modified_by')
                        ->select('a.metakey, a.metadesc, a.file_language, a.access')
                        ->select('a.publish_from AS publish_start_date, a.publish_to AS publish_end_date')
                        ->select('c.cat_dir AS category, c.published AS cat_state, c.access AS cat_access');

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
                ->from($this->table . ' AS a')
                ->join('LEFT', $this->category_table . ' AS c ON c.id = a.cat_id')
                ->join('LEFT', '#__users AS us ON us.id = a.file_title');

        return $query;
    }

    protected function getURL($id, $extension, $view) {
        return 'index.php?option=' . $extension . '&view=' . $view . '&id=' . $id;
    }

    protected function getStateQuery()
    {
        $query = $this->db->getQuery(true);

        // Item ID
        $query->select('a.' . $this->identifier_field . ' AS id');

        // Item and category published state
        $query->select('a.' . $this->state_field . ' AS state, c.published AS cat_state');

        // Item and category access levels
        $query->select('a.access, c.access AS cat_access')
          ->from($this->table . ' AS a')
          ->join('LEFT', $this->category_table . ' AS c ON c.id = a.cat_id');

        return $query;
    }

    /**
     * Method to check the existing access level for categories
     *
     * @param   JTable  $row  A JTable object
     *
     * @return  void
     *
     * @since   3.5
     */
    protected function checkCategoryAccess($row)
    {
      $query = $this->db->getQuery(true)
        ->select($this->db->quoteName('access'))
        ->from($this->db->quoteName($this->category_table))
        ->where($this->db->quoteName('id') . ' = ' . (int) $row->id);

      $this->db->setQuery($query);

      // Store the access level to determine if it changes
      $this->old_cataccess = $this->db->loadResult();
    }

    private function isInJdownloadsContext($context)
    {
      return $context == 'com_jdownloads.form' ||
             $context == 'com_jdownloads.download';
    }
}
