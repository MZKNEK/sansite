<?php
    // Copy this file to inc/config.php on the server and fill it in. That file
    // holds a secret, so it is not in git. Without it the gallery in i/ and the
    // admin panel stay closed (the pictures still work by their direct links).
    //
    // The same login guards the API documentation in api/.
    //
    // Discord application: https://discord.com/developers/applications
    // → your application → OAuth2. Copy the Client ID and Client Secret, and
    // add DISCORD_REDIRECT_URI below to the Redirects list there, exactly the same.
    const DISCORD_CLIENT_ID = '';
    const DISCORD_CLIENT_SECRET = '';
    const DISCORD_REDIRECT_URI = 'https://sanakan.pl/i/';

    // Discord accounts that may open the admin panel (admin/, linked from the home
    // page once logged in). Only here, the panel cannot change it.
    const PANEL_ADMINS = [
        // '123456789012345678',
    ];

    // Discord accounts that may view the gallery and add, move and delete pictures.
    // The admin panel can add more; this list is the fixed part.
    // User ID: Discord settings → Advanced → Developer Mode, then right click
    // the user → Copy User ID.
    const GALLERY_ADMINS = [
        // '123456789012345678',
    ];

    // Other accounts that may only view the gallery (the panel can add more);
    // true lets in anyone logged in with Discord. Without this line only the
    // admins see it.
    const GALLERY_VIEWERS = [
        // '234567890123456789',
    ];

    // Accounts that may read the API documentation in api/ (the panel can add
    // more, and PANEL_ADMINS always can); true lets in anyone logged in with Discord.
    const API_VIEWERS = [
        // '234567890123456789',
    ];

    // Key of this site's application in the bot API, sent as x-app-key; it needs
    // the Info right (Site covers it too). With it the site asks the bot for the
    // roles of the logged-in account on its Discord server: dev, admin,
    // semi-admin and tester may read the API documentation, admin and dev see
    // the moderator and debug commands on cmd/. Without it neither happens.
    const BOT_APP_KEY = '';
