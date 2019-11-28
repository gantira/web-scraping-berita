<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Spider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class Beritasatu extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:beritasatu';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Beritasatu news';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        $bisnis = $this->getArticles(0, 'bisnis', 'bisnis');
        usort($bisnis, function($a, $b) {
            $sortby = 'date';
            return strcmp($b[$sortby], $a[$sortby]);
        });
        News::saveLatest('beritasatu', $bisnis);
        News::saveDaily('beritasatu', $bisnis);
    }

    public function getArticles($page, $section, $section_name) {
        
        $url = "http://www.beritasatu.com/newsindex/" . $section;
        $raw = Spider::getContent($url);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        if(!$raw) {
            return [];
        }
        $document->loadHTML($raw);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//div[contains(attribute::class, "custom-media-index")]') as $e) {
            $row=[];
            $links=$e->getElementsByTagName('h2');
            foreach($links as $a) {
                $row['title'] = $a->nodeValue;
            }
            $links=$e->getElementsByTagName('a');
            foreach($links as $a) {
                $row['url'] = $a->getAttribute('href');
                $row['md5url'] = md5($row['url']);
            }
            $image = $e->getElementsByTagName('img');
            foreach($image as $img) {
                $row['thumbnail'] = $img->getAttribute('src');
                $row['image'] = str_replace("200x200-2","910x580-2",$row['thumbnail']);
            }
            $date = $e->getElementsByTagName('span');
            foreach($date as $aa) {
                //$row['date_raw'] = $aa->nodeValue;
                $row['date'] = $this->niceDate($aa->nodeValue);
            }
            $article=$this->getArticle($row['url']);
            $row['content']=$article['content'];
            $row['source']='beritasatu';
            $row['section']=$section_name;
            $data[]=$row;
        }
        return $data;

    }
    
    public function niceDate($raw) {
        $raw= explode(", ", $raw);
        $raw = explode(" ", $raw[1]);
        $time = explode(":",$raw[4]);
        $month = $this->getMonthFromString(@$raw[1]);
        $date = Carbon::create(@$raw[2], $month, @$raw[0], @$time[0], @$time[1], 0, 'Asia/Jakarta');
        return $date->toW3cString();
    }
    
    public function getMonthFromString($string) {
        $indonesianMonth = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        $flip = array_flip($indonesianMonth);
        //dump($flip);
        try {
            $a= $flip[$string]??1;
        } catch (\Exception $e) {
            dump($string, $e->getMessage());
        }
        return $a??1;
    }

    public function getArticle($url) {
        $raw = Spider::getContent($url, 100000);
        try {
            $x = str_replace("&nbsp;", " ", $raw);
            $x = str_replace("&lrm;", " ", $x);
            $x = mb_convert_encoding($x, 'HTML-ENTITIES', 'UTF-8');
            
            $x = str_replace("&lrm;", " ", $x);
            
            $document = new \DOMDocument('1.0', 'UTF-8');
            $internalErrors = libxml_use_internal_errors(true);
            $document->loadHTML($x);
            libxml_clear_errors();

            $xpath = new \DOMXPath($document);
            $return['content']='';
            foreach ($xpath->query('//script') as $e) {
                $e->parentNode->removeChild($e);
            }
            foreach ($xpath->query('//div[contains(attribute::itemprop, "articleBody")]') as $e) {
                $string=$e->nodeValue;
                $string = str_replace(array("\t", "\r", "\x20\x20", "\0", "\x0B"), "", $string);
                
                $return['content']=trim($string);
            }
            
        } catch (\Exception $e) {
            $return = [
                'content'=>''
            ];
            dump($e->getMessage());
        }
        return $return;
    }

    public function getBetween($string, $start, $end) {
        $string = str_replace(array("\t", "\n", "\r", "\x20\x20", "\0", "\x0B"), "", html_entity_decode($string));
        $ini = strpos($string, $start);
        if ($ini == 0)
            return "";
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }

}
