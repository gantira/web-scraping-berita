<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Spider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class Tribun extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:tribun';

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
//        $a = $this->getArticle('http://www.tribunnews.com/bisnis/2018/11/12/jokowi-minta-para-bupati-dorong-industri-kreatif-dan-sederhanakan-birokrasi');
//        dd($a);
////        dd('=');
        $bisnis = $this->getArticles(0, 4, 'bisnis');
        $news = $this->getArticles(0, 100, 'news');
        $x = array_merge($bisnis, $news);
        usort($x, function($a, $b) {
            $sortby = 'date';
            return strcmp($b[$sortby], $a[$sortby]);
        });
        dump($x);
        News::saveLatest("tribun", $x);
        News::saveDaily('tribun', $x);
    }

    public function getArticles($start, $section, $section_name) {
        $time = time();
        $visitor = 18300667429624749829 + $time;
        $callback = "jQuery" . sprintf('%0.0f', $visitor) . "_" . $time;
        $url = "http://api.tribunnews.com/ajax/latest_section?callback=" . $callback . "&start=" . $start . "&img=thumb2&section=" . $section . "&category=&_=" . time();
        
        $content = Spider::getContent($url);
        

        $data = [];
        if ($content) {
            $string = str_replace($callback . '(', "", $content);
            $string = substr($string, 0, -1);
            $obj = json_decode($string, true);
            foreach ($obj['posts'] as $a) {
                $a['title'] = preg_replace('/(\x{200e}|\x{200f})/u', '', $a['title']);
                $row = [
                    'title' => $a['title'],
                    'url' => $a['url'],
                    'md5url' => md5($a['url']),
                    'thumbnail' => $a['thumb'],
                    'image' => str_replace('thumbnails2','images',$a['thumb']),
                    'content' => $this->getArticle($a['url'].'?&page=all'),
                    'source' => 'tribun',
                    'section' => $section_name,
                    'date' => $a['date']
                ];
                $data[] = $row;
            }
        }
        return $data;
    }

    public function getArticle($url) {
        dump('article ' . $url);
        $raw = Spider::getContent($url, 100000);
        $x = $this->getBetween($raw, '<div class="side-article txt-article" >', '</div><div class="side-article mb5" >');
        
        
        try {
            //$extractionResult = \WebArticleExtractor\Extract::extractFromHTML($x);
            $x = str_replace("&nbsp;", " ", $x);
            $x = str_replace("&lrm;", " ", $x);
            $x = mb_convert_encoding($x, 'HTML-ENTITIES', 'UTF-8');
            
            $x = str_replace("&lrm;", " ", $x);
            
            $document = new \DOMDocument('1.0', 'UTF-8');
            $internalErrors = libxml_use_internal_errors(true);
            $document->loadHTML($x);
            libxml_clear_errors();

            $xpath = new \DOMXPath($document);
            foreach ($xpath->query('//p[contains(attribute::class, "baca")]') as $e) {
                // Delete this node
                $e->parentNode->removeChild($e);
            }
            foreach ($xpath->query('//script') as $e) {
                $e->parentNode->removeChild($e);
            }
            foreach ($xpath->query('//figure') as $e) {
                $e->parentNode->removeChild($e);
            }
            $olah = $document->saveHTML($document->documentElement);

            $olah = str_replace("</p>", "</p>", $olah);
            $return = strip_tags($olah);
            $return = trim($return);
        } catch (\Exception $e) {
            $return = "";
            dump($e->getMessage());
        }
        return $return;
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

}
