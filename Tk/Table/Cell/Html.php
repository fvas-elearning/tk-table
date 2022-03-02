<?php
namespace Tk\Table\Cell;


/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class Html extends Text
{

    private $html = '';

    /**
     * @return string
     */
    public function getHtml()
    {
        return $this->html;
    }

    /**
     * @param string $html
     * @return $this
     */
    public function setHtml($html)
    {
        $this->html = $html;
        return $this;
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
        $value = strip_tags($this->getPropertyValue($obj));
        return $value;
    }


    /**
     * @param mixed $obj
     * @param int|null $rowIdx The current row being rendered (0-n) If null no rowIdx available.
     * @return string
     */
    public function getCellHtml($obj, $rowIdx = null)
    {
        $propValue = $this->getPropertyValue($obj);
        if ($this->getHtml()) $propValue = $this->getHtml();
        $str = $propValue;
        $url = $this->getCellUrl($obj);
        if ($url) {
            $str = sprintf('<a href="%s" title="%s">%s</a>', htmlentities($url->toString()), htmlentities($this->getLabel()), htmlentities($propValue));
        }
        return $str;
    }


}