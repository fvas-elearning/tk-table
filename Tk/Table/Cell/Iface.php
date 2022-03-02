<?php
namespace Tk\Table\Cell;

use Tk\Callback;
use Tk\ConfigTrait;
use Tk\Table;
use Tk\Uri;


/**
 * Tk\Table\Cell Interface
 *
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
abstract class Iface
{
    use ConfigTrait;
    use \Tk\Dom\AttributesTrait;
    use \Tk\Dom\CssTrait;


    /**
     * @var \Tk\Table\Row
     */
    protected $row = null;
    
    /**
     * This will be used for the cell header title
     * @var string
     */
    protected $label = '';

    /**
     * @var bool
     */
    protected $showLabel = false;

    /**
     * This is the row object's property name
     * @var string
     */
    protected $property = '';

    /**
     * This cell's order property
     * By default this is set to use the cell property
     * if '' is used then ordering will be disabled for this cell
     * @var bool
     */
    protected $orderProperty = null;

    /**
     * @var string|Uri|callable
     */
    protected $url = null;

    /**
     * @var string
     */
    protected $urlProperty = 'id';

    /**
     * @var Table
     */
    protected $table = null;

    /**
     * @var array
     */
    protected $store = array();

    /**
     * @var boolean
     */
    protected $visible = true;

    /**
     * @var Callback
     */
    protected $onPropertyValue = null;

    /**
     * @var Callback
     */
    protected $onCellHtml = null;

    /**
     * @var string
     */
    protected $headTitle = '';

    
    

    /**
     * Create
     *
     * @param string $property
     * @param null|string $label If null the property name is used EG: 'propName' = 'Prop Name'
     */
    public function __construct($property, $label = null)
    {
        $this->onPropertyValue = Callback::create();
        $this->onCellHtml = Callback::create();
        $this->property = $property;
        if (!$label) {
            $label = preg_replace('/Id$/', '', $property);
            $label = str_replace(array('_', '-'), ' ', $label);
            $label = ucwords(preg_replace('/[A-Z]/', ' $0', $label));
        }
        $this->label = $label;
        //$this->row = new \Tk\Table\Row();
        $this->setOrderProperty($property);
    }

    /**
     * @param string $property
     * @param null|string $label
     * @return static
     */
    public static function create($property, $label = null)
    {
        $obj = new static($property, $label);
        return $obj;
    }

    /**
     * @return string
     */
    public function getHeadTitle()
    {
        return $this->headTitle;
    }

    /**
     *
     * @param string $headTitle
     * @return $this
     */
    public function setHeadTitle(string $headTitle)
    {
        $this->headTitle = $headTitle;
        return $this;
    }

    /**
     * Return the cell header HTML string
     * @return string
     */
    public function getCellHeader()
    {
        // TODO: check this change, as replacing 'id' interferes with legit labels (EG: 'Paid' becomes 'Pa')
        //$str = str_replace(array('id', 'Id'), '', $this->getLabel());
        $str = str_replace(array('_id', 'Id'), '', $this->getLabel());
        $url = $this->getOrderUrl();
        if ($url && $this->getTable()->getStaticOrderBy() === null) {
            $t = 'Click to order by: ' . $this->getLabel();
            if ($this->getHeadTitle()) {
                $t = $this->getHeadTitle();
            }
            $str = sprintf('<a href="%s" class="noblock" title="%s">%s</a>',
                htmlentities($url->toString()), $t, $this->getLabel());
        }
        return $str;
    }

    /**
     * @param mixed $obj
     * @param int|null $rowIdx The current row being rendered (0-n) If null no rowIdx available.
     * @return string
     */
    abstract public function getCellHtml($obj, $rowIdx = null);

    /**
     * Called when the Table::execute is called
     */
    public function execute() { }

    /**
     * Reset any persistent fields to the state of the last store() call.
     *
     * We use this method so a cell can modify its own (and row) properties and
     * they will be reset to their initial state on each row render.
     *
     * @note: Add new properties as required.
     */
    public function resetProperties()
    {
        $this->setCssList($this->store['cellCssList']);
        $this->setAttrList($this->store['cellAttrList']);
        $this->getRow()->setCssList($this->store['rowCssList']);
        $this->getRow()->setAttrList($this->store['rowAttrList']);
    }

    /**
     * Save the state of the cell's persistent fields
     *
     * We use this method so a cell can modify its own (and row) properties and
     * they will be reset to their initial state on each row render.
     *
     * @note: Add new properties as required.
     */
    public function storeProperties()
    {
        $this->store['cellCssList'] = $this->getCssList();
        $this->store['cellAttrList'] = $this->getAttrList();
        $this->store['rowCssList'] = $this->getRow()->getCssList();
        $this->store['rowAttrList'] = $this->getRow()->getAttrList();
    }


    // -------------------------------------------------------------------


    /**
     * Get the property value from the object that is to be displayed
     * to the table cell.
     *
     * @param object $obj
     * @return mixed
     */
    public function getPropertyValue($obj)
    {
        $value = $this->getObjectPropertyValue($obj);
        return $value;
    }

    /**
     * Get the raw string property value with no formatting.
     * This call can be used for exporting data into a csv, json, xml format
     *
     * @param mixed $obj
     * @return string
     */
    public function getRawValue($obj)
    {
        $value = $this->getPropertyValue($obj);
        return $value;
    }

    /**
     * Get the property value from the object
     * This should be the clean property data with no HTML or rendering attached,
     * unless the rendering code is part of the value as it will be called for
     * outputting to other files like XML or CSV.
     *
     * @param object $obj
     * @param string $property      (Optional)
     * @param bool $withCallable    (Optional)
     * @return mixed
     * @todo: This method seems a bit bulky in params, look to refactor it at some point.
     */
    protected function getObjectPropertyValue($obj, $property = null, $withCallable = true)
    {
        if ($property === null) {
            $property = $this->getProperty();
        }
        $value = '';
        if (is_array($obj)) {
            if (isset($obj[$property]))
                $value = $obj[$property];
        } else {

            if ($this->propertyExists($obj, $property)) {
                $value = $obj->{$property};
            } else {
                // Get property by method if accessor exists
                $method = 'get' . ucfirst($property);
                if (!method_exists($obj, $method)) {
                    $method = 'is' . ucfirst($property);
                }
                if (!method_exists($obj, $method)) {
                    $method = 'has' . ucfirst($property);
                }
                if (!method_exists($obj, $method)) {
                    $method = '';
                }
                if ($method) {
                    $value = $obj->$method();
                }
            }
        }
        if ($withCallable && $this->getOnPropertyValue()->isCallable() && $this->getProperty() == $property) {
            $r = $this->getOnPropertyValue()->execute($this, $obj, $value);
            return $r;
            //return call_user_func_array($this->getOnPropertyValue(), array($this, $obj, $value));
        }
        return $value;
    }

    /**
     * @param object $obj
     * @param string $property
     * @return bool
     */
    private function propertyExists($obj, $property)
    {
        $exists = false;
        try {
            $class = new \ReflectionClass($obj);
            $property = $class->getProperty($property);
            if ($property->isPublic())
                $exists = true;
        } catch (\Exception $e) {
            $exists = property_exists($obj, $property);
        }
        //vd($obj, $exists);
        return $exists;
    }

    /**
     * Return the provided URL with the GET parameter of the object ID
     *
     * @param mixed $obj
     * @return Uri|null
     */
    public function getCellUrl($obj)
    {
        $url = $this->getUrl();
        if (is_callable($url)) {
            $url = call_user_func_array($this->getUrl(), array($this, $obj));
            if (!$url && !is_callable($this->url)) {
                $url = \Tk\Uri::create($this->url);
            }
        }

        if (!$url) {
            return null;
        }
        $url = Uri::create($url);
        if ($this->getUrlProperty()) {
            list($prop, $val) = $this->getRowPropVal($obj, $this->getUrlProperty());
            $url->set($prop, $val);
        }
        return $url;
    }

    /**
     * @param $obj
     * @param string $urlProperty
     * @return array|null
     */
    public function getRowPropVal($obj, $urlProperty = 'id')
    {
        $propVal = null;
        if ($urlProperty) {
            $prop = $urlProperty;
            if ($prop == 'id' && is_object($obj)) {     // If 'id' then convert to '{ObjClass}Id'
                $class = get_class($obj);
                $pos = strrpos($class, '\\');
                if (!$pos === false) {
                    $name = substr(get_class($obj), $pos + 1);
                } else {
                    $name = $class;
                }
                $prop = strtolower($name[0]) . substr($name, 1) . 'Id';
            }
            if ($prop == '/id') // Should not be used as id params are frowned upon in urls anyway
                $urlProperty = $prop = 'id';

            $val = $this->getObjectPropertyValue($obj, $urlProperty, false);
            $propVal = array($prop, $val);
        }
        return $propVal;
    }

    /**
     * Create an order by url for this cell.
     *
     * @return Uri|null
     */
    public function getOrderUrl()
    {
        if (!$this->getOrderProperty()) {
            return null;
        }
        
        $order = '';

        //vd($this->getTable()->getOrder(), $this->getOrderProperty(), $this->getTable()->getOrderProperty());
        if ($this->getOrderProperty() == $this->getTable()->getOrderProperty()) {
            $order = $this->getTable()->getOrder();
        }
        
        $key = $this->getTable()->makeInstanceKey(Table::PARAM_ORDER_BY);
        $pre = $this->getOrderProperty() . ' ';
        $url = Uri::create()->remove($key);

        // DESC first
        if ($order == Table::ORDER_ASC) {
            $url->set($key, Table::ORDER_NONE);
        } else if ($order == Table::ORDER_DESC) {
            $url->set($key, $pre . Table::ORDER_ASC);
        } else if ($order == Table::ORDER_NONE) {
            $url->set($key, $pre . Table::ORDER_DESC);
        }

        // ASC first
//        if ($order == Table::ORDER_ASC) {
//            $url->set($key, $pre . Table::ORDER_DESC);
//        } else if ($order == Table::ORDER_DESC) {
//            $url->set($key, Table::ORDER_NONE);
//        } else if ($order == Table::ORDER_NONE) {
//            $url->set($key, $pre . Table::ORDER_ASC);
//        }
        return $url;
    }


    /**
     * Set the default cell data url
     *
     * Callable Eg:
     *   function (Iface $cell, $obj) { return new \Tk\Uri::create(); }
     *
     *
     * @param string|Uri|callable $url
     * @param string $urlProperty
     * @return $this
     * @note When using a callable do not call $cell->setUrl($url) within the function or else you overwrite the callable method
     */
    public function setUrl($url, $urlProperty = '')
    {
        $this->url = $url;
        if ($urlProperty)
            $this->urlProperty = $urlProperty;
        return $this;
    }

    /**
     * Get the default data URL
     *
     * @return Uri|string|callable
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * `id` is reserved and converted to '{objectClass}Id'
     * EG:
     *   For the class UserComment with id = 4 the following will be append
     *   to the url:
     *      'userCommentId=4'
     * The first letter of the object class is converted to lowercase and the
     * value 'Id' is appended as the property name, then the objects id parameter is
     * used for the value.
     *
     *
     * Note: `id` is not recommended to be used in public urls by convention
     *   as it can be a security issue and non SEO compliant, in simple terms
     *   just avoid using id. However if you find there is no way to avoid it,
     *   You can escape the cells default behaviour by sending '/id' instead of 'id'
     *
     * @param string $urlProperty
     * @return $this
     */
    public function setUrlProperty($urlProperty)
    {
        $this->urlProperty = $urlProperty;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrlProperty()
    {
        return $this->urlProperty;
    }

    /**
     * Set the id to be the same as the table. This will be used by the
     * cells for the event key
     *
     * @param Table $table
     * @return $this
     */
    public function setTable($table)
    {
        $this->table = $table;
        // $this->init();
        return $this;
    }

    /**
     * Get the parent table object
     *
     * @return Table
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * If set the label will be rendered
     * This depends on the table renderer being used.
     *
     * @param bool $b
     * @return $this
     */
    public function setShowLabel($b = true)
    {
        $this->showLabel = $b;
        return $this;
    }

    /**
     * getShowLabel
     *
     * @return bool
     */
    public function showLabel()
    {
        return $this->showLabel;
    }

    /**
     * Set the property that the order header uses by default this is the same as property
     *
     * @param string $orderProperty
     * @return $this
     */
    public function setOrderProperty($orderProperty = '')
    {
        $this->orderProperty = $orderProperty;
        return $this;
    }

    /**
     * Get the order by property name
     *
     * @return string
     */
    public function getOrderProperty()
    {
        return $this->orderProperty;
    }

    /**
     * Get the cell label
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * Set the cell label
     *
     * @param string $str
     * @return $this
     */
    public function setLabel($str)
    {
        $this->label = $str;
        return $this;
    }

    /**
     * Get the object property name to get data from
     * the object/table
     *
     * @return string
     */
    public function getProperty()
    {
        return $this->property;
    }

    /**
     * @param Table\Row $row
     * @return Iface
     */
    public function setRow($row = null)
    {
        $this->row = $row;
        return $this;
    }

    /**
     * @return Table\Row
     */
    public function getRow()
    {
        return $this->row;
    }

    /**
     * @return bool
     */
    public function isVisible()
    {
        return $this->visible;
    }

    /**
     * @param bool $visible
     * @return $this
     */
    public function setVisible($visible)
    {
        $this->visible = $visible;
        return $this;
    }

    /**
     * @return Callback
     */
    public function getOnPropertyValue()
    {
        return $this->onPropertyValue;
    }

    /**
     *
     * @param callable|null $onPropertyValue
     * @return $this
     * @deprecated use $this->getOnShow()->append($callable, $priority) or $this->addOnPropertyValue($callback, $priority);
     */
    public function setOnPropertyValue($onPropertyValue)
    {
        $this->addOnPropertyValue($onPropertyValue);
        return $this;
    }

    /**
     * Set a callback to return a modified property value
     * Callback: function (\Tk\Table\Cell\Iface $cell, $obj, $value) { return $value; }
     *
     * @param callable $callable
     * @param int $priority [optional]
     * @return $this
     */
    public function addOnPropertyValue($callable, $priority=Callback::DEFAULT_PRIORITY)
    {
        $this->getOnPropertyValue()->append($callable, $priority);
        return $this;
    }

    /**
     * @return Callback
     */
    public function getOnCellHtml()
    {
        return $this->onCellHtml;
    }

    /**
     * @param callable|null $onCellHtml
     * @return $this
     * @deprecated use $this->getOnShow()->append($callable, $priority) OR $this->addOnCellHtml($callable)
     */
    public function setOnCellHtml($onCellHtml)
    {
        $this->addOnCellHtml($onCellHtml);
        return $this;
    }

    /**
     * Set the onShowCell callback
     * Callback: function (\Tk\Table\Cell\Iface $cell, $obj, $html) { return $html; }
     *
     * @param callable $callable
     * @param int $priority
     * @return $this
     */
    public function addOnCellHtml($callable, $priority=Callback::DEFAULT_PRIORITY)
    {
        $this->getOnCellHtml()->append($callable);
        return $this;
    }

}
