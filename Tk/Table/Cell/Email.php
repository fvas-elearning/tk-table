<?php
namespace Tk\Table\Cell;


/**
 * Class Text
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class Email extends Text
{

    /**
     * @param mixed $obj
     * @param int|null $rowIdx The current row being rendered (0-n) If null no rowIdx available.
     * @return string
     */
    public function getCellHtml($obj, $rowIdx = null)
    {
        $str = $propValue = $this->getPropertyValue($obj);
        if (filter_var($propValue, FILTER_VALIDATE_EMAIL))
            $str = sprintf('<a href="mailto:%s" title="Compose an email to this address.">%s</a>', $propValue, $propValue);
        return $str;
    }


}