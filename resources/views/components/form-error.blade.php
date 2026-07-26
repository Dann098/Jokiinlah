@props(['name'])
@error($name)<p id='{{ $name }}-error' class='mt-2 text-sm font-semibold text-red-700'>{{ $message }}</p>@enderror
