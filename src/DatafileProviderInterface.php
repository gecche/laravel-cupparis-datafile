<?php
/**
 * Created by PhpStorm.
 * User: giacomoterreni
 * Date: 25/02/15
 * Time: 14:28
 */
namespace Cupparis\Datafile;

interface DatafileProviderInterface
{
    /**
     * @return null
     */
    public function getHeaders();

    public function getFileProperties();

    public function saveDatafileRow($row, $index, $id = null);

    public function beforeLoad();

    public function afterLoad();

    public function beforeLoadPart();

    public function afterLoadPart();

    public function saveRow($index);

    public function countRows();

    public function getFiletype();

}