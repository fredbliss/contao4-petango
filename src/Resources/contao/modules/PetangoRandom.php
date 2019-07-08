<?php

namespace IntelligentSpark\Petango\Modules;

use Contao\Module as Module;

class PetangoRandom extends Module {


    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_petangorandom';
    /**
     * Display a wildcard in the back end
     *
     * @return string
     */
    public function generate()
    {
        if (TL_MODE == 'BE')
        {
            /** @var \BackendTemplate|object $objTemplate */
            $objTemplate = new \BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ' . utf8_strtoupper($GLOBALS['TL_LANG']['FMD']['petango_details'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        return parent::generate();
    }

    /**
     * Generate the module
     */
    protected function compile()
    {
        $arrImageSize = array();
        $content = file_get_contents(TL_ROOT . '/files/petango/src/public/petango_cache/random.json');

        if($content) {
            $arrPet = json_decode($content,true);

            //$GLOBALS['TL_HEAD'][] = '<meta property="og:title" content="Meet '.$arrPet['AnimalName'].' - Dakin Humane Society" />';

            //$GLOBALS['TL_HEAD'][] = '<meta property="og:description" content="'.(strlen(strip_tags(trim($arrPet['Dsc'])))>0 ? strip_tags($arrPet['Dsc']) : 'Check it out...').'" />';

            //$GLOBALS['TL_HEAD'][] = '<meta property="og:url" content="'.\Environment::get('url').'/'.\Environment::get('request').'" />
            //<meta property="og:image" content="https:'.$arrPet['Slides'][0]['src'].'" />
            
            //<meta property="og:type" content="website" />';

            $arrPet['realage'] = $this->getRealAge((int)$arrPet['Age']);

            $this->Template->pet = $arrPet;
        }else{
            $this->Template->pet = false;
        }

    }

    protected function getRealAge($intAge) {
        $years = $intAge/12;

        if($intAge<12) {
            return $intAge . ' month'.($intAge>1 ? 's' : '');
        }else{
            return floor($years).' year'. (round($years)>1 ? 's':'').(($intAge % 12)>0 ? ', '.$intAge%12 . ' month'.(($intAge%12>1 ? 's' : '')) : '');
        }
    }
}