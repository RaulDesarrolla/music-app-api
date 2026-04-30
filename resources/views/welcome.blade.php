<!-- resources/views/welcome.blade.php -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bienvenido</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding-top: 50px; }
        .btn { padding: 10px 20px; background: #1DB954; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>¡Hola, {{ Auth::user()->name }}!</h1>
    <p>Has iniciado sesión correctamente en la aplicación.</p>
    <br>
    <a href="/spotify/connect" class="btn">Conectar con Spotify</a>
</body>
</html>