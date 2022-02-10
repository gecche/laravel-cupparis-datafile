<?php

namespace Gecche\Cupparis\Datafile\DatafileProviders;

use Gecche\Cupparis\Datafile\Breeze\BreezeDatafile;
use Gecche\Cupparis\Datafile\Breeze\BreezeDatafileProvider;
use App\Models\Provincia;

class ComuneIstat extends BreezeDatafileProvider
{
	/*
	 * array del tipo di datafile, ha la seguente forma: array( 'headers' => array( 'header1' => array( 'datatype' => 'string|int|data...', (default string) 'checks' => array( 'checkCallback1' => array(params => paramsArray,type => error|alert), ... 'checkCallbackN' => array(params => paramsArray,type => error|alert), ), (deafult array()) 'transforms' => array( 'transformCallback1' => array(params), ... 'transformCallbackN' => array(params), ), (default array()) 'blocking' => true|false (default false) ) ... 'headerN' => array( 'datatype' => 'string|int|data...', (default string) 'checks' => array( 'checkCallback1' => array(params), ... 'checkCallbackN' => array(params), ), (deafult array()) 'transforms' => array( 'transformCallback1' => array(params), ... 'transformCallbackN' => array(params), ), (default array()) ) 'peremesso' => 'permesso_string' (default 'datafile_upload') 'blocking' => true|false (default false) ) ) I chechCallbacks e transformCallbacks sono dei nomi di funzioni di questo modello (o sottoclassi) dichiarati come protected e con il nome del callback preceduto da _check_ o _transform_ e che accettano i parametri specificati I checkCallbacks hanno anche un campo che specifica se si tratta di errore o di alert I checks servono per verificare se i dati del campo corrispondono ai requisiti richiesti I transforms trasformano i dati in qualcos'altro (es: formato della data da gg/mm/yyyy a yyyy-mm-gg) Vengono eseguiti prima tutti i checks e poi tutti i transforms (nell'ordine specificato dall'array) Blocking invece definisce se un errore nei check di una riga corrisponde al blocco dell'upload datafile o se si può andare avanti saltando quella riga permesso è se il
	 */

    protected $modelDatafileName = \App\DatafileModels\ComuneIstat::class;
    protected $modelTargetName = \App\Models\ComuneIstat::class;

	protected $zip = false;
	protected $zipDir = false;
	protected $zipDirName = '';

    protected $fileProperties = [
        'separator' => "|",
    ];

    /*
     * HEADERS array header => datatype
     */
    public $headers = array(

        'GTComIstat',
        'GTComDes',
        'GTComPrv',
        'GTComCod',
    );

    public function associateRow(BreezeDatafile $modelDatafile) {
        $codice = $modelDatafile->GTComIstat;
        $modelTargetName = $this->modelTargetName;
        return $modelTargetName::findOrNew($codice);

    }

    public function formatRow(BreezeDatafile $modelDatafile) {

        $values = array();

        $values['id'] = $modelDatafile->GTComIstat;
        $values['nome'] = $modelDatafile->GTComDes;
        
        $values['provincia_id'] = Provincia::where('sigla','=',$modelDatafile->GTComPrv)->first()->id;
        $values['cap'] = '56123';
        return $values;
    }

}

// End Datafile Core Model
