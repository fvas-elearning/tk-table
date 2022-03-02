<?php
namespace Tk\Table\Cell;


/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class Checkbox extends Iface
{
    /**
     * If true the checkbox is set to checked if the property value evaluates to true
     * @var bool
     */
    protected $useValue = false;

    /**
     * @param string $property
     */
    public function __construct($property)
    {
        parent::__construct($property, ucfirst(preg_replace('/[A-Z]/', ' $0', $property)));
        $this->setLabel('');
        $this->addCss('tk-tcb-cell');
    }

    /**
     * @return string
     */
    public function getCellHeader()
    {
        if ($this->getLabel()) return parent::getCellHeader();

        $xhtml = sprintf('<span><input type="checkbox" name="%s_all" title="Select All" class="tk-tcb-head" /></span>', $this->getProperty());
        $template = \Dom\Loader::load($xhtml);

        $js = <<<JS
jQuery(function($) {
  
  var init = function () {
    var form = $(this);
    var table = form.parent();
    if (!table.is('.tk-table')) return;
    
    function checkAll(headCheckbox) {
      var _cb = $(headCheckbox);
      var name = _cb.attr('name').match(/([a-zA-Z0-9]+)_all/i)[1];
      var _list = _cb.parents('div.tk-table').find('.table-body input[name^=\''+name+'\']');
	  if (_cb.prop('checked'))  {
	    _list.prop('checked', true);
	  } else {
	    _list.prop('checked', false);
	  }
	  _list.trigger('change');
    }
    
    var head = form.find('.tk-tcb-head');
    head.on('change', function(e){
        checkAll(this);
    }).trigger('change');
    
  };
  $('.tk-table form').on('init', document, init).each(init);
});
JS;
        $template->appendJs($js);

        return $template;
    }

    /**
     * @param mixed $obj
     * @param int|null $rowIdx The current row being rendered (0-n) If null no rowIdx available.
     * @return string
     */
    public function getCellHtml($obj, $rowIdx = null)
    {
        $prop = $this->getProperty();
        $propValue = $this->getPropertyValue($obj);

        $checked = '';
        if ($this->useValue && ($propValue == $prop || $propValue === 1 || strtolower($propValue) === 'true' || strtolower($propValue) === 'yes' || $propValue === true))
            $checked = ' checked="checked"';
        $str = sprintf('<input type="checkbox" name="%s[]" value="%s" class="tk-tcb" title="%s: %s" %s/>', $prop, htmlentities($propValue), $prop, htmlentities($propValue), $checked);
        return $str;
    }

    /**
     * @return bool
     */
    public function isUseValue()
    {
        return $this->useValue;
    }

    /**
     * @param bool $useValue
     * @return Checkbox
     */
    public function setUseValue($useValue)
    {
        $this->useValue = $useValue;
        return $this;
    }

}