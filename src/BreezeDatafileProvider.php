<?php

namespace Gecche\Cupparis\Datafile;

use \Cupparis\Ardent\Ardent;
use Gecche\Breeze\Breeze;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Auth;

class BreezeDatafileProvider implements DatafileProviderInterface
{
    /*
     * array del tipo di datafile, ha la seguente forma: array( 'headers' => array( 'header1' => array( 'datatype' => 'string|int|data...', (default string) 'checks' => array( 'checkCallback1' => array(params => paramsArray,type => error|alert), ... 'checkCallbackN' => array(params => paramsArray,type => error|alert), ), (deafult array()) 'transforms' => array( 'transformCallback1' => array(params), ... 'transformCallbackN' => array(params), ), (default array()) 'blocking' => true|false (default false) ) ... 'headerN' => array( 'datatype' => 'string|int|data...', (default string) 'checks' => array( 'checkCallback1' => array(params), ... 'checkCallbackN' => array(params), ), (deafult array()) 'transforms' => array( 'transformCallback1' => array(params), ... 'transformCallbackN' => array(params), ), (default array()) ) 'peremesso' => 'permesso_string' (default 'datafile_upload') 'blocking' => true|false (default false) ) ) I chechCallbacks e transformCallbacks sono dei nomi di funzioni di questo modello (o sottoclassi) dichiarati come protected e con il nome del callback preceduto da _check_ o _transform_ e che accettano i parametri specificati I checkCallbacks hanno anche un campo che specifica se si tratta di errore o di alert I checks servono per verificare se i dati del campo corrispondono ai requisiti richiesti I transforms trasformano i dati in qualcos'altro (es: formato della data da gg/mm/yyyy a yyyy-mm-gg) Vengono eseguiti prima tutti i checks e poi tutti i transforms (nell'ordine specificato dall'array) Blocking invece definisce se un errore nei check di una riga corrisponde al blocco dell'upload datafile o se si puo' andare avanti saltando quella riga permesso e se il
     */


    /**
     * @var null|string
     * Classe del modello breeze datafile
     */
    protected $modelDatafileName = null;
    /**
     * @var null|string
     * Classe del modello breeze con cui verranno salvati i dati
     */
    protected $modelTargetName = null;

    protected $config = null;

    public $datafile_id = null;
    protected $filename = null;

    protected $handler = null;

    protected $fileProperties = [];
    protected $filetype = 'csv'; //csv, fixed_text, excel

    protected $chunkRows = 100;

    protected $skipFirstLine = false;
    protected $skipEmptyLines = true;

    protected $doubleDatafileErrorNames = ['Uniquedatafile'];

    /*
     * HEADERS array header => datatype
     */
    public $headers = array();

    protected $inputEncoding = 'UTF-8';
    protected $outputEncoding = 'UTF-8';

    public $formPost = [];
    protected $excludeFromFormat = ['id', 'row', 'datafile_id'];


    public function __construct()
    {

        $this->config = Config::get('datafile',[]);


        $reflector = new \ReflectionClass($this->modelDatafileName);
        if (!$reflector->isSubclassOf(BreezeDatafile::class)) {
            throw new \ReflectionException('Invalid class for model datafile');
        };
        $reflector = new \ReflectionClass($this->modelTargetName);
        if (!$reflector->isSubclassOf(Breeze::class)) {
            throw new \ReflectionException('Invalid class for model target');
        };

        $this->datafileModelErrorName = ($this->modelDatafileName)::getErrorsModelName();

        if (!$this->headers) {
            $this->createHeaders();
        }

        $this->setHandler($this->filetype);

    }

    public function setHandler($driverType)
    {
        $this->handler = new DatafileHandler($driverType, $this);
    }

    /**
     * @return string
     */
    public function getFiletype()
    {
        return $this->filetype;
    }

    /**
     * @return null
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @return array
     */
    public function getFileProperties()
    {
        return $this->fileProperties;
    }

    /**
     * @param array $fileProperties
     */
    public function setFileProperties($fileProperties)
    {
        $this->fileProperties = $fileProperties;
    }

    /**
     * @param null $filename
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
        $this->handler->setDataFile($filename);
    }


    public function createHeaders()
    {
        $modelDatafile = new $this->modelDatafileName;
        $this->setHeaders($modelDatafile->getDefaultHeaders());
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
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param null $headers
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }

    /**
     * @return null
     */
    public function getModelDatafileName()
    {
        return $this->modelDatafileName;
    }

    /**
     * @param null $modelDatafileName
     */
    public function setModelDatafileName($modelDatafileName)
    {
        $this->modelDatafileName = $modelDatafileName;
    }

    /**
     * @return null
     */
    public function getModelTargetName()
    {
        return $this->modelTargetName;
    }

    /**
     * @param null $modelTargetName
     */
    public function setModelTargetName($modelTargetName)
    {
        $this->modelTargetName = $modelTargetName;
    }


    public function isRowEmpty($row)
    {
        $empty = true;
        foreach ($row as $value) {
            if (strlen($value) > 0) {
                $empty = false;
                break;
            }
        }
        return $empty;
    }

    public function saveDatafileRow($row, $index, $id = null)
    {

        if ($index == 0 && $this->skipFirstLine) {
            return;
        }
        if ($this->skipEmptyLines && $this->isRowEmpty($row)) {
            return;
        }

        $modelDatafileName = $this->modelDatafileName;

        if ($id) {
            $model = $modelDatafileName::find($id);
        } else {
            $model = new $modelDatafileName;
        }

        $row = $this->formatDatafileRow($row);
//        Log::info("Index: " . $index);
        $model->fill($row);
        $model->setDatafileIdValue($this->getDatafileId());
        $model->setRowIndexValue($index);

        $model->saveDatafile();
        $validator = $model->getValidator();
        //echo "$modelDatafileName validatore = $validator\n";
//        Log::info("DATAFILE ERRORS: ".$index);
        $this->setDatafileErrors($index, $model, $validator);

    }

    public function saveRow($index)
    {

        $modelDatafileName = $this->modelDatafileName;
        $modelDatafile = new $modelDatafileName;

        $modelDatafile = $modelDatafileName::where($modelDatafile->getDatafileIdField(), '=', $this->getDatafileId())
            ->where($modelDatafile->getRowIndexField(), '=', $index)->first();

        if (!$modelDatafile || !$modelDatafile->getKey()) {
            throw new Exception('datafile.row-not-found');
        }

        if ($modelDatafile->errors()->count() > 0)
            return false;


        //Agganciare riga del modello target
        $modelTarget = $this->associateRow($modelDatafile);

        //trasformazione dei valori eventualmente
        $values = $this->formatRow($modelDatafile);


//        Log::info('VALUES: '.print_r($values,true));
        $modelTarget->fill($values);
//        Log::info('DIRTIES: '.print_r($modelTarget->getDirty(),true));
        $modelTarget->save();

        $this->finalizeRow($values, $modelDatafile, $modelTarget);

        $modelDatafile->delete();

        return true;
    }

    public function associateRow(ArdentDatafile $modelDatafile)
    {
        return new $this->modelTargetName;
    }

    public function formatDatafileRow($row)
    {
        return $row;
    }

    public function formatRow(ArdentDatafile $modelDatafile)
    {
        $values = $modelDatafile->toArray();
        foreach ($this->excludeFromFormat as $field) {
            if (array_key_exists($field, $values)) {
                unset($values[$field]);
            }
        }
        return $values;

    }

    public function finalizeRow($values, $modelDatafile, $modelTarget)
    {
        return true;
    }

    public function countRows()
    {
        $modelDatafileName = $this->modelDatafileName;
        $modelDatafile = new $modelDatafileName;
        return $modelDatafileName::where($modelDatafile->getDatafileIdField(), '=', $this->getDatafileId())->count();

    }

    /**
     * ritorna il primo valore della row, utile nel caso di recovery per far partire il salvataggio
     * dalla prima riga utile e non da zero.
     */
    public function getFirstRow()
    {
        $modelDatafileName = $this->modelDatafileName;
        $modelDatafile = new $modelDatafileName;
        $entry = $modelDatafileName::where($modelDatafile->getDatafileIdField(), '=', $this->getDatafileId())->orderBy($modelDatafile->getRowIndexField())->first()->toArray();
        return array_get($entry, $modelDatafile->getRowIndexField(), 0);
    }

    /*
     * Funzione per far el'update di una riga una volta corretti gli errori
     */
    public function fixErrorDatafileRow($row_values = array())
    {

        $datafileIdValue = array_get($row_values, 'datafile_id', -1);
        $datafileTableIdValue = array_get($row_values, 'datafile_table_id', -1);
        $index = array_get($row_values, 'row', -1);
        $fieldName = array_get($row_values, 'field_name', -1);

        $modelName = $this->modelDatafileName;
        $model = $modelName::find($datafileTableIdValue);


        $this->setDatafileId($datafileIdValue);

        $field = array_get($row_values, $fieldName, null);
        $model->fill([$fieldName => $field]);

        $model->setDatafileIdValue($this->getDatafileId());
        $model->setRowIndexValue($index);

        $model->saveDatafile();
        $validator = $model->getValidator();
        //echo "$modelDatafileName validatore = $validator\n";
        $this->setDatafileErrors($index, $model, $validator);


        $this->finalizeDatafileErrors();


    }

    public function updateDatafileRow($row_values = array())
    {

        $model = new $this->modelDatafileName;
        $datafileIdValue = $row_values[$model->getDatafileIdField()];
        $index = $row_values[$model->getRowIndexField()];

        $this->setDatafileId($datafileIdValue);

        $this->saveDatafileRow($row_values, $index, $row_values['id']);

        $this->finalizeDatafileErrors();


    }


    public function massiveUpdate($row_values = array())
    {

        $model = new $this->modelDatafileName;
//        $datafileIdValue = $row_values[$model->getDatafileIdField()];
        $rowValues = $row_values['values'];
        $fieldName = $row_values['field'];

//        $datafileIdField = $model->getDatafileIdField();
        $table = $model->getTable();
        $pkName = $model->getKeyName();

//        Log::info('MASSIVE: ');
//        Log::info($table . ' ' . $pkName . ' ' . $fieldName);
//        Log::info(print_r($rowValues, true));
        foreach ($rowValues as $pk => $value) {
            DB::table($table)
                ->where($pkName, $pk)
                ->update([$fieldName => intval($value)]);
        }


    }

    /**
     * esegue una rivalidazione delle righe nel db del jobId
     * @param $job_id
     */
    public function revalidate($job_id)
    {
        $this->setDatafileId($job_id);
        $this->finalizeDatafileErrors();
    }

    public function setDatafileErrors($index, $model, $validator)
    {
        if (!$validator) {
            throw new \Exception("ArdentDatafileProvider::setDatafileErrors   Attenzione VALIDATORE NULL ");
        }

        $datafileErrorName = $this->datafileModelErrorName;
        //CANCELLA errori gia' presenti assocaiti a quella riga
        $model->errors()->delete();

        $data = $validator->getData();
        $failedRules = $validator->failed();

//        Log::info('FAILED RULES');
//        Log::info(print_r($validator->getRules(),true));
//        Log::info(print_r($data,true));
//        Log::info(print_r($failedRules,true));

        foreach ($failedRules as $field_name => $rule) {
            foreach ($rule as $error_name => $ruleParameters) {

                $datafileError = new $datafileErrorName(array(
                    'datafile_id' => $this->getDatafileId(),
                    'field_name' => $field_name,
                    'error_name' => $error_name,
                    'type' => 'error', //per ora sono tutti error (poi ci si puo' mettere ad esempio warning, vedremo come)
                    'template' => 0, //per ora non ci sono templates, forse questo va a sparire
                    'param' => NULL, //questo sempre null, eventualmnete va aggiornato alla fine del primo caricamento delle righe
                    'value' => $data[$field_name],
                    'row' => $index,
                ));
                $model->errors()->save($datafileError);
            }

        }
    }

    public function loadPart($initRow = 0)
    {

        $endRow = $initRow + $this->chunkRows;
        $chunk = $this->handler->readDatafile($initRow, $endRow);
        //Log::info(print_r($chunk,true));
        return $chunk;
    }


    public function beforeLoadPart()
    {

    }

    public function afterLoadPart()
    {

    }

    public function beforeLoad()
    {

    }

    public function afterLoad()
    {
        $this->finalizeDatafileErrors();
    }

    public function beforeSave()
    {

    }

    public function afterSave()
    {

    }


    public function finalizeDatafileErrors()
    {
        $doubleDatafileErrorNames = $this->doubleDatafileErrorNames;


        $datafileErrorName = $this->datafileModelErrorName;
        $doubleDatafileErrors = $datafileErrorName::where('datafile_id', '=', $this->getDatafileId())
            ->whereIn('error_name', $doubleDatafileErrorNames)
            ->groupBy('error_name')
            ->groupBy('field_name')
            ->get();

        $modelDatafileName = $this->modelDatafileName;
        $model = new $modelDatafileName;

        foreach ($doubleDatafileErrors as $doubleDatafileError) {

            $errorName = $doubleDatafileError->error_name;
            $column = $doubleDatafileError->field_name;
            $columnValue = $doubleDatafileError->value;
            $methodName = 'doubleErrorModels' . $errorName;
            if (method_exists($this, $methodName)) {
                $models = $this->$methodName($doubleDatafileError);
            } else {
                $models = $modelDatafileName::where($column, '=', $columnValue)
                    ->where($model->getDatafileIdField(), '=', $this->getDatafileId())
                    ->get();
                $datafileErrorName::where('datafile_id', '=', $this->getDatafileId())
                    ->where('error_name', '=', $errorName)
                    ->where('field_name', '=', $column)
                    ->where('value', '=', $columnValue)
                    ->delete();
            }
            $modelRows = $models->lists('row')->all();

            if (count($models) > 1) {
                foreach ($models as $currModel) {
                    $datafile_table_id = $currModel->getKey();
                    $row = $currModel->getRowIndexValue();
                    $paramString = "Records: " . implode(',', $modelRows);

                    $datafileErrorName::create(array(
                        'datafile_table_type' => trim($modelDatafileName, "\\"),
                        'datafile_table_id' => $datafile_table_id,
                        'datafile_id' => $this->getDatafileId(),
                        'field_name' => $column,
                        'error_name' => $errorName,
                        'type' => 'error',
                        //per ora sono tutti error (poi ci si puo' mettere ad esempio warning, vedremo come)
                        'template' => 0,
                        //per ora non ci sono templates, forse questo va a sparire
                        'param' => $paramString,
                        //questo sempre null, eventualmnete va aggiornato alla fine del primo caricamento delle righe
                        'value' => $columnValue,
                        'row' => $row,
                    ));
                }
            }

        }
    }


    /*
     * VARIE RIFATTORIZZAZIONE
     */

    public function checkHeaders()
    {
        $check = $this->handler->checkHeaders($this->headers);
        if (!$check) {
            $modelHeaders = $this->headers;
            $fileHeaders = $this->handler->getHeaders();
            $fileHeaders = is_null($fileHeaders) ? [] : $fileHeaders;

            $not_found = [];
            $msg = "";
            foreach ($modelHeaders as $field) {
                if (in_array($field, $fileHeaders)) {
                    $index = array_search($field, $fileHeaders);
                    array_splice($fileHeaders, $index, 1);
                } else {
                    $not_found[] = $field;
                }
            }

            $msg .= "Colonne non trovate\n [" . implode("] [", $not_found) . "]\n\n";
            $msg .= "Campi extra\n [" . implode("] [", $fileHeaders) . "]\n\n";

            throw new Exception ("Intestazioni non corrette nel file:\n\n" . $msg);
        }
    }

    public function getDatafileNumRows()
    {
        return $this->handler->countRows();
    }


    public function getTemplateFile($path, $filename = null)
    {
        if (is_null($filename)) {
            $relativeName = substr($this->modelDatafileName, strlen($this->datafilemodels_namespace));
            $filename = 'template_' . $relativeName;
        }

        $fullFilename = $path . '/' . $filename;

        return $this->handler->writeHeaders($fullFilename);
    }

}

// End Datafile Core Model
