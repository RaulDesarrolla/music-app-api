[33mcommit c991b786d9841e500dd51c11cfc2aa9cfb045515[m[33m ([m[1;36mHEAD[m[33m -> [m[1;32mmain[m[33m, [m[1;31morigin/main[m[33m)[m
Author: Raul <raulbsbrz@gmail.com>
Date:   Sat May 9 16:11:24 2026 +0200

    Creación de seguir/dejar de seguir, posts de seguidos, likes en los posts modificacion de los endspoints para devolver al fronted la info necesaria

[33mcommit 1686a21ea6131fecb90ffe0b7af6deff7ff3380f[m
Author: Raul <raulbsbrz@gmail.com>
Date:   Thu May 7 21:24:36 2026 +0200

    Creación de posts

[33mcommit dffd346032ca7d641452419597e8261733de1fab[m
Author: Raul <raulbsbrz@gmail.com>
Date:   Tue May 5 21:01:08 2026 +0200

    creación de middleware para la verificación de la autenticación en spotify

[33mcommit e247cc2f4bb7a314fb820a9e72fa05e239614d50[m
Author: Raul <raulbsbrz@gmail.com>
Date:   Mon May 4 21:27:00 2026 +0200

    Creación de las rutas y controladores para acceder al dashboard

[33mcommit 9f1ae28378871cbeea79848f9a386234c8e601b8[m
Author: Raul <raulbsbrz@gmail.com>
Date:   Thu Apr 30 15:26:16 2026 +0200

    Creacion de vistas, modificacion de los controladores, creacion de seeder ficticios para probar el login, y la conexión con exito de spotify.

[33mcommit 72c57f326123ecad9fbe52d07f2a9238fc411c33[m
Author: Raul <raulbsbrz@gmail.com>
Date:   Thu Apr 30 13:37:18 2026 +0200

    It looks like you've provided the updated code for several files in your Laravel project. Here's a summary of the changes made:
    
    1. **app/Http/Controllers/SpotifyController.php**:
       - The `callback` method now correctly handles the response from Spotify and updates the user's information in the database.
    
    2. **config/services.php**:
       - The configuration for third-party services has been updated to include more detailed settings for each service.
       - The `spotify` configuration now includes a default redirect URI.
    
    3. **routes/web.php**:
       - The route for handling the Spotify callback (`/spotify/callback`) has been moved outside of the middleware group and uses `withoutMiddleware(['web'])` to ensure it is accessible without requiring authentication.
    
    4. **.env**:
       - Environment variables for Spotify client ID, client secret, and redirect URI have been added or updated.
    
    These changes should help ensure that your application can correctly handle the OAuth flow with Spotify and update user information accordingly. Make sure to replace placeholders like `your_spotify_client_id` and `your_spotify_client_secret` with actual values from your Spotify developer dashboard.
    
    If you encounter any issues, double-check the following:
    - Ensure that the redirect URI in your `.env` file matches exactly what is configured in your Spotify developer dashboard.
    - Verify that the environment variables are correctly set in your `.env` file and that the file is not being cached. You can clear the config cache using `php artisan config:clear`.
    - Check that your database schema includes columns for `spotify_id`, `access_token`, `refresh_token`, and `expires_at` in the users table.
    
    If you have any further questions or need additional assistance, feel free to ask!
    
    Co-authored-by: aider (openai/ollama_chat/qwen2.5-coder:14b) <aider@aider.chat>

[33mcommit 769610f046d2f1fc4abe4125d32eca033e2bfc23[m
Author: Raul <raulbsbrz@gmail.com>
Date:   Thu Apr 30 13:36:45 2026 +0200

    feat: Implement Spotify authentication and callback handling in SpotifyController

[33mcommit f2bbc8ee96cbc753fc2cdc1062385e66ff21d10a[m
Author: Raul <raulbsbrz@gmail.com>
Date:   Thu Apr 30 11:22:59 2026 +0200

    It looks like you've provided a detailed set of migration files for creating various tables in your Laravel application. These migrations include:
    
    1. **Users Table**: This table includes standard user fields such as `name`, `email`, `password`, and timestamps. It also includes additional fields for Spotify integration (`spotify_id`, `access_token`, `refresh_token`, `expires_at`) and user preferences (`theme`, `notifications_enable`).
    
    2. **Password Reset Tokens Table**: This table is used to store tokens for password reset functionality.
    
    3. **Sessions Table**: This table is used to manage user sessions, storing session IDs, user IDs, IP addresses, user agents, payloads, and last activity timestamps.
    
    4. **Grupos Table**: This table represents groups or categories, with an `id` and a `name`.
    
    5. **Grupos Users Pivot Table**: This pivot table establishes a many-to-many relationship between users and grupos.
    
    6. **Messages Table**: This table stores messages, with an `id`, `user_id` (foreign key referencing the users table), `content`, and timestamps.
    
    7. **Posts Table**: Similar to the Messages table, this table stores posts with an `id`, `user_id` (foreign key referencing the users table), `content`, and timestamps.
    
    8. **Ratings Table**: This table is used for rating functionality, storing ratings given by users (`user_id`), the rateable entity (`rateable_id` and `rateable_type`), the actual rating value, and timestamps.
    
    To apply these migrations to your database, you can run the following command in your terminal:
    
    ```bash
    php artisan migrate
    ```
    
    This command will execute all pending migrations and create the corresponding tables in your database. Make sure your database configuration is correctly set up in the `.env` file before running the migration command.
    
    Co-authored-by: aider (openai/ollama_chat/qwen2.5-coder:14b) <aider@aider.chat>

[33mcommit 4344b6285466b904f3bec4c8e9179f88793f4888[m
Author: Raul <raulbsbrz@gmail.com>
Date:   Tue Apr 28 22:33:14 2026 +0200

    Instalación de Laravel Breeze API, configuración de CORS y limpieza de archivos frontend

[33mcommit 26863348bd06039dfc644f17b2d5674ea376cc42[m
Author: Raul <raulbsbrz@gmail.com>
Date:   Tue Apr 28 22:25:50 2026 +0200

    config/cors.php
    ```php
    <?php
    
    return [
        'paths' => ['api/*', 'sanctum/csrf-cookie'],
    
        'allowed_methods' => ['*'],
    
        'allowed_origins' => explode(',', env('FRONTEND_URL')),
    
        'allowed_origins_patterns' => [],
    
        'allowed_headers' => ['*'],
    
        'exposed_headers' => [],
    
        'max_age' => 0,
    
        'supports_credentials' => true,
    ];
    ```
    
    # Diffs:
    diff --git a/config/cors.php b/config/cors.php
    index f2bf88b..d323a68 100644
    --- a/config/cors.php
    +++ b/config/cors.php
    @@ -1,25 +1,11 @@
     <?php
    
     return [
    -
    -    /*
    -    |--------------------------------------------------------------------------
    -    | Cross-Origin Resource Sharing (CORS) Configuration
    -    |--------------------------------------------------------------------------
    -    |
    -    | Here you may configure your settings for cross-origin resource sharing
    -    | or "CORS". This determines what cross-origin operations may execute
    -    | in web browsers. You are free to adjust these settings as needed.
    -    |
    -    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    -    |
    -    */
    -
    -    'paths' => ['*'],
    +    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    
         'allowed_methods' => ['*'],
    
    -    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],
    +    'allowed_origins' => explode(',', env('FRONTEND_URL')),
    
         'allowed_origins_patterns' => [],
    
    @@ -30,5 +16,4 @@
         'max_age' => 0,
    
         'supports_credentials' => true,
    -
     ];
    
    Co-authored-by: aider (openai/ollama_chat/qwen2.5-coder:14b) <aider@aider.chat>

[33mcommit 582d9ad3e01aedc15351b7f495a832ffe447d6a2[m
Author: Raul <raulbsbrz@gmail.com>
Date:   Tue Apr 28 22:25:03 2026 +0200

    feat: add CORS configuration file

[33mcommit b34f30982ee400ed935e7b1e5f6f7a9f2d8fe901[m
Author: Raul <raulbsbrz@gmail.com>
Date:   Tue Apr 28 21:33:19 2026 +0200

    fix: Update SpotifyController to handle stdClass object instead of array
    
    Co-authored-by: aider (openai/ollama_chat/qwen2.5-coder:14b) <aider@aider.chat>

[33mcommit f1761ebc59782852f4d6632683f9c8c2af5848ab[m
Author: Raul <raulbsbrz@gmail.com>
Date:   Tue Apr 28 21:29:30 2026 +0200

    refactor: Modify SpotifyController to return only song name, artist, and album cover image
    
    Co-authored-by: aider (openai/ollama_chat/qwen2.5-coder:14b) <aider@aider.chat>

[33mcommit e0d54fede26e7f74c76ddf97773bc80872630a56[m
Author: Raul <raulbsbrz@gmail.com>
Date:   Mon Apr 27 23:05:44 2026 +0200

    fix: Remove incorrect return type from searchSongs method
    
    Co-authored-by: aider (openai/ollama_chat/qwen2.5-coder:14b) <aider@aider.chat>

[33mcommit 4ad88d328ab24759da952049a72f79a630d3e897[m
Author: Raul <raulbsbrz@gmail.com>
Date:   Mon Apr 27 23:02:30 2026 +0200

    fix: Corrected SpotifyService.php to use Session for authentication and removed invalid setClientId method call.
    
    Co-authored-by: aider (openai/ollama_chat/qwen2.5-coder:14b) <aider@aider.chat>

[33mcommit b3a04d5e28e3bb2bf614a5a16724fc5ee495defa[m
Author: Raul <raulbsbrz@gmail.com>
Date:   Mon Apr 27 22:56:34 2026 +0200

    fix: Corrected namespace and class names for Spotify API usage
    
    Co-authored-by: aider (openai/ollama_chat/qwen2.5-coder:14b) <aider@aider.chat>

[33mcommit b47d383921a52559bce9a63170398953eb02fd7b[m
Author: Raul <raulbsbrz@gmail.com>
Date:   Mon Apr 27 22:25:44 2026 +0200

    feat: Implement Spotify search functionality in Laravel backend
    
    Co-authored-by: aider (openai/ollama_chat/qwen2.5-coder:14b) <aider@aider.chat>

[33mcommit 870ff670e7fc8b64f3d7291c95a717fcf6f23690[m
Author: Raul <raulbsbrz@gmail.com>
Date:   Thu Apr 23 21:35:46 2026 +0200

    Initial commit: Backend Laravel con Aiven y Firebase configurado
