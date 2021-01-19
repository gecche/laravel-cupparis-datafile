<?php

namespace Gecche\Cupparis\Datafile\Models;

use Gecche\Breeze\Breeze;

class Datafile extends Breeze {

	protected $table = 'datafiles';

	protected $guarded = ['id'];

    public static $relationsData = [];

    public $timestamps = true;
    public $ownerships = true;

}
