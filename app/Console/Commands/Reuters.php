<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Spider;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class Reuters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:reuters';

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
       /* $check=$this->getArticle('https://www.reuters.com/article/us-money-investing-esgimpact/is-impact-investing-too-good-to-be-true-idUSKCN1NO16G');
        dd($check);*/

        // $business_max_page = 3277;
        // $wealth_max_page = 2120;

        $business_max_page = 1;
        $data_business=$this->getArticles('businessnews','business', $business_max_page);
        $wealth_max_page = 1;
        $data_wealth=$this->getArticles('personalfinance','wealth', $wealth_max_page);
        $data=array_merge($data_business,$data_wealth);
        //dd($data);
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp(@$b[$sortby], @$a[$sortby]);
        });
        
        News::saveLatest('reuters', $data);
        News::saveDaily('reuters', $data);
    }

    public function getArticles($section, $section_name, $maxpage) {
        $data = [];
        foreach (range(1, $maxpage) as $i) {
            $url="https://www.reuters.com/news/archive/".$section."?view=page&page=".$i."&pageSize=10";
            $raw = Spider::getContent($url);
            $document = new \DOMDocument('1.0', 'UTF-8');
            $internalErrors = libxml_use_internal_errors(true);
            $document->loadHTML($raw);
            libxml_clear_errors();

            $xpath = new \DOMXPath($document);
            foreach ($xpath->query('//div[contains(attribute::class, "column1 col col-10")]') as $container) {
                $articles = $container->getElementsByTagName('article');
                foreach($articles as $e) {
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
                            $row['url']="https://www.reuters.com/".$href;
                            $row['md5url']=md5($row['url']);
                            break;
                        }
                    }
                    if(!@$row['url']) {
                        continue;
                    }
                    //dd($row);
                    $img = $e->getElementsByTagName("img");
                    //dd($img);
                    $row['thumbnail']='';
                    $row['image']='';
                    foreach($img as $i) {
                        $row['thumbnail'] = $i->getAttribute('org-src');
                        if($row['thumbnail'] == ''){
                            $row['thumbnail'] = '';
                            $row['image'] = '';
                        }else {
                            $raw = explode("&w=", $row['thumbnail']);
                            $tail = explode("&r=", $raw[1]);
                            $row['image'] = $raw[0]."&r=".$tail[1]."&w=1280";
                        }
                    }
                    $content = $this->getArticle($row['url']);
                    $row['source']='reuters';
                    $row['section']=$section_name;
                    $row['content']=$content['content'];
                    $row['raw_date']=$content['raw_date'];
                    $row['date']=$content['date'];
                    array_push($data, $row);
                }
            }
        }
        return $data;
    }

    public function getContent($document)
    {
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query("//div[contains(attribute::class, 'StandardArticleBody_body')]") as $article) {
            if(!$article) {
                return $return;
            }
            $doc = new \DOMDocument('1.0', 'UTF-8');
            $doc->appendChild($doc->importNode($article, true));
            $xpath = new \DOMXPath($doc);
            
            foreach ($xpath->query('//div') as $d) {
                if($d->getAttribute('class')!='StandardArticleBody_body') {
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
        }
        return trim($text);
    }

    public function getTime($document)
    {
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//div[contains(attribute::class, "ArticleHeader_date")]') as $container) {
            $raw_date=trim($container->nodeValue);
            $date=$this->niceDate($raw_date);
        }
        return $time[] = [$raw_date, $date];
    }

    public function getArticle($url)
    {
        //dump($url);
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
        $return['content'] = $content;
        
        $time = $this->getTime($document); 
        $return['raw_date']=$time[0];
        $return['date']=$time[1];
        
        return $return;
    }
    
    public function niceDate($raw) {
        //November 26, 2018 /  2:39 AM / Updated 32 minutes ago
        $raw = trim($raw);
        $raw = explode(" / ", $raw);
        
        $date = str_replace(',', '', $raw[0]);
        $date = explode(" ", $date);
        $month = $this->getMonth($date[0]);

        $time =date("H:i", strtotime($raw[1]));
        $time = explode(":",$time);
        
        $date = Carbon::create($date[2], $month, $date[1], $time[0], $time[1], 0, 'Europe/London');
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
