<?php namespace Cupparis\Datafile\Driver\ExcelFilter;

/**
 * Created by PhpStorm.
 * User: gecche
 * Date: 10/05/16
 * Time: 12:11
 */

class ChunksReadFilter implements \PHPExcel_Reader_IReadFilter {

    protected $startRow = 1;
    protected $endRow = 0;
    protected $finalRow = null;

    protected $startingColumnIndex = 0;
    protected $endingColumnIndex = null;

    function __construct($startRow = 0, $finalRow = null, $startingColumnIndex = 0, $endingColumnIndex = null, $endRow = null)
    {
        $this->startRow = $startRow;
        $this->finalRow = $finalRow;
        $this->startingColumnIndex = $startingColumnIndex;
        $this->endingColumnIndex = $endingColumnIndex;
        $this->endRow = $endRow;
    }


    /**
     *
     */
    public function setRows($startRow,$numRows)
    {
        $this->startRow = $startRow;
        $this->endRow = $startRow + $numRows;

    }



    /**
     * Should this cell be read?
     *
     * @param    $column        String column index
     * @param    $row            Row index
     * @param    $worksheetName    Optional worksheet name
     * @return    boolean
     */
    public function readCell($column, $row, $worksheetName = '')
    {
        $column = \PHPExcel_Cell::columnIndexFromString($column);
        $rowOk = $row >= $this->startRow && $row <= $this->endRow && (!$this->finalRow || $row <= $this->finalRow);
        if (!$rowOk) {
            return false;
        }

        if ($column >= $this->startingColumnIndex && (!$this->endingColumnIndex || $column <= $this->endingColumnIndex) ) {
            return true;
        }

        return false;
    }


}