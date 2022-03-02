<?php
namespace Tk\Table\Renderer\Dom;

use \Tk\Table\Cell;
use Tk\Table\Renderer\Dom\Ui\Limit;
use Tk\Table\Renderer\Dom\Ui\Pager;
use Tk\Table\Renderer\Dom\Ui\Results;
use \Tk\Table\Renderer\Iface;
use Tk\Form;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2007 Michael Mifsud
 */
class Table extends Iface
{

    /**
     * @var \Dom\Repeat
     */
    protected $rowRepeat = null;

    /**
     * @var \Dom\Repeat
     */
    protected $cellRepeat = null;

    /**
     * @var array
     */
    protected $rowClassArr = array();

    /**
     * Enable the rendering of the pager individual page buttons
     * Set to false to only show next/prev buttons
     * @var bool
     */
    protected $pageButtons = true;

    /**
     * @var null|\Tk\Form\Renderer\Dom
     */
    protected $formRenderer = null;

    /**
     * construct
     *
     * @param \Tk\Table|null $table
     */
    public function __construct($table = null)
    {
        parent::__construct($table);
    }

    /**
     * @param \Tk\Table $table
     * @return Table
     */
    static function create($table = null)
    {
        $obj = new static($table);
        // TODO: remove this from here as it is not the right place for it
        $table->addCss('table table-bordered table-striped table-hover');
        return $obj;
    }


    /**
     * Get the default form renderer object
     * 
     * @return Form\Renderer\Dom|Form\Renderer\Iface
     */
    public function getFormRenderer()
    {
        if (!$this->getTable()->getFilterForm()->getRenderer()) {
            $this->getTable()->getFilterForm()->setRenderer(\Tk\Form\Renderer\Dom::create($this->getTable()->getFilterForm()));
        }
        return $this->getTable()->getFilterForm()->getRenderer();
    }

    /**
     * @return bool
     */
    public function hasPageButtons()
    {
        return $this->pageButtons;
    }

    /**
     * @param bool $pageButtons
     */
    public function enablePageButtons($pageButtons)
    {
        $this->pageButtons = $pageButtons;
    }

    /**
     * Execute the renderer.
     * The returned object can be anything that you need to render
     * the output.
     *
     * @return \Dom\Template
     */
    public function show()
    {
        $template = $this->getTemplate();

        // Render Form
        if ($template->keyExists('var', 'filters')) {
            if (count($this->getTable()->getFilterForm()->getFieldList()) > 2) {
                $template->appendTemplate('filters', $this->getFormRenderer()->show());
                $template->setVisible('filters');
            }
        }

        $template->setAttr('form', 'id', $this->getTable()->getId().'_form');
        $template->setAttr('form', 'action', \Tk\Uri::create());
        $template->setAttr('form', 'method', 'post');
        $template->setAttr('fragment', 'name', $this->getTable()->getId());

        $this->showHeader();
        $this->showBody();

        $count = 0;
        $countAll = 0;
        if ($this->getTable()->getList()) {
            $count = count($this->getTable()->getList());
            $countAll = count($this->getTable()->getList());
        }
        $tool = $this->getTable()->getTool();
        if ($tool) {
            $countAll = $tool->getFoundRows();
        }

        // TODO: this could be un-required since we added $tool->getFoundRows()
        if ($this->getTable()->getList() instanceof \Tk\Db\Map\ArrayObject) {
            $countAll = $this->getTable()->getList()->countAll();
            $tool = $this->getTable()->getList()->getTool();
        }

        if ($this->hasFooter() && $count && $tool) {
            /** @var Results $results */
            $results = $this->getFootRenderer('Results');
            if ($results) {
                $results->initFromDbTool($tool, $countAll);
                //$results->setInstanceId($this->getTable()->getId());
                $results->addCss('col-2 col-sm-2');
                $this->appendFootRenderer($results);
            }

            /** @var Pager $pager */
            $pager = $this->getFootRenderer('Pager');
            if ($pager) {
                $pager->initFromDbTool($tool, $countAll);
                $pager->setEnablePageButtons($this->pageButtons);
                //$pager->setInstanceId($this->getTable()->getId());
                $pager->addCss('col-8 col-sm-8 text-center');
                $this->appendFootRenderer($pager);
            }

            /** @var Limit $limit */
            $limit = $this->getFootRenderer('Limit');
            if ($limit) {
                // deprecated if code remove in the future use ->setLimitList() not params
                $limit->setLimit($tool->getLimit());
                if ($this->getTable()->getParam('limitList')) {
                    $limit->setLimitList($this->getTable()->getParam('limitList'));
                }
                //$limit->setInstanceId($this->getTable()->getId());
                $limit->addCss('col-2 col-sm-2');
                $this->appendFootRenderer($limit);
            }
            // TODO: Change this out of this condition if not good for all tables.
            $this->showFooter();
        }


        $this->showAttributes($template);

        return $template;
    }

    /**
     * @param \Dom\Template $template
     */
    protected function showAttributes($template)
    {
        $template->addCss('table', $this->getTable()->getCssString());
        $template->setAttr('table', $this->getTable()->getAttrList());
    }


    /**
     * Render the table header
     * Render header row and any filters, etc....
     *
     * @return mixed
     */
    protected function showHeader()
    {
        $template = $this->getTemplate();

        //  Show Actions
        if ($template->keyExists('var', 'actions')) {
            /* @var \Tk\Table\Action\Iface $action */
            foreach ($this->getTable()->getActionList() as $action) {
                if (!$action instanceof \Tk\Table\Action\Iface || !$action->isVisible()) continue;
                $html = $action->show();
                if ($html instanceof \Dom\Template) {
                    $template->appendTemplate('actions', $html);
                } else {
                    $template->appendHtml('actions', $html);
                }
                $template->setVisible('actions');
            }
        }

        if ($template->keyExists('repeat', 'th')) {
            /* @var \Tk\Table\Cell\Iface $cell */
            foreach ($this->getTable()->getCellList() as $property => $cell) {
                if (!$cell->isVisible()) continue;
                $repeat = $template->getRepeat('th');
                if (!$repeat) continue;
                if ($this->getTable()->getOrderProperty() == $cell->getOrderProperty()) {
                    if ($this->getTable()->getOrder() == \Tk\Table::ORDER_DESC) {
                        $repeat->addCss('th', 'orderDesc');
                    } else {
                        $repeat->addCss('th', 'orderAsc');
                    }
                }
                $data = $cell->getCellHeader();
                if ($data === null) {
                    $data = '&#160;';
                }
                if ($data instanceof \Dom\Template) {
                    $repeat->insertTemplate('th', $data);
                } else {
                    $repeat->insertHtml('th', $data);
                }

                $repeat->addCss('th', 'mh' . ucfirst($cell->getProperty()));
                $repeat->addCss('th', $cell->getCssString());
                $repeat->setAttr('th', 'data-label', $cell->getLabel());
                $repeat->setAttr('th', 'data-prop', $cell->getProperty());
                $repeat->appendRepeat();
            }
        }
    }

    /**
     * Render the table body
     *
     */
    protected function showBody()
    {
        $template = $this->getTemplate();
        $this->rowClassArr = array();
        $this->rowId = 0;

        if ($this->getTable()->getTool()) {
            $this->rowId = $this->getTable()->getTool()->getOffset();
            //$this->rowId = $this->getTable()->getList()->getTool()->getOffset();
        }
//        if ($this->getTable()->getList() instanceof \Tk\Db\Map\ArrayObject) {
//            $this->rowId = $this->getTable()->getList()->getTool()->getOffset();
//        }

        if (!$template || !$template->keyExists('repeat', 'tr')) return;
        if (!$this->getTable()->getList()) return;
        foreach($this->getTable()->getList() as $i => $obj) {
            $this->rowRepeat = $template->getRepeat('tr');
            $this->showRow($obj);
            $this->rowRepeat->setAttr('tr', 'data-rowid', $this->rowId);    // deprecated
            //$this->rowRepeat->setAttr('tr', 'data-row-id', $this->rowId);     // TODO: upgrade to use this instead
            if (!$this->rowRepeat->getAttr('tr', 'data-obj-id') && method_exists($obj, 'getId')) {
                $this->rowRepeat->setAttr('tr', 'data-obj-id', $obj->getId());
            }
            $this->rowRepeat->appendRepeat();
            $this->rowId++;
        }
    }

    /**
     * Render the table row
     *
     * @param mixed $obj
     */
    protected function showRow($obj)
    {
        $cell = null;
        $rowCssList = array();
        $rowAttrList = array();
        $row = $this->getTable()->getRow();
        $row->resetRow()->setRowId($this->rowId);

        if (!$this->rowRepeat || !$this->rowRepeat->keyExists('repeat', 'td')) return;

        /* @var \Tk\Table\Cell\Iface $cell */
        foreach($this->getTable()->getCellList() as $k => $cell) {
            if (!$cell->isVisible()) continue;
            $cell->storeProperties();
            $this->cellRepeat = $this->rowRepeat->getRepeat('td');
            $this->showCell($cell, $obj);
            $rowCssList = array_merge($rowCssList, $cell->getRow()->getCssList());
            $rowAttrList = array_merge($rowAttrList, $cell->getRow()->getAttrList());
            $this->cellRepeat->appendRepeat();
            $cell->resetProperties();
        }

        if ($cell && $cell->getRow()) {
            $this->rowRepeat->addCss('tr', $rowCssList);
            $this->rowRepeat->setAttr('tr', $rowAttrList);

        }
    }

    /**
     * Render the table cell
     *
     * @param Cell\Iface $cell
     * @param mixed $obj
     */
    protected function showCell(Cell\Iface $cell, $obj)
    {
        $html = $cell->getCellHtml($obj, $this->rowId);
        //if (is_callable($cell->getOnCellHtml())) {
            //$r = call_user_func_array($cell->getOnCellHtml(), array($cell, $obj, $html));
            $r = $cell->getOnCellHtml()->execute($cell, $obj, $html);
            if ($r !== null) $html = $r;
        //}

        $this->cellRepeat->addCss('td', 'm' . ucfirst($cell->getProperty()));
        $this->cellRepeat->addCss('td', $cell->getCssString());
        foreach ($cell->getAttrList() as $name => $value) {
            $this->cellRepeat->setAttr('td', $name, $value);
        }
        if ($html === null) {
            $html = '&#160;';
        }
        if ($html instanceof \Dom\Template) {
            $this->cellRepeat->insertTemplate('td', $html);
        } else {
            $this->cellRepeat->insertHtml('td', $html);
        }
    }

    /**
     * Render the table footer
     * Render the table footer html like pagination etc...
     */
    protected function showFooter()
    {
        $template = $this->getTemplate();
        if (!$template->keyExists('var', 'foot') || !$this->hasFooter()) return;

        // Render any footer widgets filters
        foreach($this->getFooterRenderList() as $renderer) {
            if ($renderer instanceof \Dom\Renderer\DisplayInterface) {      // TODO: should this reside here
                $renderer->show();
            }
            if ($renderer instanceof \Dom\Renderer\RendererInterface) {
                $template->appendTemplate('foot', $renderer->getTemplate());
            } else if ($renderer instanceof \Dom\Template) {
                $template->appendTemplate('foot', $renderer);
            } else {
                $this->cellRepeat->appendHtml('foot', $renderer);
            }
            $template->setVisible('foot');
        }
    }

    /**
     * makeTemplate
     *
     * @return string
     */
    public function __makeTemplate()
    {
        $xhtml = <<<HTML
<div class="tk-table" var="tk-table">
  <a name="" var="fragment"/>
  <div class="tk-filters" var="filters" choice="filters"></div>

  <form var="form">
      <div class="tk-actions" var="actions" choice="actions"></div>
      
      <div class="tk-table-wrap table-responsive">
        <table border="0" cellpadding="0" cellspacing="0" class="" var="table">
          <thead var="head">
            <tr>
              <th var="th" repeat="th"></th>
            </tr>
          </thead>
          <tbody class="table-body" var="body">
            <tr var="tr" repeat="tr">
              <td var="td" repeat="td">&#160;</td>
            </tr>
          </tbody>
        </table>
      </div>
       
      <div class="tk-foot row" choice="foot" var="foot"></div>
  </form>
  
</div>
HTML;

        return \Dom\Loader::load($xhtml);
    }

}