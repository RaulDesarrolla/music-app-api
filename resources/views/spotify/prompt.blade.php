<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vinculación Necesaria - Spotify</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-gray-800 border border-gray-700 rounded-2xl shadow-2xl overflow-hidden">
        <div class="bg-green-600 p-6 text-center">
            <div class="inline-block bg-black rounded-full p-3 mb-2">
                <svg class="w-8 h-8 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.491 17.306c-.215.353-.674.464-1.026.249-2.813-1.72-6.353-2.108-10.523-1.154-.404.093-.807-.159-.9-.563-.093-.404.159-.807.563-.9 4.557-1.042 8.468-.6 11.614 1.326.352.215.464.674.249 1.026zm1.465-3.264c-.269.438-.841.579-1.28.31-3.218-1.977-8.125-2.55-11.932-1.395-.494.15-1.02-.128-1.17-.622-.15-.494.128-1.02.622-1.17 4.35-1.32 9.756-.675 13.45 1.59.439.27.579.841.31 1.28zm.127-3.41c-3.858-2.292-10.231-2.504-13.935-1.38-.592.18-1.22-.154-1.4-.746-.18-.592.154-1.22.746-1.4 4.25-1.29 11.296-1.037 15.753 1.611.532.316.706 1.004.39 1.536-.316.532-1.004.706-1.536.39z"/>
                </svg>
            </div>
            <h2 class="text-2xl font-extrabold text-white uppercase tracking-tight">¡Ya casi estás!</h2>
        </div>

        <div class="p-8 text-center">
            <p class="text-gray-300 text-lg mb-8">
                Para poder utilizar las funciones musicales de esta App, primero debes sincronizar tu cuenta de <strong>Spotify</strong>.
            </p>

            <div class="space-y-4">
                <a href="{{ route('spotify.connect') }}" 
                   class="flex items-center justify-center w-full py-4 bg-green-500 hover:bg-green-400 text-black font-bold rounded-full transition duration-300 transform hover:scale-105 shadow-lg uppercase tracking-wider">
                    Conectar ahora
                </a>

                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-gray-500 hover:text-white text-sm transition font-medium">
                        Cancelar y cerrar sesión
                    </button>
                </form>
            </div>
        </div>

        <div class="bg-gray-900/50 p-4 border-t border-gray-700">
            <p class="text-xs text-gray-500 text-center">
                Serás redirigido a la página oficial de Spotify para autorizar el acceso.
            </p>
        </div>
    </div>

</body>
</html>