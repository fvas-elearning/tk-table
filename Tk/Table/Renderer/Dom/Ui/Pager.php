<?php
namespace Tk\Table\Renderer\Dom\Ui;

/**
 * Class
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class Pager extends Iface
{

    /**
     * @var int
     */
    protected $total = 0;

    /**
     * @var int
     */
    protected $limit = 0;

    /**
     * @var int
     */
    protected $offset = 0;

    /**
     * @var int
     */
    protected $maxPages = 10;

    /**
     * @var \Tk\Uri
     */
    protected $pageUrl = null;

    /**
     * @var bool
     */
    protected $enablePageButtons = true;



    /**
     * Create
     *
     * @param int $total The total number of records on all pages
     * @param int $limit
     * @param int $offset
     */
    public function __construct($total = 0, $limit = 25, $offset = 0)
    {
        $this->total = (int)$total;
        $this->limit = (int)$limit;
        $this->offset = (int)$offset;

        $this->pageUrl = \Tk\Uri::create();
    }


    /**
     * @param \Tk\Db\Tool $tool
     * @param int $foundRows
     * @return Pager
     */
    public static function createFromDbTool($tool, $foundRows = 0)
    {
        return new self($foundRows, $tool->getLimit(), $tool->getOffset());
    }

    /**
     *
     * @param \Tk\Db\Map\ArrayObject $list
     * @return Pager
     */
    public static function createFromDbArray(\Tk\Db\Map\ArrayObject $list)
    {
        return new self($list->countAll(), $list->getTool()->getLimit(), $list->getTool()->getOffset());
    }

    /**
     * Set the maximum number of page values to display
     * Default: 10 page numbers
     *
     * @param int $i
     * @return $this
     */
    public function setMaxPages($i)
    {
        $this->maxPages = $i;
        return $this;
    }

    /**
     * Set the new page Url, all pager urls will be created from this url
     *
     * @param \Tk\Uri $url
     * @return $this
     */
    public function setPageUrl(\Tk\Uri $url)
    {
        $this->pageUrl = $url;
        return $this;
    }

    /**
     * @return bool
     */
    public function isEnablePageButtons()
    {
        return $this->enablePageButtons;
    }

    /**
     * @param bool $enablePageButtons
     */
    public function setEnablePageButtons($enablePageButtons)
    {
        $this->enablePageButtons = $enablePageButtons;
    }

    /**
     * show
     *
     */
    public function show()
    {
        $template = $this->getTemplate();

        $template->addCss('tk-pager', $this->getCssString());
        foreach ($this->getAttrList() as $k => $v) {
            $template->setAttr('tk-pager', $k, $v);
        }

        if ($this->limit > -1 && $this->limit < $this->total) {
            $numPages = 0;
            $currentPage = 0;
            if ($this->limit > 0) {
                $numPages = ceil($this->total / $this->limit);
                $currentPage = ceil($this->offset / $this->limit);
            }

            $startPage = 0;
            $endPage = $this->maxPages;
            $center = floor($this->maxPages / 2);
            if ($currentPage > $center) {
                $startPage = $currentPage - $center;
                $endPage = $startPage + $this->maxPages;
            }
            if ($startPage > $numPages - $this->maxPages) {
                $startPage = $numPages - $this->maxPages;
                $endPage = $numPages;
            }

            if ($startPage < 0) {
                $startPage = 0;
            }
            if ($endPage >= $numPages) {
                $endPage = $numPages;
            }

            if ($numPages > 0) {
                $template->setChoice('Pager');
            }

            $pageUrl = $this->pageUrl;
            $pageUrl->setFragment(substr($this->makeInstanceKey(''), 0, -1));       // TODO: can cause conflicts with javasacript, keep testing, remove if required
            $pageUrl->remove($this->makeInstanceKey(self::PARAM_OFFSET));
            if ($this->enablePageButtons) {
                for ($i = $startPage; $i < $endPage; $i++) {
                    $repeat = $template->getRepeat('page');
                    $repeat->insertText('pageUrl', $i + 1);
                    $repeat->setAttr('pageUrl', 'title', 'Page ' . ($i + 1));
                    $pageUrl->set($this->makeInstanceKey(self::PARAM_OFFSET), $i * $this->limit);
                    $repeat->setAttr('pageUrl', 'href', $pageUrl->toString());
                    if ($i == $currentPage) {
                        $repeat->addCss('page', self::CSS_SELECTED);
                        $repeat->setAttr('pageUrl', 'title', 'Current Page ' . ($i + 1));
                    }
                    $repeat->appendRepeat();
                }
            }

            if ($this->offset >= $this->limit) {
                $pageUrl->set($this->makeInstanceKey(self::PARAM_OFFSET), $this->offset - $this->limit);
                $template->setAttr('backUrl', 'href', $pageUrl->toString());
                $template->setAttr('backUrl', 'title', 'Previous Page');
                $pageUrl->set($this->makeInstanceKey(self::PARAM_OFFSET), 0);
                $template->setAttr('startUrl', 'href', $pageUrl->toString());
                $template->setAttr('startUrl', 'title', 'Start Page');
            } else {
                $template->addCss('start', self::CSS_DISABLED);
                $template->addCss('back', self::CSS_DISABLED);
            }

            if ($this->offset < ($this->total - $this->limit)) {
                $pageUrl->set($this->makeInstanceKey(self::PARAM_OFFSET), $this->offset + $this->limit);
                $template->setAttr('nextUrl', 'href', $pageUrl->toString());
                $template->setAttr('nextUrl', 'title', 'Next Page');
                $pageUrl->set($this->makeInstanceKey(self::PARAM_OFFSET), ($numPages - 1) * $this->limit);
                $template->setAttr('endUrl', 'href', $pageUrl->toString());
                $template->setAttr('endUrl', 'title', 'Last Page');
            } else {
                $template->addCss('end', self::CSS_DISABLED);
                $template->addCss('next', self::CSS_DISABLED);
            }
        }

    }

    /**
     * makeTemplate
     *
     * @return string
     */
    public function __makeTemplate()
    {
        $xhtml = <<<XHTML
<nav class="tk-pager" var="tk-pager" aria-label="Page Navigation">
  <ul choice="Pager" var="pager" class="pagination justify-content-center">
    <li class="page-item" var="start"><a class="page-link" href="javascript:;" var="startUrl" rel="nofollow">Start</a></li>
    <li class="page-item" var="back"><a class="page-link" href="javascript:;" var="backUrl">&laquo;</a></li>

    <li class="page-item" repeat="page" var="page"><a class="page-link" href="javascript:;" var="pageUrl" rel="nofollow"></a></li>

    <li class="page-item" var="next"><a class="page-link" href="javascript:;" var="nextUrl">&raquo;</a></li>
    <li class="page-item" var="end"><a class="page-link" href="javascript:;" var="endUrl" rel="nofollow">End</a></li>
  </ul>
</nav>
XHTML;
        return \Dom\Loader::load($xhtml);
    }

}