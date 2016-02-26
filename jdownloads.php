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
        return $this->remove($id);
    }

    public function onFinderAfterSave($context, $row, $isNew) {

        if ($context == 'com_jdownloads.download') {
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

        if ($context == 'com_jdownloads.download') {
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

        if ($context == 'com_jdownloads.download') {
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

        $item->addTaxonomy('Type', 'JDownloads');

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
                        ->select('c.title AS category, c.published AS cat_state, c.access AS cat_access');

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
