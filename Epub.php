<?php 
/**
 * CI Library to READ epub meta data. Extract Author and all that stuff.
 * 
 * @category   Epub
 * @package    CodeIgniter
 * @subpackage Libraries
 * @author     Shemes <semrah@gmail.com>
 * @license    to come
 * @version    testing
 * @link       Coming soon to github.
 * 
 */
?>
<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

/**
 * INSPIRED by this lib, that is much much better than mine.
 * 
 * PHP EPub Meta library
 * 
 * @author Andreas Gohr <andi@splitbrain.org>
 * @link   https://github.com/splitbrain/php-epub-meta/blob/master/epub.php
 * 
 */

class Epub
{

    protected $xml          = ''; 
    protected $xpath        = ''; // Contents folder with trailing slash 
    protected $file         = '';
    protected $meta         = '';
    protected $namespaces   = '';
    protected $imagetoadd   = '';
    protected $title        = ''; // Book Title
    protected $titleSort    = ''; 
    protected $authors      = ''; // Authors, translators, colabs...
    protected $description  = ''; 
    protected $date         = ''; // Published Date
    protected $publisher    = ''; 
    protected $language     = ''; // ISO 639 ?? What does calibre do?
    protected $subjects     = array(); 
    protected $series       = ''; 
    protected $seriesIndex  = '';
    protected $cover        = ''; // path to the saved image.
    protected $coversDir    = '';
    protected $prepositions = 'A|AN|El|La|The|En|Un|Uno|Una'; // for "titleSort", maybe chage this to a config file?

    /**
     * Constructor - Sets Preferences
     *
     * The constructor can be passed an array of config values
     * @param $config EPUB FILE
     */
    public function __construct($config = array()) 
    {
        if (count($config) > 0) {
            $this->initialize($config);
        }

        log_message('debug', "Epub Library Initialized");
    }

    function initialize($config = array()) 
    {

        foreach ($config as $key => $val) {
            if (isset($this->$key)) {
                $this->$key = $val;
            }
        }

        $zip = new ZipArchive();
        $zip->open($this->file);
        $container = $zip->getFromName('META-INF/container.xml');
        

        $meta = new SimpleXMLElement($container);
        $opf = $meta->rootfiles->rootfile['full-path'];
        $this->xpath = (preg_match('/^(.)+\//', $opf, $this->xpath)) ? $this->xpath : array( '0' => '');
        $opf = $zip->getFromName($opf);
        $this->xml = new SimpleXMLElement($opf);
        $this->meta = $this->xml->metadata;
        
        $this->get_title();
        $this->get_description();
        $this->get_date();
        $this->get_publisher();
        $this->get_language();
        $this->get_subjects();
        $this->get_titleSort();
        $this->get_series();
        $this->get_seriesIndex();
        $this->get_authors();
        $this->get_cover($zip);
        $zip->close();
    }

    function get_title() 
    {
        $this->title = htmlentities($this->meta->children('dc', true)->title, ENT_COMPAT, 'UTF-8');
        return (!$this->title) ? false : $this->title;
    }
    function get_titleSort()
    {
        if($this->title) {
            if ($this->get_calibreMeta('calibre:title_sort')) {
                $this->titleSort = $this->get_calibreMeta('calibre:title_sort');
            } else {
                $this->titleSort = trim(preg_replace('/^(' . $this->prepositions . ')(.+)/', '$2, $1', $this->title));
            }
        }

        return (!$this->titleSort) ? false : $this->titleSort;
    }
    function get_authors()
    {
        $this->authors = htmlentities($this->meta->children('dc', true)->creator, ENT_COMPAT, 'UTF-8');
        return $this->authors;
    }
    function get_description()
    {
        $this->description = htmlentities($this->meta->children('dc', true)->description, ENT_COMPAT, 'UTF-8');
        return $this->description;
    }
    function get_date()
    {
        $this->date = htmlentities($this->meta->children('dc', true)->date, ENT_COMPAT, 'UTF-8');
        return $this->date;
    }
    function get_publisher()
    {
        $this->publisher = htmlentities($this->meta->children('dc', true)->publisher, ENT_COMPAT, 'UTF-8');
        return $this->publisher;
    }
    function get_language()
    {   
        $this->language = htmlentities($this->meta->children('dc', true)->language, ENT_COMPAT, 'UTF-8');
        return $this->language;
    }
    function get_subjects()
    {
        foreach($this->meta->children('dc', true)->subject as $subject) {
            $this->subjects[] = (string)$subject;
        }
        return $this->subjects;
    }
    function get_series()
    {
        $this->series = $this->get_calibreMeta('calibre:series');
        return $this->series;
    }
    function get_seriesIndex()
    {
        $this->seriesIndex = $this->get_calibreMeta('calibre:series_index');
        return $this->seriesIndex;
    }
    function get_cover($zip)
    {
        $cover = $this->get_calibreMeta('cover');
        $cover = $this->get_fromManifest($cover);
        $cover = $zip->getFromName($this->xpath[0] . $cover);

        $this->cover = $this->save_cover($cover);
        return $this->cover;
    }
    private function save_cover($cover)
    {
        $cover = imagecreatefromstring($cover);
        $name = './' . $this->coversDir . '/' . $this->get_title() . '.jpg';
        if ($cover !== false) {
            imagejpeg($cover, $name);
            imagedestroy($cover);
            return htmlentities($this->coversDir . '/' . $this->get_title() . '.jpg');
        } else {
            return "Ha ocurrido un error al guardar la imagen"; //change by a lang thingy
        }
    }
    private function get_calibreMeta($type)
    {
        $metadata = (array)$this->xml->metadata;
        foreach ($metadata['meta'] as $meta) {
            $meta = (array)$meta;
            $arr[$meta['@attributes']['name']] = $meta['@attributes']['content'];
        }
        if(!array_key_exists($type, $arr)) {
            return '';
        }
        return $arr[$type];
    }
    private function get_fromManifest($what)
    {
        $manifest = (array)$this->xml->manifest;
        foreach ($manifest['item'] as $meta) {
            $meta = (array)$meta;
            $arr[$meta['@attributes']['id']] = $meta['@attributes']['href'];
        }
        return $arr[$what];   
    }
    public function data()
    {
        return array (
            'title'         => $this->title,
            'titleSort'     => $this->titleSort,
            'authors'       => $this->authors,
            'description'   => $this->description,
            'date'          => $this->date,
            'publisher'     => $this->publisher,
            'language'      => $this->language,
            'subjects'      => $this->subjects,
            'series'        => $this->series,
            'seriesIndex'   => $this->seriesIndex,
            'cover'         => $this->cover
                    );
    }
}