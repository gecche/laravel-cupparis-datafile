<?php namespace Gecche\Cupparis\Datafile\Driver;

use Illuminate\Support\Facades\Log;

class FixedTextDriver extends CsvDriver
{

    protected $filePropertiesKeys = [
        'fixedTextArray' => [],
        'maxLineSize' => 3000,
    ];



    // ritorna una media di grandezza delle righe in byte
    public function getRowByteSize()
    {
        return array_sum($this->fixedTextArray);

    }


    protected function splitFixedTextLine($line)
    {
        if (count($this->fixedTextArray) <= 0) {
            return $line;
        }

        $fieldCounter = 0;
        $values = [];
        foreach ($this->fixedTextArray  as $key => $fieldLength) {
            $values[$fieldCounter] = trim(substr($line, 0, $fieldLength));
            $line = substr($line, $fieldLength);
            $fieldCounter++;
        }

        return $values;
    }


    protected function getLine($fp) {
        $line = fgets($fp, $this->maxLineSize);
        if (!$line) {
            return $line;
        }
        return $this->splitFixedTextLine($line);
    }
}

?>
