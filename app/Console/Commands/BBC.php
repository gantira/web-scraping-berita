<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Spider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class BBC extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:bbc';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'BBC Indonesia';

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
        $indonesia = $this->getArticles('indonesia','indonesia');
        $dunia = $this->getArticles('dunia','dunia');
        $data=[];
        foreach($indonesia as $n) {
            $row=$n;
            $content = $this->getArticle($n['url']);
            $row['content']=$content['content'];
            $row['image']=$content['image'];
            $row['thumbnail']=$content['thumbnail'];
            $data[]=$row;
        }
        foreach($dunia as $n) {
            $row=$n;
            $content = $this->getArticle($n['url']);
            $row['content']=$content['content'];
            $row['image']=$content['image'];
            $row['thumbnail']=$content['thumbnail'];
            $data[]=$row;
        }
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp($b[$sortby], $a[$sortby]);
        });
        
        News::saveLatest('bbc', $data);
        News::saveDaily('bbc', $data);
    }
    
    public function getArticles($section,$section_name)
    {
        $index_url="https://www.bbc.com/indonesia/".$section;
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
        foreach ($xpath->query('//div[contains(attribute::class, "eagle-item faux-block-link")]') as $e) {
            $row=[];
            $t = $e->getElementsByTagName('span');
            foreach($t as $ti) {
                $row['title'] = $ti->nodeValue;
            }
            $u = $e->getElementsByTagName('a');
            foreach($u as $ur) {
                if($ur->getAttribute('class')=='title-link') {
                    $row['url']='https://bbc.com'.$ur->getAttribute('href');
                    $row['md5url']=md5($row['url']);
                }
            }
            $d = $e->getElementsByTagName('div');
            foreach($d as $dt) {
                if($dt->getAttribute('class')=='date date--v2') {
                    $dateCarbon = Carbon::createFromTimestamp($dt->getAttribute('data-seconds'),'Asia/Jakarta');
                    $row['date']=$dateCarbon->toW3cString();
                }
            }
            
            $row['source']='bbc';
            $row['section']=$section_name;
            $data[] = $row;
        }
        return $data;
    }
    
    public function getArticle($url)
    {
        $return = [
            'content'=>"",
            'image'=>"",
            'thumbnail'=>''
        ];
        $raw = Spider::getContent($url, 10000);
        $x = str_replace("&nbsp;", " ", $raw);
        $x = str_replace("&lrm;", " ", $x);
        $x = mb_convert_encoding($x, 'HTML-ENTITIES', 'UTF-8');

        $x = str_replace("&lrm;", " ", $x);

        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTML($x);
        libxml_clear_errors();
        
        $img = $document->getElementsByTagName('img');
        foreach($img as $im) {
            if($im->getAttribute('class')=='js-image-replace') {
                $return['thumbnail'] = $im->getAttribute('src');
                $return['image']=str_replace('/320/','/660/',$return['thumbnail']);
            }
        }
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//div[contains(attribute::class, "story-body__inner")]') as $e) {
            $article = new \DomDocument;
            $article->appendChild($article->importNode($e, true));
            $articlepath = new \DOMXPath($article);
            foreach ($articlepath->query('//ul[contains(attribute::class, "story-body__unordered-list")]') as $e) {
                $e->parentNode->removeChild($e);
            }
            foreach ($articlepath->query('//figure') as $e) {
                $e->parentNode->removeChild($e);
            }
            foreach ($articlepath->query('//ul') as $e) {
                $e->parentNode->removeChild($e);
            }
            foreach ($articlepath->query('//hr') as $e) {
                $e->parentNode->removeChild($e);
            }
            foreach ($articlepath->query('//div[contains(attribute::class, "social-embed")]') as $e) {
                $e->parentNode->removeChild($e);
            }
            $olah = $article->saveHTML($article->documentElement);
            $text = \Html2Text\Html2Text::convert($olah);
            $return['content']=trim($text);
        }
        
        return $return;
    }
}
