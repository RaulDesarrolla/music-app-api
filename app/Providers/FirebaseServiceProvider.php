<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Google\Cloud\Firestore\FirestoreClient;
use Kreait\Laravel\Firebase\Facades\Firebase; // 🌟 Importamos el Facade de Kreait

class FirebaseServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Mantenemos tu singleton para poder inyectar FirestoreClient donde quieras
        $this->app->singleton(FirestoreClient::class, function ($app) {
            
            // 1. Mantenemos tu verificación de seguridad del archivo JSON
            $path = storage_path('app/mussicgossip-firebase-adminsdk-fbsvc-330e37ad56.json');

            if (!file_exists($path)) {
                throw new \Exception("Archivo de credenciales no encontrado en: " . $path);
            }

            // 2. SOLUCIÓN: En lugar de instanciar a mano, dejamos que Kreait use 
            // sus componentes internos compatibles con tu PHP 8.3 sin gRPC.
            $firestore = Firebase::firestore();
            return $firestore->database(); 
        });
    }

    public function boot()
    {
        //
    }
}