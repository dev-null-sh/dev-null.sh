<?php

declare(strict_types=1);

return [
    'counts' => [
        'commits' => 'कमिट|कमिट',
        'following' => 'फ़ॉलोइंग',
        'followers' => 'फ़ॉलोअर|फ़ॉलोअर्स',
    ],

    'follows_you' => 'आपको फ़ॉलो करते हैं',
    'muted' => 'म्यूट',

    'follow' => [
        'follow' => 'फ़ॉलो करें',
        'following' => 'फ़ॉलोइंग',
        'unfollow' => 'अनफ़ॉलो करें',
        'follow_label' => '@:username को फ़ॉलो करें',
        'unfollow_label' => '@:username को अनफ़ॉलो करें',
    ],

    'profile' => [
        'edit_on_github' => 'GitHub पर एडिट करें',
        'view_achievements' => 'उपलब्धियाँ और Unix रैंक देखें',
        'sponsor' => 'GitHub पर स्पॉन्सर करें',
        'joined' => ':date में जुड़े',
        'pasteis_received' => 'pastel मिला|pastéis मिले',
        'feed_label' => 'प्रोफ़ाइल फ़ीड',
        'tabs' => [
            'commits' => 'कमिट',
            'replies' => 'जवाब',
        ],
        'end' => [
            'own_first_reply' => 'यह आपका पहला जवाब था।',
            'first_reply' => 'यह @:username का पहला जवाब है।',
            'own_first_commit' => 'यह आपका पहला कमिट था। हर कोई कहीं से शुरुआत करता है।',
            'root_commit' => 'यह @:username का रूट कमिट है।',
        ],
        'empty' => [
            'replies_command' => 'fatal: अभी कोई जवाब नहीं',
            'own_replies' => 'आपके जवाब यहाँ दिखते हैं। अपने लॉग से किसी बातचीत में शामिल हों।',
            'replies' => '@:username ने अभी तक किसी का जवाब नहीं दिया है।',
            'own_commits_command' => 'fatal: आपका अभी कोई कमिट नहीं है',
            'own_commits' => 'आपके कमिट यहाँ दिखते हैं। अपने लॉग से पहला कमिट करें।',
            'commits_command' => 'fatal: अभी कोई कमिट नहीं',
            'commits' => '@:username ने अभी तक कुछ कमिट नहीं किया है।',
        ],
    ],

    'connections' => [
        'titles' => [
            'following' => 'फ़ॉलोइंग',
            'followers' => 'फ़ॉलोअर्स',
        ],
        'tabs_label' => 'कनेक्शन',
        'back' => '@:username पर वापस',
        'empty_command' => [
            'following' => 'fatal: अभी कोई फ़ॉलोइंग नहीं',
            'followers' => 'fatal: अभी कोई फ़ॉलोअर नहीं',
        ],
        'empty' => [
            'following' => '@:username अभी किसी को फ़ॉलो नहीं करते।',
            'followers' => 'अभी कोई @:username को फ़ॉलो नहीं करता।',
        ],
    ],

    'saved' => [
        'title' => 'सेव किए',
        'only_you' => 'सिर्फ़ आप',
        'description' => 'वे कमिट जिन पर आप वापस आना चाहते हैं। सबसे हाल में सेव किए पहले।',
        'end' => 'आप अपने सेव किए कमिट के अंत तक पहुँच गए।',
        'empty_command' => 'fatal: अभी कोई सेव किया कमिट नहीं',
        'empty' => 'किसी कमिट को यहाँ पाने के लिए उसे सेव करें। आपके सेव किए कमिट निजी हैं।',
        'explore' => 'ट्रेंडिंग देखें',
    ],

    'preview' => [
        'about' => '@:username के बारे में',
        'blocked' => 'आपने @:username को ब्लॉक किया है।',
        'unavailable' => 'यह प्रोफ़ाइल उपलब्ध नहीं है।',
    ],

    'safety' => [
        'more_options' => '@:username के लिए और विकल्प',
        'mute' => '@:username को म्यूट करें',
        'unmute' => '@:username को अनम्यूट करें',
        'block' => '@:username को ब्लॉक करें',
        'unblock' => '@:username को अनब्लॉक करें',
        'block_title' => '@:username को ब्लॉक करें?',
        'block_description' => 'आप एक-दूसरे को फ़ॉलो करना बंद कर देंगे। आप दोनों में से कोई भी एक-दूसरे को फ़ॉलो, जवाब या स्टार नहीं कर पाएगा, न ही एक-दूसरे के कमिट देख पाएगा। उन्हें बताया नहीं जाएगा, लेकिन उन्हें आपकी प्रोफ़ाइल उपलब्ध नहीं दिखेगी। आप कभी भी अपनी सेटिंग्स से उन्हें अनब्लॉक कर सकते हैं।',
        'cancel' => 'रद्द करें',
        'confirm_block' => 'ब्लॉक करें',
    ],

    'achievements' => [
        'title' => 'डेवलपर उपलब्धियाँ',
        'label' => 'डेवलपर उपलब्धियाँ',
        'level' => 'लेवल :level · :rank',
        'summary' => '@:username · :xp XP · :unlocked/:total बैज अनलॉक',
        'unlocked' => ':unlocked / :total अनलॉक',
        'xp' => ':xp XP',
        'xp_progress' => ':xp / :next XP',
        'xp_to_rank' => ':rank तक :xp XP',
        'max_rank' => 'अधिकतम रैंक (UID 0)',
        'badges_tab' => 'बैज (:count)',
        'ranks_tab' => 'Unix रैंक (:count)',
        'current' => 'वर्तमान',
        'achieved' => '✓ हासिल',
        'requires' => 'ज़रूरी: :xp XP',
        'formula' => 'XP: 10/कमिट · 15/दिन की स्ट्रीक · 5/मिला स्टार · 5/जवाब · 2/दिया स्टार',
    ],

    'activity' => [
        'heading' => 'कमिट गतिविधि',
        'no_commits_yet' => 'अभी कोई कमिट नहीं',
        'no_commits' => 'कोई कमिट नहीं',
        'commit_count' => ':count कमिट|:count कमिट',
        'summary' => ':window :commits',
        'windows' => [
            'day' => 'पिछले 24 घंटों में',
            'week' => 'पिछले 7 दिनों में',
            'month' => 'पिछले 30 दिनों में',
            'year' => 'पिछले एक साल में',
        ],
        'on_date' => ':date को :commits',
        'between_today' => 'आज :start से :end के बीच :commits',
        'between_yesterday' => 'कल :start से :end के बीच :commits',
        'month_day' => ':day :month',
        'date_with_year' => ':day :month :year',
        'date_with_weekday' => ':weekday, :day :month',
        'weekday_day' => ':weekday :day',
        'less' => 'कम',
        'more' => 'ज़्यादा',
        'months' => [
            'jan' => 'जन॰',
            'feb' => 'फ़र॰',
            'mar' => 'मार्च',
            'apr' => 'अप्रैल',
            'may' => 'मई',
            'jun' => 'जून',
            'jul' => 'जुल॰',
            'aug' => 'अग॰',
            'sep' => 'सित॰',
            'oct' => 'अक्तू॰',
            'nov' => 'नव॰',
            'dec' => 'दिस॰',
        ],
        'weekdays' => [
            'sun' => 'रवि',
            'mon' => 'सोम',
            'tue' => 'मंगल',
            'wed' => 'बुध',
            'thu' => 'गुरु',
            'fri' => 'शुक्र',
            'sat' => 'शनि',
        ],
    ],

    'streaks' => [
        'longest' => 'सबसे लंबी स्ट्रीक',
        'days' => 'दिन|दिन',
        'current' => 'वर्तमान',
    ],

    'embed' => [
        'title' => 'अपना गतिविधि ग्राफ़ एम्बेड करें',
        'description' => 'आपका कमिट ग्राफ़ SVG के रूप में, आपकी GitHub प्रोफ़ाइल README के लिए, या जहाँ भी Markdown रेंडर होता है। इसमें सिर्फ़ स्वीकृत कमिट दिखते हैं और यह लगभग हर घंटे रीफ़्रेश होता है।',
        'theme_label' => 'ग्राफ़ थीम',
        'themes' => [
            'light' => 'लाइट',
            'dark' => 'डार्क',
            'auto' => 'ऑटो',
        ],
        'theme_hints' => [
            'light' => 'हमेशा लाइट, पढ़ने वाले की पसंद चाहे जो हो।',
            'dark' => 'हमेशा डार्क, पढ़ने वाले की पसंद चाहे जो हो।',
            'auto' => 'पढ़ने वाले की कलर स्कीम को फ़ॉलो करता है।',
        ],
        'preview_alt' => '@:username का कमिट गतिविधि ग्राफ़',
        'copy' => 'Markdown कॉपी करें',
        'copied' => 'कॉपी हो गया',
        'copied_toast' => 'Markdown कॉपी हो गया। इसे अपनी README में पेस्ट करें।',
        'copy_failed' => 'fatal: Markdown कॉपी नहीं हो सका',
    ],

    'live' => [
        'heading' => 'हमें इसे लाइव बनाते देखें',
        'player_title' => 'YouTube पर हमें /dev/null लाइव बनाते देखें',
    ],
];
