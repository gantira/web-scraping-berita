<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Spider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class CNN extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:cnn';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tribun news';

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
//        $a=$this->getArticle('https://www.cnnindonesia.com/ekonomi/20181116160440-78-347144/bank-mandiri-akan-sesuaikan-bunga-bca-dan-bni-masih-mengkaji');
//        $a=$this->getArticle('https://www.cnnindonesia.com/nasional/20181128182852-33-350022/mimpi-keluar-dari-lingkaran-setan-kampanye-olok-olok');
//        dd($a);
        $data=$this->getArticles(0);
        $data2=$this->getArticles(1);
        $data3=$this->getArticles(2);
        $data= array_merge($data,$data2,$data3);
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp(@$b[$sortby], @$a[$sortby]);
        });
        News::saveLatest('cnn', $data);
        News::saveDaily('cnn', $data);
    }

    public function getArticles($start=0) {
        $url="https://www.cnnindonesia.com/indeks/" . $start;
        $raw = Spider::getContent($url);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTML($raw);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($document);
        $data=[];
        foreach ($xpath->query('//div[contains(attribute::class, "list media_rows middle")]') as $container) {
            $articles = $container->getElementsByTagName('article');
                foreach($articles as $e) {

                $row=[];
                $ti = $e->getElementsByTagName('h2');
                foreach($ti as $t) {
                    $title=$t->nodeValue;
                    $row['title']=$title;
                }
                $anchor = $e->getElementsByTagName("a");
                foreach($anchor as $a) {
                    if($href=$a->getAttribute('href')) {
                        $row['url']=$href;
                        $row['md5url']=md5($row['url']);
                        break;
                    }
                }
                $img = $e->getElementsByTagName("img");
                foreach($img as $i) {
                    $row['thumbnail']=$i->getAttribute('src');
                }
                $span = $e->getElementsByTagName("span");
                foreach($span as $s) {
                    if($s->getAttribute('class')=='kanal') {
                        $row['section']=strtolower($s->nodeValue);
                    }
                    if($s->getAttribute('class')=='date') {
                        $doc = new \DOMDocument('1.0', 'UTF-8');
                        $doc->appendChild($doc->importNode($s, true));
                        //$row['raw_date']= News::getBetween($doc->saveHTML(),"<!--", "-->");
                        //$row['date'] = $this->niceDate($row['raw_date']);
                    }
                    //$row['raw_date'] = $d->nodeValue;
                    //$row['date'] = $this->niceDate($d->nodeValue);
                }
                $row['source']='cnn';
                $content = $this->getArticle($row['url']);
                $row['image']=$row['thumbnail'] . '&w=1024';
                $row['content']=$content['content'];
                $row['raw_date']=$content['raw_date'];
                $row['date']=$content['date'];
                $data[]=$row;
            }
        }
        return $data;
        

    }
    
    public function niceDate($raw) {
        //13/11/2018, 21:01 WIB
        $raw = trim($raw);
        $raw = explode('|',$raw);
        $raw=$raw[1];
        $raw= explode(", ", $raw);
        $raw= explode(" ", $raw[1]);
        $date = explode("/",$raw[0]);
        $time = explode(":",$raw[1]);
//        dd($time);
        $date = Carbon::create(@$date[2], $date[1], $date[0], @$time[0], @$time[1], 0, 'Asia/Jakarta');
        return $date->toW3cString();
    }

    public function getArticle($url)
    {
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
        dump($url);
        $article=$document->getElementById("detikdetailtext");
        if($article) {
            $raw=$article->C14N();
            $raw=str_replace("<a","<span",$raw);
            $raw= str_replace("</a>", "</span>", $raw);
            $text = \Html2Text\Html2Text::convert($raw);
            $return['content']=trim($text);
        }
        
        
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//div[contains(attribute::class, "date")]') as $container) {
            $s=$container->getElementsByTagName('strong');
            foreach($s as $ss) {
                $ss->parentNode->removeChild($ss);
            }
            $return['raw_date']=trim(str_replace(',  CNN ', 'CNN ', $container->nodeValue));
            $return['date']=$this->niceDate($return['raw_date']);
        }
        return $return;
    }
    

}
