
@if (session('response'))
    <div {{$attributes->merge(['class' => 'alert alert-' . (session('response')['status'] === 'success' ? 'success' : 'danger')])}}>
        <pre>{{ print_r(session('response')['changes'], true) }}</pre>
    </div>
@endif
