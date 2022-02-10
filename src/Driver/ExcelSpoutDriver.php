<?php namespace Gecche\Cupparis\Datafile\Driver;


use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Illuminate\Support\Facades\Log;
use Gecche\Cupparis\Datafile\Driver\ExcelFilter\ChunksReadFilter;
use Illuminate\Support\Str;



/*
 * DA FINIRE PUO' ESSERE UTILE PER GRANDI FILES INCOLONNATI
 */
class ExcelSpoutDriver extends DatafileDriver
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

    protected $fileSheetName = null;

    protected $objectReader;

    protected function setObjectReader()
    {
        if (!$this->dataFile) {
            return;
        }

        try {
            $reader = ReaderEntityFactory::createXLSXReader();

            $reader->open($this->dataFile);
            $this->fileSheetName = null;

            foreach ($reader->getSheetIterator() as $sheet) {
                // only read data from "summary" sheet
                if (
                    (is_int($this->sheetName) && $sheet->getIndex() === $this->sheetName) ||
                    (!is_int($this->sheetName) && $sheet->getName() === $this->sheetName)
                ) {
                        $this->fileSheetName = $sheet->getName();
                        break; // no need to read more sheets
                }
            }

            if (is_null($this->fileSheetName)) {
                throw new \Exception('NOME DEL FOGLIO INESISTENTE NEL FILE: ' . $this->sheetName);
            }
            $reader->close();

        } catch (\Exception $e) {
            $reader->close();

            $msg = $e->getMessage();

            if (Str::startsWith($msg, 'NOME DEL FOGLIO INESISTENTE NEL FILE:')) {
                throw $e;
            }

            $msg = 'Problemi ad aprire il file: non sembra un file salvato correttamente come file excel. Provare ad aprirlo con Excel e salvarlo nuovamente.<br/>' . $msg;
            throw new \Exception($msg);
        }

        $this->objectReader = $reader;

    }

    public function getObjectReader()
    {
        return $this->objectReader;
    }

    protected function manageFileProperties($fileProperties = null)
    {
        $this->nRows = null;
        $this->calculateFilePropertiesArray($fileProperties);
        $this->setObjectReader();

        if (!$this->dataFile) {
            return;
        }

        $this->objectReader->open($this->dataFile);
        foreach ($this->objectReader->getSheetIterator() as $sheet) {
            // only read data from "summary" sheet
            if ($sheet->getName() === $this->fileSheetName) {
                foreach ($sheet->getRowIterator() as $row) {
                    // do stuff with the row
                    $cells = $row->getCells();
                }

            }
        }

        $this->objectReader->close();


        //$this->calculateHeadersAndBoundaries();
    }

    protected function calculateHeadersAndBoundaries()
    {

        if (!$this->dataFile) {
            return;
        }

        $this->startingcolumnIndex = \PHPExcel_Cell::columnIndexFromString($this->startingColumn);

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
            $this->endingColumn = \PHPExcel_Cell::stringFromColumnIndex($this->startingcolumnIndex + count($this->headerData) - 1);
        }
        $this->endingColumnIndex = \PHPExcel_Cell::columnIndexFromString($this->endingColumn);

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
            $endingColumn = \PHPExcel_Cell::stringFromColumnIndex($this->startingcolumnIndex + count($this->provider->getHeaders()) - 1);
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


        $this->phpExcel = new \PHPExcel();
        $this->phpExcel->getProperties()->setCreator(env('EXCEL_AUTHOR', 'Cupparis'));
        $this->phpExcel->getProperties()->setLastModifiedBy(env('EXCEL_AUTHOR', 'Cupparis'));

        try {



            $this->phpExcel->setActiveSheetIndex(0);
            $column = 0;
            foreach ($headers as $header) {
                $coordinate = \PHPExcel_Cell::stringFromColumnIndex($column).'1';
                $this->phpExcel->getActiveSheet()->SetCellValue($coordinate,$header);
                $column++;
            }

            $objWriter = \PHPExcel_IOFactory::createWriter($this->phpExcel, 'Excel2007');
            $filename .= '.xlsx';
            $objWriter->save($filename);


        } catch (\Exception $e) {
            throw $e;
        }

        return $filename;

    }

}

?>
