<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Spider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class Grid extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:grid';

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
//        $a=$this->getArticle('http://www.grid.id/read/041242636/rosa-meldianti-tinggalkan-polda-metro-jaya-lebih-dulu-dari-dewi-perssik');
//        dd($a);
        $data=$this->getArticles('berita/bisnis','bisnis');
//        dd($data);
        //$data= array_merge($data,$data2,$data3);
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp(@$b[$sortby], @$a[$sortby]);
        });
        dump($data);
        
        News::saveLatest('grid', $data);
        News::saveDaily('grid', $data);
    }

    public function getArticles($section, $section_name) {
        $url="http://www.grid.id/";
        $raw = Spider::getContent($url);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        if(!$raw) {
            return [];
        }
        $document->loadHTML($raw);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($document);
        $data=[];
        foreach ($xpath->query('//div[contains(attribute::class, "main__content--item")]') as $e) {
//            
//            $articles = $container->getElementsByTagName('li');
//            foreach($articles as $e) {
//dump($e->nodeValue);
                $row=[];
                $ti = $e->getElementsByTagName('a');
                foreach($ti as $t) {
                    if($t->getAttribute('class')=='main__content--title') {
                        $title=$t->nodeValue;
                        $row['title']=$title;
                        $href=$t->getAttribute('href');
                        $row['url']=$href;
                        $row['md5url']=md5($row['url']);
                    } else if($t->getAttribute('class')=='main__content--note') {
                        $row['section']=strtolower(trim($t->nodeValue));
                    }
                    //dump($t->nodeValue);
                    
                }
                $row['source']='grid';
                if(!@$row['url']) {
                    continue;
                }
                
                $img = $e->getElementsByTagName("img");
                $row['thumbnail']='';
                $row['image']='';
                foreach($img as $i) {
                    $row['thumbnail']=$i->getAttribute('src');
                    $row['image']= str_replace("345x242", "700x465", $row['thumbnail']);
                }
                
                
                $content = $this->getArticle($row['url']);
                $row['content']=$content['content'];
                $row['raw_date']=$content['raw_date'];
                $row['date']=$content['date'];
                $data[]=$row;
//            }
        }
        return $data;
        

    }
    
    public function niceDate($raw) {
        /*
    0 => "Senin,"
    1 => "19"
    2 => "November"
    3 => "2018"
    4 => "|"
    5 => "12:32"
    6 => "WIB"

         */
        $raw = trim($raw);
        $raw= explode(" ", $raw);
        $time = explode(":",$raw[5]);
        $month=$this->getMonth($raw[2]);
        
        $date = Carbon::create(@$raw[3], $month, $raw[1], @$time[0], @$time[1], 0, 'Asia/Jakarta');
        return $date->toW3cString();
    }

    public function getArticle($url)
    {
        $url = $url . '?&page=all';
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
        foreach ($xpath->query('//script') as $e) {
            $e->parentNode->removeChild($e);
        }
        foreach ($xpath->query('//img') as $e) {
            $e->parentNode->removeChild($e);
        }
        
        foreach ($xpath->query('//span[contains(attribute::class, "read__time pink")]') as $date) {
            $return['raw_date']=$date->nodeValue;
            $return['date']=$this->niceDate($return['raw_date']);
        }
        foreach ($xpath->query('//div[contains(attribute::class, "read__article")]') as $d) {
            $p=$d->getElementsByTagName('p');
            $content="";
            foreach($p as $ph) {
                $val = trim($ph->nodeValue);
                if(substr($val,0,9)!="Baca Juga") {
                    $content .= $val . "\n";
                }
            }
            $return['content']=trim($content);
            
        }
        return $return;
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
