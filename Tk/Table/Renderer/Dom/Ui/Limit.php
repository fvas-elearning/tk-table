<?php
namespace Tk\Table\Renderer\Dom\Ui;

/**
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class Limit extends Iface
{

    /**
     * @var int
     */
    protected $limit = 0;

    /**
     * @var array
     */
    private $limitList = null;


    /**
     * Create
     *
     * @param int $limit
     * @param array|null $limitList
     */
    public function __construct($limit = 0, $limitList = null)
    {
        $this->setLimit($limit);
        if (!$limitList) {
            $limitList = array(10, 25, 50, 100, 250, 500);
        }
        $this->setLimitList($limitList);
    }

    /**
     * @return Limit
     * @param int $limit
     * @param array|null $limitList
     */
    public static function create($limit = 0, $limitList = null): Limit
    {
        return new self($limit, $limitList);
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
     * @return Limit
     */
    public function setLimit(int $limit): Limit
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @return array
     */
    public function getLimitList(): array
    {
        return $this->limitList;
    }

    /**
     * @param array $limitList
     * @return Limit
     */
    public function setLimitList(array $limitList): Limit
    {
        $this->limitList = $limitList;
        return $this;
    }

    /**
     * show
     *
     */
    public function show()
    {
        $template = $this->getTemplate();
        $domform = $template->getForm();
        if(!$domform) {
            return;
        }

        $template->addCss('tk-limit', $this->getCssString());
        foreach ($this->getAttrList() as $k => $v) {
            $template->setAttr('tk-limit', $k, $v);
        }
        /** @var \Dom\Form\Select $select */
        $select = $domform->getFormElement('limit');
        foreach($this->limitList as $val) {
            $select->appendOption($val, $val);
        }

        $select->setValue($this->limit);
        //$select->setAttribute('name', $this->makeInstanceKey(self::PARAM_LIMIT));
        $select->setAttribute('name', '');
        $select->setAttribute('data-name', $this->makeInstanceKey(self::PARAM_LIMIT));


        //$template->appendJsUrl(\Tk\Uri::create('/vendor/ttek/tk-base/assets/js/Uri.js'));

        $js = <<<JS
jQuery(function($) {
  
    // Limit onchange event
    $('.tk-limit select').change(function(e) {
        if ($(this).val() === 0) {
            if (!confirm('WARNING: If there are many records this action could be slow.')) {
                return false;
            }
        }
        var url = new Uri();
        url.set($(this).data('name'), $(this).val())
        url.redirect();
        return false;
    });

});
JS;
        $template->appendJs($js);


    }

    /**
     * makeTemplate
     *
     * @return \Dom\Template
     */
    public function __makeTemplate()
    {
        $xhtml = <<<XHTML
<div class="tk-limit" var="tk-limit">
    <select class="form-control input-sm" id="limit" var="select">
      <option value="0">-- ALL --</option>
    </select>
</div>
XHTML;
        return \Dom\Loader::load($xhtml);
    }

}