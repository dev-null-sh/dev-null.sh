<?php

declare(strict_types=1);

return [
    'body' => 'Body',
    'placeholder' => 'What did you ship?',
    'poll_placeholder' => 'Ask a question',
    'commit' => 'Commit',
    'committing' => 'Committing…',
    'commit_title' => 'Commit (:keys)',

    'dialog' => [
        'title' => 'Write a commit',
        'description' => 'Your commit is added to your log once it is submitted.',
    ],

    'guest' => [
        'title' => 'A social network for developers.',
        'description' => 'Everyone below signed in with GitHub and committed plain text. Your turn.',
        'sign_in_to_commit' => 'Sign in with GitHub to commit',
        'profile' => 'Your profile comes from GitHub. Nothing to fill in.',
        'sign_in' => 'Sign in with GitHub',
    ],

    'schedule' => [
        'button' => 'Schedule',
        'submit_at' => 'Submit at',
        'commit' => 'Schedule commit',
        'scheduling' => 'Scheduling…',
        'commit_title' => 'Schedule commit (:keys)',
        'nonexistent_time' => 'This time does not exist in your timezone. Choose another time.',
        'help_admin' => ':timezone. Within 30 days. Private until submitted; your commit will be approved automatically.',
        'help_review' => ':timezone. Within 30 days. Private until submitted; then submitted for review, not published automatically.',
        'scheduled_commits' => 'Scheduled commits →',
    ],

    'poll' => [
        'add' => 'Add poll',
        'images_or_poll' => 'A commit can have images or a poll, not both',
        'legend' => 'Poll',
        'option' => 'Option :number',
        'remove_option' => 'Remove option :number',
        'remove_option_title' => 'Remove option',
        'add_option' => 'Add option',
        'length' => 'Poll length',
        'remove' => 'Remove poll',
        'hours' => ':count hour|:count hours',
        'days' => ':count day|:count days',
    ],

    'images' => [
        'invalid' => 'Only JPEG, PNG, GIF, or WebP images up to 10 MB.',
        'too_many' => 'Up to :max images per commit.',
        'attach' => 'Attach images',
        'attach_title' => 'Attach up to :max images',
        'attached' => 'Attached images',
        'attached_alt' => 'Attached image :number',
        'remove' => 'Remove image :number',
        'edit_description' => 'Edit description of image :number',
        'add_description' => 'Add description to image :number',
        'description_label' => 'Description of image :number',
        'description_placeholder' => "Describe the image for people who can't see it",
        'done' => 'Done',
    ],

    'preview' => [
        'label' => 'Preview',
        'title' => 'Preview (:keys)',
        'empty' => 'Nothing to preview yet.',
        'link_card' => 'Unfurls into a link card after you commit.',
    ],

    'link_card' => [
        'title' => 'Choose which link gets a card',
        'label' => 'Link card',
        'sr_label' => 'Link card:',
        'none' => 'No card',
    ],

    'counter' => [
        'remaining' => ':count character left|:count characters left',
        'fold_hint' => 'Feeds show the first :length characters or :lines lines, then “Show more”.',
    ],

    'stash' => [
        'restored' => 'Restored your unsent draft',
        'drop_title' => 'Discard the restored draft',
    ],

    'mentions' => [
        'emoji_label' => 'Insert an emoji',
        'label' => 'Mention a developer',
        'following' => 'People you follow',
        'searching' => 'Searching…',
        'no_match' => "fatal: no developers match ':query'",
    ],

    'secrets' => [
        'warning' => 'Sensitive text warning',
        'detected' => 'Possible secret detected|Possible secrets detected',
        'checked_locally' => "Checked on your device. This draft won't be saved while it contains a detected secret.",
        'select' => 'Select possible secret',
        'select_numbered' => 'Select possible secret :number',
        'remove_detected' => 'Remove detected secrets',
        'confirm_title' => 'Publish possible secrets?',
        'confirm_description' => 'This text may contain credentials. Remove them before publishing, or continue only if these are safe examples.',
        'keep_editing' => 'Keep editing',
        'remove' => 'Remove secrets',
        'publish_anyway' => 'Publish anyway',
    ],

    'quote' => [
        'button' => 'Quote',
        'button_title' => 'Quote (:keys)',
        'unsent' => 'You have an unsent quote here',
        'title' => 'Quote :handle',
        'description' => 'Write a commit of your own. This commit is embedded below it.',
        'label' => 'Your commit',
        'placeholder' => 'Add your take',
        'cancel' => 'Cancel',
        'submit' => 'Quote',
        'quoting' => 'Quoting…',
    ],
];
