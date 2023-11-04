@php
    $id = $attributes->get('id') ?? Str::random(10);    
@endphp
<a href="{{ route('databaseupdater') }}"
    {{ $attributes->merge(['id' => $id, 'class' => 'btn btn-primary']) }}>
    {{ $slot }}
</a>

<script>
    document.getElementById('{{ $id }}').addEventListener('click', function(e) {
        e.href = "#";
        e.target.innerHTML = "Updating...";
        e.target.classList.add('disabled');
    });
</script>
