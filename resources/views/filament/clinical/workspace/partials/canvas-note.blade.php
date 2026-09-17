{{-- Rendered inside `<template x-for="note in layout.notes">` — one root element. --}}
<div
    class="absolute rounded-lg border shadow-md"
    x-bind:class="[noteClass(note), isSelected(note.id) ? 'ring-2 ring-primary-500' : '', connectFrom === note.id ? 'ring-2 ring-primary-400 ring-dashed' : '']"
    x-bind:style="`left: ${note.x}px; top: ${note.y}px; width: ${note.w}px; height: ${note.h}px`"
    x-bind:data-note-id="note.id"
    x-on:pointerdown.stop="onItemPointerDown($event, note.id, 'note')"
>
    <div class="flex h-6 items-center justify-end gap-0.5 px-1 pt-1">
        <button type="button" class="rounded p-0.5 opacity-60 hover:opacity-100" title="Change colour" x-on:pointerdown.stop x-on:click.stop="cycleNoteColor(note.id)">
            <x-heroicon-m-swatch class="h-3.5 w-3.5" />
        </button>
        <button type="button" class="rounded p-0.5 opacity-60 hover:opacity-100" title="Remove note" x-on:pointerdown.stop x-on:click.stop="removeNote(note.id)">
            <x-heroicon-m-x-mark class="h-3.5 w-3.5" />
        </button>
    </div>
    <textarea
        style="height: calc(100% - 1.5rem)" class="block w-full resize-none border-0 bg-transparent p-2 pt-0 text-xs leading-snug placeholder:opacity-50 focus:ring-0"
        placeholder="Write a note…"
        maxlength="2000"
        x-bind:readonly="readOnly"
        x-model="note.text"
        x-on:input="markDirty()"
        x-on:pointerdown.stop="selection = { type: 'note', id: note.id }"
        x-on:keydown.stop
    ></textarea>
    <div
        class="absolute bottom-0 right-0 h-4 w-4 cursor-nwse-resize opacity-50"
        x-show="!readOnly"
        x-on:pointerdown="onNoteResizePointerDown($event, note.id)"
    >
        <svg viewBox="0 0 16 16" class="h-4 w-4 fill-current"><path d="M14 14H12V12H14V14ZM14 10H12V8H14V10ZM10 14H8V12H10V14Z"/></svg>
    </div>
</div>
