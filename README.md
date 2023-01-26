# Post Version

A WordPress Plugin to version your posts.

## Known Issues

- WP queries using `ids` and `id=>parent` fields are not automatically converted to their latest version due lack of `posts_results` or `the_posts` filters. Results are converted on `all` fields, unless the `post_version_hide_unreleased` returns false.
