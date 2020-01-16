<?php

namespace Gecche\Cupparis\Datafile\Breeze\Concerns;

use Illuminate\Support\Facades\Validator;

trait HasDatafileValidation
{

    /*
     * Get the model validation rules, custom messages and custom attributes
     * to be used by an external validator instance
     */
    public function getDatafileModelValidationSettings($uniqueRules = true, $rules = [], $customMessages = [], $customAttributes = [])
    {
        $rules = array_merge(static::$rules, $rules);
        if ($this->getKey() && $uniqueRules) {
            $rules = $this->buildUniqueExclusionRules($rules);
        }

        $rules = array_merge($rules,$this->buildUniqueDatafileRules($rules,$this->getDatafileIdValue()));
        $rules = array_merge($rules,$this->buildExistsDatafileRules($rules,$this->getDatafileIdValue()));
        $validationData = [
            'rules' => $rules,
            'customMessages' => array_merge(static::$customMessages, $customMessages),
            'customAttributes' => array_merge(static::$customAttributes, $customAttributes),
        ];

        return $validationData;

    }

    /*
     * Get a full validator instance for the model
     */
    public
    function getDatafileValidator($data = null, $uniqueRules = true, $rules = [], $customMessages = [], $customAttributes = [])
    {
        $validatorSettings = $this->getDatafileModelValidationSettings($uniqueRules, $rules, $customMessages, $customAttributes);

        $data = $data ?: $this->getAttributes();

        return Validator::make($data, $validatorSettings['rules'], $validatorSettings['customMessages'], $validatorSettings['customAttributes']);
    }


    protected
    function buildUniqueDatafileRules(array $rules = [], $datafile_id = null)
    {

        if (!$datafile_id) {
            $datafile_id = $this->getDatafileIdValue();
        }

        foreach ($rules as $field => &$ruleset) {
            // If $ruleset is a pipe-separated string, switch it to array
            $ruleset = (is_string($ruleset)) ? explode('|', $ruleset) : $ruleset;

            $ruleName = 'unique_datafile';
            $ruleNameFull = $ruleName . ':';
            foreach ($ruleset as &$rule) {
                if (strpos($rule, $ruleNameFull) === 0) {
                    // Stop splitting at 4 so final param will hold optional where clause
                    $params = explode(',', substr($rule, strlen($ruleNameFull)), 5);

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


    protected
    function buildExistsDatafileRules(array $rules = [], $datafile_id = null)
    {

        if (!$datafile_id) {
            $datafile_id = $this->getDatafileIdValue();
        }

        foreach ($rules as $field => &$ruleset) {
            // If $ruleset is a pipe-separated string, switch it to array
            $ruleset = (is_string($ruleset)) ? explode('|', $ruleset) : $ruleset;

            $ruleName = 'exists_datafile';
            $ruleNameFull = $ruleName . ':';
            foreach ($ruleset as &$rule) {
                if (strpos($rule, $ruleNameFull) === 0) {
                    // Stop splitting at 4 so final param will hold optional where clause
                    $params = explode(',', substr($rule, strlen($ruleNameFull)), 4);

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
                    array_push($extraParamsDatafile, $this->getDatafileIdField());
                    array_push($extraParamsDatafile, $datafile_id);

                    $existsRules[2] = implode('#', $extraParamsDatafile);
                    $existsRules[3] = $params[3];

                    $rule = $ruleNameFull . implode(',', $existsRules);
                } // end if strpos unique

            } // end foreach ruleset
        }

        return $rules;
    }


}
