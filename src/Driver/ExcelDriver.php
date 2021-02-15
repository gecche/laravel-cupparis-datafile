<?php namespace Gecche\Cupparis\Datafile\Driver;


use Illuminate\Support\Facades\Log;
use Gecche\Cupparis\Datafile\Driver\ExcelFilter\ChunksReadFilter;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;


class ExcelDriver extends DatafileDriver
{

    use ExcelDriverTrait;

    protected $standardFilePropertiesKeys = [
        'checkHeadersCaseSensitive' => true,
        'hasHeadersLine' => true,
        'headersLineNumber' => 1,
        'startingDataLine' => null,
        'endingDataLine' => null,
        'startingColumn' => 'A',
        'endingColumn' => null,
        'skipEmptyLines' => false,  // Se la procedura salta le righe vuote che trova
        'stopAtEmptyLine' => false, // Se la procedura di importazione si ferma alla prima riga vuota incontrata
    ];


    protected $filePropertiesKeys = [
        'sheetName' => 0,
        'startingColumnIndex' => null,
        'endingColumnIndex' => null,
    ];

    protected $maxColIndex;
    protected $minColIndex;
    protected $maxCol;
    protected $minCol;
    protected $maxRow;
    protected $minRow;
    protected $minDataRow;


    protected $objectReader;

    protected function setObjectReader()
    {
        if (!$this->dataFile) {
            return;
        }

        $inputFileType = IOFactory::identify($this->dataFile);
        $this->objectReader = IOFactory::createReader($inputFileType);


        try {
            $this->setSheets();
            //Carico il foglio indicato nella cofnigurazione
            //Se non presente carico il foglio 0;
            $this->setCurrentSheet($this->sheetName);
        } catch (\Exception $e) {
            $msg = 'Problemi ad aprire il file: non sembra un file salvato correttamente come file excel. Provare ad aprirlo con Excel e salvarlo nuovamente.<br/>';
            $msg .= $e->getMessage();
            throw new \Exception($msg);
        }

        $this->objectReader->setLoadSheetsOnly([$this->currentSheet]);

    }


    protected function manageFileProperties($fileProperties = null)
    {
        $this->nRows = null;
        $this->calculateFilePropertiesArray($fileProperties);
        $this->setObjectReader();
        $this->calculateHeadersAndBoundaries();
    }


}

?>
