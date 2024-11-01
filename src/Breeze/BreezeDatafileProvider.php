<?php

namespace Gecche\Cupparis\Datafile\Breeze;

use Gecche\Breeze\Breeze;
use Gecche\Breeze\Contracts\BreezeInterface;
use Gecche\Cupparis\Datafile\Breeze\Contracts\BreezeDatafileInterface;
use Gecche\Cupparis\Datafile\Breeze\Contracts\DatafileBreezeInterface;
use Gecche\Cupparis\Datafile\DatafileHandler;
use Gecche\Cupparis\Datafile\DatafileProviderInterface;
use Gecche\Cupparis\Datafile\Facades\Datafile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Validation\Validator;

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

    /**
     * @var null|string
     * Classe del modello breeze dove verrano salvati eventuali errori delle righe
     */
    protected $datafileModelErrorName = null;
    /**
     * @var null|string
     * Classe del modello breeze che memorizza le importazioni (i datafile)
     */
    protected $datafileModelName = null;

    protected $datafile = null;

    protected $config = null;

    public $datafile_id = null;
    protected $filename = null;

    protected $handler = null;

    protected $fileProperties = [];
    protected $filetype = 'csv'; //csv, fixed_text, excel

    protected $sheetsToUse;

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
    protected $excludeFromFormat = ['id', 'row', 'datafile_id', 'datafile_sheet'];

    protected $stringRowIndexInDb = false;

    protected $currentSheet;

    protected $useTransactions = true;


    public function __construct()
    {

        $this->config = Config::get('datafile', []);


        $reflector = new \ReflectionClass($this->modelDatafileName);
        if (!$reflector->implementsInterface(DatafileBreezeInterface::class)) {
            throw new \ReflectionException('Invalid class for model datafile');
        };
        $reflector = new \ReflectionClass($this->modelTargetName);
        if (!$reflector->implementsInterface(BreezeInterface::class)) {
            throw new \ReflectionException('Invalid class for model target');
        };

        $this->datafileModelErrorName = ($this->modelDatafileName)::getErrorsModelName();
        $this->datafileModelName = ($this->modelDatafileName)::getDatafileModelName();

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
        $fileProperties = $this->fileProperties;
        if (!array_key_exists('inputEncoding',$fileProperties)) {
            $fileProperties['inputEncoding'] = $this->inputEncoding;
        }
        return $fileProperties;
    }

    /**
     * @param array $fileProperties
     */
    public function setFileProperties($fileProperties)
    {
        $this->fileProperties = $fileProperties;
    }

    /**
     * @return mixed
     */
    public function getSheetsToUse()
    {
        return $this->sheetsToUse;
    }

    /**
     * @param mixed $sheetsToUse
     */
    public function setSheetsToUse($sheetsToUse): void
    {
        $this->sheetsToUse = $sheetsToUse;
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

    /**
     * @return string
     */
    public function getInputEncoding()
    {
        return $this->inputEncoding;
    }

    /**
     * @return bool
     */
    public function useTransactions(): bool
    {
        return $this->useTransactions;
    }

    /**
     * @param bool $useTransactions
     */
    public function setUseTransactions(bool $useTransactions): void
    {
        $this->useTransactions = $useTransactions;
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

    public function saveDatafileRow($row, $sheet, $index, $id = null)
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
//        Log::info("Sheet:: ".$sheet);
        $model->setDatafileSheetValue($sheet);
        $model->setRowIndexValue($index);

        $validator = $model->getDatafileValidator();
        $model->save();
        //echo "$modelDatafileName validatore = $validator\n";
//        Log::info("DATAFILE ERRORS: ".$index);
        $this->setDatafileErrors($sheet, $index, $model, $validator);

    }

    public function saveRow($sheet, $index)
    {

        $modelDatafileName = $this->modelDatafileName;
        $modelDatafile = new $modelDatafileName;

//        echo $modelDatafile . "\n";
//        echo $modelDatafile->getDatafileIdField() . "\n";
//        echo $this->getDatafileId() . "\n";
//        echo $modelDatafile->getRowIndexField() . "\n";
//        echo $index . "\n";

        $modelDatafile = $modelDatafileName::where($modelDatafile->getDatafileIdField(), '=', $this->getDatafileId())
            ->where($modelDatafile->getDatafileSheetField(), $sheet)
            ->where($modelDatafile->getRowIndexField(), '=', $index)->first();

        if (!$modelDatafile || !$modelDatafile->getKey()) {
            throw new Exception('cupparis-datafile.row-not-found' . " - Row: "
                . $index . " - Sheet: " . $this->getCurrentSheet() . " - Datafile Id: " . $this->getDatafileId());
        }

        if ($modelDatafile->errors()->count() > 0)
            return false;


        //Agganciare riga del modello target
        $modelTarget = $this->associateRow($modelDatafile);

        //trasformazione dei valori eventualmente
        $values = $this->formatRow($modelDatafile, $modelTarget);


//        Log::info('VALUES: '.print_r($values,true));
        $modelTarget->fill($values);
//        Log::info('DIRTIES: '.print_r($modelTarget->getDirty(),true));
        $modelTarget->save();

        $this->finalizeRow($values, $modelDatafile, $modelTarget);

        $modelDatafile->delete();

        return true;
    }

    public function associateRow(BreezeDatafileInterface $modelDatafile)
    {
        return new $this->modelTargetName;
    }

    public function formatDatafileRow($row)
    {
        return $row;
    }

    public function formatRow(BreezeDatafileInterface $modelDatafile, Model $modelTarget = null)
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
        return $modelDatafileName::where($modelDatafile->getDatafileIdField(), '=', $this->getDatafileId())
                ->where($modelDatafile->getDatafileSheetField(),$this->getCurrentSheet())
                ->count();

    }

    /**
     * ritorna il primo valore della row, utile nel caso di recovery per far partire il salvataggio
     * dalla prima riga utile e non da zero.
     */
    public function getFirstRow()
    {
        $modelDatafileName = $this->modelDatafileName;
        $modelDatafile = new $modelDatafileName;
        $entry = $modelDatafileName::where($modelDatafile->getDatafileIdField(), '=', $this->getDatafileId())
                    ->where($modelDatafile->getDatafileSheetField(),$this->getCurrentSheet());
        if ($this->stringRowIndexInDb) {
            $entry = $entry->orderByRaw('ABS('.$modelDatafile->getRowIndexField().')')->first();
        } else {
            $entry = $entry->orderBy($modelDatafile->getRowIndexField())->first();
        }
        $entry = $entry?$entry->toArray():[];
        return Arr::get($entry, $modelDatafile->getRowIndexField(), 0);
    }

    /*
     * Funzione per far el'update di una riga una volta corretti gli errori
     */
    public function fixErrorDatafileRow($row_values = array())
    {

        $datafileIdValue = Arr::get($row_values, 'datafile_id', -1);
        $datafileTableIdValue = Arr::get($row_values, 'datafile_table_id', -1);
        $index = Arr::get($row_values, 'row', -1);
        $fieldName = Arr::get($row_values, 'field_name', -1);

        $modelName = $this->modelDatafileName;
        $model = $modelName::find($datafileTableIdValue);


        $this->setDatafileId($datafileIdValue);

        $field = Arr::get($row_values, $fieldName, null);
        $model->fill([$fieldName => $field]);

        $model->setDatafileIdValue($this->getDatafileId());
        $model->setDatafileSheetValue($this->getCurrentSheet());
        $model->setRowIndexValue($index);

        $model->save();
        //echo "$modelDatafileName validatore = $validator\n";
        $this->setDatafileErrors($index, $model, $model->getValidator());


        $this->finalizeDatafileErrors();


    }

    public function updateDatafileRow($row_values = array())
    {

        $model = new $this->modelDatafileName;
        $datafileIdValue = $row_values[$model->getDatafileIdField()];
        $sheet = $row_values[$model->getDatafileSheetField()];
        $index = $row_values[$model->getRowIndexField()];

        $this->setDatafileId($datafileIdValue);

        $this->saveDatafileRow($row_values, $sheet, $index, $row_values['id']);

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

    public function setDatafileErrors($sheet, $index, $model, Validator $validator)
    {
        $datafileErrorName = $this->datafileModelErrorName;
        //CANCELLA errori gia' presenti assocaiti a quella riga
        $model->errors()->delete();

        if (!$validator->passes()) {

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
                        'datafile_sheet' => $sheet,
                    ));
                    $model->errors()->save($datafileError);
                }

            }
        }

    }

    public function loadPart($initRow = 1)
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

    public function getDatafile() {
        if (is_null($this->datafile)) {
            $this->setDatafile();
        }
        return $this->datafile;
    }

    protected function setDatafile() {
        $this->associateDatafile();
        if (!$this->datafile->getKey()) {
            $this->setDatafileData();
        }
    }

    public function beforeLoad()
    {
        return true;
    }

    protected function associateDatafile() {

        $datafile = ($this->datafileModelName)::where('datafile_id',$this->datafile_id)->first();
        if (!$datafile) {
            $datafile = new $this->datafileModelName;
        }

        $this->datafile = $datafile;
    }

    protected function setDatafileData() {

        $data = [
            'datafile_id' => $this->getDatafileId(),
            'datafile_type' => trim($this->modelDatafileName, "\\"),
            'datafile_sheet' => $this->getSheetsNames(),
        ];

        $this->datafile->fill($data);
        $this->datafile->save();

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
        $doubleDatafileErrors = $datafileErrorName::select(['error_name','field_name','value'])
            ->where('datafile_id', '=', $this->getDatafileId())
            ->whereIn('error_name', $doubleDatafileErrorNames)
            ->groupBy('error_name')
            ->groupBy('field_name')
            ->groupBy('value')
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
            $modelRows = $models->pluck('row')->all();

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
                        'datafile_sheet' => $this->getCurrentSheet(),
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

            $relativeName = Str::snake(substr($this->modelDatafileName,strrpos($this->modelDatafileName,"\\")+1));
            $filename = 'template_' . $relativeName;
        }

        $fullFilename = $path . '/' . $filename;

        return $this->handler->writeHeaders($fullFilename);
    }

    public function getSheetsNames($loaded = false) {
        if ($loaded) {
            return $this->getDatafile()->datafile_sheet;
        }
        return $this->handler->getSheetsNames();
    }

    public function setCurrentSheet($sheetName,$fileProperties = null,$loaded = false) {
        $this->currentSheet = $sheetName;
        if (!$loaded) {
            $this->handler->setCurrentSheet($sheetName,$fileProperties);
        }
    }

    public function getCurrentSheet($loaded = false) {
        return $this->currentSheet;
    }

}

// End Datafile Core Model
