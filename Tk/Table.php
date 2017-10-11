<?php
namespace Tk;

use Tk\Table\Action;
use Tk\Table\Cell;
use Tk\Db\Tool;
use \Tk\Form\Event;

/**
 * Class Table
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @link http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 * TODO: Thinking of moving the filter form and actions out to their own objects so we
 * TODO: can remove the responsibility from the Table ????
 * TODO: Then I think we can remove the need for a session and request from the Table Object ?? ;-)
 */
class Table implements \Tk\InstanceKey
{

    const PARAM_ORDER_BY = 'orderBy';
    const ORDER_NONE = '';
    const ORDER_ASC = 'ASC';
    const ORDER_DESC = 'DESC';

    use \Tk\Dom\AttributesTrait;
    use \Tk\Dom\CssTrait;

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
     * @var \Tk\Request|array|\ArrayAccess
     */
    protected $request = null;

    /**
     * @var \Tk\Session|array|\ArrayAccess
     */
    protected $session = null;

    /**
     * @var string
     */
    protected $staticOrderBy = null;

    /**
     * @var bool
     */
    protected $hasExecuted = false;

    /**
     * @var null|\Tk\Event\Dispatcher
     */
    protected $dispatcher = null;


    /**
     * Create a table object
     *
     * @param string $tableId
     * @param array $params
     */
    public function __construct($tableId, $params = array())
    {
        $this->id = $tableId;
        $this->paramList = $params;
        $this->setAttr('id', $this->getId());

        if (!$this->request) {
            $this->request = &$_REQUEST;
        }
        if (!$this->session) {
            $this->session = &$_SESSION;
        }
        $this->form = $this->makeForm();
    }

    /**
     * @return Form
     */
    protected function makeForm()
    {
        $form = new Form($this->id . 'Filter');
        $form->setParamList($this->paramList);
        $form->addCss('form-inline');
        return $form;
    }

    /**
     * @param $id
     * @param array $params
     * @param null|array|\Tk\Request $request
     * @param null|array|\Tk\Session $session
     * @return static
     */
    public static function create($id, $params = array(), $request = null, $session = null)
    {
        $obj = new static($id, $params);
        if (!$request)
            $request = \Tk\Config::getInstance()->getRequest();

        if (!$session)
            $session = \Tk\Config::getInstance()->getSession();
            
        $obj->setRequest($request);
        $obj->setSession($session);
        
        return $obj;
    }

    /**
     * Execute the table
     * Generally called in the renderer`s show() method
     *
     */
    public function execute()
    {
        if (!$this->hasExecuted) {
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
            $this->hasExecuted = true;
        }
    }


    protected function initFilterForm()
    {
        // Add Filter button events
        $this->addFilter(new Event\Submit($this->makeInstanceKey('search'), array($this, 'doSearch')))->setAttr('value', $this->makeInstanceKey('search'))->addCss('btn-primary')->setLabel('Search');
        $this->addFilter(new Event\Submit($this->makeInstanceKey('clear'), array($this, 'doClear')))->setAttr('value', $this->makeInstanceKey('clear'))->setLabel('Clear');
    }

    public function doSearch($form)
    {
        //  Save to session
        $this->saveFilterSession();
        $this->resetSessionOffset();
        $this->getUri($form)->redirect();
    }

    public function doClear($form)
    {
        // Clear session
        $this->clearFilterSession();
        $this->resetSessionOffset();
        $this->getUri($form)->redirect();
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
     * @param \Tk\Request|array|\ArrayAccess $request
     * @return $this
     */
    public function setRequest(&$request)
    {
        $this->request = &$request;
        return $this;
    }
    
    /**
     * @return array|\ArrayAccess
     */
    public function &getRequest()
    {
        return $this->request;
    }

    /**
     * @param \Tk\Session|array|\ArrayAccess $session
     * @return $this
     */
    public function setSession(&$session)
    {
        $this->session = &$session;
        return $this;
    }
    
    /**
     * @return \Tk\Session|array|\ArrayAccess
     */
    public function &getSession()
    {
        return $this->session;
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
        if (isset($session[$key])) {
            $tableSession = $session[$key];
        }
        $session[$key] = $tableSession;

        $instanceSession = new Collection();
        if ($tableSession->has($this->getId())) {
            $instanceSession = $tableSession->get($this->getId());
        }
        $tableSession->set($this->getId(), $instanceSession);
        return $instanceSession;
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
     * @param array|\ArrayAccess|\Iterator $list
     * @return $this
     */
    public function setList($list)
    {
        $this->list = $list;
        $this->execute();
        return $this;
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
     * Get a parameter from the array
     *
     * @param $name
     * @return string|mixed
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
     */
    public function setParam($name, $value)
    {
        $this->paramList[$name] = $value;
        return $this;
    }

    /**
     * Get the param array
     *
     * @return array
     */
    public function getParamList()
    {
        return $this->paramList;
    }

    /**
     * @param array $params
     * @return $this
     */
    public function setParamList($params)
    {
        $this->paramList = $params;
        return $this;
    }

    /**
     * Add a cell to this table
     *
     * @param Cell\Iface $cell
     * @return Cell\Iface
     */
    public function addCell($cell)
    {
        $cell->setTable($this);
        $this->cellList[] = $cell;
        return $cell;
    }

    /**
     * Add a field element before another element
     *
     * @param Cell\Iface $anchorCell
     * @param Cell\Iface $cell
     * @return Cell\Iface
     */
    public function addCellBefore($anchorCell, $cell)
    {
        $newArr = array();
        $cell->setTable($this);
        /** @var Cell\Iface $c */
        foreach ($this->getCellList() as $c) {
            if ($c === $anchorCell) {
                $newArr[] = $cell;
            }
            $newArr[] = $c;
        }
        $this->setCellList($newArr);
        return $cell;
    }

    /**
     * Add an element after another element
     *
     * @param Cell\Iface $anchorCell
     * @param Cell\Iface $cell
     * @return Cell\Iface
     */
    public function addCellAfter($anchorCell, $cell)
    {
        $newArr = array();
        $cell->setTable($this);
        /** @var Cell\Iface $c */
        foreach ($this->getCellList() as $c) {
            $newArr[] = $c;
            if ($c === $anchorCell) {
                $newArr[] = $cell;
            }
        }
        $this->setCellList($newArr);
        return $cell;
    }

    /**
     * Remove a cell from the table
     *
     * @param Cell\Iface $cell
     * @return $this
     */
    public function removeCell($cell)
    {
        /** @var Cell\Iface $c */
        foreach ($this->getCellList() as $i => $c) {
            if ($c === $cell) {
                unset($this->cellList[$i]);
                $this->cellList = array_values($this->cellList);
                break;
            }
        }
        return $this;
    }

    /**
     * Find a cell in the table that match the given property and/or label
     *
     * @param string $property
     * @param null|string $label
     * @return array|Cell\Iface
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
     * @return array
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
     * Add an action to this table
     *
     * @param Action\Iface $action
     * @return Action\Iface
     */
    public function addAction($action)
    {
        $action->setTable($this);
        $this->actionList[$action->getName()] = $action;
        return $action;
    }

    /**
     * Get the action list array
     *
     * @return array
     */
    public function getActionList()
    {
        return $this->actionList;
    }

    /**
     *
     * @return Form
     */
    public function getFilterForm()
    {
        return $this->form;
    }

    /**
     * Add a field to the filter form
     *
     * @param \Tk\Form\Field\Iface $field
     * @return \Tk\Form\Field\Iface
     */
    public function addFilter($field)
    {
        if (!$field instanceof \Tk\Form\Event\Iface && !count($this->getFilterForm()->getFieldList())) {
            $this->initFilterForm();
        }
        $field->setLabel(null);
        return $this->getFilterForm()->addField($field);
    }

    /**
     * getFilterValues
     *
     * @param null|array|string $regex A regular expression or array of field names to get
     * @return array
     */
    public function getFilterValues($regex = null)
    {
        static $x = false;
        if (!$x && $this->getFilterForm()) { // execute form on first access
            $this->getFilterForm()->load($this->getFilterSession()->all());
            $this->getFilterForm()->execute($this->getRequest());
            $x = true;
        }
        return $this->getFilterForm()->getValues($regex);
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
        if (count($ord) >= 2) {
            return trim($ord[1]);
        }
        return self::ORDER_NONE;
    }

    /**
     * Get the active order property
     *
     * @return string
     */
    public function getOrderProperty()
    {
        $ord = $this->getOrderStatus();
        if (count($ord)) {
            return trim($ord[0]);
        }
        return '';
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
        if ($this->getList() instanceof \Tk\Db\Map\ArrayObject) {
            return explode(' ', $this->makeDbTool()->getOrderBy());
        }
        return array();
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
     * Create a DbTool from the request using the table ID and
     * default parameters...
     *
     * @param string $defaultOrderBy
     * @param int $defaultLimit
     * @return Tool
     * TODO: we could put this into the pager`s area of responsibility if we wish to reduce the Table objects complexity
     */
    public function makeDbTool($defaultOrderBy = '', $defaultLimit = 25)
    {
        $tool = Tool::create($defaultOrderBy, $defaultLimit);
        $tool->setInstanceId($this->getId());

        $dbToolSession = $this->getDbToolSession();
        $tool->updateFromArray($dbToolSession->all());

        $isRequest = $tool->updateFromArray(\Tk\Uri::create()->all());  // Use GET params only
        if ($this->getStaticOrderBy() !== null) {
            $tool->setOrderBy($this->getStaticOrderBy());
        }

        if ($isRequest) {   // note, should only fire on GET requests.
            $dbToolSession->replace($tool->toArray());
            \Tk\Uri::create()
                ->remove($this->makeInstanceKey(Tool::PARAM_ORDER_BY))
                ->remove($this->makeInstanceKey(Tool::PARAM_LIMIT))
                ->remove($this->makeInstanceKey(Tool::PARAM_OFFSET))
                ->remove($this->makeInstanceKey(Tool::PARAM_GROUP_BY))
                ->remove($this->makeInstanceKey(Tool::PARAM_HAVING))
                ->remove($this->makeInstanceKey(Tool::PARAM_DISTINCT))
                ->redirect();
        }
        return $tool;
    }

    /**
     * Reset the db tool offset to 0
     *
     * @return $this
     */
    public function resetSessionOffset()
    {
        $sesh = $this->getDbToolSession();
        $instKey = $sesh->get($this->getId());
        if ($instKey && isset($instKey[$this->makeInstanceKey(Tool::PARAM_OFFSET)])) {
            $instKey[$this->makeInstanceKey(Tool::PARAM_OFFSET)] = 0;
        }
        $sesh->set($this->getId(), $instKey);
        return $this;
    }

    /**
     * Reset the db tool offset to 0
     *
     * @return $this
     */
    public function resetSessionTool()
    {
        $sesh = $this->getDbToolSession();
        $sesh->remove($this->getId());

        return $this;
    }

    /**
     * Create request keys with prepended string
     *
     * returns: `{instanceId}_{$key}`
     *
     * @param $key
     * @return string
     */
    public function makeInstanceKey($key)
    {
        return $this->getId() . '_' . $key;
    }


    /**
     * @return null|\Tk\Event\Dispatcher
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * @param null|\Tk\Event\Dispatcher $dispatcher
     */
    public function setDispatcher($dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }
    
}