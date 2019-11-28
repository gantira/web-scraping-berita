<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Spider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class Detik extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:detik';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detik.com';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
//        $x=$this->getArticle('https://news.detik.com/berita/d-4303947/mega-serang-prabowo-koalisi-membela');
//        dd($x);
        $finance = $this->getArticles(0, 'finance', 'finance');
        $news = $this->getArticles(0, 'news', 'news');
        $data=[];
        foreach($news as $n) {
            $row=$n;
            $content = $this->getArticle($n['url']);
            $row['content']=$content['content'];
            $row['image']=$content['image'];
            $row['thumbnail']=$content['thumbnail'];
            $data[]=$row;
        }
        foreach($finance as $n) {
            $row=$n;
            $content = $this->getArticle($n['url']);
            $row['content']=$content['content'];
            $row['image']=$content['image'];
            $row['thumbnail']=$content['thumbnail'];
            $data[]=$row;
        }
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp($b[$sortby], $a[$sortby]);
        });
        
        \App\Console\News::saveLatest('detik', $data);
        \App\Console\News::saveDaily('detik', $data);
    }
    
    public function getArticles($start, $section, $section_name)
    {
        $data=[];
        $index_url="https://".$section.".detik.com/indeks";
        $raw = Spider::getContent($index_url);
        $x = str_replace("&nbsp;", " ", $raw);
        $x = str_replace("&lrm;", " ", $x);
        $x = mb_convert_encoding($x, 'HTML-ENTITIES', 'UTF-8');

        $x = str_replace("&lrm;", " ", $x);
        if(!$x) {
            dump("error ",$x,$start,$section,$section_name,$index_url);
            return $data;
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTML($x);
        libxml_clear_errors();

        
        //$a = $document->getElementById("indeks-container");        
        $a=$document;
        $elements=$a->getElementsByTagName("article");
        foreach($elements as $e) {
            $row=[];
            $temp = $e->getElementsByTagName("h2");
            foreach($temp as $t) {
                $row['title'] = $t->nodeValue;
                break;
            }
            $temp = $e->getElementsByTagName("a");
            foreach($temp as $t) {
                $row['url'] = $t->getAttribute('href');
                $row['md5url'] = md5($row['url']);
                break;
            }
            $date = $e->getElementsByTagName("span");
            foreach($date as $d) {
                //if($d->getAttribute('class')=='labdate f11 mt5 mb5') {
                    $row['date'] = $this->niceDate($d->nodeValue);
                    if(!$row['date']) {
                        dump($d->nodeValue, 'no date ', $row['title']);
                    }
                    break;
                //}
            }
            $row['section']=$section_name;
            $row['source']="detik";
            if(strlen($row['url']) || strlen($row['content'])) {
                $data[]=$row;
            }
        }
        return $data;
    }
    
    public function getArticle($url)
    {
        $return = [
            'content'=>"",
            'image'=>"",
            'thumbnail'=>''
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
        foreach ($xpath->query('//div[contains(attribute::class, "pic_artikel")]') as $pc) {
            $img = $pc->getElementsByTagName("img");
            foreach($img as $i) {
                $img_url = $i->getAttribute("src");
                $return['image']=$img_url;
                $return['thumbnail']=$img_url.'&w=300';
            }
        }
        
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//script') as $e) {
            $e->parentNode->removeChild($e);
        }
        foreach ($xpath->query('//table[contains(attribute::class, "linksisip")]') as $e) {
            $e->parentNode->removeChild($e);
        }
        foreach ($xpath->query('//div[contains(attribute::class, "pic")]') as $e) {
            $e->parentNode->removeChild($e);
        }
        foreach ($xpath->query('//div[contains(attribute::class, "detail_tag")]') as $e) {
            $e->parentNode->removeChild($e);
        }
        foreach ($xpath->query('//div[contains(attribute::class, "multi-nav")]') as $e) {
            $e->parentNode->removeChild($e);
        }
        
        $olah = $document->saveHTML($document->documentElement);
        
        
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTML($olah);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//div[contains(attribute::class, "detail_text")]') as $e) {
            $raw=$e->C14N();
            $raw=str_replace("<a","<span",$raw);
            $raw= str_replace("</a>", "</span>", $raw);
            $text = \Html2Text\Html2Text::convert($raw);
            $return['content']=trim($text);
        }
        return $return;
    }
    
    public function niceDate($string)
    {
        $explode=explode(' ', $string);
        $date=@$explode[1];
        $month=$this->getMonth(@$explode[2]);
        $year=str_replace(',','',@$explode[3]);
        $times = explode(":", @$explode[4]);
        try {
            $date = Carbon::create($year, $month, @$date, @$times[0], @$times[1], 0, 'Asia/Jakarta');
        } catch (\Exception $e) {
            return '';
        }
        
        return $date->toW3cString();
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
