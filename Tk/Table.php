<?php

namespace Tk;

use Tk\Dom\AttributesTrait;
use Tk\Dom\CssTrait;
use Tk\Table\Action;
use Tk\Table\Cell;
use Tk\Db\Tool;
use \Tk\Form\Event;
use Tk\Table\Row;


/**
 *
 * - Add ?rts=rts to the URL request to reset the table session
 *
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class Table implements \Tk\InstanceKey
{

    use AttributesTrait;
    use CssTrait;
    use CollectionTrait;
    use ConfigTrait;

    /**
     * This is the query string to set to reset the table session fully
     */
    const RESET_TABLE = 'rts';

    const PARAM_ORDER_BY = 'orderBy';
    const ORDER_NONE = '';
    const ORDER_ASC = 'ASC';
    const ORDER_DESC = 'DESC';

    /**
     * Used internally to flag when the filter submit buttons have been appended
     * @var bool
     */
    protected $formInit = false;

    /**
     * Internal flag for executing the filter form
     * @var bool
     */
    protected $filterFormExecuted = false;


    /**
     * @var int|null
     */
    private $instanceId = null;

    /**
     * @var string
     */
    protected $id = '';

    /**
     * @var Action\Iface[]
     */
    protected $actionList = array();

    /**
     * @var Cell\Iface[]
     */
    protected $cellList = array();

    /**
     * @var array|\ArrayAccess
     * @deprecated
     * @remove 2.4.0
     */
    protected $paramList = array();

    /**
     * @var array
     */
    protected $list = null;

    /**
     * @var Form
     */
    protected $form = null;

    /**
     * @var string
     */
    protected $staticOrderBy = null;

    /**
     * @var bool
     */
    protected $executed = false;

    /**
     * @var null|\Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    protected $dispatcher = null;

    /**
     * @var Tool
     */
    private $tool = null;

    /**
     * @var null|Table\Renderer\Iface
     */
    private $renderer = null;

    /**
     * @var Row
     */
    private $row = null;

    
    /**
     * Create a table object
     *
     * @param string $tableId
     */
    public function __construct($tableId = '')
    {
        static $tid = 1;
        $this->getInstanceId();             // Init the instance ID so is can be used if needed
        if (!$tableId) {
            $uri = \Tk\Uri::create();
            $uri = str_replace('.'.$uri->getExtension(), '', $uri->basename()) . '-' . $tid++;
            $tableId = trim(strtolower(preg_replace('/[A-Z]/', '-$0', $uri . \Tk\ObjectUtil::basename(get_class($this)) )), '-');
        }
        
        $this->id = $tableId;
        $this->row = new Row();
        $this->setAttr('id', $this->getId());
        $this->getTableSession();           // init Table Session
        $this->form = $this->makeForm();
        
        // TODO: Re-think this, we need to look at both the tables and forms create/init/execute/show logic so they work together.
        //$this->initCells();
    }

    /**
     * get the unique table instance ID
     * @return int|null
     */
    public function getInstanceId()
    {
        static $count = 1;
        if ($this->instanceId === null) {
            $this->instanceId = $count++;
        }
        return $this->instanceId;
    }

    /**
     * @param string $tableId
     * @return static
     */
    public static function create($tableId = '')
    {
        $obj = new static($tableId);
        return $obj;
    }

    /**
     * @return bool
     */
    public function isExecuted(): bool
    {
        return $this->executed;
    }

    /**
     * @param bool $executed
     * @return Table
     */
    protected function setExecuted(bool $executed): Table
    {
        $this->executed = $executed;
        return $this;
    }

    /**
     * Execute the table
     * Generally called in the renderer`s show() method
     */
    public function execute()
    {
        if (!$this->isExecuted()) {
            /* @var Cell\Iface $cell */
            foreach ($this->getCellList() as $cell) {
                $cell->execute();
            }
            /* @var Action\Iface $action */
            foreach ($this->getActionList() as $action) {
                $action->init();
                if ($action->hasTriggered()) {
                    $action->execute();
                }
            }
            $this->setExecuted(true);
            if ($this->getRequest()->has(self::RESET_TABLE) && $this->getRequest()->get(self::RESET_TABLE) == $this->getId()) {
                $this->resetSession();
                \Tk\Uri::create()->remove(self::RESET_TABLE)->redirect();
            }
        }
    }

    /**
     * @param array|\ArrayAccess|\Iterator|\Tk\Db\Map\ArrayObject $list
     * @return $this
     */
    public function setList($list)
    {
        $this->list = $list;
        if ($list instanceof \Tk\Db\Map\ArrayObject) {
            if($list->countAll() < $this->getTool()->getOffset()) {
                // not the best solution because it requires the page to be reloaded,
                //  but it usually only happens on developer error or testing.
                $this->resetSessionOffset();
                //$this->resetSessionTool();
            }
        }

        if ($this->dispatcher) {
            $e = new \Tk\Event\TableEvent($this);
            $this->dispatcher->dispatch(\Tk\Table\TableEvents::TABLE_INIT, $e);
        }

        $this->execute();

        if ($this->dispatcher) {
            $this->dispatcher->dispatch(\Tk\Table\TableEvents::TABLE_EXECUTE, $e);
        }
        return $this;
    }

    /**
     * Get the data list array
     *
     * @return array|\Tk\Db\Map\ArrayObject
     */
    public function getList()
    {
        return $this->list;
    }


    /**
     * Add Filter submit events
     */
    protected function initFilterForm()
    {
        if (!$this->formInit) {
            $this->appendFilter(new Event\Submit($this->makeInstanceKey('search'), array($this, 'doSearch')))
                ->setAttr('value', $this->makeInstanceKey('search'))->setLabel('Search');
            $this->appendFilter(new Event\Submit($this->makeInstanceKey('clear'), array($this, 'doClear')))
                ->setAttr('value', $this->makeInstanceKey('clear'))->setLabel('Clear');
            $this->formInit = true;
        }

    }

    /**
     * @return Form
     */
    protected function makeForm()
    {
        $form = new Form($this->id . 'Filter');
        $form->setDispatcher($this->getDispatcher());
        $form->setParamList($this->all());      // TODO: remove by v2.4.0
        $form->addCss('tk-table-filter-form form-inline');      // TODO: move to a table form renderer, this is the wrong place to set the classes?
        return $form;
    }

    /**
     * @param $form
     */
    public function doSearch($form)
    {
        //  Save to session
        $this->saveFilterSession();
        $this->resetSessionOffset();
        $this->getUri($form)->redirect();
    }

    /**
     * @param $form
     */
    public function doClear($form)
    {
        // Clear session
        $this->clearFilterSession();
        $this->resetSessionOffset();
        $this->getUri($form)->redirect();
    }

    /**
     * @return null|\Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * @param null|\Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function setDispatcher($dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @return null|Table\Renderer\Iface
     */
    public function getRenderer()
    {
        return $this->renderer;
    }

    /**
     * @param null|Table\Renderer\Iface $renderer
     * @return static
     */
    public function setRenderer($renderer)
    {
        $this->renderer = $renderer;
        return $this;
    }

    /**
     * @param Form $form
     * @return Uri
     */
    protected function getUri($form = null)
    {
        $uri = \Tk\Uri::create();
        if ($form) {
            /* @var \Tk\Form\Field\Iface $field */
            foreach ($form->getFieldList() as $field) {
                $uri->remove($field->getName());
            }
        }
        return $uri;
    }

    /**
     * Get the table Id
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param Cell\Iface $cell
     * @param null|Cell\Iface|string $refCell
     * @return Cell\Iface
     * @since 2.0.68
     */
    public function appendCell($cell, $refCell = null)
    {
        $cell->setTable($this);
        $cell->setRow($this->row);
        if (is_string($refCell)) {
            $refCell = $this->findCell($refCell);
        }
        if (!$refCell || !$refCell instanceof Cell\Iface) {    // Append to end of list
            array_push($this->cellList, $cell);
        } else {
            $newArr = array();
            /** @var Cell\Iface $c */
            foreach ($this->getCellList() as $c) {
                $newArr[] = $c;
                if ($c === $refCell) $newArr[] = $cell;
            }
            $this->setCellList($newArr);
        }
        return $cell;
    }

    /**
     * @param Cell\Iface $cell
     * @param null|Cell\Iface|string $refCell
     * @return Cell\Iface
     * @since 2.0.68
     */
    public function prependCell($cell, $refCell = null)
    {
        $cell->setTable($this);
        $cell->setRow($this->row);
        if (is_string($refCell)) {
            $refCell = $this->findCell($refCell);
        }
        if (!$refCell || !$refCell instanceof Cell\Iface) {    // Prepend to start of list
            array_unshift($this->cellList, $cell);
        } else {
            // TODO: optimise this if possible
            $newArr = array();
            /** @var Cell\Iface $c */
            foreach ($this->getCellList() as $c) {
                if ($c === $refCell) $newArr[] = $cell;
                $newArr[] = $c;
            }
            $this->setCellList($newArr);
        }
        return $cell;
    }

    /**
     * Remove a cell from the table
     *
     * @param string|Cell\Iface $cell
     * @return array|Cell\Iface
     */
    public function removeCell($cell)
    {
        $cells = $cell;
        if (is_string($cells)) {
            $cells = $this->findCells($cells);
        }
        if ($cells) {
            if (!is_array($cells)) $cells = array($cells);
            $arr = array();
            /** @var Cell\Iface $c */
            foreach ($this->getCellList() as $i => $c) {
                $found = false;
                foreach ($cells as $cell) {
                    if ($c === $cell) {
                        $found = true;
                    }
                }
                if (!$found) $arr[] = $c;
            }
            $this->cellList = $arr;
        }
        return $cell;
    }

    /**
     * Find a cell in the table that match the given property and/or label
     *
     * @param string $property
     * @param null|string $label
     * @return Cell\Iface
     */
    public function findCell($property, $label = null)
    {
        $found = $this->findCells($property, $label);
        return current($found);
    }

    /**
     * Find all cells that match the given property and/or label
     *
     * @param string $property
     * @param null|string $label
     * @return array|Cell\Iface[]
     */
    public function findCells($property, $label = null)
    {
        $found = array();
        foreach ($this->getCellList() as $c) {
            if ($c->getProperty() == $property) {
                if ($label !== null) {
                    if ($c->getLabel() == $label)
                        $found[] = $c;
                } else {
                    $found[] = $c;
                }
            }
        }
        return $found;
    }

    /**
     * Set the cells, init with the table
     *
     * @param Cell\Iface[] $array
     * @return Table
     */
    public function setCellList($array)
    {
        foreach ($array as $cell) {
            $cell->setTable($this);
            $cell->setRow($this->row);
        }
        $this->cellList = $array;
        return $this;
    }

    /**
     * Get the cell list array
     *
     * @return Cell\Iface[]
     */
    public function getCellList()
    {
        return $this->cellList;
    }

    /**
     * @param Action\Iface $action
     * @param null|Action\Iface|string $refAction
     * @return Action\Iface
     * @since 2.0.68
     */
    public function appendAction(Action\Iface $action, $refAction = null)
    {
        $action->setTable($this);
        if (is_string($refAction)) {
            $refAction = $this->findAction($refAction);
        }
        if (!$refAction) {
            $this->actionList[$action->getName()] = $action;
        } else {
            $newArr = array();
            foreach ($this->actionList as $a) {
                $newArr[$a->getName()] = $a;
                if ($a === $refAction) $newArr[$action->getName()] = $action;
            }
            $this->actionList = $newArr;
        }
        return $action;
    }

    /**
     * @param Action\Iface $action
     * @param null|Action\Iface|string $refAction
     * @return Action\Iface
     * @since 2.0.68
     */
    public function prependAction(Action\Iface $action, $refAction = null)
    {
        $action->setTable($this);
        if (is_string($refAction)) {
            $refAction = $this->findAction($refAction);
        }
        if (!$refAction) {
            $this->actionList = array($action->getName() => $action) + $this->actionList;
        } else {
            $newArr = array();
            foreach ($this->actionList as $a) {
                if ($a === $refAction) $newArr[$action->getName()] = $action;
                $newArr[$a->getName()] = $a;
            }
            $this->actionList = $newArr;
        }
        return $action;
    }

    /**
     * @param Action\Iface|string $action
     * @since 2.0.68
     * @return null|Action\Iface
     */
    public function removeAction($action)
    {
        if (is_string($action)) {
            $action = $this->findAction($action);
        }
        if ($action) {
            /** @var Action\Iface $c */
            foreach ($this->getActionList() as $i => $a) {
                if ($a === $action) {
                    unset($this->actionList[$i]);
                    $this->actionList = array_values($this->actionList);
                    return $action;
                }
            }
        }
        return null;
    }

    /**
     * @param string $name
     * @return null|Action\Iface
     */
    public function findAction($name)
    {
        foreach ($this->getActionList() as $a) {
            if ($a->getName() == $name) {
                return $a;
            }
        }
        return null;
    }

    /**
     * Get the action list array
     *
     * @return array|Action\Iface[]
     */
    public function getActionList()
    {
        return $this->actionList;
    }

    /**
     * @param array $array
     * @return $this
     */
    public function setActionList($array = array())
    {
        $this->actionList = $array;
        return $this;
    }

    /**
     * @return Form
     */
    public function getFilterForm()
    {
        return $this->form;
    }

    /**
     * @param \Tk\Form\Field\Iface $field
     * @param null|\Tk\Form\Field\Iface|string $refField
     * @return \Tk\Form\Field\Iface
     * @since 2.0.68
     */
    public function appendFilter(\Tk\Form\Field\Iface $field, $refField = null)
    {
        if (!$field instanceof \Tk\Form\Event\Iface) $this->initFilterForm();
        $field->setShowLabel(false);
        return $this->getFilterForm()->appendField($field, $refField);
    }

    /**
     * @param \Tk\Form\Field\Iface $field
     * @param null|\Tk\Form\Field\Iface|string $refField
     * @return \Tk\Form\Field\Iface
     * @since 2.0.68
     */
    public function prependFilter(\Tk\Form\Field\Iface $field, $refField = null)
    {
        if (!$field instanceof \Tk\Form\Event\Iface) $this->initFilterForm();
        $field->setShowLabel(false);
        return $this->getFilterForm()->prependField($field, $refField);
    }

    /**
     * @param string|\Tk\Form\Field\Iface $field
     * @return null|string|\Tk\Form\Field\Iface
     * @since 2.0.68
     */
    public function removeFilter($field)
    {
        $this->initFilterForm();
        return $this->getFilterForm()->removeField($field);
    }

    /**
     * @param array $array
     * @return $this
     */
    public function setFilterList($array = array())
    {
        $this->getFilterForm()->setFieldList($array);
        return $this;
    }

    /**
     * @param null|array|string $regex A regular expression or array of field names to get
     * @return array
     * @throws \Exception
     */
    public function getFilterValues($regex = null)
    {
        if (!$this->filterFormExecuted && $this->getFilterForm()) { // execute form on first access
            $this->getFilterForm()->load($this->getFilterSession()->all());
            $this->getFilterForm()->execute($this->getRequest());
            $this->filterFormExecuted = true;
        }
        return $this->getFilterForm()->getValues($regex);
    }

    /**
     * Clear the filter form session data.
     * This should be called from the clear filter event usually
     *
     * @return $this
     */
    public function clearFilterSession()
    {
        $this->getFilterSession()->clear();
        return $this;
    }

    /**
     * Save the filter form session data
     * This should be called from the search filter event
     *
     * @return $this
     */
    public function saveFilterSession()
    {
        $filterSession = $this->getFilterSession();
        if ($this->getFilterForm()) {
            $filterSession->replace($this->getFilterForm()->getValues());
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getStaticOrderBy()
    {
        return $this->staticOrderBy;
    }

    /**
     * @param string $staticOrderBy
     * @return $this
     */
    public function setStaticOrderBy($staticOrderBy)
    {
        $this->staticOrderBy = $staticOrderBy;
        return $this;
    }

    /**
     * Get the active order By value
     *
     * Will be one of: '', 'ASC', 'DESC'
     *
     * @return string
     */
    public function getOrder()
    {
        $ord = $this->getOrderStatus();
        $val = self::ORDER_NONE;
        if (count($ord) >= 2) {
            $val = trim($ord[1]);
        }
        return $val;
    }

    /**
     * Get the active order property
     *
     * @return string
     */
    public function getOrderProperty()
    {
        $ord = $this->getOrderStatus();
        $prop = '';
        if (count($ord)) {
            $prop = trim($ord[0]);

        }
        return $prop;
    }


    /**
     * Get the property and order value from the Request or params
     *
     * EG: from "lastName DESC" TO array('lastName', 'DESC');
     *
     * @return array
     */
    private function getOrderStatus()
    {
        $o = array();
        if ($this->getTool()) {
            $o = explode(' ', $this->getTool()->getOrderBy());
        }
        return $o;
    }

    /**
     * Create a DbTool from the request using the table ID and
     * default parameters...
     *
     * @param string $defaultOrderBy
     * @param int $defaultLimit
     * @return Tool
     * TODO: we could put this into the pager`s area of responsibility if we wish to reduce the Table objects complexity
     */
    public function getTool($defaultOrderBy = '', $defaultLimit = 25)
    {
        if (!$this->tool) {

            $this->tool = Tool::create($defaultOrderBy, $defaultLimit);
            $this->tool->setInstanceId($this->getId());

            $dbToolSession = $this->getDbToolSession();
            $this->tool->updateFromArray($dbToolSession->all());

            $a = \Tk\Uri::create()->all();
            $isRequest = $this->tool->updateFromArray($a);  // Use GET params only
            if ($this->getStaticOrderBy() !== null) {
                $this->tool->setOrderBy($this->getStaticOrderBy());
            }

            if ($isRequest) {   // note, should only fire on GET requests.
                $dbToolSession->replace($this->tool->toArray());
                \Tk\Uri::create()
                    ->remove($this->makeInstanceKey(Tool::PARAM_ORDER_BY))
                    ->remove($this->makeInstanceKey(Tool::PARAM_LIMIT))
                    ->remove($this->makeInstanceKey(Tool::PARAM_OFFSET))
                    ->remove($this->makeInstanceKey(Tool::PARAM_GROUP_BY))
                    ->remove($this->makeInstanceKey(Tool::PARAM_HAVING))
                    ->remove($this->makeInstanceKey(Tool::PARAM_DISTINCT))
                    ->redirect();
            }
        }
        return $this->tool;
    }

    /**
     * @param string $defaultOrderBy
     * @param int $defaultLimit
     * @return Tool
     * @deprecated  Use self::getTool() instead
     */
    public function makeDbTool($defaultOrderBy = '', $defaultLimit = 25)
    {
        return $this->getTool($defaultOrderBy, $defaultLimit);
    }

    /**
     * Reset the db tool offset to 0
     *
     * @return $this
     */
    public function resetSessionOffset()
    {
        \Tk\Log::warning('Resetting Offset Session.');
        $sesh = $this->getDbToolSession();
        $sesh->set($this->makeInstanceKey(Tool::PARAM_OFFSET), 0);
        return $this;
    }


    /**
     * All table related data should be save to this object
     *
     * @return Collection
     */
    public function getTableSession()
    {
        $session = $this->getSession();
        $key = 'tables';
        $tableSession = new Collection();
        if ($session->has($key)) {
            $tableSession = $session->get($key);
        }
        $session->set($key, $tableSession);
        $instanceSession = new Collection();
        if ($tableSession->has($this->getId())) {
            $instanceSession = $tableSession->get($this->getId());
        }
        $tableSession->set($this->getId(), $instanceSession);

        return $instanceSession;
    }

    /**
     * @param string $key
     * @return Collection
     */
    public function getDbToolSession($key = 'dbTool')
    {
        $tableSession = $this->getTableSession();
        $dbToolSession = $tableSession->get($key);
        if (!$dbToolSession instanceof Collection) {
            $dbToolSession = new Collection();
            $tableSession->set($key, $dbToolSession);
        }
        return $dbToolSession;
    }

    /**
     * @param string $key
     * @return Collection
     */
    public function getFilterSession($key = 'filter')
    {
        $tableSession = $this->getTableSession();
        $filterSession = $tableSession->get($key);
        if (!$filterSession instanceof Collection) {
            $filterSession = new Collection();
            $tableSession->set($key, $filterSession);
        }
        return $filterSession;
    }

    /**
     * @param string $key
     * @return Collection
     */
    public function getActionSession($key = 'action')
    {
        $tableSession = $this->getTableSession();
        $filterSession = $tableSession->get($key);
        if (!$filterSession instanceof Collection) {
            $filterSession = new Collection();
            $tableSession->set($key, $filterSession);
        }
        return $filterSession;
    }

    /**
     * Reset the db tool offset to 0
     *
     * @return $this
     */
    public function resetSessionTool()
    {
        \Tk\Log::warning('Resetting Session Tool.');
        $sesh = $this->getDbToolSession();
        $sesh->clear();
        return $this;
    }

    /**
     * Reset the table session values
     *
     * @return $this
     */
    public function resetSession()
    {
        \Tk\Log::warning('Resetting Table Session.');
//        $this->resetSessionOffset();
//        $this->resetSessionTool();
        $this->getTableSession()->clear();
        return $this;
    }

    /**
     * Create request keys with prepended string
     *
     * returns: `{instanceId}-{$key}`
     *
     * @param $key
     * @return string
     */
    public function makeInstanceKey($key)
    {
        //return $this->getInstanceId() . '-' . $key;
        return $this->getId() . '-' . $key;
    }

    /**
     * @param Cell\Iface $cell
     * @return Cell\Iface
     * @deprecated Use appendCell($cell)
     * @remove 2.4.0
     */
    public function addCell($cell)
    {
        return $this->appendCell($cell);
    }

    /**
     * Add a field element before another element
     *
     * @param string|Cell\Iface $refCell
     * @param Cell\Iface $cell
     * @return Cell\Iface
     * @deprecated use prependCell($cell, $refCell)
     * @remove 2.4.0
     */
    public function addCellBefore($refCell, $cell)
    {
        return $this->prependCell($cell, $refCell);
    }

    /**
     * Add an element after another element
     *
     * @param string|Cell\Iface $refCell
     * @param Cell\Iface $cell
     * @return Cell\Iface
     * @deprecated Use appendCell($cell, $refCell)
     * @remove 2.4.0
     */
    public function addCellAfter($refCell, $cell)
    {
        return $this->prependCell($cell, $refCell);
    }

    /**
     * @param Action\Iface $action
     * @return Action\Iface
     * @deprecated use prependAction($action)
     * @remove 2.4.0
     */
    public function addAction($action)
    {
        return $this->appendAction($action);
    }

    /**
     * Add a field to the filter form
     *
     * @param \Tk\Form\Field\Iface $field
     * @return \Tk\Form\Field\Iface
     * @deprecated use appendFilter($field)
     * @remove 2.4.0
     */
    public function addFilter($field)
    {
        return $this->appendFilter($field);
    }

    /**
     * @return Row
     */
    public function getRow(): Row
    {
        return $this->row;
    }

    /**
     * Use for your own table parent objects
     * @deprecated
     * @remove 2.4.0
     */
    //public function initCells() {}


    // TODO: I do not think this is used enough to keep, if it is then wew need to find an external solution I think...
    // TODO:   - It seems to be used in the VOCE site to update the limit list in the renderer, this is not a good way to update it
    // TODO:   Find a new way to pass these params around to those objects, probably better to use the renderer
    // TODO:
    // TODO:
    // TODO:
    /**
     * @param $name
     * @return string|mixed
     * @deprecated
     * @remove 2.4.0
     */
    public function getParam($name)
    {
        if (!empty($this->paramList[$name])) {
            return $this->paramList[$name];
        }
        return '';
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return $this
     * @deprecated
     * @remove 2.4.0
     */
    public function setParam($name, $value)
    {
        $this->paramList[$name] = $value;
        return $this;
    }

    /**
     * @return array
     * @deprecated
     * @remove 2.4.0
     */
    public function getParamList()
    {
        return $this->paramList;
    }

    /**
     * @param array $params
     * @return $this
     * @deprecated
     * @remove 2.4.0
     */
    public function setParamList($params)
    {
        $this->paramList = $params;
        return $this;
    }

}