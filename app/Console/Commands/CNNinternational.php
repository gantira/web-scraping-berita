<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Spider;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class Cnninternational extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:cnninternational';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'CNNinternational';

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
        /*$check=$this->getArticle('https://edition.cnn.com/2018/12/06/europe/angela-merkel-successor-candidates-grm-intl/index.html');
        dd($check);*/

        $data_business=$this->getArticles('business','business');
        $data_world=$this->getArticles('world','world');
        //dd($data_world);
        $data=array_merge($data_business,$data_world);
        //dd($data);
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp(@$b[$sortby], @$a[$sortby]);
        });
        
        News::saveLatest('cnninternational', $data);
        News::saveDaily('cnninternational', $data);
    }

    public function getArticles($section, $section_name) {
        $url="https://edition.cnn.com/".$section;
        dump($url);
        $raw = Spider::getContent($url);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTML($raw);
        libxml_clear_errors();
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//article[not(@data-video-id)]') as $e) {
            $row=[];
            $ti = $e->getElementsByTagName('h3');
            foreach($ti as $t) {
                //dump($t->nodeValue);
                $title=\Html2Text\Html2Text::convert($t->nodeValue);
                $row['title']=$title;
            }
            $anchor = $e->getElementsByTagName("a");
            foreach($anchor as $a) {
                if($href=$a->getAttribute('href')) {
                    if(strpos($href, 'https://edition.cnn.com') == 'true'){
                        $row['url']=$href;
                    }else {
                        if(strpos($href, 'https://') == 'true'){
                            continue;
                        }else{
                            $row['url']="https://edition.cnn.com".$href;
                        }
                    }
                    $row['md5url']=md5($row['url']);
                    break;
                }
            }
            if(!@$row['url']) {
                continue;
            }
            //dump($row);
            $content = $this->getArticle($row['url']);
            if(!@$content['content']) {
                continue;
            }
            //dump($content);
          
            $row['thumbnail'] = $content['thumbnail']; 
            $row['image'] = $content['image'];   
            
            $row['source']='cnninternational';
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
        $chekArticle = $xpath->query("//section[contains(attribute::data-zone-label, 'bodyText')]");
        //dd($chekArticle->length);
        $text = '';
        if($chekArticle->length > 0){
            foreach ($xpath->query("//section[contains(attribute::data-zone-label, 'bodyText')]") as $article) {
                if(!$article) {
                    return $return;
                }
                // dd($article->nodeValue);
                $doc = new \DOMDocument('1.0', 'UTF-8');
                $doc->appendChild($doc->importNode($article, true));
                $xpath = new \DOMXPath($doc);
                //dd($xpath);
                foreach ($xpath->query('//section') as $d) {
                    if($d->getAttribute('data-zone-label')!='bodyText') {
                        $d->parentNode->removeChild($d);
                    }
                }
                foreach ($xpath->query('//div') as $d) {
                    if($d->getAttribute('class')=='zn-body__read-more-outbrain' || $d->getAttribute('class')=='read-more-link' || $d->getAttribute('class')=='el__embedded el__embedded--standard' || $d->getAttribute('class')=='el__embedded el__embedded--fullwidth') {
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
        if($xpath->query('//p[contains(attribute::class, "update-time")]')->length > 0){
            foreach ($xpath->query('//p[contains(attribute::class, "update-time")]') as $container) {
                $raw_date=trim($container->nodeValue);
                //dd($raw_date);
                $date=$this->niceDate($raw_date);
            }
        }else {
            $pubdate = $xpath->query('//meta[contains(attribute::property, "og:pubdate")]')[0]->getAttribute('content');
            $raw_date = trim($pubdate);
            $date=$this->nicePubDate($raw_date);
        }
        return $time[] = [$raw_date, $date];
    }

    public function getImages($document)
    {
        $xpath = new \DOMXPath($document);
        $image = $xpath->query('//meta[contains(attribute::property, "og:image")]')[0]->getAttribute('content');
        //dd($image);
        $thumbnail = str_replace("super-tease.jpg", "medium-tease.jpg", $image);
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
        //Updated 0155 GMT (0955 HKT) November 27, 2018
        $raw = trim($raw);
        $raw = explode(" ", $raw);
        //dd($raw);
        $month = $this->getMonth($raw[5]);
        //dd($month);

        $hour = substr($raw[1], 0, 2);
        $minute = substr($raw[1], 2, 4);
        //dd($hour, $minute);
        
        $date = Carbon::create($raw[7], $month, str_replace(",", "", $raw[6]), $hour, $minute, 0, 'Europe/London');
        return $date->toW3cString();
    }

    public function nicePubDate($raw) {
        //2018-12-09T08:16:06Z
        $raw = explode("T", $raw);
        $date = explode("-", $raw[0]);
        $time = str_replace('Z', '', $raw[1]);
        $time = explode(":", $time);

        $date = Carbon::create($date[0], $date[1], $date[2], $time[0], $time[1], $time[2], 'Europe/London');
        return $date->toW3cString();
    }

    public function getMonth($string)
    {
        $month = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
        $flip = array_flip($month);
        $a= $flip[$string];
        return $a;
    }
}

