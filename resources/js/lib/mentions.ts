// Keep this rule in sync with app/Services/Mentions.php.

// A GitHub username after an "@" that doesn't follow a letter, digit, "_", ".", "/", "@" or "-", and doesn't run
// into a path or a domain, so emails, "@inertiajs/react" and "@vue.js" are not mentions.
export const mentionPattern =
    /(?<![\p{L}\p{N}_./@-])@([A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38})(?![A-Za-z0-9_@/-]|\.[A-Za-z0-9])/gu;
