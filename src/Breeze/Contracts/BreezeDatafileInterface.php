<?php namespace Gecche\Cupparis\Datafile\Breeze\Contracts;


use Closure;
use Gecche\Breeze\Contracts\BreezeInterface;
use Gecche\Cupparis\Datafile\Models\DatafileError;
use Gecche\DBHelper\Facades\DBHelper;
use Illuminate\Support\Arr;

/**
 * Breeze - Eloquent model base class with some pluses!
 *
 */
interface BreezeDatafileInterface extends BreezeInterface {



    /*
     * DA METTERE IN RELATIONS
     */
    public function errors();

    /**
     * @return string
     */
    public function getDatafileIdField();

    /**
     * @param string $datafile_id_field
     */
    public function setDatafileIdField($datafile_id_field);

    /**
     * @return string
     */
    public function getRowIndexField();

    /**
     * @param string $row_index_field
     */
    public function setRowIndexField($row_index_field);

    /**
     * @return string
     */
    public function getDatafileIdValue();

    /**
     * @return string
     */
    public function setDatafileIdValue($datafileIdValue);

    /**
     * @return string
     */
    public function getRowIndexValue();

    /**
     * @return string
     */
    public function setRowIndexValue($rowIndexValue);


    /*
     * CALCOLO DEGLI HEADERS DI DEFAULT CON DB HELPERS
     */
    public function getDefaultHeaders();

    public function getHeaders();



    /*
     * VALIDAZIONE AL SALVATAGGIO DA CAMBIARE
     */

    public function validateDatafile(array $rules = array(), array $customMessages = array(),$datafile_id = null);

    public function saveDatafile(
        $datafile_id = null,
        array $rules = array(),
        array $customMessages = array(),
        array $options = array(),
        Closure $beforeSave = null,
        Closure $afterSave = null
    );


    public static function getErrorsModelName();

    public static function getDatafileModelName();

}
