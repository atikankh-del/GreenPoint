@extends('layouts.app')
@section('title','แก้ไขโพสต์')
@section('content')
<div class="card"><h1>แก้ไขโพสต์</h1>
@if($post->status==='rejected')
<p>เหตุผลที่ปฏิเสธ: {{$post->review_note}}</p>
@if($post->image)
<p>หากไม่เลือกรูปใหม่ จะใช้หลักฐานเดิม</p>
@include('partials.evidence')
@endif
@include('partials.post-form')
@else
<p>แก้ไขข้อความและความเป็นส่วนตัวได้ ข้อมูลกิจกรรมและหลักฐานเดิมจะคงไว้สำหรับการตรวจคะแนน</p>
<form method="post" action="{{route('posts.update',$post)}}" class="admin-form">
@csrf
@method('PUT')
<label>ชื่อโพสต์<input name="title" maxlength="120" value="{{old('title',$post->title)}}"></label>
<label>รายละเอียด<textarea name="content" required maxlength="2000">{{old('content',$post->content)}}</textarea></label>
<label>การมองเห็น<select name="privacy"><option value="public" @selected(old('privacy',$post->privacy)==='public')>สาธารณะ</option><option value="private" @selected(old('privacy',$post->privacy)==='private')>ส่วนตัว</option></select></label>
<button class="btn" type="submit">บันทึกการแก้ไข</button>
</form>
@endif
</div>
@endsection
