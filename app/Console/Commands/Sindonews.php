<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Spider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class Sindonews extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:sindonews';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'sindonews';

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
//        $a=$this->getArticle('https://ekbis.sindonews.com/read/1355672/34/sampai-november-uji-operasi-bbm-satu-harga-capai-65-titik-1542520178');
//        dd($a);
        $data=$this->getArticles(8,'bisnis');
//        dd($data);
        //$data= array_merge($data,$data2,$data3);
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp(@$b[$sortby], @$a[$sortby]);
        });
        dump($data);
        
        News::saveLatest('sindonews', $data);
        News::saveDaily('sindonews', $data);
    }

    public function getArticles($section, $section_name) {
        $url="https://index.sindonews.com/index/".$section;
        $raw = Spider::getContent($url);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTML($raw);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($document);
        $data=[];
        foreach ($xpath->query('//div[contains(attribute::class, "indeks-news")]') as $container) {
            $doc = new \DOMDocument('1.0', 'UTF-8');
            $doc->appendChild($doc->importNode($container,true));
            //dump($doc->nodeValue);
            $path = new \DOMXPath($doc);
            $articles = $path->query('//div[contains(attribute::class, "indeks-rows")]');
            //dd($articles);
            foreach($articles as $e) {
                $row=[];
                $anchor = $e->getElementsByTagName("div");
                foreach($anchor as $a) {
                    if($a->getAttribute('class')=='indeks-title') {
                        $href = $a->getElementsByTagName('a');
                        foreach($href as $h) {
                            if($hrf=$h->getAttribute('href')) {
                                $row['url']=$hrf;
                                $row['md5url']=md5($row['url']);
                                $row['title']=$h->nodeValue;
                            break;
                        }
                        }
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
                
                $row['source']='sindonews';
                $row['section']=$section_name;
                
                $miniinfo = $e->getElementsByTagName('div');
                foreach($miniinfo as $mi) {
                    if($mi->getAttribute('class')=='mini-info') {
                        $li=$mi->getElementsByTagName('li');
                        foreach($li as $k=>$l) {
                            if($k==1) {
                                $row['raw_date']=$l->nodeValue;
                                $row['date']=$this->niceDate($row['raw_date']);
                            }
                        }
                    }
                }                
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
        $raw= explode(" ", $raw);
        $month=$this->getMonth($raw[2]);
        $time = explode(':',$raw[5]);
        $date = Carbon::create(@$raw[3], $month, $raw[1], @$time[0], @$time[1], 0, 'Asia/Jakarta');
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
        
        $content = $document->getElementById('content');
        if(!$content) {
            return $return;
        }
        $raw=$content->C14N();
        $raw=str_replace("<a","<span",$raw);
        $raw= str_replace("</a>", "</span>", $raw);
        $text = \Html2Text\Html2Text::convert($raw);
        $return['content']=trim($text);
        
        $figure = $document->getElementsByTagName('figure');
        foreach($figure as $f) {
            $imgs = $f->getElementsByTagName('img');
            foreach($imgs as $img) {
                $return['image'] = $img->getAttribute('src');
                continue;
            }
            continue;
        }
        return $return;
        
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
