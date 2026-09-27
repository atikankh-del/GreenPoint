@if(auth()->id()===$post->user_id)
<details class="post-options">
<summary aria-label="เมนูโพสต์" title="แก้ไขหรือลบโพสต์">•••</summary>
<div class="post-options-menu">
<a href="{{route('posts.edit',$post)}}">แก้ไข</a>
<form method="post" action="{{route('posts.destroy',$post)}}" onsubmit="return confirm('ลบโพสต์นี้ออกจากเว็บไซต์? ประวัติคะแนนและรางวัลที่ได้รับจะยังคงอยู่')">
@csrf
@method('DELETE')
<button type="submit" class="danger-text" aria-label="ลบโพสต์ของฉัน">ลบ</button>
</form>
</div>
</details>
@else
<details style="padding:12px"><summary>รายงานโพสต์</summary><form method="post" action="{{route('posts.report',$post)}}">@csrf<label>เหตุผล<textarea name="reason" required maxlength="1000"></textarea></label><button class="mini-btn" type="submit">ส่งรายงาน</button></form></details>
@endif
