<form method="post" action="{{isset($post)?route('posts.update',$post):route('posts.store')}}" enctype="multipart/form-data" id="post-form">
@csrf @isset($post) @method('PUT') @endisset
<input name="title" value="{{old('title',($post??null)?->title??null)}}" placeholder="ชื่อกิจกรรม (ไม่จำเป็น)">
<textarea name="content" required placeholder="เล่าเรื่องราวของคุณ...">{{old('content',($post??null)?->content??null)}}</textarea>
<div class="form-grid">
<select name="activity_type_id"><option value="">แชร์เรื่องราวทั่วไป</option>@foreach($types as $t)<option value="{{$t->id}}" @selected(old('activity_type_id',($post??null)?->activity_type_id??null)==$t->id)>{{$t->icon}} {{$t->name}} (+{{$t->points}})</option>@endforeach</select>
<input type="date" name="activity_date" value="{{old('activity_date',isset($post)?$post->activity_date?->format('Y-m-d'):null)}}">
<input type="number" name="tree_count" min="0" value="{{old('tree_count',($post??null)?->tree_count??null)}}" placeholder="จำนวนต้นไม้">
<input name="location" value="{{old('location',($post??null)?->location??null)}}" placeholder="สถานที่ (คร่าว ๆ)">
<select name="privacy"><option value="public">🌐 สาธารณะ</option><option value="private" @selected(old('privacy',($post??null)?->privacy??null)==='private')>🔒 ส่วนตัว</option></select>
<label class="check"><input type="checkbox" name="request_points" id="request-points" @disabled(isset($post)) value="1" @checked(old('request_points',($post??null)?->request_points??null))> ส่งผลงานเพื่อรับคะแนน</label>
</div>
<div class="evidence-upload">
<label for="evidence-file">รูปหลักฐานกิจกรรม</label>
<input type="file" id="evidence-file" name="image" accept=".jpg,.jpeg,.png,image/jpeg,image/png" aria-describedby="evidence-help evidence-error" data-existing="{{isset($post)&&$post->image?'1':'0'}}" @required(old('request_points',($post??null)?->request_points??null) && !(isset($post)&&$post->image))>
<small id="evidence-help">แนบได้ 1 รูป ชนิด JPG, JPEG หรือ PNG ขนาดไม่เกิน 5 MB · ต้องแนบเมื่อขอรับคะแนน</small>
<p id="evidence-error" role="alert">@error('image'){{$message}}@enderror</p>
@if($errors->any())<small>กรุณาเลือกรูปอีกครั้งก่อนส่ง</small>@endif
<img id="evidence-preview" class="evidence-image" alt="ตัวอย่างรูปหลักฐานก่อนส่ง" hidden>
<button type="button" id="remove-evidence" class="btn secondary" hidden>นำรูปออก</button>
</div>
<button class="btn">{{isset($post)?'ส่งให้ตรวจสอบใหม่':'เผยแพร่โพสต์'}}</button>
</form>
<script src="{{asset('js/activity-evidence.js')}}" defer></script>
