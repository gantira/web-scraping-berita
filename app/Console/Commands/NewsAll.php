<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class NewsAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate all news';

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
        $sources = [
            'detik',
            'tribun',
            'beritasatu',
            'bbc',
            'jawapos',
            'cnn',
            'viva',
            'kompas',
            'liputan6',
            'sindonews',
            'kumparan',
            'grid',
            'idntimes',
            'merdeka',
            'reuters',
            'theguardian',
            'cnninternational',
            'tirto',
            'suara',
            'antaranews',
            'mediaindonesia',
            'bisnis',
            'jpnn',
            'industry',
            'liputan6',
            'bbcinternational'
            //'matain'
        ];
        $sources = \App\News::$sources;
        foreach($sources as $s) {
            Artisan::call('news:'.$s);
        }
    }
}
