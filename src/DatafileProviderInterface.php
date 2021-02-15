<?php
/**
 * Created by PhpStorm.
 * User: giacomoterreni
 * Date: 25/02/15
 * Time: 14:28
 */
namespace Gecche\Cupparis\Datafile;

interface DatafileProviderInterface
{
    /**
     * @return null
     */
    public function getHeaders();

    public function getFileProperties();

    public function saveDatafileRow($row, $sheet, $index, $id = null);

    public function beforeLoad();

    public function afterLoad();

    public function beforeLoadPart();

    public function afterLoadPart();

    public function saveRow($sheet, $index);

    public function countRows();

    public function getFiletype();

    public function getSheetsNames();

    public function setCurrentSheet($sheetName);

    public function getCurrentSheet();
}
