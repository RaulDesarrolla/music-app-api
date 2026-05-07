<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spotify Debug Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-900 min-h-screen text-white">

    <nav class="bg-gray-800 border-b border-gray-700 p-4">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <span class="font-bold text-xl text-green-500">Spotify API Debug</span>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="text-sm bg-red-600 hover:bg-red-700 px-4 py-2 rounded-full transition">
                    Cerrar Sesión
                </button>
            </form>
        </div>
    </nav>

    <main class="py-12 px-4">
        <div class="max-w-4xl mx-auto space-y-8">

            @if(isset($profile))
                <div
                    class="bg-gray-800 border border-gray-700 rounded-xl p-6 flex flex-col md:flex-row items-center gap-6 shadow-2xl">
                    @if(!empty($profile['images']))
                        <img src="{{ $profile['images'][0]['url'] }}" alt="Avatar"
                            class="w-24 h-24 rounded-full border-2 border-green-500">
                    @endif

                    <div class="flex-grow text-center md:text-left">
                        <h1 class="text-3xl font-bold">{{ $profile['display_name'] }}</h1>
                        <p class="text-gray-400">{{ $profile['email'] }}</p>
                        <div class="mt-3 flex flex-wrap justify-center md:justify-start gap-4">
                            <span class="bg-gray-700 px-3 py-1 rounded-md text-xs uppercase tracking-wider">
                                {{ $profile['followers']['total'] }} Seguidores
                            </span>
                            <a href="{{ route('spotify.connect') }}"
                                class="text-green-500 hover:underline text-sm font-bold">
                                ↻ Reconectar cuenta
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            <div class="bg-gray-800 border border-gray-700 rounded-xl p-8 shadow-xl">
                <h3 class="text-2xl font-bold mb-6 text-center">Buscador de Música</h3>

                <form action="{{ route('spotify.search') }}" method="GET" class="relative">
                    <input type="text" name="query" placeholder="Canción, álbum o artista..." required
                        class="w-full bg-gray-700 border-none text-white rounded-full py-4 px-6 focus:ring-2 focus:ring-green-500 placeholder-gray-500 text-lg">

                    <button type="submit"
                        class="absolute right-2 top-2 bg-green-500 hover:bg-green-400 text-black font-bold p-3 rounded-full transition shadow-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </button>
                </form>
                @if(isset($results) && count($results) > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-8">
                        @foreach($results as $item)
                            <div class="bg-gray-700 p-4 rounded-lg flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <img src="{{ $item['portada'] }}" class="w-16 h-16 rounded shadow">
                                    <div>
                                        <h4 class="font-bold">{{ $item['nombre'] }}</h4>
                                        <p class="text-sm text-gray-400">{{ $item['artista'] }}</p>
                                    </div>
                                </div>

                                <form action="{{ route('posts.store') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="track_name" value="{{ $item['nombre'] }}">
                                    <input type="hidden" name="artist_name" value="{{ $item['artista'] }}">
                                    <input type="hidden" name="album_name" value="{{ $item['album'] }}">
                                    <input type="hidden" name="image_url" value="{{ $item['portada'] }}">

                                    <button type="submit"
                                        class="bg-green-500 hover:bg-green-400 text-black text-xs font-bold py-2 px-4 rounded-full uppercase">
                                        Publicar
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="bg-blue-900/30 border border-blue-500/50 rounded-lg p-4 text-sm text-blue-200">
                <strong>Nota de desarrollo:</strong> Estas vistas son temporales para pruebas.
                El endpoint de búsqueda actualmente devuelve un <code>JSON</code> puro para que el Frontend pueda
                consumirlo después.
            </div>

        </div>
    </main>

</body>

</html>