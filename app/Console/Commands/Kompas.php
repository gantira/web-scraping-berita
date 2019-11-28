<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Spider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class Kompas extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:kompas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kompas';

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
//        $a=$this->getArticle('https://ekonomi.kompas.com/read/2018/11/18/090000126/cara-berkomunikasi-ini-membuat-anda-gampang-raih-sukses');
//        dd($a);
        $data=$this->getArticles('ekonomi','ekonomi');
        $data2=$this->getArticles('news','news');
        $data=array_merge($data,$data2);
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp(@$b[$sortby], @$a[$sortby]);
        });
        
        News::saveLatest('kompas', $data);
        News::saveDaily('kompas', $data);
    }

    public function getArticles($section, $section_name) {
        $url="https://indeks.kompas.com/".$section;
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
        foreach ($xpath->query('//div[contains(attribute::class, "latest--indeks")]') as $container) {
            dump($container->C14N());
            $articles = $container->getElementsByTagName('div');
            foreach($articles as $e) {
                if($e->getAttribute('class')!='article__list clearfix') {
                    continue;
                }
                $row=[];
                $ti = $e->getElementsByTagName('h3');
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
                
                $foto_url=explode('viva.co.id/foto/', $row['url']);
                if(count($foto_url)>1) {
                    continue;
                }
                $video_url=explode('viva.co.id/video/', $row['url']);
                if(count($video_url)>1) {
                    continue;
                }
                $img = $e->getElementsByTagName("img");
                $row['thumbnail']='';
                $row['image']='';
                foreach($img as $i) {
                    $row['thumbnail']=$i->getAttribute('src');
                    $row['image']= str_replace("230x152", "690x456", $row['thumbnail']);
                }
                
                $row['source']='kompas';
                $row['section']=$section_name;
                
                $divs = $e->getElementsByTagName('div');
                foreach($divs as $div) {
                    if($div->getAttribute('class')=='article__date') {
                        $row['raw_date']=$div->nodeValue;
                        $row['date']=$this->niceDate($row['raw_date']);
                    }
                }
                $content = $this->getArticle($row['url']);
                $row['content']=$content['content'];
//                $row['raw_date']=$content['raw_date'];
//                $row['date']=$content['date'];
                if(strlen($row['content'])) {
                    $data[]=$row;
                }
            }
        }
        return $data;
        

    }
    
    public function niceDate($raw) {
        //13/11/2018, 21:01 WIB
        $raw = trim($raw);
        $raw= explode(", ", $raw);
        
        $time = explode(" ",$raw[1]);
        $time = explode(":",$time[0]);
        $raw= explode("/", $raw[0]);
        $month=$this->getMonth($raw[1]);
        
        $date = Carbon::create(@$raw[2], $raw[1], $raw[0], @$time[0], @$time[1], 0, 'Asia/Jakarta');
        return $date->toW3cString();
    }

    public function getArticle($url)
    {
        dump($url);
        $return = [
            'content'=>"",
            'raw_date'=>'',
            'date'=>''
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
        foreach ($xpath->query('//div[contains(attribute::class, "read__content")]') as $container) {
            
            $doc = new \DOMDocument('1.0', 'UTF-8');
            $doc->appendChild($doc->importNode($container, true));

            $xpath = new \DOMXPath($doc);
//
            foreach ($xpath->query('//div') as $d) {
                if($d->getAttribute('class')!='read__content') {
                    $d->parentNode->removeChild($d);
                }
            }
            foreach ($xpath->query('//script') as $e) {
                $e->parentNode->removeChild($e);
            }
            foreach ($xpath->query('//img') as $e) {
                $e->parentNode->removeChild($e);
            }
//        
            $raw=$doc->C14N();
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
