@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'required' => false, 'hint' => null])

@php
    $id = $attributes->get('id') ?? $name;
    $selected = old($name, $value);
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

    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->merge(['class' => 'input appearance-none pr-10']) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            @php
                $isObject = is_object($optionLabel);
                $val = $isObject ? $optionLabel->value : $optionValue;
                $text = $isObject ? $optionLabel->label() : $optionLabel;
            @endphp
            <option value="{{ $val }}" @selected((string) $selected === (string) $val)>{{ $text }}</option>
        @endforeach
    </select>

    @error($name)
        <p id="{{ $errorId }}" class="error-text">{{ $message }}</p>
    @enderror

    @if ($hint)
        <p id="{{ $hintId }}" class="hint">{{ $hint }}</p>
    @endif
</div>
