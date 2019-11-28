<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Spider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class BBCinternational extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:bbcinternational';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'BBC International';

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
        /*$check=$this->getArticle('http://www.bbc.com/capital/story/20181211-why-you-shouldnt-lie-to-your-children-about-santa');
        dd($check);*/

        $data = $this->getArticles('news','news');
        //dd($data);
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp($b[$sortby], $a[$sortby]);
        });
        
        News::saveLatest('bbcinternational', $data);
        News::saveDaily('bbcinternational', $data);
    }
    
    public function getArticles($section,$section_name)
    {
        $index_url="https://www.bbc.com/".$section;
        $raw = Spider::getContent($index_url);
        $x = str_replace("&nbsp;", " ", $raw);
        $x = str_replace("&lrm;", " ", $x);
        $x = mb_convert_encoding($x, 'HTML-ENTITIES', 'UTF-8');

        $x = str_replace("&lrm;", " ", $x);
        if(!$x) {
            dump("error ",$start,$section,$section_name,$index_url);
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTML($x);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($document);
        $data=[];
        foreach ($xpath->query('//div[contains(attribute::class, "gs-c-promo nw-c-promo gs-o-faux-block-link gs-u-pb gs-u-pb+@m nw-p-default")]') as $e) {
            $row=[];
            $t = $e->getElementsByTagName('h3');
            foreach($t as $ti) {
                //dd($ti->getAttribute('class'));
                if(strpos($ti->getAttribute('class'), 'gs-c-promo-heading__title') == 'true'){
                    $row['title'] = $ti->nodeValue;   
                }
            }
            //dump($row['title']);
            $u = $e->getElementsByTagName('a');
            foreach($u as $ur) {
                if(strpos($ur->getAttribute('class'), 'gs-c-promo-heading') == 'true'){
                    $href = $ur->getAttribute('href');
                    if(strpos($href, 'https://') == 'true' || strpos($href, 'http://') == 'true'){
                        $row['url']=$href;
                    }else {
                        $row['url']="https://bbc.com".$href;
                    }
                    $row['md5url']=md5($row['url']);
                    //dump($row['url']);
                    break;
                }
            }
            if(!@$row['url']) {
                continue;
            }
            $content = $this->getArticle($row['url']);
            if(!@$content['content']) {
                continue;
            }
            $i = $e->getElementsByTagName('img');
            foreach($i as $img) {
                $thumbnail = $img->getAttribute('src');
                if(strpos($thumbnail, 'data:image/gif') == 'true'){
                    $thumbnail_raw = $img->getAttribute('data-src');
                    $thumbnail = str_replace('{width}','320',$thumbnail_raw);
                    $image = str_replace('{width}','950',$thumbnail_raw);
                }else {
                    $parsed = $this->get_string_between($thumbnail, 'news/', '/');
                    $image=str_replace($parsed,'950',$thumbnail);
                }
            }
            //dump($image);
            
            $row['thumbnail'] = $thumbnail; 
            $row['image'] = $image;   
            
            $row['source']='bbcinternational';
            $row['section']=$section_name;
            $row['content']=$content['content'];
            $row['raw_date']=$content['raw_date'];
            $row['date']=$content['date'];
            $data[] = $row;
            //dump($row);
        }
        return $data;
    }

    public function get_string_between($string, $start, $end){
        $string = ' ' . $string;
        $ini = strpos($string, $start);
        if ($ini == 0) return '';
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }

    public function getTime($document)
    {
        $xpath = new \DOMXPath($document);
        if($xpath->query('//div[contains(attribute::class, "story-body__mini-info-list-and-share-row")]')->length > 0){
            foreach ($xpath->query('//div[contains(attribute::class, "story-body__mini-info-list-and-share-row")]') as $e) {
                $d = $e->getElementsByTagName('div');
                foreach($d as $dt) {
                    if($dt->getAttribute('class')=='date date--v2') {
                        $dateCarbon = Carbon::createFromTimestamp($dt->getAttribute('data-seconds'),'Asia/Jakarta');
                        $date=$dateCarbon->toW3cString();
                    }
                }
            }
        }else if($xpath->query('//li[contains(attribute::class, "story-info__item story-info__item--time")]')->length > 0) {
            foreach ($xpath->query('//li[contains(attribute::class, "story-info__item story-info__item--time")]') as $e) {
                $d = $e->getElementsByTagName('time');
                foreach($d as $dt) {
                    $dateCarbon = Carbon::createFromTimestamp($dt->getAttribute('data-timestamp'),'Asia/Jakarta');
                    $date=$dateCarbon->toW3cString();
                }
            }
        }
        return $time = $date;
    }

    public function getContent($document)
    {
        $xpath = new \DOMXPath($document);
        $text = '';
        if($xpath->query("//div[contains(attribute::class, 'story-body__inner')]")->length > 0){
            foreach ($xpath->query("//div[contains(attribute::class, 'story-body__inner')]") as $article) {
                if(!$article) {
                    return $return;
                }
                // dd($article->nodeValue);
                $doc = new \DOMDocument('1.0', 'UTF-8');
                $doc->appendChild($doc->importNode($article, true));
                $xpath = new \DOMXPath($doc);
                //dd($xpath);
                foreach ($xpath->query('//figure') as $d) {
                    $d->parentNode->removeChild($d);
                }
                foreach ($xpath->query('//div') as $d) {
                    if($d->getAttribute('class')!='story-body__inner') {
                        $d->parentNode->removeChild($d);
                    }
                }
                foreach ($xpath->query('//ul[contains(attribute::class, "story-body__unordered-list")]') as $d) {
                    $d->parentNode->removeChild($d);
                }
                foreach ($xpath->query('//ul') as $d) {
                    $doc_ul = new \DOMDocument('1.0', 'UTF-8');
                    $doc_ul->appendChild($doc_ul->importNode($d, true));
                    $xpath_ul = new \DOMXPath($doc_ul);
                    $xpath_ul = $xpath_ul->query('//a')->length;
                    if($xpath_ul > 0){
                        $d->parentNode->removeChild($d);
                    }
                }
                foreach ($xpath->query('//a') as $d) {
                    if($d->getAttribute('class')=='story-body__link') {
                        $d->parentNode->removeChild($d);
                    }
                }
                foreach ($xpath->query('//script') as $d) {
                    $d->parentNode->removeChild($d);
                }
                foreach ($xpath->query('//img') as $d) {
                    $d->parentNode->removeChild($d);
                }
                
                $raw=$doc->C14N();
                $raw=str_replace("<a","<span",$raw);
                $raw= str_replace("</a>", "</span>", $raw);
                $text = \Html2Text\Html2Text::convert($raw);
                //dd($text);
            }
        }else if($xpath->query("//div[contains(attribute::class, 'story-body sp-story-body gel-body-copy')]")->length > 0) {
            foreach ($xpath->query("//div[contains(attribute::class, 'story-body sp-story-body gel-body-copy')]") as $article) {
                if(!$article) {
                    return $return;
                }
                //dd($article->nodeValue);
                $doc = new \DOMDocument('1.0', 'UTF-8');
                $doc->appendChild($doc->importNode($article, true));
                $xpath = new \DOMXPath($doc);
                //dd($xpath);
                foreach ($xpath->query('//figure') as $d) {
                    $d->parentNode->removeChild($d);
                }
                foreach ($xpath->query('//div') as $d) {
                    if($d->getAttribute('class')!='story-body sp-story-body gel-body-copy') {
                        $d->parentNode->removeChild($d);
                    }
                }
                foreach ($xpath->query('//ul[contains(attribute::class, "story-body__unordered-list")]') as $d) {
                    $d->parentNode->removeChild($d);
                }
                foreach ($xpath->query('//ul') as $d) {
                    $doc_ul = new \DOMDocument('1.0', 'UTF-8');
                    $doc_ul->appendChild($doc_ul->importNode($d, true));
                    $xpath_ul = new \DOMXPath($doc_ul);
                    $xpath_ul = $xpath_ul->query('//a')->length;
                    if($xpath_ul > 0){
                        $d->parentNode->removeChild($d);
                    }
                }
                foreach ($xpath->query('//a') as $d) {
                    if($d->getAttribute('class')=='story-body__link') {
                        $d->parentNode->removeChild($d);
                    }
                }
                foreach ($xpath->query('//script') as $d) {
                    $d->parentNode->removeChild($d);
                }
                foreach ($xpath->query('//img') as $d) {
                    $d->parentNode->removeChild($d);
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
        $return['date']=$time;
        
        return $return;
    }
}

