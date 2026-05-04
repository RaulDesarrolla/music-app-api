<!-- resources/views/dashboard.blade.php -->
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Dashboard - Perfil de Spotify</title>
    <style>
        .profile-card {
            border: 1px solid #ddd;
            padding: 20px;
            text-align: center;
            width: 300px;
            border-radius: 10px;
        }

        .avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
        }
    </style>
</head>

<body>
    <h1>Bienvenido a tu Dashboard</h1>

    @if(isset($profile))
        <div class="profile-card">
            <!-- Spotify devuelve las imágenes en un array 'images' -->
            @if(!empty($profile['images']))
                <img src="{{ $profile['images'][0]['url'] }}" alt="Foto de perfil" class="avatar">
            @endif

            <h2>{{ $profile['display_name'] }}</h2>
            <p>Email: {{ $profile['email'] }}</p>
            <p>Seguidores: {{ $profile['followers']['total'] }}</p>
            <a href="{{ $profile['external_urls']['spotify'] }}" target="_blank">Ver en Spotify</a>
        </div>
    @endif

    <br>
    <a href="{{ route('spotify.connect') }}">Reconectar Spotify</a>
    <form action="{{ route('logout') }}" method="POST">
        @csrf
        <button type="submit">Cerrar Sesión</button>
    </form>
</body>

</html>