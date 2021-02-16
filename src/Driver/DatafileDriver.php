<?php namespace Gecche\Cupparis\Datafile\Driver;

use Gecche\Cupparis\Datafile\Breeze\BreezeDatafileProvider;
use Illuminate\Support\Facades\Log;

abstract class DatafileDriver {

	protected $dataFile;
	protected $headerData = null;	//
	protected $chunkDataArray;	//
	protected $chunkLinesNumber;
	protected $nRows;

    protected $sheets = null;
    protected $sheetsToUse = null;

    protected $currentSheet;



    protected $standardFilePropertiesKeys = [
    	'checkHeadersCaseSensitive' => true,
    	'hasHeadersLine' => true,
		'headersLineNumber' => 0,
		'startingDataLine' => null,
		'endingDataLine' => null,
		'startingColumn' => 0,
		'endingColumn' => null,
        'skipEmptyLines' => false,  // Se la procedura salta le righe vuote che trova
        'stopAtEmptyLine' => false, // Se la procedura di importazione si ferma alla prima riga vuota incontrata
    ];

    protected $filePropertiesKeys = [];
    protected $fileProperties = [];

	protected $provider = null;

    /**
     * @return array
     */
    public function getFileProperties()
    {
        return $this->fileProperties;
    }

	public function __construct(BreezeDatafileProvider $provider) {		//Constructor
		$this->provider = $provider;
	}


    protected function calculateFilePropertiesArray($fileProperties) {
        $filePropertiesKeys = array_merge($this->standardFilePropertiesKeys,$this->filePropertiesKeys);
        foreach ($filePropertiesKeys as $filePropertyKey => $filePropertyValue) {
            if (!array_key_exists($filePropertyKey,$fileProperties)) {
                $fileProperties[$filePropertyKey] = $filePropertyValue;
            }
        }
        $this->fileProperties = $fileProperties;
    }

	public function manageFileProperties($fileProperties = null) {

        if (is_null($fileProperties)) {
            $fileProperties = $this->provider->getFileProperties();
        }

        $this->nRows = null;

        $this->calculateFilePropertiesArray($fileProperties);
//        Log::info('FILE PROPERTIES: '.print_r($this->fileProperties,true));
    	$this->calculateHeadersAndBoundaries();
	}


    protected function setSheetsToUse() {
        $this->sheetsToUse = $this->provider->getSheetsToUse();
    }

    protected function calculateHeadersAndBoundaries() {

    	//In pratica se c'è la riga di headers nel file la calcolo con il resolveHeaders
		//altrimenti la prendo dal provider... una riga di headers in qualche modo ci deve essere
        if (!$this->dataFile) {
            return;
        }

//        Log::info("File HEADERDATA: " .print_r($this->headerData,true));
//        Log::info("File PROPERTIES: " .print_r($this->fileProperties,true));

        if ($this->hasHeadersLine) {
            $this->resolveHeaders();
        } else {
        	$this->headerData = $this->provider->getHeaders();
		}


        if (is_null($this->startingDataLine)) {
            if ($this->hasHeadersLine) {
                $this->startingDataLine = $this->headersLineNumber + 1;
            } else {
                $this->startingDataLine = 0;
            }
        }
    }

	/**
	 * @return mixed
	 */
	public function getDataFile()
	{
		return $this->dataFile;
	}

	/**
	 * @param mixed $dataFile
	 */
	public function setDataFile($dataFile)
	{
		$this->dataFile = $dataFile;
        $this->setSheetsToUse();
    }



	abstract public function resolveHeaders();

	abstract public function readDatafile($fromLine=0,$toLine=0);

    abstract public function countRows();



    public function checkHeaders($headers) {

    	//non ci sono headers nel file
    	if (is_null($this->hasHeadersLine)) {
    		return true;
		}
//        Log::info('HEADERS');
//		Log::info(print_r($headers,true));
//        Log::info('HEADERDATA FROM FILE');
//        Log::info(print_r($this->HeaderData,true));

		if ($this->checkHeadersCaseSensitive) {
            if ($this->headerData == $headers)
                return true;
            return false;
        }


        $headersLower = array_map('strtolower',$this->headerData);
        $headerDataLower = array_map('strtolower',$headers);

        if ($headerDataLower == $headersLower)
            return true;
        return false;

    }

    public function getHeaders() {
        return $this->headerData;
    }

    function __get($name)
    {
    	return $this->fileProperties[$name];
    }

    function __set($name, $value)
    {
        $this->fileProperties[$name] = $value;
    }

    public function getSheetsNames() {
        return [-1];
    }

    public function setCurrentSheet($sheetName = null,$fileProperties = null) {
        $this->currentSheet = $sheetName;
        $this->manageFileProperties($fileProperties);
    }

    public function getCurrentSheet() {
        return $this->currentSheet;
    }

}
?>
