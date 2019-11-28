<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Spider;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class Mediaindonesia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:mediaindonesia';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mediaindonesia';

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
        /*$check=$this->getArticle('http://mediaindonesia.com/read/detail/201052-pln-pastikan-program-35-ribu-mw-teralisasi');
        dd($check);*/

        $data=$this->getArticles('ekonomi','ekonomi');
        //dd($data);
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp(@$b[$sortby], @$a[$sortby]);
        });
        
        News::saveLatest('mediaindonesia', $data);
        News::saveDaily('mediaindonesia', $data);
    }

    public function getArticles($section, $section_name) {
        $url="http://mediaindonesia.com/".$section;
        //dd($url);
        $raw = Spider::getContent($url);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTML($raw);
        libxml_clear_errors();
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query("//div[contains(attribute::class, 'article-big')]") as $e) {
            $row=[]; 
            $ti = $e->getElementsByTagName('h2');
            foreach($ti as $t) {
                //dump($t->nodeValue);
                $title=$t->nodeValue;
                $row['title']=$title;
            }
            //dd($row['title']);
           $anchor = $e->getElementsByTagName("a");
            foreach($anchor as $a) {
                if($href=$a->getAttribute('href')) {
                    $row['url']=$href;
                    $row['md5url']=md5($row['url']);
                    break;
                }
            }
            //dd($row['url']);
            if(!@$row['url']) {
                continue;
            }
            $img = $e->getElementsByTagName("img");
            $row['thumbnail']='';
            $row['image']='';
            foreach($img as $i) {
                $row['image']=$i->getAttribute('data-original');
                $row['thumbnail']= str_replace("600x400", "215x140", $row['image']);
            }

            $content = $this->getArticle($row['url']);
            if(!@$content['content']) {
                continue;
            }
            //dd($content);  
            $row['source']='mediaindonesia';
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
        $chekArticle = $xpath->query("//div[contains(attribute::itemprop, 'articleBody')]");
        //dd($chekArticle->length);
        $text = '';
        if($chekArticle->length > 0){
            foreach ($xpath->query("//div[contains(attribute::itemprop, 'articleBody')]") as $article) {
                if(!$article) {
                    return $return;
                }
                //dd($article->nodeValue);
                $doc = new \DOMDocument('1.0', 'UTF-8');
                $doc->appendChild($doc->importNode($article, true));
                $xpath = new \DOMXPath($doc);
                foreach ($xpath->query('//div') as $d) {
                    if($d->getAttribute('class')=='dable_placeholder') {
                        $d->parentNode->removeChild($d);
                    }
                }
                foreach ($xpath->query('//script') as $e) {
                    $e->parentNode->removeChild($e);
                }
                foreach ($xpath->query('//img') as $e) {
                    $e->parentNode->removeChild($e);
                }
                foreach ($xpath->query('//a') as $e) {
                    $e->parentNode->removeChild($e);
                }
                foreach ($xpath->query('//strong') as $e) {
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
        $raw_date = $xpath->query('//span[contains(attribute::class, "meta")]')[0]->nodeValue;
        $raw_date= \Html2Text\Html2Text::convert($raw_date);;
        $date=$this->niceDate($raw_date);
        //dd($date);
        
        return $time[] = [$raw_date, $date];
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

        return $return;
    }
    
    public function niceDate($raw) {
        //Pada: Kamis, 29 Nov 2018, 17:19 WIB Ekonomi
        $raw = trim($raw);
        $raw = str_replace(',', '', $raw);
        $raw = explode(" ", $raw);
        //dd($raw);
        $month = $this->getMonth($raw[3]);
        //dd($month);
        $time = explode(":", $raw[5]);

        $date = Carbon::create($raw[4], $month, $raw[2], $time[0], $time[1], 0, 'Asia/Jakarta');
        return $date->toW3cString();
    }

    public function getMonth($string)
    {
        $month = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        $flip = array_flip($month);
        $a= $flip[$string];
        return $a;
    }
}





