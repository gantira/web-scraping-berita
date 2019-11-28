<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Spider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class Idntimes extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:idntimes';

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
//        $a=$this->getArticle('https://www.idntimes.com/business/economy/helmi/kominfo-batal-cabut-izin-first-media-dan-bolt/full');
//        dd($a);
        $data=$this->getArticles(0, 'business', 'bisnis');
//        dd($data);
        //$data= array_merge($data,$data2,$data3);
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp(@$b[$sortby], @$a[$sortby]);
        });
        dump($data);
        
        News::saveLatest('idntimes', $data);
        News::saveDaily('idntimes', $data);
    }

    public function getArticles($page, $section, $section_name) {
        $url="https://www.idntimes.com/ajax/category/".$section."?page=" . $page;
        $raw = Spider::getContent($url);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTML($raw);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($document);
        $data=[];
        foreach ($xpath->query('//div[contains(attribute::class, "box-list")]') as $e) {
            
//            $articles = $container->getElementsByTagName('li');
//            foreach($articles as $e) {
//dump($e->nodeValue);
                $row=[];
                
                $ti = $e->getElementsByTagName('h2');
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
                $time = $e->getElementsByTagName('time');
                foreach($time as $tm) {
                    $row['raw_date'] = $tm->getAttribute('datetime');
                    $row['date'] = $this->niceDate($row['raw_date']);
                }
                
                $img = $e->getElementsByTagName("img");
                $row['thumbnail']='';
                $row['image']='';
                foreach($img as $i) {
                    $row['thumbnail']=$i->getAttribute('src');
                    $row['image']= str_replace("300x200", "600x400", $row['thumbnail']);
                }
                
                $row['source']='idntimes';
                $row['section']=$section_name;
                $content = $this->getArticle($row['url']);
                $row['content']=$content;
//                $row['raw_date']=$content['raw_date'];
//                $row['date']=$content['date'];
                $data[]=$row;
//            }
        }
        return $data;
        

    }
    
    public function niceDate($raw) {
        //13/11/2018, 21:01 WIB
        $raw = trim($raw);
        $raw= explode("-", $raw);
        $date = Carbon::create(@$raw[0], $raw[1], $raw[2], 0, 0, 0, 'Asia/Jakarta');
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
        
        $article=$document->getElementById("article-content");
        if(!$article) {
            return $return;
        }
        
        $par = $article->getElementsByTagName('p');
        $content="";
        foreach($par as $p) {
            $val = trim($p->nodeValue);
            if(substr($val,0,9)!="Baca Juga") {
                $content .= $val . "\n";
            }
        }
        return $content;
        
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
