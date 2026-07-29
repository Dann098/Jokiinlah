@include('errors.minimal', [
    'status' => '429',
    'title' => 'Terlalu banyak permintaan',
    'message' => 'Tunggu beberapa saat sebelum mencoba kembali.',
])
