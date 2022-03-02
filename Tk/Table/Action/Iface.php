<?php
namespace Tk\Table\Action;


use Tk\Callback;
use Tk\ConfigTrait;
use Tk\Dom\AttributesTrait;
use Tk\Dom\CssTrait;
use Tk\Table;

/**
 * The interface for a table Action
 *
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
abstract class Iface extends \Dom\Renderer\Renderer implements \Dom\Renderer\DisplayInterface
{

    use AttributesTrait;
    use CssTrait;
    use ConfigTrait;


    /**
     * This will be used for the event name using the instance ID
     * @var string
     */
    protected $name = '';

    /**
     * @var string
     */
    protected $label = '';

    /**
     * @var Table
     */
    protected $table = null;

    /**
     * @var boolean
     */
    protected $visible = true;

    /**
     * @var Callback
     */
    public $onInit = null;

    /**
     * @var Callback
     */
    public $onShow = null;

    /**
     * @var Callback
     */
    public $onExecute = null;


    /**
     * @param string $name The action event name
     */
    public function __construct($name)
    {
        $this->onInit = Callback::create();
        $this->onExecute = Callback::create();
        $this->onShow = Callback::create();
        $this->setName($name);
        $this->setLabel(ucfirst(preg_replace('/[A-Z]/', ' $0', $name)));
        $this->addCss('a'.ucFirst($name));
    }

    /**
     * Use this to init any code. This will be run on every page load.
     * 
     */
    public function init()
    {
        $this->getOnInit()->execute($this);
    }

    /**
     *
     */
    public function execute()
    {
        $this->getOnExecute()->execute($this);
    }

    /**
     * @return \Dom\Renderer\Renderer|\Dom\Template|void|null
     */
    public function show()
    {
        $template = $this->getTemplate();
        $this->getOnShow()->execute($this);
        return $template;
    }


    /**
     * @return \Dom\Template|string
     * @deprecated Use show()
     */
    public function getHtml()
    {
        return $this->show();
    }


    /**
     * Has this button action been fired
     *
     * @return bool
     */
    public function hasTriggered()
    {
        $request = $this->getTable()->getRequest();
        return $request->has($this->getTable()->makeInstanceKey($this->getName()));
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
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName($name)
    {
        $name = preg_replace('/[^a-z0-9_-]/i', '_', $name);
        $this->name = $name;
        return $this;
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
    public function getOnInit()
    {
        return $this->onInit;
    }

    /**
     * Eg: function (\Tk\Table\Action\Iface $action) { }
     *
     * @param callable|null $onInit
     * @return $this
     * @deprecated use addOnInit($callable, Priority)
     */
    public function setOnInit($onInit)
    {
        $this->addOnInit($onInit);
        return $this;
    }

    /**
     * Eg: function (\Tk\Table\Action\Iface $action) { }
     *
     * @param callable $callable
     * @param int $priority
     * @return $this
     */
    public function addOnInit($callable, $priority = Callback::DEFAULT_PRIORITY)
    {
        $this->getOnInit()->append($callable, $priority);
        return $this;
    }


    /**
     * @return Callback
     */
    public function getOnShow()
    {
        return $this->onShow;
    }

    /**
     * Eg: function (\Tk\Table\Action\Iface $action) { }
     *
     * @param callable|null $onShow
     * @return $this
     * @deprecated use addOnShow($callable, Priority)
     */
    public function setOnShow($onShow)
    {
        $this->addOnShow($onShow);
        return $this;
    }

    /**
     * Eg: function (\Tk\Table\Action\Iface $action) { }
     *
     * @param callable $callable
     * @param int $priority
     * @return $this
     */
    public function addOnShow($callable, $priority = Callback::DEFAULT_PRIORITY)
    {
        $this->getOnShow()->append($callable, $priority);
        return $this;
    }

    /**
     * @return Callback
     */
    public function getOnExecute()
    {
        return $this->onExecute;
    }

    /**
     * Eg: function (\Tk\Table\Action\Iface $action) { }
     *
     * @param callable|null $onExecute
     * @return $this
     * @deprecated use addOnExecute($callable, Priority)
     */
    public function setOnExecute($onExecute)
    {
        $this->addOnExecute($onExecute);
        return $this;
    }

    /**
     * Eg: function (\Tk\Table\Action\Iface $action) { }
     *
     * @param callable $callable
     * @param int $priority
     * @return $this
     */
    public function addOnExecute($callable, $priority = Callback::DEFAULT_PRIORITY)
    {
        $this->getOnExecute()->append($callable, $priority);
        return $this;
    }

}
