<?php namespace Gecche\Cupparis\Datafile;

use Gecche\Cupparis\Datafile\Facades\Datafile;
use Gecche\Cupparis\Queue\Queues\MainQueue;

use Illuminate\Support\Arr;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class DatafileQueue extends MainQueue {

    public function load($job, $data) {
		$this->jobStart ($job,$data, 'datafile_load' );
		try {
            $this->validateData("datafile_load");

            $filename = Arr::get($this->data,'fileName');

            if (Arr::get($this->data,'fileInTempFolder',true)) {
                $filename = $this->filenameToTempFolder($filename,Arr::get($data,'userId'));
            }

            $datafileProviderName = Arr::get($this->data, 'datafileProviderName');
            $datafileProviderName = $this->resolveProviderName($datafileProviderName);
            $datafileProvider = new $datafileProviderName;

//            Log::info('Datafile provider name: '. $datafileProviderName);

            $datafileProvider->formPost = $this->data;
            Datafile::setFormPost($this->data);
            Datafile::init($this->acQueue->getKey(),$datafileProvider,$filename,$this->acQueue->getKey());
            //Datafile::$user_id = $this->data['userId'];
            Datafile::beforeLoad();
			$initRow = 1;
			do {
                Datafile::beforeLoadPart($initRow);
				$nextInitRow = Datafile::loadPart($initRow);
                Datafile::afterLoadPart($initRow);
                $initRow = $nextInitRow;
			} while(!Datafile::isEof());
//			Log::info('hereENDENF');
            Datafile::afterLoad();
//            Log::info('hereENDENF2');
			$this->jobEnd ();
//            Log::info('hereENDENF3');

        } catch (Exception $e) {
			$this->jobEnd(1,$e->getMessage() . " in " . $e->getFile() . " " . $e->getLine());
			throw $e;
		}
		
	}
	public function save($job, $data) {
		$this->jobStart ($job,$data, 'datafile_save' );

        try {

            $this->validateData("datafile_save");

            $datafileProviderName = Arr::get($this->data, 'datafileProviderName');
            $datafileProviderName = $this->resolveProviderName($datafileProviderName);

            $datafileProvider = new $datafileProviderName;
            $datafileProvider->formPost = $this->data;
            Datafile::setFormPost($this->data);
            Datafile::init($data['datafile_load_id'], $datafileProvider, null, $this->acQueue->getKey());

            //Senza spezzarlo in parti
            Datafile::beforeSave();
            Datafile::save();
            Datafile::afterSave();
            $this->jobEnd ();
        } catch (Exception $e) {
            $this->jobEnd (1,$e->getMessage());

        }
	}
	
	protected function validateData($job_type) {
		
		if ($job_type == "datafile_load") {
			if (!Arr::get($this->data, 'fileName',false)) {
				throw new Exception("File datafile non definito!");
			}
		}
		if ($job_type == "datafile_save") {
		    if (!Arr::get($this->data, 'datafile_load_id',false)) {
		        throw new Exception("Datafile id non definito!");
		    }
		}
		
		if (!Arr::get($this->data, 'datafileProviderName',false)) {
			throw new Exception("Datafile provider name non definito!");
		}
		if (!Arr::get($this->data,"userId",false)) {
			throw new Exception("Utente non definito!");
		}
	}


	protected function filenameToTempFolder($filename,$userId) {
        Auth::loginUsingId($userId);
        $temp_dir = storage_temp_path();
//			echo $temp_dir;
        if (! is_dir ( $temp_dir )) {
            mkdir ( $temp_dir );
        }
//			Log::info('Input data: '. implode(';',$this->data));

        return rtrim($temp_dir,"/") . "/" . $filename;

    }

    protected function resolveProviderName($datafileProviderName) {
        $providerInConfig = Arr::get(Config::get('cupparis-datafile.providers',[]),$datafileProviderName);
        return $providerInConfig ?: $datafileProviderName;
    }
}
