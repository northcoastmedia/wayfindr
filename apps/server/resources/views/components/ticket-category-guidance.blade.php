@props(['categories'])

<div class="notice-list" aria-label="Category guide">
    @foreach ($categories as $category)
        <p>{{ $category['label'] }} - {{ $category['description'] }}</p>
    @endforeach
</div>
