@if($post->image)
<a class="evidence-link" href="{{ route('posts.image', $post) }}" target="_blank" rel="noopener">
    <img class="evidence-image" src="{{ route('posts.image', $post) }}" alt="รูปหลักฐานกิจกรรม{{ $post->title ? ': '.$post->title : '' }}" loading="lazy">
    <small>กดดูรูปขนาดใหญ่</small>
</a>
@endif
