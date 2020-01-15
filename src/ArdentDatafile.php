<?php

namespace Gecche\Cupparis\Datafile;

use Closure;
use \Cupparis\Ardent\Ardent;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Auth;
use Cupparis\Form\ModelDBMethods;

class ArdentDatafile extends Ardent {

	public $timestamps = false;
	// campi predefiniti, necessari per il funzionamento del modello
	public $datafile_id_field = 'datafile_id';
    public $row_index_field = 'row';

    public $headers;

	public static $relationsData = array(
		//'address' => array(self::HAS_ONE, 'Address'),
		//'orders'  => array(self::HAS_MANY, 'Order'),
		'errors' => array(self::MORPH_MANY, 'App\Datafilemodels\Error', 'name' => 'datafile_table','id' => "datafile_table_id",'type' => 'datafile_table_type'),
	);

    public function __construct(array $attributes = array()) {

        parent::__construct($attributes);

        if (!$this->fillable) {

            $fillables = array_merge(array($this->getRowIndexField(),$this->getDatafileIdField()),$this->getHeaders());

            $this->fillable($fillables);
        }

    }
	public function errors()
	{
		return $this->morphMany('App\Datafilemodels\Error', 'datafile_table');
	}

    public function getDefaultHeaders() {
        $dbMethods = new ModelDBMethods($this->getConnection());

        $headers = $dbMethods->listColumnsDatatypes($this->getTable());

        unset($headers['id']);
        unset($headers[$this->getDatafileIdField()]);
        unset($headers[$this->getRowIndexField()]);

        return array_keys($headers);
    }

    public function getHeaders() {
        return $this->headers ? $this->headers : $this->getDefaultHeaders();
    }

    /**
     * @return string
     */
    public function getDatafileIdField()
    {
        return $this->datafile_id_field;
    }

    /**
     * @param string $datafile_id_field
     */
    public function setDatafileIdField($datafile_id_field)
    {
        $this->datafile_id_field = $datafile_id_field;
    }

    /**
     * @return string
     */
    public function getRowIndexField()
    {
        return $this->row_index_field;
    }

    /**
     * @param string $row_index_field
     */
    public function setRowIndexField($row_index_field)
    {
        $this->row_index_field = $row_index_field;
    }




    /**
     * @return string
     */
    public function getDatafileIdValue()
    {
        return $this->{$this->datafile_id_field};
    }

    /**
     * @return string
     */
    public function setDatafileIdValue($datafileIdValue)
    {
        $this->{$this->datafile_id_field} = $datafileIdValue;
    }

    /**
     * @return string
     */
    public function getRowIndexValue()
    {
        return $this->{$this->row_index_field};
    }

    /**
     * @return string
     */
    public function setRowIndexValue($rowIndexValue)
    {
        $this->{$this->row_index_field} = $rowIndexValue;
    }

    public function validateDatafile(array $rules = array(), array $customMessages = array(),$datafile_id = null) {
        $rules = $this->buildUniqueDatafileRules($rules,$datafile_id);
        $rules = $this->buildExistsDatafileRules($rules,$datafile_id);
        return $this->validate($rules,$customMessages);
    }

    public function saveDatafile(
        $datafile_id = null,
        array $rules = array(),
        array $customMessages = array(),
        array $options = array(),
        Closure $beforeSave = null,
        Closure $afterSave = null
    ) {

        $rules = $this->buildUniqueDatafileRules($rules,$datafile_id);
        $rules = $this->buildExistsDatafileRules($rules,$datafile_id);
        return $this->forceSave($rules, $customMessages, $options, $beforeSave,
            $afterSave); // TODO: Change the autogenerated stub
    }



    protected function buildUniqueDatafileRules(array $rules = array(),$datafile_id = null) {

        if (!count($rules))
            $rules = static::$rules;

        if (!$datafile_id) {
            $datafile_id = $this->getDatafileIdValue();
        }

        foreach ($rules as $field => &$ruleset) {
            // If $ruleset is a pipe-separated string, switch it to array
            $ruleset = (is_string($ruleset))? explode('|', $ruleset) : $ruleset;

            $ruleName = 'unique_datafile';
            $ruleNameFull = $ruleName . ':';
            foreach ($ruleset as &$rule) {
                if (strpos($rule, $ruleNameFull) === 0) {
                    // Stop splitting at 4 so final param will hold optional where clause
                    $params = explode(',', substr($rule,strlen($ruleNameFull)), 5);

                    $uniqueRules = array();

                    //table
                    $uniqueRules[0] = $params[0];

                    // Append field name if needed
                    if (!isset($params[1]))
                        $uniqueRules[1] = $field;
                    else
                        $uniqueRules[1] = $params[1];

                    if (!isset($params[2])) {
                        if ($this->getKey()) {
                            $uniqueRules[2] = $this->getKey();
                        } else {
                            $uniqueRules[2] = "NULL";
                        }
                    } else {
                        $uniqueRules[2] = $params[2];
                    }

                    if (!isset($params[3]))
                        $uniqueRules[3] = "id";
                    else
                        $uniqueRules[3] = $params[3];

                    $uniqueRules[4] = $this->getDatafileIdField();
                    $uniqueRules[5] = $datafile_id;

                    if (isset($params[4]))
                        $uniqueRules[6] = $params[4];


                    $rule = $ruleNameFull . implode(',', $uniqueRules);
                } // end if strpos unique

            } // end foreach ruleset
        }

        return $rules;
    }


    protected function buildExistsDatafileRules(array $rules = array(),$datafile_id = null) {

        if (!count($rules))
            $rules = static::$rules;

        if (!$datafile_id) {
            $datafile_id = $this->getDatafileIdValue();
        }

        foreach ($rules as $field => &$ruleset) {
            // If $ruleset is a pipe-separated string, switch it to array
            $ruleset = (is_string($ruleset))? explode('|', $ruleset) : $ruleset;

            $ruleName = 'exists_datafile';
            $ruleNameFull = $ruleName . ':';
            foreach ($ruleset as &$rule) {
                if (strpos($rule, $ruleNameFull) === 0) {
                    // Stop splitting at 4 so final param will hold optional where clause
                    $params = explode(',', substr($rule,strlen($ruleNameFull)), 4);

                    //Deve averci almeno 4 parametri
                    if (count($params) < 4)
                        continue;

                    $existsRules = array();

                    //table datafile
                    $existsRules[0] = $params[0];

                    //field datafile
                    $existsRules[1] = $params[1];


                    if ($params[2] === 'NULL') {
                        $extraParamsDatafile = array();
                    } else {
                        $extraParamsDatafile = explode('#', $params[2]);
                    }
                    array_push($extraParamsDatafile,$this->getDatafileIdField());
                    array_push($extraParamsDatafile,$datafile_id);

                    $existsRules[2] = implode('#', $extraParamsDatafile);
                    $existsRules[3] = $params[3];

                    $rule = $ruleNameFull . implode(',', $existsRules);
                } // end if strpos unique

            } // end foreach ruleset
        }

        return $rules;
    }

    public function getBuildedRules(array $rules = array())
    {
        $rules = parent::getBuildedRules($rules);
        $rules = $this->buildUniqueDatafileRules($rules,$this->getDatafileIdValue());
        return $this->buildExistsDatafileRules($rules,$this->getDatafileIdValue());

    }

    public function getCsvExportFields($type = 'default', $modelParams = [])
    {
        $attributes = parent::getCsvExportFields($type, $modelParams); // TODO: Change the autogenerated stub

        if (!in_array('errors',$attributes)) {
            array_push($attributes,'errors');
        }

        return $attributes;
    }

    public function getCsvExportErrors($type = 'default', $modelParams = []) {

        $value = '';
        $nError = 1;
        foreach ($this->errors as $error) {
            $value .= $nError . ' ' . $error->field_name . ': '
                . ucfirst(Lang::getRaw('validation.' . snake_case($error->error_name)))
                . $this->csvExportSettings[$type]['separator'];
            $nError++;
        }

        return $value;

    }



}

// End Datafile Core Model
