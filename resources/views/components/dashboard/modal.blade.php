@props([
    'name' => 'dashboard-modal',
    'title' => null,
    'description' => null,
    'confirmLabel' => 'Confirm',
    'cancelLabel' => 'Cancel',
    'variant' => 'default',
    'formAction' => null,
    'method' => 'POST',
])

{{-- Explicitly forward confirm/form props — attribute bag alone was dropping formAction on the nested ui.modal. --}}
<x-ui.modal
    :name="$name"
    :title="$title"
    :description="$description"
    :confirm-label="$confirmLabel"
    :cancel-label="$cancelLabel"
    :variant="$variant"
    :form-action="$formAction"
    :method="$method"
    {{ $attributes }}
>
    {{ $slot }}
    @isset($form)
        <x-slot:form>{{ $form }}</x-slot:form>
    @endisset
    @isset($footer)
        <x-slot:footer>{{ $footer }}</x-slot:footer>
    @endisset
</x-ui.modal>
