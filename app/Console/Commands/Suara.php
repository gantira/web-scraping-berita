<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Spider;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class Suara extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:suara';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Suara';

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
        /*$check=$this->getArticle('https://www.suara.com/bisnis/2018/11/29/180948/ini-alasan-kirim-uang-lewat-truemoney-lebih-mudah');
        dd($check);*/

        $data=$this->getArticles('indeks/terkini/bisnis','bisnis');
        //dd($data);
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp(@$b[$sortby], @$a[$sortby]);
        });
        
        News::saveLatest('suara', $data);
        News::saveDaily('suara', $data);
    }

    public function getArticles($section, $section_name) {
        $url="https://www.suara.com/".$section;
        //dd($url);
        $raw = Spider::getContent($url);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTML($raw);
        libxml_clear_errors();
        $xpath = new \DOMXPath($document);
        $data = [];
        foreach ($xpath->query('//li[contains(attribute::class, "item-outer")]') as $e) {
            $row=[];
                        
            $anchor = $e->getElementsByTagName("a");
            foreach($anchor as $a) {
                if($href=$a->getAttribute('href')) {
                    $url=$href;
                    $md5url=md5($url);
                    break;
                }
            }
            if(!@$url) {
                continue;
            }
            $content = $this->getArticle($url);
            if(!@$content['content']) {
                continue;
            }
            //dump($content);
            $row['title'] = $content['title'];
            $row['url'] = $url;
            $row['md5url'] = $md5url;
            $row['thumbnail'] = $content['thumbnail']; 
            $row['image'] = $content['image'];   
            $row['source']='suara';
            $row['section']=$section_name;
            $row['content']=$content['content'];
            $row['raw_date']=$content['raw_date'];
            $row['date']=$content['date'];
            $data[] = $row;
        }
        return $data;
    }

    public function getContent($document)
    {
        $xpath = new \DOMXPath($document);
        $chekArticle = $xpath->query("//article[contains(attribute::class, 'content-article')]");
        //dd($chekArticle->length);
        $text = '';
        if($chekArticle->length > 0){
            foreach ($xpath->query("//article[contains(attribute::class, 'content-article')]") as $article) {
                if(!$article) {
                    return $return;
                }
                //dd($article->nodeValue);
                $doc = new \DOMDocument('1.0', 'UTF-8');
                $doc->appendChild($doc->importNode($article, true));
                $xpath = new \DOMXPath($doc);
                //dd($xpath);
                foreach ($xpath->query('//div') as $d) {
                    if($d->getAttribute('class')=='new_bacajuga') {
                        $d->parentNode->removeChild($d);
                    }
                }
                //dd($xpath);
                foreach ($xpath->query('//script') as $e) {
                    $e->parentNode->removeChild($e);
                }
                foreach ($xpath->query('//img') as $e) {
                    $e->parentNode->removeChild($e);
                }
                
                $raw=$doc->C14N();
                $raw=str_replace("<a","<span",$raw);
                $raw= str_replace("</a>", "</span>", $raw);
                $text = \Html2Text\Html2Text::convert($raw);
                //dd($text);
            }
        }
        return trim($text);
    }

    public function getTime($document)
    {
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//time[contains(attribute::itemprop, "datePublished")]') as $container) {
            $raw_date=trim($container->nodeValue);
            //dd($raw_date);
            $date=$this->niceDate($raw_date);
        }
        return $time[] = [$raw_date, $date];
    }

    public function getImages($document)
    {
        $xpath = new \DOMXPath($document);
        $image = $xpath->query('//meta[contains(attribute::property, "og:image")]')[0]->getAttribute('content');
        //dd($image);
        $thumbnail = str_replace("653x366", "336x188", $image);
        //dd($thumbnail);
        return $images[] = [$thumbnail, $image];
    }

    public function getTitle($document)
    {
        $xpath = new \DOMXPath($document);
        $title = $xpath->query('//meta[contains(attribute::property, "og:title")]')[0]->getAttribute('content');
        //dd($title);
        return $title;
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
        //dd($document);
        $content =$this->getContent($document);
        $return['content'] = $content;
        //dd($return['content']);
        
        $time = $this->getTime($document); 
        //dd($time);
        $return['raw_date']=$time[0];
        $return['date']=$time[1];

        $images = $this->getImages($document);
        //dd($images);
        $return['thumbnail']=$images[0];
        $return['image']=$images[1];

        $title = $this->getTitle($document);
        $return['title']=$title;

        return $return;
    }
    
    public function niceDate($raw) {
        //Kamis, 29 November 2018 | 18:09 WIB
        $raw = trim($raw);
        $raw = explode(" | ", $raw);
        //dd($raw);
        $date = explode(" ", $raw[0]);
        //dd($date);
        $month = $this->getMonth($date[2]);
        //dd($month);
        $time = str_replace(' WIB', '', $raw[1]);
        $time = explode(":", $time);
        
        $date = Carbon::create($date[3], $month, $date[1], $time[0], $time[1], 0, 'Asia/Jakarta');
        return $date->toW3cString();
    }

    public function getMonth($string)
    {
        $month = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        $flip = array_flip($month);
        $a= $flip[$string];
        return $a;
    }
}



