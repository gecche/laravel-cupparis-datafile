<?php namespace Gecche\Cupparis\Datafile\Breeze\Contracts;

/**
 * Breeze - Eloquent model base class with some pluses!
 *
 */
interface  HasDatafileValidationInterface {


    public function getDatafileModelValidationSettings($uniqueRules = true, $rules = [], $customMessages = [], $customAttributes = []);

    public
    function getDatafileValidator($data = null, $uniqueRules = true, $rules = [], $customMessages = [], $customAttributes = []);


}
