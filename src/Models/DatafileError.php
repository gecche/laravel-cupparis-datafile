<?php

namespace Gecche\Cupparis\Datafile\Models;

use Gecche\Breeze\Breeze;

class DatafileError extends Breeze {

	protected $table = 'datafile_error';

    protected $guarded = ['id'];

    public static $relationsData = [];

    public $timestamps = false;

    public function datafile_table()
    {
        return $this->morphTo();
    }


}
