<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Spider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class Jawapos extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:jawapos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tribun news';

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
//        $a=$this->getArticle('https://www.jawapos.com/ekonomi/bisnis/14/11/2018/godok-rpjmn-2020-2024-kadin-bappenas-fokuskan-perkuat-sektor-ini');
//        dd($a);
        $bisnis=$this->getArticles('ekonomi/bisnis', 'bisnis');
        $data=$bisnis;
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp($b[$sortby], $a[$sortby]);
        });
        News::saveLatest('jawapos', $data);
        News::saveDaily('jawapos', $data);
    }

    public function getArticles($section, $section_name) {
        $url = "https://www.jawapos.com/".$section;
        $raw = Spider::getContent($url);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTML($raw);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($document);
        $data=[];
        foreach ($xpath->query('//div[contains(attribute::class, "wrapper-img-caption")]') as $e) {
            $row=[];
            $ti = $e->getElementsByTagName('h4');
            foreach($ti as $t) {
                $title=$t->nodeValue;
                $row['title']=$title;
            }
            $anchor = $e->getElementsByTagName("a");
            foreach($anchor as $a) {
                if($href=$a->getAttribute('href')) {
                    $row['url']=$href;
                    $row['md5url']=md5($row['url']);
                    break;
                }
            }
            $img = $e->getElementsByTagName("img");
            foreach($img as $i) {
                $row['thumbnail']=$i->getAttribute('src');
            }
            $date = $e->getElementsByTagName("span");
            foreach($date as $d) {
                //$row['raw_date'] = $d->nodeValue;
                $row['date'] = $this->niceDate($d->nodeValue);
            }
            $row['section']=$section_name;
            $row['source']='jawapos';
            $content = $this->getArticle($row['url']);
            $row['image']=$content['image'];
            $row['content']=$content['content'];
            $data[]=$row;
        }
        return $data;
        

    }
    
    public function niceDate($raw) {
        //13/11/2018, 21:01 WIB
        $raw = trim($raw);
        $raw = str_replace(",", '', $raw);
        $raw= explode(" ", $raw);
        //$date = explode("/",$raw[0]);
        $month = $this->getMonth($raw[1]);
        $time = explode(":",$raw[3]);
        /*
         * array:5 [
  0 => "09"
  1 => "Desember"
  2 => "2018"
  3 => "14:45:59"
  4 => "WIB"
]

         */
        //dd($raw);
        $date = Carbon::create(@$raw[2], $month, $raw[0], @$time[0], @$time[1], 0, 'Asia/Jakarta');
        return $date->toW3cString();
    }

    public function getArticle($url)
    {
        $return = [
            'content'=>"",
            'image'=>""
        ];
        $raw = Spider::getContent($url, 10000);
        $x = str_replace("&nbsp;", " ", $raw);
        $x = str_replace("&lrm;", " ", $x);
        $x = mb_convert_encoding($x, 'HTML-ENTITIES', 'UTF-8');

        $x = str_replace("&lrm;", " ", $x);

        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        if(!$x) {
            return $return;
        }
        $document->loadHTML($x);
        libxml_clear_errors();
        
        
        $xpath = new \DOMXPath($document);
        
        foreach ($xpath->query('//figure[contains(attribute::class, "article-img")]') as $e) {
            $img=$e->getElementsByTagName("img");
            foreach($img as $i) {
                $return['image']=$i->getAttribute('src');
            }
        }
        
        foreach ($xpath->query('//article') as $e) {
            $raw=$e->C14N();
            $raw=str_replace("<a","<span",$raw);
            $raw= str_replace("</a>", "</span>", $raw);
            $text = \Html2Text\Html2Text::convert($raw);
            $return['content']=trim($text);
            
        }
        
        return $return;
    }
    
    public function getMonth($string)
    {
        $indonesianMonth = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        $flip = array_flip($indonesianMonth);
        
        $indonesianMonth2 = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        $flip2 = array_flip($indonesianMonth2);
        //dump($flip);
        try {
            $a= $flip[$string]??$flip2[$string]??1;
        } catch (\Exception $e) {
            dump($string, $e->getMessage());
        }
        return $a??1;
    }

}
