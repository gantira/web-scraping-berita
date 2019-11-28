<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\NewsCrawler;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->call(function(){
            $sources = [
                'detik',
                'tribun',
                'jawapos',
                'cnn',
                'kompas',
                'sindonews',
                'grid',
                'idntimes',
                'merdeka',
                'liputan6'
            ];
            foreach($sources as $s) {
                dispatch(new \App\Jobs\NewsCrawler($s));
            }
        })->everyFiveMinutes();
        $schedule->call(function(){
            $sources = [
                'beritasatu',
                'bbc',
                'viva',
                'kumparan',
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
                'bbcinternational'
            ];
            foreach($sources as $s) {
                dispatch(new \App\Jobs\NewsCrawler($s));
            }
        })->everyTenMinutes();
        // $schedule->command('inspire')
        //          ->hourly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
