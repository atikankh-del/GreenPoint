@extends('layouts.app')
@section('title','ภารกิจสีเขียว')
@section('content')
<div class="page-head"><div><h1>ภารกิจสีเขียว</h1><p>นับจากวันที่ทำกิจกรรมที่ได้รับอนุมัติ รับคะแนนอัตโนมัติเมื่อครบเกณฑ์</p></div></div>
<div class="challenge-grid">
@forelse($challenges as $c)
@php($row=$progress->get($c->id))
@php($p=$row?->progress??0)
<div class="card challenge"><div class="challenge-icon">{{$c->icon}}</div><h2>{{$c->title}}</h2><p>{{$c->description}}</p>
<p>{{$c->starts_at->format('d/m/Y')}} – {{$c->ends_at->format('d/m/Y')}}</p>
<small>{{$c->activityType?->name??'ทุกกิจกรรม'}} · {{['trees'=>'นับจำนวนต้นไม้','days'=>'นับจำนวนวันที่ทำกิจกรรม ไม่ต้องติดต่อกัน','activities'=>'นับจำนวนกิจกรรม'][$c->metric]??'นับจำนวนกิจกรรม'}}</small>
<div class="progress tall"><i style="width:{{min(100,$p/max(1,$c->target)*100)}}%"></i></div>
<div class="challenge-bottom"><span>{{$p}} / {{$c->target}}</span><b>⭐ +{{$c->reward_points}}</b></div>
<p class="status">{{$row?->claimed_at?'✓ ได้รับรางวัลแล้ว':(today()->lt($c->starts_at)?'ยังไม่เริ่ม':(today()->gt($c->ends_at)?'สิ้นสุดช่วงกิจกรรม · รอผลตรวจหลักฐาน':'กำลังทำภารกิจ'))}}</p>
</div>
@empty<div class="card">ยังไม่มีภารกิจ</div>@endforelse
</div>
@endsection
