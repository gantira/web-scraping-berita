<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Spider;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class Tirto extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:tirto';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tirto';

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
        /*$check=$this->getArticle('https://tirto.id/pertamina-targetkan-belanja-modal-55-miliar-dolar-as-pada-2019-daxz');
        dd($check);*/

        $data_bisnis=$this->getArticles('q/bisnis-j7','bisnis');
        $data_ekonomi=$this->getArticles('q/ekonomi-kP','ekonomi');
        $data=array_merge($data_bisnis,$data_ekonomi);
        //dd($data);
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp(@$b[$sortby], @$a[$sortby]);
        });
        
        News::saveLatest('tirto', $data);
        News::saveDaily('tirto', $data);
    }

    public function getArticles($section, $section_name) {
        $url="https://tirto.id/".$section;
        dump($url);
        $raw = Spider::getContent($url);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTML($raw);
        libxml_clear_errors();
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//div[contains(attribute::class, "news-list-fade")]') as $e) {
            $row=[];
            $anchor = $e->getElementsByTagName("a");
            foreach($anchor as $a) {
                if($href=$a->getAttribute('href')) {
                    $url="https://tirto.id".$href;
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
            $row['title'] = $content['title'];
            $row['url'] = $url;
            $row['md5url'] = $md5url;
            $row['thumbnail'] = $content['thumbnail']; 
            $row['image'] = $content['image'];   
            $row['source']='tirto';
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
        $chekArticle = $xpath->query("//div[contains(attribute::class, 'content-text-editor')]");
        //dd($chekArticle->length);
        $text = '';
        if($chekArticle->length > 0){
            $article = $xpath->query("//div[contains(attribute::class, 'content-text-editor')]")->item(1);
            //dd($article);
            if(!$article) {
                return $return;
            }
            // dd($article->nodeValue);
            $doc = new \DOMDocument('1.0', 'UTF-8');
            $doc->appendChild($doc->importNode($article, true));
            $xpath = new \DOMXPath($doc);
            //dd($xpath);
            foreach ($xpath->query('//div') as $d) {
                if($d->getAttribute('class')=='baca-holder bold') {
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
        
        return trim($text);
    }

    public function getBetween($string, $start, $end) {
        $string = str_replace(array("\t", "\n", "\r", "\x20\x20", "\0", "\x0B"), "", html_entity_decode($string));
        $ini = strpos($string, $start);
        if ($ini == 0)
            return "";
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }

    public function getTime($document)
    {
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//span[contains(attribute::class, "detail-date mt-1 text-left")]') as $contents) {
            $raw_date=trim($contents->nodeValue);
            $raw_date=explode("- ",$raw_date)[1];
        }
        return $raw_date;
    }

    public function getTimeAndImages($jsonData)
    {
            $title = $jsonData["headline"];
            $date = $jsonData["datePublished"];
            $date = $this->niceDate($date);
            $image = $jsonData["image"][0];
            $thumbnail = str_replace("https://mmc.tirto.id/image/", "", $image);
            $thumbnail = "https://mmc.tirto.id/image/otf/260x0/".$thumbnail;
        
        return $time[] = [$date, $thumbnail, $image, $title];
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
        $jsonData=$this->getBetween($x,'<script data-n-head="true" data-hid="json_tag" type="application/ld+json">','</script>');
        if(!$jsonData) {
            return $return;
        }
        $jsonData = json_decode($jsonData,true);
    
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

        $raw_date = $this->getTime($document); 
        $timeandimages = $this->getTimeAndImages($jsonData); 
        //dd($timeandimages);
        $return['raw_date']=$raw_date;

        $return['date']=$timeandimages[0];
        $return['thumbnail']=$timeandimages[1];
        $return['image']=$timeandimages[2];
        $return['title']=$timeandimages[3];
        return $return;
    }
    
    public function niceDate($raw) {
        //2018-11-28 14:20:28
        //$raw = trim($raw);
        $raw = explode(" ", $raw);
        $date = explode("-", $raw[0]);
        $time = explode(":", $raw[1]);
        
        $date = Carbon::create($date[0], $date[1], $date[2], $time[0], $time[1], $time[2], 'Asia/Jakarta');
        return $date->toW3cString();
    }
}



