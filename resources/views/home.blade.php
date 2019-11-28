@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">Dashboard</div>

                <div class="card-body">
                    @if (session('status'))
                    <div class="alert alert-success" role="alert">
                        {{ session('status') }}
                    </div>
                    @endif

                    <form>
                        {{ csrf_field() }}

                        <div class="form-group">
                            <label>Keyword</label>
                            <div class='row'>
                                <div class='col-md-12'>
                                    <input type="text" name="search" value="{{ app('request')->input('search') }}" class="form-control" placeholder="Search">
                                </div>
                            </div>
                            <div class='row mt-1'>
                                <div class='col-md-3'>
                                    <select name='media' class='form-control'>
                                        <option value=''>All Site</option>
                                        @foreach(\App\News::$sources as $k=>$s)
                                        <option value='{{$s}}' @if(app('request')->input('media')==$s) selected @endif>{{$k}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </form>
                    <hr />
                    <div class="row">

                        <div class="col-md-9">
                            @foreach($news as $n)
                            <div class="row mt-2">
                                <div class="col-md-2" style="background:#aaa<?php if ($n->thumbnail) :
                                                                                echo ';background-image:url(\'' . $n->thumbnail . '\')';
                                                                            endif; ?>; background-position:center;background-size:cover">
                                </div>
                                <div class="col-md-10">
                                    <h5><a href="{{ $n->url }}" target="__blank">{{ $n->title }}</a></h5>
                                    <span>{{ $n->date->format('j M Y') }}</span><br />
                                    <span>{{ $n->getMediaWeb() }} - {{ $n->section }}</span>
                                </div>

                            </div>
                            @endforeach
                            <div class="row mt-2">
                                <div class="col-md-12 text-xs-center">
                                    <div class="text-xs-center">
                                        {{ $news->links() }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div>
                                <h4>Scraping News</h4>
                                <form action="/generate" method="POST">
                                    @csrf
                                    <div class="form-group">
                                        <select name='media' class='form-control'>
                                            @foreach(\App\News::$sources as $k=>$s)
                                            <option value='{{$s}}' @if(app('request')->input('media')==$s) selected @endif>{{$k}}</option>
                                            @endforeach
                                        </select>                                       
                                    </div>
                                <div class="form-group">
                                    <input type="submit" value="GENERATE" class="btn btn-primary btn-block">
                                </div>
                            </form>
                            </div>

                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Source</th>
                                        <th class="text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($statistic as $s)
                                    <tr>
                                        <td>{{$s->getMediaWeb()}}</td>
                                        <td class="text-right">{{$s->total}}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection