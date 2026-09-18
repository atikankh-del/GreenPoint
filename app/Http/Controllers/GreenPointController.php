<?php

namespace App\Http\Controllers;

use App\Models\ActivityType;
use App\Models\Badge;
use App\Models\Challenge;
use App\Models\Comment;
use App\Models\Like;
use App\Models\PointsTransaction;
use App\Models\Post;
use App\Models\Report;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\BadgeAwardService;
use App\Services\ChallengeAwardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GreenPointController extends Controller
{
    public function home()
    {
        return Auth::check() ? redirect()->route('feed') : view('landing');
    }

    public function loginForm()
    {
        return view('auth', ['mode' => 'login']);
    }

    public function registerForm()
    {
        return view('auth', ['mode' => 'register']);
    }

    public function login(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required']);
        if (Auth::attempt($d)) {
            if (Auth::user()->status !== 'active') {
                Auth::logout();

                return back()->withErrors(['email' => 'บัญชีนี้ถูกระงับ กรุณาติดต่อผู้ดูแล']);
            }$r->session()->regenerate();

            return redirect()->intended(route(Auth::user()->role === 'admin' ? 'admin' : 'feed'));
        }

return back()->withErrors(['email' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง'])->onlyInput('email');
    }

    public function register(Request $r)
    {
        $d = $r->validate(['name' => 'required|max:80', 'email' => 'required|email|unique:users', 'password' => 'required|min:8|confirmed']);
        $u = User::create(['name' => $d['name'], 'email' => $d['email'], 'password' => Hash::make($d['password'])]);
        Auth::login($u);

        return redirect()->route('feed')->with('success', 'ยินดีต้อนรับสู่ GreenPoint!');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/');
    }

    public function feed()
    {
        return view('feed', ['posts' => Post::with(['user', 'activityType', 'likes', 'comments.user'])->where('privacy', 'public')->whereIn('status', ['published', 'approved'])->latest()->get(), 'types' => ActivityType::where('active', 1)->get()]);
    }

    public function storePost(Request $r, ?Post $post = null)
    {
        if ($post) {
            abort_unless($post->user_id === Auth::id(), 403);
            abort_unless($post->status === 'rejected', 409);
            $r->merge(['request_points' => $post->request_points]);
        }
        $d = $r->validate([
            'content' => 'required|max:2000', 'title' => 'nullable|max:120',
            'activity_type_id' => 'nullable|exists:activity_types,id', 'tree_count' => 'nullable|integer|min:0',
            'location' => 'nullable|max:120', 'activity_date' => 'nullable|date', 'privacy' => 'required|in:public,private',
            'image' => ['bail', Rule::requiredIf($r->boolean('request_points') && ! $post?->image),
                'nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'mimetypes:image/jpeg,image/png', 'extensions:jpg,jpeg,png', 'max:5120',
                function ($attribute, $file, $fail) {
                    $info = @getimagesize($file->getRealPath());
                    $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
                    if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true) || ! in_array($mime, ['image/jpeg', 'image/png'], true)) {
                        $fail('ไฟล์หลักฐานต้องเป็นรูปภาพจริงชนิด JPG, JPEG หรือ PNG');
                    }
                }],
        ], [
      'image.required' => 'กรุณาแนบรูปหลักฐานเมื่อส่งผลงานเพื่อรับคะแนน',
      'image.file' => 'กรุณาแนบไฟล์รูปภาพ 1 รูป',
      'image.image' => 'ไฟล์หลักฐานต้องเป็นรูปภาพจริงชนิด JPG, JPEG หรือ PNG',
      'image.mimes' => 'รองรับเฉพาะรูป JPG, JPEG และ PNG เท่านั้น',
      'image.mimetypes' => 'รองรับเฉพาะรูป JPG, JPEG และ PNG เท่านั้น',
      'image.extensions' => 'นามสกุลไฟล์ต้องเป็น JPG, JPEG หรือ PNG',
      'image.max' => 'รูปหลักฐานต้องมีขนาดไม่เกิน 5 MB',
      'image.uploaded' => 'อัปโหลดรูปไม่สำเร็จ กรุณาเลือกไฟล์ JPG, JPEG หรือ PNG ขนาดไม่เกิน 5 MB',
  ]);
        unset($d['image']);
        $d += ['user_id' => Auth::id(), 'tree_count' => $d['tree_count'] ?? 0, 'request_points' => $r->boolean('request_points')];
        $d['status'] = $post || $d['request_points'] || SystemSetting::valueOf('auto_approve_story', '1') !== '1' ? 'pending' : 'published';
        $path = null;
        try {
            if ($r->hasFile('image')) {
                $d['image'] = $path = $r->file('image')->store('posts', 'evidence');
            }
            DB::transaction(function () use ($post, $d) {
                if (! $post) {
                    return Post::create($d);
                } $locked = Post::lockForUpdate()->findOrFail($post->id);
                abort_unless($locked->status === 'rejected', 409);
                $locked->update($d + ['review_note' => null, 'points_awarded' => 0]);
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('evidence')->delete($path);
            }
            throw $e;
        }
        if ($post && $path && $post->image) {
            Storage::disk('evidence')->delete($post->image);
        }
        if ($post) {
            return redirect()->route('activities')->with('success', 'ส่งกิจกรรมให้ตรวจสอบใหม่แล้ว');
        }

        return back()->with('success', $d['status'] === 'pending' ? 'ส่งโพสต์ให้ผู้ดูแลตรวจสอบแล้ว' : 'เผยแพร่โพสต์แล้ว');
    }

    public function showPost(Post $post)
    {
        abort_unless(Post::visibleTo(Auth::user())->whereKey($post->id)->exists(), 403);

        return view('post', ['post' => $post->load(['user', 'activityType'])]);
    }

    public function editPost(Post $post)
    {
        abort_unless($post->user_id === Auth::id(), 403);
        abort_unless($post->status === 'rejected', 409);

        return view('edit-post', ['post' => $post, 'types' => ActivityType::where('active', true)->get()]);
    }

    public function evidence(Request $r, Post $post)
    {
        abort_unless(Post::visibleTo($r->user())->whereKey($post->id)->exists(), 403);
        $disk = Storage::disk('evidence');
        abort_unless($post->image && $disk->exists($post->image), 404);

        return $disk->response($post->image, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function like(Post $post)
    {
        abort_unless(Post::visibleTo(Auth::user())->whereKey($post->id)->exists(), 403);
        $q = Like::where(['user_id' => Auth::id(), 'post_id' => $post->id])->first();
        $q ? $q->delete() : Like::create(['user_id' => Auth::id(), 'post_id' => $post->id]);

        return back();
    }

    public function comment(Request $r, Post $post)
    {
        abort_unless(Post::visibleTo(Auth::user())->whereKey($post->id)->exists(), 403);
        $d = $r->validate(['content' => 'required|max:500']);
        Comment::create($d + ['user_id' => Auth::id(), 'post_id' => $post->id]);

        return back();
    }

    public function dashboard()
    {
        $u = Auth::user();

        return view('dashboard', ['postCount' => $u->posts()->count(), 'treeCount' => $u->posts()->where('status', 'approved')->sum('tree_count'), 'pending' => $u->posts()->where('status', 'pending')->count(), 'recent' => $u->posts()->latest()->take(4)->get()]);
    }

    public function profile(?User $user = null)
    {
        $user = $user ?: Auth::user();

        return view('profile', ['profile' => $user, 'posts' => $user->posts()->visibleTo(Auth::user())->with(['activityType', 'likes', 'comments'])->latest()->get(), 'badges' => $user->badges]);
    }

    public function activities()
    {
        return view('activities', ['posts' => Auth::user()->posts()->with('activityType')->where('request_points', 1)->latest()->get()]);
    }

    public function points()
    {
        return view('points', ['transactions' => PointsTransaction::where('user_id', Auth::id())->latest()->get()]);
    }

    public function rewards()
    {
        return view('rewards', ['rewards' => Reward::where('status', 'active')->get(), 'redemptions' => RewardRedemption::with('reward')->where('user_id', Auth::id())->latest()->get()]);
    }

    public function redeem(Reward $reward)
    {
        DB::transaction(function () use ($reward) {
            // Acquire SQLite's write lock before reading balances (PHP 8.2).
            if (DB::getDriverName() === 'sqlite') {
                DB::table('users')->where('id', Auth::id())->update(['points'=>DB::raw('points')]);
            }
            $u = User::lockForUpdate()->findOrFail(Auth::id());
            $reward = Reward::lockForUpdate()->findOrFail($reward->id);
            if ($reward->status !== 'active' || $u->points < $reward->points_required || $reward->stock < 1) {
                throw ValidationException::withMessages(['reward' => 'คะแนนไม่พอ สินค้าหมด หรือรางวัลปิดให้แลก']);
            }
            $u->decrement('points', $reward->points_required);
            $reward->decrement('stock');
            RewardRedemption::create(['user_id' => $u->id, 'reward_id' => $reward->id, 'points_spent' => $reward->points_required, 'redemption_code' => 'GP-'.Str::upper(Str::random(16))]);
            PointsTransaction::create(['user_id' => $u->id, 'amount' => -$reward->points_required, 'type' => 'spend', 'description' => 'แลก '.$reward->name, 'balance_after' => $u->fresh()->points]);
        }, 5);

        return back()->with('success', 'แลกรางวัลสำเร็จแล้ว');
    }

    public function challenges()
    {
        $challenges = Challenge::with('activityType')->where('active', 1)->get();
        $progress = DB::table('challenge_progress')->where('user_id', Auth::id())->get()->keyBy('challenge_id');
        foreach ($challenges as $challenge) {
            $row = $progress->get($challenge->id) ?? (object) ['claimed_at'=>null];
            if (!$row->claimed_at) $row->progress = app(\App\Services\ChallengeAwardService::class)->progress(Auth::user(), $challenge);
            $progress->put($challenge->id, $row);
        }
        return view('challenges', compact('challenges', 'progress'));
    }

    public function leaderboard()
    {
        abort_if(SystemSetting::valueOf('ranking_enabled', '1') !== '1', 404);

        return view('leaderboard', ['users' => User::where('role', 'user')->where('status', 'active')->where('join_ranking', 1)->orderByDesc('points')->get()]);
    }

    public function admin()
    {
        abort_unless(Auth::user()->role === 'admin', 403);

        return view('admin', ['users' => User::count(), 'posts' => Post::count(), 'trees' => Post::where('status', 'approved')->sum('tree_count'), 'points' => PointsTransaction::where('amount', '>', 0)->sum('amount'), 'pending' => Post::with(['user', 'activityType'])->where('status', 'pending')->latest()->get(), 'rewards' => Reward::all()]);
    }

    public function review(Request $r, Post $post, BadgeAwardService $badgeAwards)
    {
        abort_unless(Auth::user()->role === 'admin', 403);
        $review = $r->validate(['status' => 'required|in:approved,rejected', 'review_note' => 'nullable|string|max:1000']);
        $status = $review['status'];
        if ($status === 'rejected' && $post->status === 'pending' && ! trim($review['review_note'] ?? '')) {
            throw ValidationException::withMessages(['review_note' => 'กรุณาระบุเหตุผลที่ปฏิเสธ']);
        }
        $result = DB::transaction(function () use ($post, $status, $badgeAwards, $review) {
            if (DB::getDriverName() === 'sqlite') {
                DB::table('users')->where('id', $post->user_id)->update(['points'=>DB::raw('points')]);
            }
            $lockedPost = Post::query()->with('activityType')->lockForUpdate()->findOrFail($post->id);
            if ($lockedPost->status !== 'pending') {
                return null;
            }$user = User::query()->lockForUpdate()->findOrFail($lockedPost->user_id);
            $lockedPost->status = $status;
            $lockedPost->review_note = $status === 'rejected' ? $review['review_note'] : null;
            if ($status === 'rejected') {
                $lockedPost->save();

                return [];
            } $type = $lockedPost->activityType;
            $earned = $lockedPost->request_points && $type ? ($type->counts_trees ? $type->points * max(1, $lockedPost->tree_count) : $type->points) : 0;
            $lockedPost->points_awarded = $earned;
            $lockedPost->save();
            if ($earned > 0) {
                $user->increment('points', $earned);
                $user->refresh();
                PointsTransaction::create(['user_id' => $user->id, 'amount' => $earned, 'type' => 'earn', 'description' => 'อนุมัติกิจกรรม: '.($lockedPost->title ?: $type?->name), 'balance_after' => $user->points, 'reference_type' => Post::class, 'reference_id' => $lockedPost->id]);
            }app(ChallengeAwardService::class)->update($user);

            return $badgeAwards->awardEligibleBadges($user);
        }, 5);
        if ($result === null) {
            return back()->withErrors(['review' => 'รายการนี้ได้รับการตรวจสอบไปแล้ว จึงไม่มีการเปลี่ยนคะแนนหรือสถานะ']);
        }if ($status === 'rejected') {
            return back()->with('success', 'ปฏิเสธกิจกรรมแล้ว');
        }$names = collect($result)->pluck('name')->join(', ');

        return back()->with('success', $names ? 'อนุมัติกิจกรรมแล้ว · มอบ Badge: '.$names : 'อนุมัติกิจกรรมแล้ว · ไม่มี Badge ใหม่');
    }

    private function adminOnly(): void
    {
        abort_unless(Auth::user()?->role === 'admin', 403);
    }

    public function adminManage(string $section)
    {
        $this->adminOnly();
        abort_unless(in_array($section, ['users', 'activities', 'rewards', 'redemptions', 'challenges', 'badges', 'reports', 'settings']), 404);
        $data = ['section' => $section, 'activityTypes' => ActivityType::orderBy('name')->get()];
        if ($section === 'users') {
            $data['items'] = User::withCount('posts')->latest()->get();
        }if ($section === 'activities') {
            $data['items'] = ActivityType::withCount('posts')->orderBy('name')->get();
        }if ($section === 'rewards') {
            $data['items'] = Reward::latest()->get();
        }if ($section === 'redemptions') {
            $data['items'] = RewardRedemption::with(['user', 'reward'])->latest()->get();
        }if ($section === 'challenges') {
            $data['items'] = Challenge::with('activityType')->latest()->get();
        }if ($section === 'badges') {
            $data['items'] = Badge::latest()->get();
        }if ($section === 'reports') {
            $data['items'] = Report::with(['reporter', 'post.user'])->latest()->get();
        }if ($section === 'settings') {
            $data['items'] = SystemSetting::orderBy('id')->get();
        }

return view('admin-manage', $data);
    }

    public function toggleUser(User $user)
    {
        $this->adminOnly();
        abort_if($user->id === Auth::id(), 422, 'ไม่สามารถระงับบัญชีของตัวเอง');
        $user->update(['status' => $user->status === 'active' ? 'suspended' : 'active']);

        return back()->with('success', 'อัปเดตสถานะสมาชิกแล้ว');
    }

    public function saveActivity(Request $r, ?ActivityType $activityType = null)
    {
        $this->adminOnly();
        $d = $r->validate(['name' => 'required|max:80', 'icon' => 'required|max:10', 'points' => 'required|integer|min:0|max:10000', 'color' => 'required|max:20']);
        ($activityType ?: new ActivityType)->fill($d + ['active' => $r->boolean('active'), 'counts_trees' => $r->boolean('counts_trees')])->save();

        return back()->with('success', 'บันทึกประเภทกิจกรรมแล้ว');
    }

    public function deleteActivity(ActivityType $activityType)
    {
        $this->adminOnly();
        abort_if($activityType->posts()->exists(), 422, 'ลบไม่ได้ เนื่องจากมีกิจกรรมใช้งานประเภทนี้');
        $activityType->delete();

        return back()->with('success', 'ลบประเภทกิจกรรมแล้ว');
    }

    public function saveReward(Request $r, ?Reward $reward = null)
    {
        $this->adminOnly();
        $d = $r->validate(['name' => 'required|max:120', 'description' => 'required|max:500', 'icon' => 'required|max:10', 'points_required' => 'required|integer|min:1', 'stock' => 'required|integer|min:0', 'status' => 'required|in:active,inactive']);
        ($reward ?: new Reward)->fill($d)->save();

        return back()->with('success', 'บันทึกรางวัลแล้ว');
    }

    public function deleteReward(Reward $reward)
    {
        $this->adminOnly();
        abort_if($reward->rewardRedemptions()->exists(), 422, 'ลบไม่ได้ เนื่องจากมีประวัติการแลกรางวัล');
        $reward->delete();

        return back()->with('success', 'ลบรางวัลแล้ว');
    }

    public function redemptionStatus(Request $r, RewardRedemption $redemption)
    {
        $this->adminOnly();
        $redemption->update($r->validate(['status' => 'required|in:pending,approved,shipped,completed,cancelled']));

        return back()->with('success', 'อัปเดตสถานะการรับรางวัลแล้ว');
    }

    public function saveChallenge(Request $r, ?Challenge $challenge = null)
    {
        $this->adminOnly();
        $d = $r->validate(['metric' => 'required|in:activities,trees,days', 'title' => 'required|max:120', 'description' => 'required|max:500', 'icon' => 'required|max:10', 'activity_type_id' => 'nullable|exists:activity_types,id', 'target' => 'required|integer|min:1', 'reward_points' => 'required|integer|min:0', 'starts_at' => 'required|date', 'ends_at' => 'required|date|after_or_equal:starts_at']);
        ($challenge ?: new Challenge)->fill($d + ['active' => $r->boolean('active')])->save();

        return back()->with('success', 'บันทึก Challenge แล้ว');
    }

    public function deleteChallenge(Challenge $challenge)
    {
        $this->adminOnly();
        DB::table('challenge_progress')->where('challenge_id', $challenge->id)->delete();
        $challenge->delete();

        return back()->with('success', 'ลบ Challenge แล้ว');
    }

    public function saveBadge(Request $r, ?Badge $badge = null)
    {
        $this->adminOnly();
        $d = $r->validate(['name' => 'required|max:100', 'description' => 'required|max:500', 'icon' => 'required|max:10', 'criteria_type' => 'required|in:trees,activities,points', 'criteria_value' => 'required|integer|min:1', 'bonus_points' => 'required|integer|min:0']);
        ($badge ?: new Badge)->fill($d)->save();

        return back()->with('success', 'บันทึก Badge แล้ว');
    }

    public function deleteBadge(Badge $badge)
    {
        $this->adminOnly();
        DB::table('user_badges')->where('badge_id', $badge->id)->delete();
        $badge->delete();

        return back()->with('success', 'ลบ Badge แล้ว');
    }

    public function reportStatus(Request $r, Report $report)
    {
        $this->adminOnly();
        $report->update($r->validate(['status' => 'required|in:pending,reviewed,dismissed']));

        return back()->with('success', 'อัปเดตรายงานแล้ว');
    }

    public function deletePost(Post $post)
    {
        $this->adminOnly();
        $post->delete();

        return back()->with('success', 'ลบโพสต์และข้อมูลที่เกี่ยวข้องแล้ว');
    }

    public function saveSettings(Request $r)
    {
        $this->adminOnly();
        $d = $r->validate(['site_name' => 'required|max:100', 'welcome_message' => 'required|max:255', 'support_email' => 'required|email']);
        foreach ($d as $k => $v) {
            SystemSetting::updateOrCreate(['key' => $k],['value' => $v, 'type' => 'text']);
        }foreach (['maintenance_mode', 'auto_approve_story', 'ranking_enabled'] as $k) {
            SystemSetting::updateOrCreate(['key' => $k],['value' => $r->boolean($k) ? '1' : '0', 'type' => 'boolean']);
        }

return back()->with('success','บันทึกการตั้งค่าระบบแล้ว');
    }
}
