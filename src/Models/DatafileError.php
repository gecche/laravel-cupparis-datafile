<?php

namespace Gecche\Cupparis\Datafile\Models;

use Gecche\Breeze\Breeze;

class DatafileError extends Breeze {

	protected $table = 'datafile_error';

	protected $fillable = ['datafile_id','datafile_table_type','datafile_table_id','field_name','error_name','row','type','value','template','param'];

    public static $relationsData = [];
    
    public $timestamps = false;
    
    public function datafile_table()
    {
        return $this->morphTo();
    }
    
    
}
