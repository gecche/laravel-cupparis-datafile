<?php namespace Gecche\Cupparis\Datafile\Driver;


use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Gecche\Cupparis\Datafile\Driver\ExcelFilter\ChunksReadFilter;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;


class ExcelDriver extends DatafileDriver
{

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
        'calculateFormulas' => false, //nella range to array calola le formule
        'formatValues' => false, //nella range to array formatta i valori (date ecc...)
    ];

    protected $sheetsNames;

    protected $filePropertiesKeys = [
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

    protected $worksheet;

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
        } catch (\Exception $e) {
            $msg = 'Problemi ad aprire il file: non sembra un file salvato correttamente come file excel. Provare ad aprirlo con Excel e salvarlo nuovamente.<br/>';
            $msg .= $e->getMessage();
            throw new \Exception($msg);
        }


    }

    public function getObjectReader()
    {
        return $this->objectReader;
    }


    protected function calculateHeadersAndBoundaries()
    {

        if (!$this->dataFile) {
            return;
        }

        $this->startingcolumnIndex = Coordinate::columnIndexFromString($this->startingColumn);

        if ($this->hasHeadersLine) {
            $this->resolveHeaders();
        } else {
            $this->headerData = $this->provider->getHeaders();
        }

        if (is_null($this->startingDataLine)) {
            if ($this->hasHeadersLine) {
                $this->startingDataLine = $this->headersLineNumber + 1;
            } else {
                $this->startingDataLine = 1;
            }
        }

        if (!$this->endingColumn) {
            $this->endingColumn = Coordinate::stringFromColumnIndex($this->startingcolumnIndex + count($this->headerData) - 1);
        }
        $this->endingColumnIndex = Coordinate::columnIndexFromString($this->endingColumn);

        if (!$this->endingDataLine) {
            $this->endingDataLine = $this->worksheet->getHighestRow();
        }

    }


    public function resolveHeaders()
    {
        $this->headerData = [];
        if (!$this->hasHeadersLine) {
            return;
        }

        $endingColumn = $this->endingColumn;
        if (!$endingColumn) {
            $endingColumn = Coordinate::stringFromColumnIndex($this->startingcolumnIndex + count($this->provider->getHeaders()) - 1);
        }


        $range = $this->startingColumn . $this->headersLineNumber . ':' . $endingColumn . $this->headersLineNumber;

        $rangeArray = $this->worksheet->rangeToArray($range, '', $this->calculateFormulas, $this->formatValues);


        $this->headerData = array_map('trim', current($rangeArray));
    }


    public function readDatafile($fromLine = 0, $toLine = 0)
    {

        $this->chunkLinesNumber = 0;
        $this->chunkDataArray = [];
        $Item = [];

        $startingChunkLine = max($this->startingDataLine, $fromLine);
//        Log::info("INFOLINES: " . $this->startingDataLine . ' ' . $startingChunkLine . ' ' . $fromLine . ' - ENDING LINE: ' . $this->endingDataLine . ' -- ' . $toLine);

        if ($toLine < $startingChunkLine) {
            $toLine = $this->endingDataLine;
        }

        $shiftRow = 0;
        if ($this->startingDataLine > $fromLine) {
            $shiftRow = $this->startingDataLine - $fromLine;
        }

        $eof = ($toLine >= $this->endingDataLine) ? true : false;

        if ($eof) {
            $toLine = $this->endingDataLine;
        }

        $filterChunk = new ChunksReadFilter($startingChunkLine, $this->endingDataLine, $this->startingColumnIndex,
            $this->endingColumnIndex, $toLine);
        $this->objectReader->setReadFilter($filterChunk);
//        $this->objectReader->setLoadSheetsOnly($this->currentSheet);


        $range = $this->startingColumn . $startingChunkLine . ':' . $this->endingColumn . $toLine;
        $rangeArray = $this->worksheet->rangeToArray($range, '', false, false);

        $checkEmptyLine = $this->skipEmptyLines || $this->stopAtEmptyLine;

        foreach ($rangeArray as $key => $row) {
//            Log::info("DATALINE: ".$key);

            $Item = array_combine($this->headerData, $row);

            if ($checkEmptyLine && count(array_filter($Item)) == 0) {
                if ($this->stopAtEmptyLine) {
                    $eof = true;
                    break;
                }
            } else {
                $Item['shiftrow'] = $shiftRow;
                array_push($this->chunkDataArray, $Item);
            }
            $this->chunkLinesNumber++;
        }

        $returnArray = [
            'data' => $this->chunkDataArray,
            'eof' => $eof,
            'nextLine' => $startingChunkLine + $this->chunkLinesNumber,
        ];
        return $returnArray;
    }


    public function countRows()
    {
        if ($this->nRows) {
            return $this->nRows;
        }

        if (!$this->objectReader) {
            return 1000;
        }

        $this->nRows = $this->worksheet->getHighestRow();
        return $this->nRows;
    }

    public function writeHeaders($filename)
    {

        $headers = $this->provider->getHeaders();


        $this->phpExcel = new Spreadsheet();
        $this->phpExcel->getProperties()->setCreator(env('EXCEL_AUTHOR', 'Cupparis'));
        $this->phpExcel->getProperties()->setLastModifiedBy(env('EXCEL_AUTHOR', 'Cupparis'));

        try {


            $this->phpExcel->setActiveSheetIndex(0);
            $column = 0;
            foreach ($headers as $header) {
                $coordinate = Coordinate::stringFromColumnIndex($column) . '1';
                $this->phpExcel->getActiveSheet()->SetCellValue($coordinate, $header);
                $column++;
            }

            $objWriter = IOFactory::createWriter($this->phpExcel, 'Xlsx');
            $filename .= '.xlsx';
            $objWriter->save($filename);


        } catch (\Exception $e) {
            throw $e;
        }

        return $filename;

    }

    protected function setSheets()
    {
        if (!$this->objectReader) {
            return;
        }
        $this->sheets = $this->objectReader->listWorksheetInfo($this->dataFile);
    }

    public function getSheetsNames()
    {
        if (is_null($this->sheetsNames)) {
            if (!is_null($this->sheetsToUse)) {
                $sheetsNames = Arr::wrap($this->sheetsToUse);
                $sheetsNames = array_map(function ($v) {
                    if (is_int($v)) {
                        return Arr::get(Arr::get($this->sheets, $v, []), 'worksheetName', $v);
                    }
                    return $v;
                }, $sheetsNames);
                $this->sheetsNames = $sheetsNames;
            } else {
                $this->sheetsNames = Arr::pluck($this->sheets, 'worksheetName');
            }
        }
        return $this->sheetsNames;
    }

    public function setCurrentSheet($sheetName = null, $fileProperties = null)
    {

        $this->setObjectReader();


        if (is_null($sheetName)) {
            $sheetName = 0;
        }
        if (is_int($sheetName)) {
            $currentSheetInfo = Arr::get($this->sheets, $sheetName, []);
            $this->currentSheet = Arr::get($currentSheetInfo, 'worksheetName', $sheetName);
        } else {
            $this->currentSheet = $sheetName;
        }

        $this->objectReader->setLoadSheetsOnly([$this->currentSheet]);
        $spreadsheet = $this->objectReader->load($this->dataFile);
        $this->worksheet = $spreadsheet->setActiveSheetIndexByname($this->getCurrentSheet());

        $this->manageFileProperties($fileProperties);
//        Log::info("CURRENT SHEET::".$sheetName.' --- ' . $this->currentSheet);

        return true;
    }

    public function getCurrentSheet()
    {
        return $this->currentSheet;
    }

    /**
     * @param mixed $dataFile
     */
    public function setDataFile($dataFile)
    {
        $this->dataFile = $dataFile;
        $this->setSheetsToUse();
        $this->setObjectReader();
    }

}

?>
