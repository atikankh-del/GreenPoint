<form method="post" action="{{route('posts.store')}}" enctype="multipart/form-data" id="post-form">
@csrf
<input name="title" value="{{old('title')}}" placeholder="ชื่อกิจกรรม (ไม่จำเป็น)">
<textarea name="content" required placeholder="เล่าเรื่องราวของคุณ...">{{old('content')}}</textarea>
<div class="form-grid">
<select name="activity_type_id"><option value="">แชร์เรื่องราวทั่วไป</option>@foreach($types as $t)<option value="{{$t->id}}" @selected(old('activity_type_id')==$t->id)>{{$t->icon}} {{$t->name}} (+{{$t->points}})</option>@endforeach</select>
<input type="date" name="activity_date" value="{{old('activity_date')}}">
<input type="number" name="tree_count" min="0" value="{{old('tree_count')}}" placeholder="จำนวนต้นไม้">
<input name="location" value="{{old('location')}}" placeholder="สถานที่ (คร่าว ๆ)">
<select name="privacy"><option value="public">🌐 สาธารณะ</option><option value="private" @selected(old('privacy')==='private')>🔒 ส่วนตัว</option></select>
<label class="check"><input type="checkbox" name="request_points" id="request-points" value="1" @checked(old('request_points'))> ส่งผลงานเพื่อรับคะแนน</label>
</div>
<div class="evidence-upload">
<label for="evidence-file">รูปหลักฐานกิจกรรม</label>
<input type="file" id="evidence-file" name="image" accept=".jpg,.jpeg,.png,image/jpeg,image/png" aria-describedby="evidence-help evidence-error" @required(old('request_points'))>
<small id="evidence-help">แนบได้ 1 รูป ชนิด JPG, JPEG หรือ PNG ขนาดไม่เกิน 5 MB · ต้องแนบเมื่อขอรับคะแนน</small>
<p id="evidence-error" role="alert">@error('image'){{$message}}@enderror</p>
@if($errors->any())<small>กรุณาเลือกรูปอีกครั้งก่อนส่ง</small>@endif
<img id="evidence-preview" class="evidence-image" alt="ตัวอย่างรูปหลักฐานก่อนส่ง" hidden>
<button type="button" id="remove-evidence" class="btn secondary" hidden>นำรูปออก</button>
</div>
<button class="btn">เผยแพร่โพสต์</button>
</form>
<script src="{{asset('js/activity-evidence.js')}}" defer></script>
