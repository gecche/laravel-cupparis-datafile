<?php namespace Cupparis\Datafile;

use Cupparis\Datafile\Facades\Datafile;
use Cupparis\App\Queue\MainQueue;

use Illuminate\Support\Facades\Config;
use Exception;
use Illuminate\Support\Facades\Log;

class DatafileQueue extends MainQueue{


    protected $datafileproviders_namespace;
    protected $datafilemodels_namespace;





    public function __construct()
    {
        parent::__construct();
        $this->datafilemodels_namespace = Config::get('app.datafilemodels_namespace') . "\\";
        $this->datafileproviders_namespace = Config::get('app.datafileproviders_namespace') . "\\";
    }

	
	public function load($job, $data) {
		$this->jobStart ($job,$data, 'datafile_load' );
		try {
			$temp_dir = storage_temp_path ();
//			echo $temp_dir;
			if (! is_dir ( $temp_dir )) {
				mkdir ( $temp_dir );
			}

            $this->_validateData("datafile_load");

			Log::info('Input data: '. implode(';',$this->data));

			$filename = $temp_dir . "/" . $this->data['fileName'];

            $datafileProviderName = $this->datafileproviders_namespace . studly_case(array_get($this->data, 'csvProviderName'));
            $datafileProvider = new $datafileProviderName;

            Log::info('Datafile provider name: '. $datafileProviderName);

            $datafileProvider->formPost = $this->data;
            Datafile::setFormPost($this->data);
            Datafile::init($this->acQueue->getKey(),$datafileProvider,$filename,$this->acQueue->getKey());
            //Datafile::$user_id = $this->data['userId'];
            Datafile::beforeLoad();
			$initRow = 0;
			do {
                Datafile::beforeLoadPart($initRow);
				$nextInitRow = Datafile::loadPart($initRow);
                Datafile::afterLoadPart($initRow);
                $initRow = $nextInitRow;
			} while(!Datafile::isEof());
			Log::info('hereENDENF');
            Datafile::afterLoad();
            Log::info('hereENDENF2');
			$this->jobEnd ();
            Log::info('hereENDENF3');

        } catch (Exception $e) {
			$this->jobEnd(1,$e->getMessage() . " in " . $e->getFile() . " " . $e->getLine());
			throw $e;
		}
		
	}
	public function save($job, $data) {
		$this->jobStart ($job,$data, 'datafile_save' );

        try {

            $this->_validateData("datafile_save");

            $datafileProviderName = $this->datafileproviders_namespace . studly_case(array_get($this->data, 'csvProviderName'));

            $datafileProvider = new $datafileProviderName;
            $datafileProvider->formPost = $this->data;
            Datafile::setFormPost($this->data);
            Datafile::init($data['csv_load_id'], $datafileProvider, null, $this->acQueue->getKey());

            //Senza spezzarlo in parti
            Datafile::beforeSave();
            Datafile::save();
            Datafile::afterSave();
            $this->jobEnd ();
        } catch (Exception $e) {
            $this->jobEnd (1,$e->getMessage());

        }
	}
	
	private function _validateData($job_type) {
		
		if ($job_type == "datafile_load") {
			if (!array_get($this->data, 'fileName',false)) {
				throw new Exception("File datafile non definito!");
			}
		}
		if ($job_type == "datafile_save") {
		    if (!array_get($this->data, 'csv_load_id',false)) {
		        throw new Exception("Datafile id non definito!");
		    }
		}
		
		if (!array_get($this->data, 'csvProviderName',false)) {
			throw new Exception("Datafile provider name non definito!");
		}
		if (!array_get($this->data,"userId",false)) {
			throw new Exception("Utente non definito!");
		}
	}


}