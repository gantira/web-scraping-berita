<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Spider;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class JPNN extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:jpnn';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'JPNN';

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
        /*$check=$this->getArticle('https://www.jpnn.com/news/bea-cukai-ditjen-perdagangan-luar-negeri-dan-insw-sepakati-mou-electronic');
        dd($check);*/

        $data=$this->getArticles('ekonomi/bisnis','bisnis');
        
        //dd($data);
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp(@$b[$sortby], @$a[$sortby]);
        });
        
        News::saveLatest('jpnn', $data);
        News::saveDaily('jpnn', $data);
    }

    public function getArticles($section, $section_name) {
        $url="https://www.jpnn.com/".$section;
        //dump($url);
        $raw = Spider::getContent($url);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTML($raw);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($document);
        $data=[];
        //dd($xpath);
        foreach ($xpath->query('//li[contains(attribute::data-offset, "15")]') as $e) {
            $row=[];
            $ti = $e->getElementsByTagName('h2');
            foreach($ti as $t) {
                //dump($t->nodeValue);
                $title=\Html2Text\Html2Text::convert($t->nodeValue);
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
            
            $row['source']='jpnn';
            $row['section']=$section_name;
            $row['content']=$content['content'];
            $row['raw_date']=$content['raw_date'];
            $row['date']=$content['date'];
            $data[] = $row;
        }
        return $data;
    }

    public function getContent($document, $url)
    {
        $count = 0;
        $xpath = new \DOMXPath($document);
        $page = $xpath->query("//div[contains(attribute::class, 'pagination')]")[0];
        //dump($page);
        if($page){
            $doc = new \DOMDocument('1.0', 'UTF-8');
            $doc->appendChild($doc->importNode($page, true));
            $xpath = new \DOMXPath($doc);
            foreach ($xpath->query('//ul') as $d) {
                $doc = new \DOMDocument('1.0', 'UTF-8');
                $doc->appendChild($doc->importNode($d, true));
                $xpath = new \DOMXPath($doc);
                foreach ($xpath->query('//li') as $g) {
                    $count++;
                }
            }
            $count = $count - 1;
        }else {
            $count = 1;
        }

        $start = 1;
        $story = '';
        while($start <= $count){
            $raw = Spider::staticRequest($url.'?page='.$start);
            //dump($url.'?page='.$start);
            if($raw->request->getStatusCode() == 200){
                $raw = Spider::getContent($url.'?page='.$start, 10000);
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
                $text = '';
                foreach ($xpath->query("//div[contains(attribute::class, 'post') and not(contains(attribute::class,'side-post'))]") as $article) {
                    if(!$article) {
                        return $return;
                    }
                    // dd($article->nodeValue);
                    $doc = new \DOMDocument('1.0', 'UTF-8');
                    $doc->appendChild($doc->importNode($article, true));
                    $xpath = new \DOMXPath($doc);
                    //dd($xpath);
                    foreach ($xpath->query('//div') as $d) {
                        if($d->getAttribute('class')!='post' || $d->getAttribute('class')=='pagination') {
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
                    $story .= "\n";
                    $story .= $text;
                }
                $start++;
            }
        }
        //dd($story);
        
        return trim($story);
    }

    public function getTime($document)
    {
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//div[contains(attribute::class, "date")]') as $container) {
            $raw_date = trim($container->nodeValue);
            //dd($raw_date);
            $date = $this->niceDate($raw_date);
        }
        return $time[] = [$raw_date, $date];
    }

    public function getImages($document)
    {
        $xpath = new \DOMXPath($document);
        $image = $xpath->query('//meta[contains(attribute::property, "og:image")]')[0]->getAttribute('content');
        //dd($image);
        $thumbnail = 'https://photo.jpnn.com/timthumb.php?src='.$image.'&h=110&zc=1&q=70';
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
        
        $content =$this->getContent($document, $url);
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
        //Sabtu, 01 Desember 2018 – 16:12 WIB
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



