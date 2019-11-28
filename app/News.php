<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    protected $fillable = ['title','content','datetime','date','thumbnail','image','url','md5url','source','section'];
    protected $dates = ['date'];
    public static $sources = [
            "detik.com" => 'detik',
            "cnnindonesia.com" => 'cnn',
            "viva.co.id" => 'viva',
            "liputan6.com" => 'liputan6',
            "sindonews.com" => 'sindonews',
            "merdeka.com" => 'merdeka',
            "tirto.id" => 'tirto',
            "suara.com" => 'suara',
            "antaranews.com" => 'antaranews',

            // "detik.com"=>'detik',
            // "tribunnews.com"=>'tribun',
            // "beritasatu.com"=>'beritasatu',
            // "bbc.com/indonesia"=>'bbc',
            // "bbc.com"=>'bbcinternational',
            // "jawapos.com"=>'jawapos',
            // "cnnindonesia.com"=>'cnn',
            // "viva.co.id"=>'viva',
            // "kompas.com"=>'kompas',
            // "liputan6.com"=>'liputan6',
            // "sindonews.com"=>'sindonews',
            // "kumparan.com"=>'kumparan',
            // "grid.id"=>'grid',
            // "idntimes.com"=>'idntimes',
            // "merdeka.com"=>'merdeka',
            // "reuters.com"=>'reuters',
            // "theguardian.com"=>'theguardian',
            // "edition.cnn.com"=>'cnninternational',
            // "tirto.id"=>'tirto',
            // "suara.com"=>'suara',
            // "antaranews.com"=>'antaranews',
            // "mediaindonesia.com"=>'mediaindonesia',
            // "bisnis.com"=>'bisnis',
            // "jpnn.com"=>'jpnn',
            // "industry.co.id"=>'industry'
            //'matain'
        ];
    
    public function getMediaWeb() {
        $keys = array_flip(static::$sources);
        return $keys[$this->source]??"";
    }
}
