<!DOCTYPE html>
<html lang="en" class="h-full bg-fp-base" style="color-scheme: dark;">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="dark">
  <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&family=jetbrains-mono:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-inter bg-fp-base text-fp-hi antialiased font-features-['cv02','cv03','cv04','cv11']">

<div class="flex min-h-full flex-col justify-center px-6 py-12 lg:px-8">
  <div class="sm:mx-auto sm:w-full sm:max-w-sm">
    <a href="/" class="flex items-center justify-center gap-2">
      <span class="flex h-7 w-7 items-center justify-center rounded-md bg-fp-accent/15 font-mono text-sm font-semibold text-fp-accent">fp</span>
      <span class="font-mono text-sm font-medium tracking-tight text-fp-hi">feedbackpilgrim</span>
    </a>
    <h2 class="mt-10 text-center text-2xl font-semibold leading-9 tracking-tight text-fp-hi">{{ $title ?? 'Sign in to your account' }}</h2>
  </div>

  <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-sm">
    {{ $slot }}
  </div>
</div>

</body>
</html>
