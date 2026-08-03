<b>Test</b>
<img src="{{ $message->embedData(file_get_contents(config('filesystems.disks.local.root').'/blue.jpg'), 'embedded-image.jpg', 'image/jpeg') }}" alt="Embedded"/>
