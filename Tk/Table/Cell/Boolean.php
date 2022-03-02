<?php
namespace Tk\Table\Cell;


/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class Boolean extends Text
{

    /**
     * Get the raw string property value with no formatting.
     * This call can be used for exporting data into a csv, json, xml format
     *
     * @param mixed $obj
     * @return string
     */
    public function getPropertyValue($obj)
    {
        $value = $this->getObjectPropertyValue($obj);
        $v = 'No';
        if ($value) {
            if ($value == true || strtolower($value) == 'yes' || strtolower($value) == 'true' ||
                strtolower($value) == 't' || $value == '1' || strtolower($value) == 'ok' || strtolower($value) == 'y' ||
                $value == $this->getProperty())
            {
                $v = 'Yes';
            }
        }
        return $v;
    }

}