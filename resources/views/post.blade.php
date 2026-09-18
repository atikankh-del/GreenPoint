@extends('layouts.app')
@section('title','โพสต์ — GreenPoint')
@section('content')
<article class="card"><h1>{{$post->title??'กิจกรรมชุมชน'}}</h1><p>โดย {{$post->user->name}}</p><p>{{$post->content}}</p>@include('partials.evidence')<a href="{{route('feed')}}">กลับฟีดชุมชน</a></article>
@endsection
