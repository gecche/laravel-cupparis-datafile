<?php

namespace Cupparis\App\Datafilemodels;

class Error extends \Cupparis\Ardent\Ardent {
	protected $table = 'datafile_error';

	protected $fillable = array('datafile_id','datafile_table_type','datafile_table_id','field_name','error_name','row','type','value','template','param');
    public static $relationsData = array(
        //'address' => array(self::HAS_ONE, 'Address'),
        //'orders'  => array(self::HAS_MANY, 'Order'),
        //'errors' => array(self::MORPH_TO, 'App\Datafilemodels\ComuneIstat', 'name' => 'datafile_table','id' => "datafile_table_id",'type' => 'datafile_table_type'),
    );
    
    public $timestamps = false;
    
    public function datafile_table()
    {
        return $this->morphTo();
    }
    
    
}
