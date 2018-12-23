<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2014 Leo Feyer
 *
 * @package Core
 * @link    https://contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * Set the script name
 */
define('TL_SCRIPT', 'index.php');


/**
 * Initialize the system
 */
define('TL_MODE', 'FE');
require __DIR__ . '/system/initialize.php';


/**
 * Class Petango
 *
 * Main front end controller.
 * @copyright  Leo Feyer 2005-2014
 * @author     Leo Feyer <https://contao.org>
 * @package    Core
 */
class Petango extends Controller
{
    protected $authKey = '8r57u9pj9u96ly8es4q8j40460u2m0g25097130u21301rc4p1';
    /**
     * Call the parent constructor.
     *
     * !!! DON'T REMOVE THIS !!!
     *
     * If you remove this you get the following error message:
     * Fatal error: Call to protected System::__construct() from invalid
     * context
     *
     * @param	void
     * @return	void
     */
    public function __construct()
    {
        parent::__construct();
    }


    /**
     * Get the ajax request and call all hooks
     *
     * @param	void
     * @return	void
     */
    public function run()
    {
        $strAction = $this->Input->get('action') ? $this->Input->get('action') : 'all';

        if($strAction=='all')
        {
            $arrActions = array('all','setpets');
        }else{
            $arrActions = array((string)$strAction);
        }

        $arrDefaultFilters = array('sex'=>'A','speciesID'=>0,'site'=>'');

        foreach($arrActions as $action)
        {
            switch($action)
            {
                case 'all':

                    $arrData = $this->getAll($arrDefaultFilters);
                    $this->writeEndpoint($arrData,'all');
                    break;
                case 'setpets':
                    $this->setPets();
                    break;
                case 'setrandom':
                    $varData = $this->getPet($this->getRandomPetId());
                    $this->writeEndpoint($varData,'random');
                    break;
                case 'setlocations':
                    $arrData = $this->getAll(array('site'=>728));

                    $this->writeEndpoint($arrData,'springfield');

                    $arrData = $this->getAll(array('site'=>56));

                    $this->writeEndpoint($arrData,'leverett');

                    break;

                case 'setgenders':
                    $arrData = $this->getAll(array('sex'=>'M'));
                    $this->writeEndpoint($arrData,'m');

                    $arrData = $this->getAll(array('sex'=>'F'));
                    $this->writeEndpoint($arrData,'f');
                    break;

                case 'setspecies':
                    $arrSpeciesIds = array(1,2,3,4,5,6,7,8,9,1003);
                    foreach($arrSpeciesIds as $id)
                    {
                        $arrData = $this->getAll(array('speciesID'=>$id));
                        $this->writeEndpoint($arrData,$id);
                    }
                    break;

                case 'pet':
                    $arrData = $this->getPet($this->Input->get('p'));
                    $this->writeEndpoint($arrData,'petango_pet_'.$this->Input->get('p'));
                    break;
                case 'filter':
                    $arrNewFilters = json_decode(file_get_contents('php://input'), true);
                    $arrFinalFilters = array();

                    foreach($arrNewFilters as $k=>$obj)
                    {
                        $arrFinalFilters[$k] = $obj['value'];
                    }

                    $arrFilters = array_merge($arrDefaultFilters,$arrFinalFilters);

                    header('Content-type: application/json');

                    echo json_encode($this->getAll($arrFilters));
                    exit;
                    break;
            }
        }

    }

    protected function getRandomPetId()
    {

        $arrFiles = glob(TL_ROOT.'/petango_cache/[0-9]*\.json');

        $func = function($f){
            $file = pathinfo($f);
            return $file['filename'];
        };

        return array_rand(array_flip(array_map($func,$arrFiles)));
    }

    protected function findDelistedPetIds($arrNewIds)
    {
        $arrExistingIds = glob(__DIR__.'/petango_cache/[0-9]*.json');

        $func = function($f){
            $file = pathinfo($f);
            return $file['filename'];
        };

        return array_diff(array_map($func,$arrExistingIds),$arrNewIds);
    }

    protected function getPet($id)
    {
        $objRequest = new Request();

        $strData = 'authkey='.$this->authKey.'&animalID='.$id;
        $objRequest->send('http://ws.petango.com/webservices/wsadoption.asmx/AdoptableDetails?'.$strData);

        if (!$objRequest->hasError())
        {
            $strXml = $objRequest->response;

            $arrReturn = $this->XMLtoArray($strXml);

            $arrData = $arrReturn['adoptableDetails'];

            $arrSlideFields = array('Photo1','Photo2','Photo3','VideoID');

            $arrData['Slides'] = array();

            $strImage = '';

            $y = 0;

            foreach($arrSlideFields as $i=>$field)
            {
                if(strlen(preg_replace("([\t|\n|\r|\s])","",$arrData[$field]))>0) {
                    switch ($arrData['Species']) {
                        case 'Cat':
                            $strSpecies = 'cat';
                            break;
                        case 'Dog':
                            $strSpecies = 'dog';
                            break;
                        default:
                            $strSpecies = 'animal';
                            break;
                    }

                    if ($field !== 'VideoID')
                        $strImage = (stripos($arrData[$field], 'Photo-Not-Available') === false ? str_replace('http:','',$arrData[$field]) : 'system/modules/petpoint/assets/images/no_photo_' . $strSpecies . '.png');

                    if (strlen($arrData[$field]) > 0)
                    {
                        $arrData['Slides'][$y] = array('type'=>($field=='VideoID'?'video':'image'),'src'=>($field=='VideoID' ? "//www.youtube.com/embed/".$arrData[$field]."?rel=0&loop=0&wmode=opaque" : $strImage));
                        $y++;
                    }
                }
            }

            return $arrData;
        }
    }

    protected function setPets()
    {
        $arrData = $this->getAll();
        $arrIDs = $arrDelisted = $arrEligibleRandoms = array();

        //collect ids
        foreach($arrData as $row)
        {
            $arrIDs[] = $row['ID'];
        }

        $arrDelisted = $this->findDelistedPetIds($arrIDs);

        //clear out the cache folder
        $this->import('Files');

        foreach($arrDelisted as $file)
        {
            var_dump($file);
            $this->Files->delete('petango_cache/'.$file.'.json');
        }

        //write each pet's json
        foreach($arrIDs as $i=>$id)
        {
            $arrReturn = $this->getPet($id);
            $this->writeEndpoint($arrReturn,$id);

            //add to eligible random pet pool if the photo is available
            if(stripos($arrReturn['Photo1'],'Photo-Not-Available')===false)
            {
                $arrEligibleRandoms[] = $id;
            }
        }

        //write our random pet
        $arrData = $this->getPet(array_rand(array_flip($arrEligibleRandoms)));

        $this->writeEndpoint($arrData,'random');

    }

    protected function getAll($arrFilters=array())
    {
        if(count($arrFilters)==0)
            $arrFilters = array('sex'=>'A','speciesID'=>0,'site'=>'');

        $objRequest = new Request();

        $strData = 'authkey='.$this->authKey.'&ageGroup=All&location=&onHold=N&orderBy=ID&primaryBreed=&secondaryBreed=&specialNeeds=A&noDogs=A&noCats=A&noKids=A&stageID=';

        if($arrFilters['site']==0)
            $arrFilters['site'] = '0';

        $strData .= '&'.http_build_query($arrFilters);

        $objRequest->send('http://ws.petango.com/webservices/wsadoption.asmx/AdoptableSearch?'.$strData);

        if (!$objRequest->hasError())
        {
            $strXml = $objRequest->response;

            $arrReturn = $this->XMLtoArray($strXml);
            $arrResult = $arrReturn['ArrayOfXmlNode']['XmlNode'];
            $arrData = array();

            foreach($arrResult as $k=>$result)
            {
                if(is_array($result['adoptableSearch']))
                    $arrData[] = $result['adoptableSearch'];
            }

            $arrResults = array();

            foreach($arrData as $row)
            {
                switch($row['Species'])
                {
                    case 'Cat':
                        $strSpecies = 'cat';
                        break;
                    case 'Dog':
                        $strSpecies = 'dog';
                        break;
                    default:
                        $strSpecies = 'animal';
                        break;
                }

                $row['Photo'] = (stripos($row['Photo'],'Photo-Not-Available')===false ? str_replace('http:','',$row['Photo']) : 'system/modules/petpoint/assets/images/no_photo_'.$strSpecies.'.png');
                $arrResults[] = $row;
            }

            return $arrResults;
        }
    }

    protected function writeEndpoint($arrData,$strFilename)
    {
        header('Content-type: application/json');
        $objFile = new \File('petango_cache/'.$strFilename.'.json');

        $objFile->write(json_encode($arrData));
        $objFile->close();
    }

    public function XMLtoArray(&$string)
    {
        $parser = xml_parser_create();
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parse_into_struct($parser, $string, $vals, $index);
        xml_parser_free($parser);
        $mnary=array();
        $ary=&$mnary;
        foreach ($vals as $r) {
            $t=$r['tag'];
            if ($r['type']=='open') {
                if (isset($ary[$t])) {
                    if (isset($ary[$t][0])) $ary[$t][]=array(); else $ary[$t]=array($ary[$t], array());
                    $cv=&$ary[$t][count($ary[$t])-1];
                } else $cv=&$ary[$t];
                if (isset($r['attributes'])) {foreach ($r['attributes'] as $k=>$v) $cv['_a'][$k]=$v;}
                /*
                $cv['_c']=array();
                $cv['_c']['_p']=&$ary;
                $ary=&$cv['_c'];
                */
                $cv=array();
                $cv['_p']=&$ary;
                $ary=&$cv;
            } elseif ($r['type']=='complete') {
                if (isset($ary[$t])) { // same as open
                    if (isset($ary[$t][0])) $ary[$t][]=array(); else $ary[$t]=array($ary[$t], array());
                    $cv=&$ary[$t][count($ary[$t])-1];
                } else $cv=&$ary[$t];
                if (isset($r['attributes'])) {foreach ($r['attributes'] as $k=>$v) $cv['_a'][$k]=$v;}

                //if (isset($r['value'])) $cv['_v'] = $r['value'];
                if (isset($r['value'])) $cv = $r['value'];
            } elseif ($r['type']=='close') {
                $ary=&$ary['_p'];
            }
        }

        $this->del_p($mnary);
        return $mnary;
    }

    protected function del_p(&$ary) {
        foreach ($ary as $k=>$v) {
            if ($k==='_p') unset($ary[$k]);
            elseif (is_array($ary[$k])) $this->del_p($ary[$k]);
        }
    }

    protected function getXMLRequest()
    {
        return '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Body>
<adoptableSearch xmlns="http://petango.com/">
<authkey>8r57u9pj9u96ly8es4q8j40460u2m0g25097130u21301rc4p1</authkey>
<speciesID>0</speciesID>
<sex>All</sex>
<ageGroup>All</ageGroup>
<location></location>
<site></site>
<onHold>No</onHold>
<orderBy>ID</orderBy>
<primaryBreed>All</primaryBreed>
<secondaryBreed>All</secondaryBreed>
<specialNeeds>A</specialNeeds>
<noDogs>A</noDogs>
<noCats>A</noCats>
<noKids>A</noKids>
</adoptableSearch>
</soap:Body>
</soap:Envelope>';

    }
}


// create a SimpleAjax instance and run it
$objPetango = new Petango();
$objPetango->run();

?>
