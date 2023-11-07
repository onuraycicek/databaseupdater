@php
    $changes = session('response')['changes'] ?? session('response');
@endphp
@if (session('response'))
    <div {{$attributes->merge(['class' => 'alert alert-' . (session('response')['status'] === 'success' ? 'success' : 'danger')])}}>
        <pre>{{ print_r($changes, true) }}</pre>
    </div>
@endif
