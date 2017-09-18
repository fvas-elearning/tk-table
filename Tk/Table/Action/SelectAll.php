<?php
namespace Tk\Table\Action;


/**
 *
 *
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @link http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class SelectAll extends Button
{

    /**
     * @var string
     */
    protected $checkboxName = 'id';



    /**
     * Create
     *
     * @param string $name
     * @param string $checkboxName The checkbox name to get the selected id's from
     * @param string $icon
     */
    public function __construct($name = 'selectAll', $checkboxName = 'id', $icon = 'fa fa-square-o')
    {
        parent::__construct($name, $icon);
        $this->checkboxName = $checkboxName;
        $this->setAttr('type', 'button');
    }

    /**
     * Create
     * 
     * @param string $name
     * @param string $checkboxName
     * @param string $icon
     * @return SelectAll
     */
    static function create($name = 'selectAll', $checkboxName = 'id', $icon = 'fa fa-square-o')
    {
        return new static($name, $checkboxName, $icon);
    }




    /**
     * @return mixed
     */
    public function execute()
    {
        $request = $this->getTable()->getRequest();

        //\Tk\Uri::create()->remove($this->getTable()->makeInstanceKey($this->getName()))->redirect();
    }

    /**
     * @return string|\Dom\Template
     */
    public function getHtml()
    {
        $this->setAttr('title', 'Select All/None');
        $this->setAttr('data-cb-name', $this->checkboxName);
        $this->addCss( 'btn-select-all');

        $template = parent::getHtml();

        $js = <<<JS
jQuery(function($) {
  
  $('.btn-select-all').on('click', function () {
    if ($(this).hasClass('selected')) {
      $(this).removeClass('selected');
      $(this).find('i').attr('class', 'fa fa-square-o');
      $(this).closest('.tk-table').find('input[name^="'+$(this).data('cb-name')+'"]').prop('checked', false);
    } else {
      $(this).addClass('selected');
      $(this).find('i').attr('class', 'fa fa-check-square-o');
      $(this).closest('.tk-table').find('input[name^="'+$(this).data('cb-name')+'"]').prop('checked', true);
    }
  });
  
});
JS;
        $template->appendJs($js);

        return $template;
    }

}
