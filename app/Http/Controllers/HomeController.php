<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, \App\News $news)
    {
        
        if($search=$request->input('search')) {
            $news=$news->where('content','like','%'.$search.'%');
        }
        if($search=$request->input('media')) {
            $news=$news->where('source',$search);
        }
        $news2=$news;
        $news_data = $news2->orderBy('id','desc')->paginate(10)->appends($request->input());
        $statistic= $news->select('source', DB::raw('count(source) as total'))
                ->groupBy('source')->orderBy('total','desc')
                 ->get();
        $data=[];
        $data['news'] = $news_data;
        $data['statistic'] = $statistic;
        return view('home',$data);
    }

    public function generate()
    {
        print_r(Artisan::call('news:'.request()->media));
        return redirect()->back();
    }
}
