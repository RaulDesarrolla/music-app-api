<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center">
                <h2 class="text-2xl font-bold mb-4">¡Ya casi estás!</h2>
                <p class="mb-6 text-gray-600">Para acceder a tu panel, primero debes sincronizar tu cuenta de Spotify.</p>
                
                <a href="{{ route('spotify.connect') }}" 
                   class="inline-flex items-center px-6 py-3 bg-green-600 border border-transparent rounded-md font-semibold text-white uppercase tracking-widest hover:bg-green-500 transition ease-in-out duration-150">
                    Conectar con Spotify
                </a>
            </div>
        </div>
    </div>
</x-app-layout>