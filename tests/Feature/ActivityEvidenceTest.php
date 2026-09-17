<?php
namespace Tests\Feature;

use App\Models\{Post, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ActivityEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('evidence');
    }

    private function member(): User { return User::where('email','kan@greenpoint.test')->firstOrFail(); }
    private function image(string $extension='png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('evidence.'.$extension,
            file_get_contents(base_path('tests/Fixtures/evidence.'.($extension==='jpeg'?'jpg':$extension))));
    }
    private function payload(array $extra=[]): array
    {
        return array_replace(['content'=>'กิจกรรมทดสอบ','privacy'=>'public','request_points'=>1],$extra);
    }

    public static function formats(): array { return [['jpg'],['jpeg'],['png']]; }
    #[DataProvider('formats')]
    public function test_valid_evidence_is_stored_privately_with_unique_names(string $extension): void
    {
        foreach ([1,2] as $i) {
            $this->actingAs($this->member())->post('/posts',$this->payload(['image'=>$this->image($extension)]))
                ->assertSessionHasNoErrors()->assertSessionHas('success');
        }
        $posts=Post::where('content','กิจกรรมทดสอบ')->get();
        $this->assertCount(2,$posts);
        $this->assertNotEquals($posts[0]->image,$posts[1]->image);
        foreach ($posts as $post) {
            $this->assertEquals('pending',$post->status);
            $this->assertStringStartsWith('posts/',$post->image);
            Storage::disk('evidence')->assertExists($post->image);
            $this->assertFalse(file_exists(public_path('storage/'.$post->image)));
            $this->get(route('posts.image',$post))->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        }
    }

    public function test_points_require_an_image_and_plain_posts_do_not(): void
    {
        $this->actingAs($this->member())->post('/posts',$this->payload())
            ->assertSessionHasErrors(['image'=>'กรุณาแนบรูปหลักฐานเมื่อส่งผลงานเพื่อรับคะแนน']);
        $this->assertDatabaseMissing('posts',['content'=>'กิจกรรมทดสอบ']);
        $this->post('/posts',$this->payload(['request_points'=>0]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('posts',['content'=>'กิจกรรมทดสอบ','image'=>null,'status'=>'published']);
        $this->get('/feed')->assertOk()->assertSee('กิจกรรมทดสอบ');
    }

    public function test_optional_image_on_a_plain_post_is_displayed(): void
    {
        $this->actingAs($this->member())->post('/posts',$this->payload(['request_points'=>0,'image'=>$this->image()]))->assertSessionHasNoErrors();
        $post=Post::where('content','กิจกรรมทดสอบ')->firstOrFail();
        $this->get('/feed')->assertOk()->assertSee(route('posts.image',$post));
    }

    public function test_invalid_files_and_multiple_files_are_rejected_without_storage(): void
    {
        $invalid=[
            UploadedFile::fake()->createWithContent('fake.jpg','This is not a photo'),
            UploadedFile::fake()->createWithContent('vector.svg','<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>'),
            UploadedFile::fake()->createWithContent('photo.gif',base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')),
            UploadedFile::fake()->createWithContent('photo.txt',file_get_contents(base_path('tests/Fixtures/evidence.png'))),
            [$this->image(),$this->image()],
        ];
        foreach ($invalid as $file) {
            $response=$this->actingAs($this->member())->post('/posts',$this->payload(['image'=>$file]));
            $response->assertSessionHasErrors('image');
            $this->assertMatchesRegularExpression('/[ก-๙]/u',session('errors')->first('image'));
        }
        $this->assertSame([],Storage::disk('evidence')->allFiles());
        $this->assertDatabaseMissing('posts',['content'=>'กิจกรรมทดสอบ']);
    }

    public function test_five_megabytes_is_allowed_but_one_byte_over_is_rejected(): void
    {
        $bytes=file_get_contents(base_path('tests/Fixtures/evidence.png'));
        $this->actingAs($this->member())->post('/posts',$this->payload([
            'image'=>UploadedFile::fake()->createWithContent('large.png',str_pad($bytes,5*1024*1024+1,"\0")),
        ]))->assertSessionHasErrors(['image'=>'รูปหลักฐานต้องมีขนาดไม่เกิน 5 MB']);
        $this->assertSame([],Storage::disk('evidence')->allFiles());
        $this->post('/posts',$this->payload([
            'image'=>UploadedFile::fake()->createWithContent('exact.png',str_pad($bytes,5*1024*1024,"\0")),
        ]))->assertSessionHasNoErrors();
    }

    public function test_invalid_other_fields_leave_no_files_and_database_failure_cleans_up(): void
    {
        $this->actingAs($this->member())->post('/posts',$this->payload(['content'=>'','image'=>$this->image()]))->assertSessionHasErrors('content');
        $this->assertSame([],Storage::disk('evidence')->allFiles());
        Post::creating(function () { throw new \RuntimeException('Simulated database failure'); });
        $this->withoutExceptionHandling();
        try {
            $this->post('/posts',$this->payload(['image'=>$this->image()]));
            $this->fail('Expected database failure');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Simulated database failure',$e->getMessage());
        } finally {
            Post::flushEventListeners();
        }
        $this->assertSame([],Storage::disk('evidence')->allFiles());
        $this->assertDatabaseMissing('posts',['content'=>'กิจกรรมทดสอบ']);
    }

    public static function visibility(): array
    {
        $rows=[];
        foreach (['public','private'] as $privacy) {
            foreach (['pending','rejected','published','approved'] as $status) {
                $rows[] = [$privacy,$status,$privacy==='public' && in_array($status,['published','approved'])];
            }
        }
        return $rows;
    }

    #[DataProvider('visibility')]
    public function test_image_route_and_profile_enforce_visibility(string $privacy,string $status,bool $public): void
    {
        $owner=$this->member();
        $post=Post::create(['user_id'=>$owner->id,'content'=>'unique-hidden-evidence','privacy'=>$privacy,'status'=>$status,
            'request_points'=>true,'image'=>$this->image()->store('posts','evidence')]);
        $url=route('posts.image',$post);
        $this->get($url)->assertRedirect(route('login'));
        $other=User::where('email','mint@greenpoint.test')->firstOrFail();
        $response=$this->actingAs($other)->get($url);
        $profile=$this->get(route('profile',$owner))->assertOk();
        $feed=$this->get('/feed')->assertOk();
        if ($public) {
            $response->assertOk();
            $profile->assertSee($post->content)->assertSee($url);
            $feed->assertSee($url);
        } else {
            $response->assertForbidden();
            $profile->assertDontSee($post->content)->assertDontSee($url);
            $feed->assertDontSee($url);
        }
        $this->actingAs($owner)->get($url)->assertOk();
        $this->get('/activities')->assertOk()->assertSee($url);
        $this->get(route('profile',$owner))->assertOk()->assertSee($url);
        $admin=User::where('role','admin')->firstOrFail();
        $this->actingAs($admin)->get($url)->assertOk();
        $this->get(route('profile',$owner))->assertOk()->assertSee($url);
        if ($status==='pending') $this->get('/admin')->assertOk()->assertSee($url)->assertSee('กดดูรูปขนาดใหญ่');
        // No alternate public or signed-storage endpoint may bypass the controller.
        $this->get('/storage/'.$post->image)->assertForbidden();
        $this->get('/storage/evidence/'.$post->image)->assertForbidden();
    }

    public function test_revoking_visibility_takes_effect_on_the_same_image_url(): void
    {
        $post=Post::create(['user_id'=>$this->member()->id,'content'=>'revoked','privacy'=>'public','status'=>'approved',
            'image'=>$this->image()->store('posts','evidence')]);
        $other=User::where('email','mint@greenpoint.test')->firstOrFail();
        $response=$this->actingAs($other)->get(route('posts.image',$post))->assertOk();
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $post->update(['privacy'=>'private']);
        $this->get(route('posts.image',$post))->assertForbidden();
    }

    public function test_legacy_posts_without_images_still_render(): void
    {
        $this->actingAs($this->member());
        foreach (['/feed','/activities','/profile'] as $url) $this->get($url)->assertOk();
        $post=Post::where('user_id',$this->member()->id)->firstOrFail();
        $this->get(route('posts.image',$post))->assertNotFound();
        $this->actingAs(User::where('role','admin')->firstOrFail())->get('/admin')->assertOk();
    }
}
