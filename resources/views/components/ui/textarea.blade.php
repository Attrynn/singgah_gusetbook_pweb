@props(['name', 'label' => null, 'value' => null, 'rows' => 4, 'hint' => null, 'required' => false, 'placeholder' => null])

@php
    $id = $attributes->get('id') ?? $name;
    $hasError = $errors->has($name);
    $errorId = $id.'-error';
    $hintId = $id.'-hint';
    $describedBy = trim(($hasError ? $errorId : '').($hint ? ($hasError ? ' ' : '').$hintId : ''));
@endphp

<div>
    @if ($label)
        <label for="{{ $id }}" class="label">
            {{ $label }}
            @if ($required)
                <span class="text-danger" aria-hidden="true">*</span>
                <span class="sr-only">(wajib)</span>
            @endif
        </label>
    @endif

    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        placeholder="{{ $placeholder }}"
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->merge(['class' => 'input resize-y']) }}
    >{{ old($name, $value) }}</textarea>

    @error($name)
        <p id="{{ $errorId }}" class="error-text">{{ $message }}</p>
    @enderror

    @if ($hint)
        <p id="{{ $hintId }}" class="hint">{{ $hint }}</p>
    @endif
</div>
