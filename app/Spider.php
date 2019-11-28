<?php

namespace App;

//use Illuminate\Database\Eloquent\Model;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use Cache;

class Spider {
    var $request;
    var $client;

    static function staticRequest($url) {
        $spider = new Spider();
        return $spider->request($url);
    }
    
    static function getContent($url, $cache=0) {
        $spider = new Spider();
        $spider->sendLog('Ada Request get Content ke ' . $url);
        $key = 'SpiderGetContent'.$url;
        if($cache) {
            if($value=Cache::get($key)) {                
                $spider->sendLog('Mendapatkan data dari cache.');
                return $value;
            }
        }
        try {
            $spider->request($url);
        } catch (\GuzzleHttp\Exception\TooManyRedirectsException  $e) {
            return null;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $context = stream_context_create(array(
                'http' => array('ignore_errors' => true),
            ));

            try {
                $result_file_get_content = file_get_contents('$url', false, $context);
            } catch (\Exception $e) {
                return '';
            }
            if($result_file_get_content) {
                return $result_file_get_content;
            } else {
                return '';
            }
        }
        
        $spider->sendLog('Melakukan request http ke ' . $url);
        if ($spider->request->getStatusCode() == 200) {
            $spider->sendLog('Mendapatkan data dari URL HTTP');
            $value = (string) $spider->request->getbody();
            //$spider->sendLog('Hasil dari URL: ' . $value);
            if($cache) {
                $spider->sendLog('Coba simpan di cache');

                try {
                    Cache::put($key,$value,$cache);
                } catch (\Exception $e) {
                    $spider->sendLog('Error ketika simpan ke cache. ' . $e->getMessage());
                    $spider->sendLog('Value yang coba disimpan: ' . $value);
                }
            }
            return $value;
        } else {
            $spider->sendLog("Gagal mendapat data dari URL");
            return null;
        }
    }
    
    function sendLog($message)
    {
        //sendLog($message, 'Spider');
    }

    function request($url) {
        $this->sendLog('Spider Request content to ' . $url);
        $session = random_int(0, 1000);
        $config=$this->getConfig();
        $client = new Client([
            // Base URI is used with relative requests
            'verify' => false,
            'base_uri' => $url,
            'http_errors' => false,
            'timeout' => 120 //timeout
        ]);

        $req = $client->request('GET', '');
        $this->sendLog('Spider Response Status Code:' . $req->getStatusCode());
        $this->sendLog('Spider Response Status Code:' . (string) $req->getBody());
        $this->request = $req;
        $this->client = $client;
        return $this;
    }

    function getConfig() {
        $configs = config('app.proxy_spider');
        $config = $configs;
        return $config;
    }

}
