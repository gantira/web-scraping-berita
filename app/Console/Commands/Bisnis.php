<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Spider;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class Bisnis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:bisnis';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bisnis';

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
        /*$check=$this->getArticle('http://finansial.bisnis.com/read/20181130/9/865004/opini-saatnya-sektor-jasa-jadi-penyelamat-defisit-neraca-perdagangan');
        dd($check);*/

        $data=$this->getArticles('ekonomi','ekonomi');
        
        //dd($data);
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp(@$b[$sortby], @$a[$sortby]);
        });
        
        News::saveLatest('bisnis', $data);
        News::saveDaily('bisnis', $data);
    }

    public function getArticles($section, $section_name) {
        $url="https://finansial.bisnis.com/".$section;
        //dump($url);
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
        foreach ($xpath->query('//div') as $d) {
            if($d->getAttribute('class')=='full gallery type2') {
                $d->parentNode->removeChild($d);
            }
        }
        foreach ($xpath->query('//ul[contains(attribute::class, "list-news")]') as $container) {
            $articles = $container->getElementsByTagName('li');
            foreach($articles as $e) {
                $row=[];
                $doc = new \DOMDocument('1.0', 'UTF-8');
                $doc->appendChild($doc->importNode($e, true));
                $xpath = new \DOMXPath($doc);
                foreach ($xpath->query('//div') as $d) {
                    if($d->getAttribute('class')=='full gallery type2' || $d->getAttribute('class')=='banner') {
                        $d->parentNode->removeChild($d);
                    }
                }
                if($xpath->query('//h2')->length > 0){
                    foreach($xpath->query('//h2') as $t) {
                        $title=trim($t->nodeValue);
                        $row['title']=$title;
                        $anchor = $t->getElementsByTagName("a");
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
                    }
                    //dd($doc);
                    $content = $this->getArticle($row['url']);
                    if(!@$content['content']) {
                        continue;
                    }
                    //dump($content);
                  
                    $row['thumbnail'] = $content['thumbnail']; 
                    $row['image'] = $content['image'];   
                    
                    $row['source']='bisnis';
                    $row['section']=$section_name;
                    $row['content']=$content['content'];
                    $row['raw_date']=$content['raw_date'];
                    $row['date']=$content['date'];
                    $data[] = $row;
                }
            }
        }
        return $data;
    }

    public function getContent($document)
    {
        $xpath = new \DOMXPath($document);
        $chekArticle = $xpath->query("//div[contains(attribute::class, 'col-sm-10')]");
        //dd($chekArticle->length);
        $text = '';
        if($chekArticle->length > 0){
            foreach ($xpath->query("//div[contains(attribute::class, 'col-sm-10')]") as $article) {
                if(!$article) {
                    return $return;
                }
                // dd($article->nodeValue);
                $doc = new \DOMDocument('1.0', 'UTF-8');
                $doc->appendChild($doc->importNode($article, true));
                $xpath = new \DOMXPath($doc);
                //dd($xpath);
                foreach ($xpath->query('//div') as $d) {
                    if($d->getAttribute('class')=='photo-details' || $d->getAttribute('class')=='tags') {
                        $d->parentNode->removeChild($d);
                    }
                }
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
        foreach ($xpath->query('//div[contains(attribute::class, "author")]') as $container) {
            $raw_date = trim($container->nodeValue);
            $date = $this->niceDate($raw_date);
        }
        return $time[] = [$raw_date, $date];
    }

    public function getImages($document)
    {
        $xpath = new \DOMXPath($document);
        $image = $xpath->query('//meta[contains(attribute::property, "og:image")]')[0]->getAttribute('content');
        //dd($image);
        $thumbnail = str_replace("w=600", "w=218", $image);
        //dd($thumbnail);
        return $images[] = [$thumbnail, $image];
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
        
        $content =$this->getContent($document);
        if(!$content){
            dump("-------break and continue-------");
            return $return;
        }
        $return['content'] = $content;
        
        $time = $this->getTime($document); 
        //dd($time);
        $return['raw_date']=$time[0];
        $return['date']=$time[1];

        $images = $this->getImages($document);
        //dd($images);
        $return['thumbnail']=$images[0];
        $return['image']=$images[1];
        
        return $return;
    }
    
    public function niceDate($raw) {
        //Hadijah Alaydrus | 15 Maret 2019 11:00 WIB
        $raw = explode("|", $raw);
        $raw = explode(" ", trim($raw[1]));
        //dd($raw);
        $month = $this->getMonth($raw[1]);
        //dd($month);

        $time = explode(":",$raw[3]);
        
        $date = Carbon::create($raw[2], $month, $raw[0], $time[0], $time[1], 0, 'Asia/Jakarta');
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


