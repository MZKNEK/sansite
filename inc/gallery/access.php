<?php
    // Who may see what in the gallery. Part of the gallery, loaded by
    // inc/gallery.php.

    // ---- Access -------------------------------------------------------------
    // checked against the config and the panel's lists on every request,
    // so taking an account out works at once

    function galleryCanView()
    {
        $user = siteUser();
        return $user !== null && canViewGalleryId($user['id']);
    }

    function galleryIsAdmin()
    {
        $user = siteUser();
        return $user !== null && isGalleryAdminId($user['id']);
    }

    function galleryCanSeePrivate()
    {
        $user = siteUser();
        return $user !== null && canSeePrivateGalleryId($user['id']);
    }

    // the folders of the accounts, or something in one of them
    function inUsersDir($rel)
    {
        return $rel === USERS_DIR || strpos($rel, USERS_DIR . '/') === 0;
    }

    // the private folder, or something in it
    function inPrivateDir($rel)
    {
        return $rel === PRIVATE_DIR || strpos($rel, PRIVATE_DIR . '/') === 0;
    }

    // a path is $folder or inside it
    function inFolder($rel, $folder)
    {
        return $rel === $folder || strpos($rel, $folder . '/') === 0;
    }

    // whether a path is in the folder of the account with this ID, whatever its
    // nick is now (the folder follows a new nick, a trash entry keeps the old name)
    function inUserFolder($rel, $id)
    {
        return preg_match('~^' . USERS_DIR . '/' . preg_quote((string)$id, '~') . '(?:-|/|$)~', (string)$rel) === 1;
    }

    // Whether the visitor may see a folder or a file: the private folder only
    // those who may see it (the panel admins and the GALLERY_PRIVATE list) and
    // whoever holds a link shared for it or a subfolder (so a shared private
    // folder gives its thumbnails and files out too), the
    // gallery admins everything else, the viewers all but the folders of the
    // accounts, an account with a folder of its own that folder, anyone the
    // folders shared with it.
    function galleryCanSee($base, $rel)
    {
        return galleryAccess($base)($rel);
    }

    // the rule of galleryCanSee() as a function of the path, with the visitor's
    // rights read once, for checking many paths (the files of a ZIP)
    function galleryAccess($base)
    {
        $private = galleryCanSeePrivate();
        $admin = galleryIsAdmin();
        $view = galleryCanView();
        $shares = sessionShares($base);
        $homes = galleryHomes($base);

        return function ($rel) use ($private, $admin, $view, $shares, $homes) {
            if (inPrivateDir($rel)) {
                if ($private)
                    return true;
                foreach ($shares as $home)
                    if (inFolder($rel, $home))
                        return true;

                return false;
            }
            if ($admin)
                return true;
            foreach ($homes as $home)
                if (inFolder($rel, $home))
                    return true;

            return !inUsersDir($rel) && $view;
        };
    }

    // The folders a visitor who does not see the whole gallery starts from: its
    // own folder first, then the ones shared with it.
    function galleryHomes($base)
    {
        return array_values(array_unique(array_filter(array_merge([ownFolder($base)], sessionShares($base)), 'is_string')));
    }
