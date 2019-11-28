<?php

namespace App\Console;

use App\Spider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\News as NewsClass;

class News
{
    
    public static function saveLatest($source, $data) {
        if(count(@$data)) {
            Storage::put('news/latest/'.$source, json_encode($data), 'public');
            foreach($data as $n) {
                $carbon = Carbon::parse($n['date']);
                $n['date']=$carbon->format('Y-m-d');
                $n['datetime']=$carbon->format('Y-m-d H:i:s');
                if(!$n['thumbnail']) {
                    $n['thumbnail']='';
                }
                //dd($n);
                try {
                    NewsClass::create($n);
                } catch (\Exception $e) {
                    
                }
            }
            $report_path = 'news/report/latest';
            $old_data = [];
            try {
                $old = Storage::get($report_path);
            } catch (\Exception $e) {
                dump($e->getMessage());
            }
            if(@$old) {
                $old_data = json_decode($old,true);
                if(!$old_data) {
                    $old_data=[];
                }
            }
            $old_data[$source]=$data[0];
            Storage::put($report_path, json_encode($old_data),'public');
            
        }
    }
    
    public static function saveDaily($source, $data) {
        //==== Save To Daily ==========
        $daily = [];
        foreach($data as $d) {
            $date = Carbon::parse($d['date']);
            $dateFormat=$date->format('Y-m-d');
            $daily[$dateFormat][]=$d;
            
            try {
                dump('try to save ' . $news['url']);
                NewsClass::create($d);
            } catch (\Exception $e) {

            }
        }
        foreach($daily as $key=>$value) {
            $old=null;
            $storage_path="news/daily/".$source."/" . $key;
            try {
                $old = Storage::get($storage_path);
            } catch (\Exception $e) {
                dump($e->getMessage());
            }
            $old_data=[];
            $md5=[];
            if($old) {
                $old_data = json_decode($old,true);
                if(is_array($old_data)) {
                    foreach($old_data as $ol) {
                        $md5[]=$ol['md5url'];
                    }
                }
            }
            $new_news=$old_data;
            foreach($value as $news) {
                if(!in_array($news['md5url'],$md5)) {
                    $new_news[]=$news;
                    dump("New " . $news['title']);
                } else {
                    dump("Skip " . $news['title']);
                }
            }
            usort($new_news, function($a, $b) {
                $sortby = 'date';
                return strcmp($b[$sortby], $a[$sortby]);
            });
            if(@count($new_news)) {
                Storage::put($storage_path, json_encode($new_news), 'public');
            }
        }
    }
    
    public static function getBetween($string, $start, $end) {
        $string = str_replace(array("\t", "\n", "\r", "\x20\x20", "\0", "\x0B"), "", html_entity_decode($string));
        $ini = strpos($string, $start);
        if ($ini == 0)
            return "";
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }
}