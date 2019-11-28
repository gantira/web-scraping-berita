<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Spider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class Liputan6 extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:liputan6';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Liputan 6';

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
//        $a=$this->getArticle('https://www.liputan6.com/bisnis/read/3694929/tembus-909-km-jalan-perbatasan-di-papua-tumbuhkan-kawasan-ekonomi');
//        dd($a);
        $data=$this->getArticles('bisnis','bisnis');
        //$data= array_merge($data,$data2,$data3);
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp(@$b[$sortby], @$a[$sortby]);
        });
        dump($data);
        
        News::saveLatest('liputan6', $data);
        News::saveDaily('liputan6', $data);
    }

    public function getArticles($section, $section_name) {
        $url="https://www.liputan6.com/".$section.'/indeks';
        dump($url);
        $raw = Spider::getContent($url);
        if(!$raw) {
            return [];
        }
        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTML($raw);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($document);
        $data=[];
        foreach ($xpath->query('//div[contains(attribute::class, "articles--list articles--list_rows")]') as $container) {
            
            $articles = $container->getElementsByTagName('article');
            foreach($articles as $e) {
//dump($e->nodeValue);
                $row=[];
                $ti = $e->getElementsByTagName('h4');
                foreach($ti as $t) {
                    //dump($t->nodeValue);
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
                if(!@$row['url']) {
                    continue;
                }
                
                $img = $e->getElementsByTagName("img");
                $row['thumbnail']='';
                $row['image']='';
                foreach($img as $i) {
                    $row['thumbnail']=$i->getAttribute('src');
                }
                
                $times = $e->getElementsByTagName("time");
                foreach($times as $t) {
                    $row['date']=$t->getAttribute('datetime');
                }
                
                $row['source']='liputan6';
                $row['section']=$section_name;
                $content = $this->getArticle($row['url']);
                $row['content']=$content['content'];
                $row['image']=$content['image'];
                $data[]=$row;
            }
        }
        return $data;
        

    }
    
    public function niceDate($raw) {
        //13/11/2018, 21:01 WIB
        $raw = trim($raw);
        $raw= explode(", ", $raw);
        $raw= explode(" ", $raw[1]);
        $time = explode(":",$raw[4]);
        $month=$this->getMonth($raw[1]);
        
        $date = Carbon::create(@$raw[2], $month, $raw[0], @$time[0], @$time[1], 0, 'Asia/Jakarta');
        return $date->toW3cString();
    }

    public function getArticle($url)
    {
        dump($url);
        $return = [
            'content'=>"",
            'image'=>''
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
        foreach ($xpath->query('//div[contains(attribute::class, "article-content-body__item-content")]') as $container) {
            $raw=$container->C14N();
            $raw=str_replace("<a","<span",$raw);
            $raw= str_replace("</a>", "</span>", $raw);
            $text = \Html2Text\Html2Text::convert($raw);
            $return['content']=trim($text);    
        }
        
        $imgs = $document->getElementsByTagName('img');
        foreach($imgs as $img) {
            if($img->getAttribute('class')=='js-lazyload read-page--photo-gallery--item__picture-lazyload') {
                $return['image']=$img->getAttribute('data-src');
                break;
            }
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
