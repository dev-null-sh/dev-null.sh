<?php

declare(strict_types=1);

return [

    /*
     * Where the authorize page may send the devnull app back to with its code. A redirect_uri has to start with one of
     * these, so a code never leaves for anywhere but the app's own URL scheme, or Raycast's, for the /dev/null Raycast
     * extension (OAuth.RedirectMethod.App). A code is only good with the key and verifier that asked for it.
     */
    'redirect_uri_prefixes' => ['devnull://', 'raycast://oauth?package_name=Extension'],

];
