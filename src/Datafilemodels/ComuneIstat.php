<?php

namespace Cupparis\App\Datafilemodels;

class ComuneIstat extends \Cupparis\Datafile\ArdentDatafile {
	protected $table = 'csv_comuni';
	//protected $fillable = array('datafile_id','row','GtComIstat','GtComDes','GtComPrv','GtComCod');


    public static $rules = array(
        'GTComIstat' => 'required|numeric', //|unique_datafile:csv_comuni,GTComIstat',
        'GTComDes' => 'required',
        'GTComPrv' => 'required|exists:province,sigla',
        'GTComCod' => 'required|',
    );

    /*
	protected function _realSave($datafile = array()) {

		foreach ($datafile as $valori) {
			//if (Arr::get($valori,'ok',false)) {


			$rec = $this->model->where('id','=',$valori->{'GtComIstat'})->get();
			if (count($rec) > 0)
				$rec = $rec[0];
			else
				$rec = new $this->model;

			//$rec->istat = $valori->{'GtComIstat'};
			$rec->nome = $valori->{'GtComDes'};
			//
			$provincia = Provincia::where('sigla',"=",$valori->{'GtComPrv'})->get()->first();

			$rec->provincia = $valori->{'GtComPrv'};
			$rec->catastale = $valori->{'GtComCod'};
			$rec->save();

			//$this->model->save();
			//}
		}
		return true;
	}
*/
}
