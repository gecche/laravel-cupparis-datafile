<?php namespace Gecche\Cupparis\Datafile\Driver;


use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Gecche\Cupparis\Datafile\Driver\ExcelFilter\ChunksReadFilter;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;


trait ExcelDriverTrait
{

    protected $sheets = [];

    protected $currentSheet;

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
            $objPHPExcel = $this->objectReader->load($this->dataFile);
            $this->endingDataLine = $objPHPExcel->setActiveSheetIndex(0)->getHighestRow();
            $objPHPExcel->disconnectWorksheets();
            unset($objPHPExcel);
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

        $objPHPExcel = $this->objectReader->load($this->dataFile);

        $range = $this->startingColumn . $this->headersLineNumber . ':' . $endingColumn . $this->headersLineNumber;

        $rangeArray = $objPHPExcel->getActiveSheet()->rangeToArray($range, '', false, false);
        $objPHPExcel->disconnectWorksheets();
        unset($objPHPExcel);

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
        $this->objectReader->setLoadSheetsOnly($this->fileSheetName);

        $objPHPExcel = $this->objectReader->load($this->dataFile);

        $range = $this->startingColumn . $startingChunkLine . ':' . $this->endingColumn . $toLine;
        $rangeArray = $objPHPExcel->getActiveSheet()->rangeToArray($range, '', false, false);

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
        $objPHPExcel = $this->objectReader->load($this->dataFile);
        $this->nRows = $objPHPExcel->setActiveSheetIndex(0)->getHighestRow();
        $objPHPExcel->disconnectWorksheets();
        unset($objPHPExcel);
        return $this->nRows;
    }

    public function writeHeaders($filename) {

        $headers = $this->provider->getHeaders();


        $this->phpExcel = new Spreadsheet();
        $this->phpExcel->getProperties()->setCreator(env('EXCEL_AUTHOR', 'Cupparis'));
        $this->phpExcel->getProperties()->setLastModifiedBy(env('EXCEL_AUTHOR', 'Cupparis'));

        try {



            $this->phpExcel->setActiveSheetIndex(0);
            $column = 0;
            foreach ($headers as $header) {
                $coordinate = Coordinate::stringFromColumnIndex($column).'1';
                $this->phpExcel->getActiveSheet()->SetCellValue($coordinate,$header);
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

    protected function setSheets() {
        if (!$this->objectReader) {
            return;
        }
        $this->sheets = $this->objectReader->listWorksheetInfo($this->dataFile);
    }

    public function getSheetsNames() {
        return Arr::pluck($this->sheets,'worksheetName');
    }

    public function setCurrentSheet($sheetName) {
        if (!$this->objectReader) {
            return true;
        }
        $spreadSheet = $this->objectReader->load($this->dataFile);
        if (is_numeric($sheetName)) {
            $spreadSheet->setActiveSheetIndex($sheetName);
        } else {
            $spreadSheet->setActiveSheetIndexByName($sheetName);
        }

        $this->currentSheet = $sheetName;
        return true;
    }

    public function getCurrentSheet() {
        return $this->currentSheet;
    }

}

?>
