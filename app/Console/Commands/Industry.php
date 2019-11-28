<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Spider;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class Industry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:industry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Industry';

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
        /*$check=$this->getArticle('http://www.industry.co.id/read/45834/bgr-logistics-jalin-kerjasama-dengan-kejaksaan-negeri-kota-bandung');
        dd($check);*/

        $data=$this->getArticles('industri','industri');
        
        //dd($data);
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp(@$b[$sortby], @$a[$sortby]);
        });
        
        News::saveLatest('industry', $data);
        News::saveDaily('industry', $data);
    }

    public function getArticles($section, $section_name) {
        $url="http://www.industry.co.id/".$section;
        //dd($url);
        $raw = Spider::getContent($url);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTML($raw);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($document);
        $data=[];
        //dd($xpath);
        foreach ($xpath->query("//div[contains(attribute::class, 'list') and not(contains(attribute::class,'lists'))]") as $container) {
            $articles = $container->getElementsByTagName('div');
            foreach($articles as $e) {
                $row=[];
                $ti = $e->getElementsByTagName('h6');
                foreach($ti as $t) {
                    //dump($t->nodeValue);
                    $title=\Html2Text\Html2Text::convert($t->nodeValue);
                    $row['title']=$title;
                }
                //dump($row['title']);
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
                $date = $e->getElementsByTagName("p");
                foreach($date as $d) {
                    if($d->getAttribute('class') == 'date') {
                        $raw_date = trim($d->nodeValue);
                        $date = $this->niceDate($raw_date);
                    }
                }
                //dump($row);
                $content = $this->getArticle($row['url']);
                if(!@$content['content']) {
                    continue;
                }
                //dump($content);
                $row['thumbnail'] = $content['thumbnail']; 
                $row['image'] = $content['image'];   
                $row['source']='industry';
                $row['section']=$section_name;
                $row['content']=$content['content'];
                $row['raw_date']=$raw_date;
                $row['date']=$date;
                $data[] = $row;
                //dd($data);
            }
        }
        return $data;
    }

    public function getContent($document)
    {
        $xpath = new \DOMXPath($document);
        $chekArticle = $xpath->query("//div[contains(attribute::class, 'detail-text') and not(contains(attribute::class,'side-post'))]");
        //dd($chekArticle->length);
        $text = '';
        if($chekArticle->length > 0){
            foreach ($xpath->query("//div[contains(attribute::class, 'detail-text') and not(contains(attribute::class,'side-post'))]") as $article) {
                if(!$article) {
                    return $return;
                }
                // dd($article->nodeValue);
                $doc = new \DOMDocument('1.0', 'UTF-8');
                $doc->appendChild($doc->importNode($article, true));
                $xpath = new \DOMXPath($doc);
                //dd($xpath);
                foreach ($xpath->query('//div') as $d) {
                    if($d->getAttribute('class')!='detail-text') {
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

    public function getImages($document)
    {
        $xpath = new \DOMXPath($document);
        $image = $xpath->query('//meta[contains(attribute::property, "og:image")]')[0]->getAttribute('content');
        //dd($image);
        $thumbnail = str_replace("detail", "small", $image);
        //dd($thumbnail);
        return $images[] = [$thumbnail, $image];
    }

    public function getArticle($url)
    {
        dump($url);
        $return = [
            'content'=>""
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

        $images = $this->getImages($document);
        //dd($images);
        $return['thumbnail']=$images[0];
        $return['image']=$images[1];
        
        return $return;
    }
    
    public function niceDate($raw) {
        //Senin, 03 Desember 2018 - 17:14 WIB
        $raw = trim($raw);
        $raw = explode(" ", $raw);
        //dd($raw);
        $month = $this->getMonth($raw[2]);
        //dd($month);

        $time = explode(":",$raw[5]);
        
        $date = Carbon::create($raw[3], $month, $raw[1], $time[0], $time[1], 0, 'Asia/Jakarta');
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




