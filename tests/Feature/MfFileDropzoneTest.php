<?php

test('MfFileDropzone declares the documented props with the 200MB default', function () {
    $source = file_get_contents(resource_path('js/components/MfFileDropzone.vue'));

    expect($source)
        ->toContain('accept: string')
        ->toContain('multiple?: boolean')
        ->toContain('maxSize?: number')
        ->toContain('disabled?: boolean')
        ->toContain('maxSize: 209_715_200');
});

test('MfFileDropzone validates extension, size, and multi-file with the documented error codes', function () {
    $source = file_get_contents(resource_path('js/components/MfFileDropzone.vue'));

    expect($source)
        ->toContain("code: 'invalid-type'")
        ->toContain("code: 'too-large'")
        ->toContain("code: 'multiple-not-allowed'")
        ->toContain('allowedExtensions.value.includes(fileExt(file.name))')
        ->toContain('file.size > props.maxSize');
});

test('MfFileDropzone exposes setProgress as a ref method and renders idle / uploading / success / error states', function () {
    $source = file_get_contents(resource_path('js/components/MfFileDropzone.vue'));

    expect($source)
        ->toContain('defineExpose({ setProgress, reset })')
        ->toContain("state === 'idle'")
        ->toContain("state === 'uploading'")
        ->toContain("state === 'success'")
        ->toContain("state === 'error'")
        ->toContain('pi pi-cloud-upload')
        ->toContain('Drop files here or click to browse');
});

test('MfFileDropzone applies the brand-orange tint on drag-over and emits upload', function () {
    $source = file_get_contents(resource_path('js/components/MfFileDropzone.vue'));

    expect($source)
        ->toContain('border-mf-orange bg-mf-orange/10')
        ->toContain("emit('upload', valid)")
        ->toContain('onDrop')
        ->toContain('onDragOver');
});
