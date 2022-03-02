<?php
namespace Tk\Table\Action;

use \Tk\Table\Cell;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class Csv extends Button
{
    
    /**
     * @var \Tk\Db\Pdo
     */
    protected $db = null;

    /**
     * @var string
     */
    protected $checkboxName = 'id';


    protected $ignoreCellList = [
        //'Tk\Table\Cell\Checkbox',
        'Tk\Table\Cell\Actions',
        'Tk\Table\Cell\ButtonCollection'
    ];

    /**
     * @var string
     */
    protected $filename = '';


    /**
     * @param \Tk\Db\Pdo $db
     * @param string $name
     * @param string $checkboxName
     * @param string $icon
     */
    public function __construct($db, $name = 'csv', $checkboxName = 'id', $icon = 'fa fa-list-alt')
    {
        parent::__construct($name, $icon);
        $this->db = $db;
        $this->checkboxName = $checkboxName;
        $this->addCss('tk-action-csv no-loader');
    }

    /**
     * @param string $name
     * @param string $checkboxName
     * @param string $icon
     * @param \Tk\Db\Pdo $db
     * @return Csv
     */
    static function create($name = 'csv', $checkboxName = 'id', $icon = 'fa fa-list-alt', $db = null)
    {
        if ($db === null)
            $db = \Tk\Config::getInstance()->getDb();

        return new self($db, $name, $checkboxName, $icon);
    }

    /**
     * @return mixed|void
     * @throws \Exception
     */
    public function execute()
    {
        parent::execute();

        $request = $this->getTable()->getRequest();
        // Headers for an download:
        ini_set('max_execution_time', 0);

        $file = $this->getTable()->getId() . '_' . date('Ymd') . '.csv';
        if ($this->getFilename()) {
            $file = $this->getFilename() . '_' . date('Ymd') . '.csv';
        }
        if ($request->has('csv_name')) {
            $file = preg_replace('/[^a-z0-9_\.-]/i', '_', basename(strip_tags(trim($request['csv_name']))));
        }

        // Get list with no limit...
        $list = $this->getTable()->getList();

        $fullList = $list;
        if ($request->has($this->checkboxName) && is_array($request->get($this->checkboxName))) {
            $fullList = array();
            foreach($list as $obj) {
                if (in_array($obj->getId(), $request->get($this->checkboxName))) {
                    $fullList[] = $obj;
                }
            }
        } else if (is_object($list) && $list->countAll() > $list->count()) {
            $st = $list->getStatement();
            $sql = $st->queryString;
            if (preg_match('/ LIMIT /i', $sql)) {
                $sql = substr($sql, 0, strrpos($sql, 'LIMIT'));
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($st->getBindParams());
            if ($list->getMapper()) {
                $fullList = \Tk\Db\Map\ArrayObject::createFromMapper($list->getMapper(), $stmt);
            } else {
                $fullList = \Tk\Db\Map\ArrayObject::create($stmt);
            }
        } else if (is_array($list)) {
            $sql = $this->getDb()->getLastQuery();
            if (preg_match('/ LIMIT /i', $sql)) {
                $sql = substr($sql, 0, strrpos($sql, 'LIMIT'));
            }
            $stmt = $this->getDb()->prepare($sql);
            $stmt->execute();
            $fullList = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        // Output the CSV data
        $out = fopen('php://output', 'w');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Transfer-Encoding: binary');

        $arr = array();
        // Write cell labels to first line of csv...
        foreach ($this->table->getCellList() as $i => $cell) {
            if ($this->ignoreCell($cell)) continue;
            //$arr[] = $cell->getLabel();           // TODO: Check this change does not influence any external functionality
            $arr[] = $cell->getProperty();
        }
        fputcsv($out, $arr);
        if ($fullList) {
            foreach ($fullList as $obj) {
                $arr = array();
                /* @var $cell Cell\Iface */
                foreach ($this->table->getCellList() as $cell) {
                    if ($this->ignoreCell($cell)) continue;
                    $value = $cell->getRawValue($obj);
                    $arr[$cell->getLabel()] = $value;
                }
                fputcsv($out, $arr);
            }
        }

        fclose($out);
        exit;
    }

    /**
     * @return string|\Dom\Template
     */
    public function show()
    {
        $this->setAttr('title', 'Export records as a CSV file.');

        $template = parent::show();

        $js = <<<JS
jQuery(function ($) {
  $('.tk-action-csv').each(function () {
    
  });
});
JS;
        //$template->appendJs($js);

        return $template;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @param string $filename
     * @return $this
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
        return $this;
    }

    /**
     *
     * @param \Tk\Table\Cell\Iface $cell
     * @return bool
     */
    private function ignoreCell($cell)
    {
        return in_array(get_class($cell), $this->ignoreCellList);
    }

    /**
     *
     * @param \Tk\Table\Cell\Iface $cell
     * @return $this
     */
    public function addIgnoreCell($cell)
    {
        $this->ignoreCellList[get_class($cell)] = get_class($cell);
        return $this;
    }

    /**
     * Set the ignore cell class array or reset the array if nothing passed
     *
     * Eg:
     *   array('Tk\Table\Cell\Checkbox', 'App\Ui\Subject\EnrolledCell');
     *
     * @param array $array
     * @return $this
     */
    public function setIgnoreCellList($array = array())
    {
        $this->ignoreCellList = $array;
        return $this;
    }

}
