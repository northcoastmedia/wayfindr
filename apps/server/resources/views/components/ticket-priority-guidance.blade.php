@props(['priorities'])

<div class="notice-list" aria-label="Priority guide">
    @foreach ($priorities as $priority)
        <p>{{ $priority['label'] }} - {{ $priority['description'] }}</p>
    @endforeach
</div>
