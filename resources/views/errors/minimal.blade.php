<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} | Jokiinlah</title>
    <style>
        body{margin:0;background:#f8f4ee;color:#0b1933;font-family:system-ui,sans-serif}
        main{min-height:100vh;display:grid;place-items:center;padding:1.5rem}
        section{max-width:36rem;border:1px solid #e2e8f0;border-radius:1rem;background:#fff;padding:2rem;box-shadow:0 1rem 3rem #0b19331a}
        a{display:inline-block;margin-top:1rem;color:#0b1933;font-weight:700}
        :focus-visible{outline:3px solid #d6a83d;outline-offset:3px}
    </style>
</head>
<body>
<main>
    <section>
        <p aria-hidden="true">{{ $status }}</p>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <a href="{{ route('home') }}">Kembali ke beranda</a>
    </section>
</main>
</body>
</html>
