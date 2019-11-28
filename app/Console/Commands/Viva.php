<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Spider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class Viva extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:viva';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Viva';

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
//        $a=$this->getArticle('https://www.viva.co.id/berita/bisnis/1095292-sejahterakan-kawasan-ri-dorong-negara-apec-manfaatkan-ekonomi-digital');
//        dd($a);
        $data=$this->getArticles('berita/bisnis','bisnis');
        //$data= array_merge($data,$data2,$data3);
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp(@$b[$sortby], @$a[$sortby]);
        });
        dump($data);
        
        News::saveLatest('viva', $data);
        News::saveDaily('viva', $data);
    }

    public function getArticles($section, $section_name) {
        $url="https://www.viva.co.id/".$section;
        $raw = Spider::getContent($url);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTML($raw);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($document);
        $data=[];
        foreach ($xpath->query('//div[contains(attribute::class, "article-list")]') as $container) {
            
            $articles = $container->getElementsByTagName('li');
            foreach($articles as $e) {
//dump($e->nodeValue);
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
                    $row['thumbnail']=$i->getAttribute('data-original');
                    $row['image']= str_replace("325_183", "665_374", $row['thumbnail']);
                }
                
                $row['source']='viva';
                $row['section']=$section_name;
                $content = $this->getArticle($row['url']);
                $row['content']=$content['content'];
                $row['raw_date']=$content['raw_date'];
                $row['date']=$content['date'];
                $data[]=$row;
            }
        }
        return $data;
        

    }
    
    public function niceDate($raw) {
        $origin=$raw;
        //13/11/2018, 21:01 WIB
        $raw = trim($raw);
        $raw= explode(", ", $raw);
        if(!isset($raw[1])) {
            $raw=explode(" ",$raw[0]);
        } else {
            $raw= explode(" ", $raw[1]);
        }
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
        
        $article=$document->getElementById("article-detail-content");
        if(!$article) {
            return $return;
        }
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->appendChild($doc->importNode($article, true));
        
        $xpath = new \DOMXPath($doc);
        
        foreach ($xpath->query('//div') as $d) {
            if($d->getAttribute('id')!='article-detail-content') {
                $d->parentNode->removeChild($d);
            }
        }
        foreach ($xpath->query('//script') as $e) {
            $e->parentNode->removeChild($e);
        }
        foreach ($xpath->query('//img') as $e) {
            $e->parentNode->removeChild($e);
        }
        //$olah = $doc->saveHTML($doc->documentElement);
        
        $raw=$doc->C14N();
        $raw=str_replace("<a","<span",$raw);
        $raw= str_replace("</a>", "</span>", $raw);
        $text = \Html2Text\Html2Text::convert($raw);
        $return['content']=trim($text);
        
        
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//div[contains(attribute::class, "leading-date")]') as $container) {
            $date_elements = $container->getElementsByTagName('div');
            foreach($date_elements as $d) {
                if($d->getAttribute('class') == 'date') {
                    $return['raw_date']=trim($d->nodeValue);
                    $return['date']=$this->niceDate($return['raw_date']);
                }
            }
            
            //$return['date']=$this->niceDate($return['raw_date']);
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
