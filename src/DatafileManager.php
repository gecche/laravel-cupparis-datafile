<?php


namespace Gecche\Cupparis\Datafile;

/*
 * TODO: 1 - aggiungere gli extrafields 2 - eventualmente migliorare i parmaetri dei checks dando la possibilta' di passare l'intero datafile ad esempio con una stringa apposita tipo :datafile 3 - Eventualmente poter decidere l'ordine di checks e transforms
 */


use Gecche\Cupparis\Queue\Events\JobProgress;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Arr;

use Illuminate\Support\Facades\DB;
use Exception;

class DatafileManager
{
    /*
     * array del tipo di datafile, ha la seguente forma:
     * array( 'headers' => array( 'header1' => array( 'datatype' => 'string|int|data...', (default string)
     *          'checks' => array( 'checkCallback1' => array(params => paramsArray,type => error|alert), ...
     *          'checkCallbackN' => array(params => paramsArray,type => error|alert), ), (deafult array())
     *          'transforms' => array( 'transformCallback1' => array(params), ... 'transformCallbackN' => array(params), ),
     *          (default array()) 'blocking' => true|false (default false) ) ...
     * 'headerN' => array( 'datatype' => 'string|int|data...', (default string) 'checks' =>
     * array( 'checkCallback1' => array(params), ... 'checkCallbackN' => array(params), ),
     * (deafult array()) 'transforms' => array( 'transformCallback1' => array(params), ...
     * 'transformCallbackN' => array(params), ), (default array()) )
     * 'peremesso' => 'permesso_string' (default 'datafile_upload') 'blocking' => true|false (default false) ) )
     * I chechCallbacks e transformCallbacks sono dei nomi di funzioni di questo modello (o sottoclassi) dichiarati come protected e
     * con il nome del callback preceduto da _check_ o _transform_ e che accettano i parametri specificati
     * I checkCallbacks hanno anche un campo che specifica se si tratta di errore o di alert I checks servono per verificare se i dati del
     * campo corrispondono ai requisiti richiesti I transforms trasformano i dati in qualcos'altro (es: formato della data da gg/mm/yyyy a yyyy-mm-gg)
     * Vengono eseguiti prima tutti i checks e poi tutti i transforms (nell'ordine specificato dall'array)
     * Blocking invece definisce se un errore nei check di una riga corrisponde al blocco dell'upload datafile o se si puo' andare avanti
     * saltando quella riga permesso e se il
     */
    public $datafileProvider = null;

    public $filename = null;
    protected $_eof = false;
    public $datafile_id = null;
    public $job_id = null;
    public $user_id = 0;

    // protected static $_datafile_max_size = max_size;
    protected $inputEncoding = 'UTF-8';
    protected $outputEncoding = 'UTF-8';

    protected $_formPost = array(); // valori in post nella form dell'oggetto

    protected $events = null;



    public function __construct(Dispatcher $dispatcher)
    {
        $this->events = $dispatcher;
    }

    /**
     * @return null
     */
    public function getDatafileProvider()
    {
        return $this->datafileProvider;
    }

    /**
     * @param null $datafileProvider
     */
    public function setDatafileProvider($datafileProvider)
    {
        $this->datafileProvider = $datafileProvider;
        $this->datafileProvider->setDatafileId($this->datafile_id);
    }

    /**
     * @return null
     */
    public function getDatafileId()
    {
        return $this->datafile_id;
    }

    /**
     * @param null $datafile_id
     */
    public function setDatafileId($datafile_id)
    {
        $this->datafile_id = $datafile_id;
    }


    /**
     * @return null
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @param null $filename
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
        $this->setEof(false);
        $this->datafileProvider->setFilename($this->filename);
    }


    /**
     * @return boolean
     */
    public function isEof()
    {
        if ($this->_eof) {
//            Log::info('EOFTRUE');
        } else {
//            Log::info('EOFFALSE');
        }
        return $this->_eof;
    }

    /**
     * @param boolean $eof
     */
    public function setEof($eof)
    {
        $this->_eof = $eof;
    }

    /**
     * @return null
     */
    public function getJobId()
    {
        return $this->job_id;
    }

    /**
     * @param null $job_id
     */
    public function setJobId($job_id)
    {
        $this->job_id = $job_id;
    }

    public function init($datafile_id, DatafileProviderInterface $datafileProvider, $filename = null, $job_id = null)
    {

        $this->setJobId($job_id);
        $this->setDatafileId($datafile_id);
        $this->setDatafileProvider($datafileProvider);

//        Log::info('SET fILENAME INIT: ' . $filename);
        if ($filename) {
            $this->setFilename($filename);
        }
    }

    public function getNumRows()
    {
        return $this->datafileProvider->getNumRows();
    }


    public function loadPart($initRow = 1)
    {

        //Il metodo direttamente manda le eccezioni.
        $this->datafileProvider->checkHeaders();

        $loadPartReturn = $this->datafileProvider->loadPart($initRow);

        if (!is_array($loadPartReturn))
            throw new Exception ("load error");

        $this->setEof($loadPartReturn['eof']);

        $datafile = $loadPartReturn['data'];
        $loadedRows = count($datafile);
//        Log::info("RORAA: ".print_r($datafile,true));
        $nextLine = $loadPartReturn['nextLine'];

        for ($i = 0; $i < $loadedRows; $i++) {
//            Log::info("DATALINELOAD: " . $i);

            $row = $datafile[$i];
            $realIndex = $this->setRealIndex($i, $initRow, $row);
            if ($this->inputEncoding != $this->outputEncoding) {
                foreach ($row as $fieldKey => $fieldValue) {
                    $row[$fieldKey] = iconv($this->inputEncoding, $this->outputEncoding . '//IGNORE', $fieldValue);
                }
            }

            $this->datafileProvider->saveDatafileRow($row, $realIndex);

        }

        return $nextLine;
    }

    protected function setRealIndex($relativeChunkIndex, $initChunkIndex, $row)
    {
        return $relativeChunkIndex + $initChunkIndex + Arr::get($row, 'shiftrow', 0);
    }

    public function beforeLoad()
    {
        $this->datafileProvider->beforeLoad();
    }

    public function afterLoad()
    {
        $this->datafileProvider->afterLoad();
    }

    public function beforeLoadPart()
    {
        $this->datafileProvider->beforeLoadPart();
    }

    public function afterLoadPart($initRow)
    {
        $this->datafileProvider->afterLoadPart();

        $rows = $this->datafileProvider->getDatafileNumRows();
        $this->fireProgress($initRow, $rows);
    }

    public function beforeSave()
    {
        $this->datafileProvider->beforeSave();
    }

    public function afterSave()
    {
        $this->datafileProvider->afterSave();
    }

    public function save()
    {


        $totalRows = $this->datafileProvider->countRows();
        $block = 250;
        $index = 0;
        $firstRow = $this->datafileProvider->getFirstRow();

        echo 'save : begin ' . $firstRow . " total rows " . $totalRows . "\n";

//        Log::info("SAVEROWS: " . $totalRows . '-' . $index . '-' . $firstRow);
        //TODO: cercare un errore se bloccante

        DB::beginTransaction();
        //TODO: transazione
        try {
            while ($index < $totalRows) {
//                echo $index . "\n";
                $this->datafileProvider->saveRow($firstRow + $index);
                if ($index % $block == 0) {
                    $this->fireProgress($firstRow + $index, $totalRows);
                    echo "row " . ($firstRow + $index) . "\n";
                }
                $index++;
            }
        } catch (Exception $e) {
            //TODO: rollback
            echo $e->getMessage();
            DB::rollback();
            throw $e;
        }
        DB::commit();

    }

    public function fireProgress($index, $rows)
    {
        if (!$this->job_id)
            return;
        $progress = floor(($index / $rows) * 100);
        if ($progress > 100) {
            $progress = 100;
        }

//        Log::info('JOBPROGRESSONFIRE: '.$this->job_id);
        if (isset($this->events)) {
            $this->events->dispatch(new JobProgress($this->job_id, $progress));
        }

    }

    public function setFormPost($form)
    {
        $this->_formPost = $form;
    }

    public function getFormPost()
    {
        return $this->_formPost;
    }


    /*
     * FUNZIONI CHE HANNO SENSO PER I PROVIDER DI TIPO EXCEL
     */

    public function getSheetsNames() {
        return $this->datafileProvider->getSheetsNames();
    }

    public function setCurrentSheet($sheetName) {
        return $this->datafileProvider->setCurrentSheet($sheetName);
    }

    public function getCurrentSheet() {
        return $this->datafileProvider->getCurrentSheet();
    }

}

// End Datafile Core Model
