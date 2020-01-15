<?php namespace Gecche\Cupparis\Datafile\Driver;


use Gecche\Cupparis\Datafile\ArdentDatafileProvider;
use Illuminate\Support\Facades\Log;

class CsvDriver extends DatafileDriver
{

    protected $filePropertiesKeys = [
        'separator' => ';',
        'maxLineSize' => 3000,
    ];


    // ritorna una media di grandezza delle righe in byte
    public function getRowByteSize()
    {
        $fp = fopen($this->dataFile, "r");

        $sizeb = 0;
        while (($DataLine = fgets($fp, $this->maxLineSize)) && ($this->chunkLinesNumber < 30)) {
            //$serializedFoo = serialize($DataLine);
            if (function_exists('mb_strlen')) {
                $sizeb += mb_strlen($DataLine, '8bit');
            } else {
                $sizeb += strlen($DataLine);
            }
            //echo $serializedFoo . " --- " .strlen($serializedFoo);
            $this->chunkLinesNumber++;
            //echo $sizeb . "<br>";
        }
//		echo "Sizebb" . $sizeb;
        fclose($fp);
        return $this->chunkLinesNumber ? ceil($sizeb / $this->chunkLinesNumber) : 1;
    }


    public function readDatafile($fromLine = 0, $toLine = 0)
    {

        $this->chunkLinesNumber = 0;
        $this->chunkDataArray = [];
        $Item = [];

        // nuovo codice
        $fp = fopen($this->dataFile, "r");

        $startingChunkLine = max($this->startingDataLine, $fromLine);
        Log::info("INFOLINES: ".$this->startingDataLine. ' ' . $startingChunkLine. ' '. $fromLine);

        for ($i = 0; $i < $startingChunkLine; $i++) {
            if (feof($fp)) {
                break;
            }
            $string = fgets($fp);
            Log::info($string);
        }

        $eof = feof($fp);

        $eofString = $eof ? 'truee' : 'falseee';
//        Log::info("INFOEOF: ".$eofString . ' ' . $startingChunkLine);

        $checkEmptyLine = $this->skipEmptyLines || $this->stopAtEmptyLine;
        $maxColumn = is_null($this->endingColumn) ? count($this->headerData) : min(count($this->headerData),$this->endingColumn);

        $currentLine = $startingChunkLine;
        if (!$eof) {

            $DataLine = null;
            while (($DataLine = $this->getLine($fp)) !== false) {
                Log::info("DATALINE: ".print_r($DataLine,true));

                if (!is_null($this->endingDataLine) && $currentLine > $this->endingDataLine) {
                    Log::info("DATALINE1: ".$this->endingDataLine." - ".$currentLine);
                    $eof = true;
                    break;
                }
                //Sono arrivato alla riga finale del chunk
                if ($toLine > 0 && $this->chunkLinesNumber == ($toLine - $startingChunkLine)) {
                    Log::info("DATALINE2: ".print_r($DataLine,true));
                    break;
                }


                $j = 0;
                for ($i = $this->startingColumn; $i < $maxColumn; $i++) {
                    $Item[$this->headerData[$j]] = array_get($DataLine, $i, '');
                    $j++;
                }

                if ($checkEmptyLine && count(array_filter($Item)) == 0) {
                    if ($this->stopAtEmptyLine) {
                        Log::info("DATALINE3: ".print_r($DataLine,true));
                        $eof = true;
                        break;
                    }
                } else {
                    array_push($this->chunkDataArray, $Item);
                }
                $this->chunkLinesNumber++;
                $currentLine++;
            }

            if (!$DataLine) {
                $this->chunkLinesNumber++;
            }

        }

        fclose($fp);
        $returnArray = [
            'data' => $this->chunkDataArray,
            'eof' => $eof,
            'nextLine' => $startingChunkLine + $this->chunkLinesNumber,
        ];
        return $returnArray;
    }


    public function resolveHeaders()
    {

        $this->headerData = [];
        if (!$this->hasHeadersLine) {
            return;
        }

        $fp = fopen($this->dataFile, "r");
        for ($i = 0; $i < $this->headersLineNumber; $i++) {
            if (feof($fp)) {
                fclose($fp);
                return;
            }
            fgets($fp);
        }

        $headerLine = $this->getLine($fp);
        if (!$headerLine) {
            return;
        }

        if (is_null($this->endingColumn)) {
            $endingColumn = count($headerLine);
        }

        for ($i = $this->startingColumn;$i<$endingColumn;$i++) {
            $this->headerData[] = array_get($headerLine,$i,'');
        }

        fclose($fp);
    }

    public function countRows()
    {
        if ($this->nRows) {
            return $this->nRows;
        }

            $fsize = filesize($this->dataFile);
            $rowsize = $this->getRowByteSize();
            if (!$rowsize) {
                $rowsize = 300;
            }
            $numRows = floor($fsize/$rowsize);
            $this->nRows = $numRows;
            return $numRows;
        // TODO: Implement getDatafileNumRows() method.
    }

    protected function getLine($fp) {
        $array = fgetcsv($fp, $this->maxLineSize, $this->separator);
        Log::info("FGETCSV: ".print_r($array,true));
        return $array;
    }

}

?>
