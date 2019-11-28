<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Spider;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class Theguardian extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:theguardian';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reuters';

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
        /*$check=$this->getArticle('https://www.theguardian.com/food/2018/nov/26/revealed-restricting-breaks-keeps-poultry-industry-workers-living-in-fear');
        dd($check);*/

        $data=$this->getArticles('business/all','business');
        
        //dd($data);
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp(@$b[$sortby], @$a[$sortby]);
        });
        
        News::saveLatest('theguardian', $data);
        News::saveDaily('theguardian', $data);
    }

    public function getArticles($section, $section_name) {
        $url="https://www.theguardian.com/".$section;
        $raw = Spider::getContent($url);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTML($raw);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($document);
        //dd($document);
        foreach ($xpath->query('//div[contains(attribute::class, "fc-item__container")]') as $e) {
            $row=[];

            $docTitle = new \DOMDocument('1.0', 'UTF-8');
            $docTitle->appendChild($docTitle->importNode($e, true));
            $xpathTitle = new \DOMXPath($docTitle);
                
            foreach ($xpathTitle->query('//span[contains(attribute::class, "js-headline-text")]') as $ti) {
                $title = $ti->nodeValue;
            }
            $row['title']=$title;

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
            //dd($row);
            $content = $this->getArticle($row['url']);
            if(!@$content['content']) {
                continue;
            }
            //dd($content);
          
            $row['thumbnail'] = $content['thumbnail']; 
            $row['image'] = $content['image'];   
            
            $row['source']='theguardian';
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
        $chekArticle = $xpath->query("//div[contains(attribute::class, 'content__article-body from-content-api js-article__body')]");
        //dd($chekArticle->length);
        $text = '';
        if($chekArticle->length > 0){
            foreach ($xpath->query("//div[contains(attribute::class, 'content__article-body from-content-api js-article__body')]") as $article) {
                if(!$article) {
                    return $return;
                }
                //dd($article);
                $doc = new \DOMDocument('1.0', 'UTF-8');
                $doc->appendChild($doc->importNode($article, true));
                $xpath = new \DOMXPath($doc);
                
                foreach ($xpath->query('//div') as $d) {
                    if($d->getAttribute('class')!='content__article-body from-content-api js-article__body') {
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
        foreach ($xpath->query('//time[contains(attribute::class, "content__dateline-wpd js-wpd")]') as $container) {
            $raw_date=trim($container->nodeValue);
            $date=$this->niceDate($raw_date);
        }
        return $time[] = [$raw_date, $date];
    }

    public function getImages($document)
    {
        $xpath = new \DOMXPath($document);
        $image = $xpath->query('//meta[contains(attribute::property, "og:image")]')[2]->getAttribute('content');
        foreach ($xpath->query('//img[contains(attribute::class, "maxed responsive-img")]') as $container) {
            $thumbnail = $container->getAttribute('src');
        }
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
        $return['raw_date']=$time[0];
        $return['date']=$time[1];

        $images = $this->getImages($document);
        $return['thumbnail']=$images[0];
        $return['image']=$images[1];
        
        return $return;
    }
    
    public function niceDate($raw) {
        //Fri 16 Nov 2018 15.43 GMT
        $raw = trim($raw);
        $raw = explode(" ", $raw);
        //dd($raw);
        $month = $this->getMonth($raw[2]);
        $time = \Html2Text\Html2Text::convert($raw[4]);
        $time = str_replace(' GMT', '', $raw[4]);
        $time = explode(".",$time);
        //dd($time);
        
        $date = Carbon::create($raw[3], $month, $raw[1], $time[0], $time[1], 0, 'Europe/London');
        return $date->toW3cString();
    }

    public function getMonth($string)
    {
        $indonesianMonth = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $flip = array_flip($indonesianMonth);
        $a= $flip[$string];
        return $a;
    }
}
