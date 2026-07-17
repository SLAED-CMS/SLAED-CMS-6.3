# User Profile Backlog

Deferred decisions from the 2026 profile redesign (`account&op=view`, Dense Split).
Each item needs an explicit go-ahead before implementation.

## Open items

1. **DB indexes for profile aggregates**
   The module hub counts favorites via `JOIN sport_favorites (modul, fid)` and filters
   content tables by `uid`. Verify and add missing indexes: `sport_favorites (modul, fid)`
   and `uid` on `_comment`, `_faq`, `_files`, `_forum`, `_jokes`, `_links`, `_media`,
   `_news`, `_pages`. Schema change — requires migration.

2. **profil() should reuse the profile card**
   "Your personal page" (`account`, logged-in home) still renders the legacy composition
   (icon nav + feed). Replace with the same Dense Split card rendered for the own account
   plus the management navigation.

3. **modules/users redesign**
   The top-users list is still a legacy table and `user_info()` duplicates the user
   summary rendering. Redesign with the profile visual language and reuse
   `getProfileModules()` where sensible.

4. **`sl-tabs-chips` modifier**
   The chip-styled tabs are currently scoped under `.sl-profile-feed` in `theme.css`.
   When a second consumer appears, promote the styles to a `sl-tabs-chips` modifier
   passed through the `tabs` part `class_attr`.

## Verification baseline for these items

`php -l`, `phpstan`, `phpunit`, browser check of guest/user/admin roles,
`storage/logs/error_*.log` review, and the DB query counter in the debug footer.
