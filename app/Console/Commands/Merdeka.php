<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Spider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class Merdeka extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:merdeka';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

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
//        $a=$this->getArticle('https://www.merdeka.com/uang/pelindo-1-bakal-sulap-3-pelabuhan-layaknya-bandara.html');
//        dd($a);
        $data=$this->getArticles('uang', 'ekonomi');
        $data2=$this->getArticles('peristiwa', 'news');
        //dd($data);
        $data= array_merge($data,$data2);
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp(@$b[$sortby], @$a[$sortby]);
        });
        dump($data);
        
        News::saveLatest('merdeka', $data);
        News::saveDaily('merdeka', $data);
    }

    public function getArticles($section, $section_name) {
        $url="https://www.merdeka.com/".$section;
        $raw = Spider::getContent($url);
        
        
        
        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTML($raw);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($document);
        $data=[];
        foreach ($xpath->query('//li[contains(attribute::class, "clearfix")]') as $e) {
            
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
                        $row['url']='https://www.merdeka.com'.$href;
                        $row['md5url']=md5($row['url']);
                        break;
                    }
                }
                if(!@$row['url']) {
                    continue;
                }
                $foto_url=explode('merdeka.com/foto/', $row['url']);
                if(count($foto_url)>1) {
                    continue;
                }
                $video_url=explode('merdeka.com/video/', $row['url']);
                if(count($video_url)>1) {
                    continue;
                }
                
                $img = $e->getElementsByTagName("img");
                $row['thumbnail']='';
                $row['image']='';
                foreach($img as $i) {
                    $row['thumbnail']=$i->getAttribute('src');
                    $row['image']= str_replace("140x70", "670x335", $row['thumbnail']);
                }
                
                $row['source']='merdeka';
                $row['section']=$section_name;
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
        //13/11/2018, 21:01 WIB
        $raw = trim($raw);
        $raw= explode(" ", $raw);
        $month=$this->getMonth($raw[2]);
        $time=explode(":",$raw[4]);
        $date = Carbon::create(@$raw[3], $month, $raw[1], $time[0], $time[1], 0, 'Asia/Jakarta');
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
        $data=[];
        foreach ($xpath->query('//div[contains(attribute::class, "mdk-body-paragpraph")]') as $e) {
        
            $par = $e->getElementsByTagName('p');
            $content="";
            foreach($par as $p) {
                $val = trim($p->nodeValue);
                if(substr($val,0,9)!="Baca Juga") {
                    $content .= $val . "\n";
                }
            }
            $return['content']=trim($content);
        }
        foreach ($xpath->query('//span[contains(attribute::class, "date-post")]') as $e) {
            $return['raw_date']=$e->nodeValue;
            $return['date']=$this->niceDate($return['raw_date']);
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
