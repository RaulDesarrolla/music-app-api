<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Google\Cloud\Firestore\FirestoreClient;
use Kreait\Laravel\Firebase\Facades\Firebase;

class FirebaseServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(FirestoreClient::class, function ($app) {
            
            $path = storage_path('app/mussicgossip-firebase-adminsdk-fbsvc-330e37ad56.json');

            if (!file_exists($path)) {
                throw new \Exception("Archivo de credenciales no encontrado en: " . $path);
            }
            
            $firestore = Firebase::firestore();
            return $firestore->database(); 
        });
    }

    public function boot()
    {
        //
    }
}