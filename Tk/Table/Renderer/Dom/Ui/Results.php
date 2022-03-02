<?php
namespace Tk\Table\Renderer\Dom\Ui;

use Tk\Db\Tool;

/**
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class Results extends Iface
{

    /**
     * @var int
     */
    private $total = 0;

    /**
     * @var int
     */
    private $limit = 0;

    /**
     * @var int
     */
    private $offset = 0;


    /**
     * Create the object instance
     *
     * @param int $total
     * @param int $limit
     * @param int $offset
     */
    public function __construct($total = 0, $limit = 0, $offset = 0)
    {
        $this->setTotal($total);
        $this->setLimit($limit);
        $this->setOffset($offset);
    }

    /**
     * @return Results
     */
    public static function create(): Results
    {
        return new self();
    }


    /**
     * @param \Tk\Db\Map\ArrayObject $list
     * @return Results
     * @deprecated Use initFromArrayObject
     */
    public static function createFromDbArray(\Tk\Db\Map\ArrayObject $list): Results
    {
        return new self($list->countAll(), $list->getTool()->getLimit(), $list->getTool()->getOffset());
    }

    /**
     * @param Tool $tool
     * @param int $foundRows
     * @return Results
     * @deprecated Use initFromDbTool
     */
    public static function createFromDbTool($tool, $foundRows = 0): Results
    {
        return new self($foundRows, $tool->getLimit(), $tool->getOffset());
    }

    /**
     * @param \Tk\Db\Map\ArrayObject $list
     * @return $this
     */
    public function initFromArrayObject(\Tk\Db\Map\ArrayObject $list): Results
    {
        $this->setTotal($list->countAll());
        $this->setLimit($list->getTool()->getLimit());
        $this->setOffset($list->getTool()->getOffset());
        return $this;
    }

    /**
     * @param Tool $tool
     * @param int $foundRows
     * @return Results
     */
    public function initFromDbTool($tool, $foundRows = 0): Results
    {
        $this->setTotal($foundRows);
        $this->setLimit($tool->getLimit());
        $this->setOffset($tool->getOffset());
        return $this;
    }

    /**
     * @return int
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * @param int $total
     * @return Results
     */
    public function setTotal(int $total): Results
    {
        $this->total = $total;
        return $this;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @param int $limit
     * @return Results
     */
    public function setLimit(int $limit): Results
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @return int
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * @param int $offset
     * @return Results
     */
    public function setOffset(int $offset): Results
    {
        $this->offset = $offset;
        return $this;
    }


    /**
     * show
     *
     */
    public function show()
    {
        $template = $this->getTemplate();

        $template->addCss('tk-results', $this->getCssString());
        foreach ($this->getAttrList() as $k => $v) {
            $template->setAttr('tk-results', $k, $v);
        }

        if (!$this->total) {
//            $template->insertText('tk-results', 'No Results Found.');
            return;
        }

        $from = 0;
        if ($this->total) {
            $from = $this->offset+1;
        }
        $to = $this->offset + $this->limit;

        if ($to > $this->total || $to == 0) {
            $to = $this->total;
        }

        $str = sprintf('%s-%s / %s', $from, $to, $this->total);
        //$template->insertText('tk-results', $str);
        $template->setAttr('tk-results', 'title', $str);

        // TODO could we just insert the string
        $template->insertText('from', $from);
        $template->insertText('to', $to);
        $template->insertText('total', $this->total);

        $template->setVisible('tk-results');
    }

    /**
     * makeTemplate
     *
     * @return \Dom\Template
     */
    public function __makeTemplate()
    {
        $xhtml = <<<XHTML
<div class="tk-results" var="tk-results">
  <span choice="tk-results">
    <span var="from"></span>-<span var="to"></span> of <span var="total"></span> rows
  </span>
</div>
XHTML;
        return \Dom\Loader::load($xhtml);
    }

}