@extends('layouts.app')
@section('title','แก้ไขและส่งใหม่')
@section('content')
<div class="card"><h1>แก้ไขและส่งใหม่</h1><p>เหตุผลที่ปฏิเสธ: {{$post->review_note}}</p>
@if($post->image)<p>หากไม่เลือกรูปใหม่ จะใช้หลักฐานเดิม</p>@include('partials.evidence')@endif
@include('partials.post-form')
</div>
@endsection
