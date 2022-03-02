<?php
namespace Tk\Table\Renderer;

use Tk\ObjectUtil;
use \Tk\Table;
use \Tk\Table\Cell;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 * @todo Major coupling issue here with the Table and Dom libs, need to remove that dependency
 */
abstract class Iface extends \Dom\Renderer\Renderer implements \Dom\Renderer\DisplayInterface
{
    /**
     * Enable Rendering of the footer
     * @var bool
     */
    private $footer = true;

    /**
     * @var Table
     */
    protected $table = null;

    /**
     * @var array|\Tk\Table\Renderer\Dom\Ui\Iface[]
     */
    protected $footRenderList = array();

    /**
     * Keep track of the currently rendered row
     * @var int
     */
    protected $rowId = 0;

    /**
     * @var null|\Tk\EventDispatcher\EventDispatcher
     */
    protected $dispatcher = null;


    /**
     * construct
     *
     * @param Table|null $table
     */
    public function __construct($table = null)
    {
        $this->setTable($table);
        $this->appendFootRenderer(Table\Renderer\Dom\Ui\Results::create(), 'Results');
        $this->appendFootRenderer(Table\Renderer\Dom\Ui\Pager::create(), 'Pager');
        $this->appendFootRenderer(Table\Renderer\Dom\Ui\Limit::create(), 'Limit');
    }

    /**
     * Set the table object
     *
     * @param Table $table
     * @return boolean Return true on successful setting of table object.
     */
    public function setTable($table)
    {
        if (!$table instanceof Table) return false;
        $this->table = $table;
        $this->table->setRenderer($this);
        return true;
    }

    /**
     * Get the table
     *
     * @return Table
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Append a renderer to the footer renderer list
     *
     * @param Dom\Ui\Iface $renderer
     * @param null|string $key If null then class name is used
     */
    public function appendFootRenderer($renderer, $key = null)
    {
        if (!$key)
            $key = ObjectUtil::basename($renderer);
        if ($this->getTable()) {
            // TODO: I would have thought to use getInstanceId() from the stable, this is an issue???
            $renderer->setInstanceId($this->getTable()->getId());
        } else {
            \Tk\Log::info('NOTE: Instance ID not set for: ' . \Tk\ObjectUtil::basename($renderer));
        }
        $this->footRenderList[$key] = $renderer;
        return $this;
    }

    /**
     * @param string $key
     * @return mixed|null|\Tk\Table\Renderer\Dom\Ui\Iface
     */
    public function getFootRenderer($key)
    {
        if (isset($this->footRenderList[$key]))
            return $this->footRenderList[$key];
    }

    /**
     * @param string $key
     * @param \Tk\Table\Renderer\Dom\Ui\Iface $renderer
     * @return $this
     */
    public function addFootRenderer($key, $renderer)
    {
        $this->footRenderList[$key] = $renderer;
        return $this;
    }

    /**
     * @param string $key
     * @return $this
     */
    public function removeFootRenderer($key)
    {
        if (isset($this->footRenderList[$key]))
            unset($this->footRenderList[$key]);
        return $this;
    }

    /**
     * @return array|\Tk\Table\Renderer\Dom\Ui\Iface[]
     */
    public function getFooterRenderList()
    {
        return $this->footRenderList;
    }

    /**
     * @param array|\Tk\Table\Renderer\Dom\Ui\Iface[] $footRenderList
     * @return $this
     */
    public function setFooterRenderList($footRenderList = array())
    {
        $this->footRenderList = $footRenderList;
        return $this;
    }

    /**
     * Return the current row being rendered.
     * This value should take any offset into account.
     *
     * @return int
     */
    public function getRowId()
    {
        return $this->rowId;
    }

    /**
     * @return boolean
     */
    public function hasFooter()
    {
        return $this->footer;
    }

    /**
     * @param boolean $footerEnabled
     * @return $this
     */
    public function enableFooter($footerEnabled)
    {
        $this->footer = $footerEnabled;
        return $this;
    }

    /**
     * @return null|\Tk\EventDispatcher\EventDispatcher
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * @param null|\Tk\EventDispatcher\EventDispatcher $dispatcher
     */
    public function setDispatcher($dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * init the filter form.
     *
     * You may need to add the filter submit/clear events here.
     * This is highly dependant on the type of renderer you are using
     * So it is left for you to implement for that reason.
     */
    //abstract protected function initFilterForm();

    /**
     * Render the table header
     */
    abstract protected function showHeader();

    /**
     * Render the table body
     */
    abstract protected function showBody();

    /**
     * Render the table row
     *
     * @param mixed $obj
     */
    abstract protected function showRow($obj);

    /**
     * Render the table cell
     *
     * @param Cell\Iface $cell
     * @param mixed $obj
     */
    abstract protected function showCell(Cell\Iface $cell, $obj);

    /**
     * Render the table footer
     */
    abstract protected function showFooter();





}