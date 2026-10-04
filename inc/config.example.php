<?php
    // Copy this file to inc/config.php on the server and fill it in. That file
    // holds a secret, so it is not in git. Without it the gallery in i/ stays
    // closed (the pictures still work by their direct links).
    //
    // Discord application: https://discord.com/developers/applications
    // → your application → OAuth2. Copy the Client ID and Client Secret, and
    // add DISCORD_REDIRECT_URI below to the Redirects list there, exactly the same.
    const DISCORD_CLIENT_ID = '';
    const DISCORD_CLIENT_SECRET = '';
    const DISCORD_REDIRECT_URI = 'https://sanakan.pl/i/';

    // Discord accounts that may view the gallery and add, move and delete pictures.
    // User ID: Discord settings → Advanced → Developer Mode, then right click
    // the user → Copy User ID.
    const GALLERY_ADMINS = [
        // '123456789012345678',
    ];

    // Other accounts that may only view the gallery; true lets in anyone
    // logged in with Discord. Without this line only the admins see it.
    const GALLERY_VIEWERS = [
        // '234567890123456789',
    ];
