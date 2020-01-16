<?php

namespace Gecche\Cupparis\Datafile\DatafileModels;

use Gecche\Cupparis\Datafile\Breeze\BreezeDatafile;

class ComuneIstat extends BreezeDatafile {

	protected $table = 'csv_comuni';

    public static $rules = array(
        'GTComIstat' => 'required|numeric', //|unique_datafile:csv_comuni,GTComIstat',
        'GTComDes' => 'required',
        'GTComPrv' => 'required|exists:province,sigla',
        'GTComCod' => 'required|',
    );

}
