@props(['name', 'id' => null])
@error($name)<p id='{{ $id ?: $name."-error" }}' class='mt-2 text-sm font-semibold text-red-700'>{{ $message }}</p>@enderror
