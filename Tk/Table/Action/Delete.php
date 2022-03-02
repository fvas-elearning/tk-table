<?php
namespace Tk\Table\Action;


use Tk\Callback;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class Delete extends Button
{

    /**
     * @var string
     */
    protected $checkboxName = 'id';

    /**
     * @var array
     */
    protected $excludeIdList = array();

    /**
     * function (Delete $delete, $obj)
     * @var Callback
     */
    protected $onDelete = null;

    /**
     * @var string
     */
    protected $confirmStr = 'Are you sure you want to delete the selected records?';


    /**
     * @param string $name
     * @param string $checkboxName The checkbox name to get the selected id's from
     * @param string $icon
     */
    public function __construct($name = 'delete', $checkboxName = 'id', $icon = 'fa fa-times')
    {
        $this->onDelete = Callback::create();
        parent::__construct($name, $icon);
        $this->addCss('tk-action-delete');
        $this->setCheckboxName($checkboxName);
    }

    /**
     * @param string $name
     * @param string $checkboxName
     * @param string $icon
     * @return static
     */
    static function create($name = 'delete', $checkboxName = 'id', $icon = 'fa fa-times')
    {
        return new static($name, $checkboxName, $icon);
    }

    /**
     * @return Callback
     */
    public function getOnDelete()
    {
        return $this->onDelete;
    }

    /**
     * @param callable $callable
     * @return $this
     * @deprecated use addOnDelete()
     */
    public function setOnDelete($callable)
    {
        $this->addOnDelete($callable);
        return $this;
    }

    /**
     * EG:  function (\Tk\Table\Action\Delete $action, $obj): ?bool { }
     * Return false from the callback to stop the call to $obj->delete()
     *
     * @param callable $callable
     * @param int $priority
     * @return $this
     */
    public function addOnDelete($callable, $priority = Callback::DEFAULT_PRIORITY)
    {
        $this->getOnDelete()->append($callable, $priority);
        return $this;
    }

    /**
     * @param $array
     * @return $this
     * @deprecated use setExcludeIdList()
     */
    public function setExcludeList($array)
    {
        return $this->setExcludeIdList($array);
    }

    /**
     * @param $array
     * @return $this
     */
    public function setExcludeIdList($array)
    {
        $this->excludeIdList = $array;
        return $this;
    }

    /**
     * @return array
     */
    public function getExcludeIdList()
    {
        return $this->excludeIdList;
    }

    /**
     * @return string
     */
    public function getCheckboxName(): string
    {
        return $this->checkboxName;
    }

    /**
     * @param string $checkboxName
     * @return Delete
     */
    public function setCheckboxName(string $checkboxName): Delete
    {
        $this->checkboxName = $checkboxName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function execute()
    {
        parent::execute();

        $reqName = $this->getTable()->makeInstanceKey($this->getName());
        $request = $this->getTable()->getRequest();
        if (empty($request->get($reqName))) {
            return;
        }
        $selected = $request->get($this->getCheckboxName());
        if (!is_array($selected)) return;

        /* @var \Tk\Db\Map\Model $obj */
        foreach($this->getTable()->getList() as $obj) {
            if (!is_object($obj)) continue;
            $keyValue = 0;
            if (property_exists($obj, $this->getCheckboxName())) {
                $keyValue = $obj->{$this->getCheckboxName()};
            }
            if (in_array($keyValue, $selected) && !in_array($keyValue, $this->getExcludeIdList())) {
                $propagate = true;
                $r = $this->getOnDelete()->execute($this, $obj);
                if ($r !== null && is_bool($r)) $propagate = $r;
                if ($propagate) {
                    $obj->delete();
                }
            }
        }

        \Tk\Uri::create()->remove($this->getTable()->makeInstanceKey($this->getName()))->redirect();
    }

    /**
     * @return string|\Dom\Template
     */
    public function show()
    {

        if (!$this->hasAttr('title'))
            $this->setAttr('title', 'Delete Selected Records');
        if (!$this->hasAttr('data-confirm'))
            $this->setAttr('data-confirm', $this->getConfirmStr());

        $this->setAttr('disabled');
        $this->setAttr('data-cb-name', $this->getCheckboxName());


        $template = parent::show();

        $template->appendJs($this->getJs());
        return $template;
    }

    /**
     * @return string
     */
    protected function getConfirmStr()
    {
        return $this->confirmStr;
    }

    /**
     * @param string $confirmStr
     * @return Delete
     */
    public function setConfirmStr($confirmStr)
    {
        $this->confirmStr = $confirmStr;
        return $this;
    }

    /**
     * @return string
     */
    protected function getJs()
    {
        $js = <<<JS
jQuery(function($) {
  var init = function () {
    function updateBtn(btn) {
      var cbName = btn.data('cb-name');
      if(btn.closest('.tk-table').find('.table-body input[name^="'+cbName+'"]:checked').length) {
        btn.removeAttr('disabled');
      } else {
        btn.attr('disabled', 'disabled');
      }
    }
    
    $('.tk-action-delete').each(function () {
      var btn = $(this);
      var cbName = btn.data('cb-name');
      btn.on('click', function () {
        var selected = $(this).closest('.tk-table').find('.table-body input[name^="'+cbName+'"]:checked');
        return selected.length > 0;
      });
      btn.closest('.tk-table').on('change', '.table-body input[name^="'+cbName+'"]', function () { updateBtn(btn); });
      updateBtn(btn);
    });
  }
  $('.tk-table form').on('init', document, init).each(init);
});
JS;
        return $js;
    }

}
