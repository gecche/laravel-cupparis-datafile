<?php namespace Gecche\Cupparis\Datafile;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class DatafileHandler {


	protected $driver = null;
	protected $provider = null;



	protected $filetype = 'csv';


	// ritorna una media di grandezza delle righe in byte
	public function getRowByteSize() {
		$fp = fopen ($this->DataFile,"r");

		if ($this->filetype == 'fixed_text') {
			$this->resolveHeaders($fp);
            fclose($fp);
			return array_sum($this->fixedTextArray);
		}

		$sizeb = 0;
		while ( ($DataLine = fgets ($fp, $this->maxLine)) && ($this->Items_Count < 30) ) {
			//$serializedFoo = serialize($DataLine);
			if (function_exists('mb_strlen')) {
				$sizeb += mb_strlen($DataLine, '8bit');
			} else {
				$sizeb += strlen($DataLine);
			}
			//echo $serializedFoo . " --- " .strlen($serializedFoo);
			$this->Items_Count++;
			//echo $sizeb . "<br>";
		}
		echo "Sizebb" . $sizeb;
        fclose($fp);
		return $this->Items_Count?ceil($sizeb / $this->Items_Count):1;
	}
// Standard User functions
	public function __construct($driverType,$provider) {		//Constructor
		$this->provider = $provider;
		$this->driver = $this->resolveDriver($driverType);
	}

	protected function resolveDriver($driverType) {

		$prefixClassName = Str::studly($driverType);

		$className = "Gecche\\Cupparis\\Datafile\\Driver\\" . $prefixClassName . 'Driver';

		return new $className($this->provider);

	}

    function __call($name, $arguments)
    {
//    	Log::info("Method: ".$name." - Driver: ".get_class($this->driver));
        return call_user_func_array(array($this->driver, $name), $arguments);
    }

}
?>
