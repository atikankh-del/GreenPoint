@extends('layouts.app')
@section('title','ร้านรางวัล')
@section('content')
<div class="page-head"><div><h1>ร้านรางวัล 🎁</h1><p>ใช้ GreenPoint แลกรางวัลดี ๆ ที่เป็นมิตรกับโลก</p></div><div class="coin big">⭐ {{number_format(auth()->user()->points)}} คะแนน</div></div>
<div class="reward-grid">@foreach($rewards as $r)<div class="card reward"><div class="reward-art">{{$r->icon}}</div><small>เหลือ {{$r->stock}} ชิ้น</small><h3>{{$r->name}}</h3><p>{{$r->description}}</p><div><b>⭐ {{number_format($r->points_required)}}</b><form method="post" action="{{route('rewards.redeem',$r)}}">@csrf<button class="btn" {{$r->stock<1||auth()->user()->points<$r->points_required?'disabled':''}}>แลกรางวัล</button></form></div></div>@endforeach</div>
@if($redemptions->count())<h2 class="section-title">รางวัลของฉัน</h2><div class="card table-card"><table><thead><tr><th>รางวัล</th><th>รหัสรับรางวัล</th><th>คะแนน</th><th>สถานะ</th></tr></thead><tbody>@foreach($redemptions as $r)<tr><td>{{$r->reward->icon}} <b>{{$r->reward->name}}</b></td><td>{{$r->redemption_code}}</td><td>-{{$r->points_spent}}</td><td><em class="status pending">{{$r->status}}</em></td></tr>@endforeach</tbody></table></div>@endif
@endsection
