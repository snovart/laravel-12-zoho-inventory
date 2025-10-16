<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Zoho Inventory — Sales Orders</title>

  {{-- Load Tailwind + our Vue SPA entry --}}
  @vite([
    'resources/css/app.css',
    'resources/js/zoho/inventory/app.js',
  ])
</head>
<body class="bg-gray-50 text-gray-900 antialiased">
  {{-- Vue mounts here --}}
  <div id="app"></div>
</body>
</html>
