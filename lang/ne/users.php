<?php

declare(strict_types=1);

return [
    'counts' => [
        'commits' => 'कमिट|कमिटहरू',
        'following' => 'फलोइङ',
        'followers' => 'फलोअर|फलोअरहरू',
    ],

    'follows_you' => 'तपाईंलाई फलो गर्छन्',
    'muted' => 'म्युट गरिएको',

    'follow' => [
        'follow' => 'फलो गर्नुहोस्',
        'following' => 'फलोइङ',
        'unfollow' => 'अनफलो गर्नुहोस्',
        'follow_label' => '@:username लाई फलो गर्नुहोस्',
        'unfollow_label' => '@:username लाई अनफलो गर्नुहोस्',
    ],

    'profile' => [
        'edit_on_github' => 'GitHub मा सम्पादन गर्नुहोस्',
        'view_achievements' => 'उपलब्धिहरू र Unix श्रेणी (rank) हेर्नुहोस्',
        'sponsor' => 'GitHub मा प्रायोजन गर्नुहोस्',
        'joined' => ':date मा सामेल हुनुभयो',
        'pasteis_received' => 'pastel प्राप्त भयो|pastéis प्राप्त भयो',
        'feed_label' => 'प्रोफाइल फिड',
        'tabs' => [
            'commits' => 'कमिटहरू',
            'replies' => 'जवाफहरू',
        ],
        'end' => [
            'own_first_reply' => 'त्यो तपाईंको पहिलो जवाफ थियो।',
            'first_reply' => 'त्यो @:username को पहिलो जवाफ हो।',
            'own_first_commit' => 'त्यो तपाईंको पहिलो कमिट थियो। सबैको सुरुवात कतै न कतैबाट हुन्छ।',
            'root_commit' => 'त्यो @:username को रूट कमिट हो।',
        ],
        'empty' => [
            'replies_command' => 'fatal: अहिलेसम्म कुनै जवाफ छैन',
            'own_replies' => 'तपाईंका जवाफहरू यहाँ देखिन्छन्। आफ्नो लगबाट कुराकानीमा सामेल हुनुहोस्।',
            'replies' => '@:username ले अहिलेसम्म कुनै पनि जवाफ दिनुभएको छैन।',
            'own_commits_command' => 'fatal: तपाईंसँग अहिलेसम्म कुनै कमिट छैन',
            'own_commits' => 'तपाईंका कमिटहरू यहाँ देखिन्छन्। आफ्नो लगबाट पहिलो कमिट गर्नुहोस्।',
            'commits_command' => 'fatal: अहिलेसम्म कुनै कमिट छैन',
            'commits' => '@:username ले अहिलेसम्म केही पनि कमिट गर्नुभएको छैन।',
        ],
    ],

    'connections' => [
        'titles' => [
            'following' => 'फलोइङ',
            'followers' => 'फलोअरहरू',
        ],
        'tabs_label' => 'सम्पर्कहरू',
        'back' => '@:username मा फर्कनुहोस्',
        'empty_command' => [
            'following' => 'fatal: अहिलेसम्म कुनै फलोइङ छैन',
            'followers' => 'fatal: अहिलेसम्म कुनै फलोअर छैन',
        ],
        'empty' => [
            'following' => '@:username ले अहिलेसम्म कसैलाई पनि फलो गर्नुभएको छैन।',
            'followers' => 'अहिलेसम्म कसैले पनि @:username लाई फलो गरेको छैन।',
        ],
    ],

    'saved' => [
        'title' => 'सुरक्षित गरिएका',
        'only_you' => 'तपाईं मात्र',
        'description' => 'तपाईं पछि फर्केर हेर्न चाहनुभएका कमिटहरू। सबैभन्दा हालसालै सुरक्षित गरिएका पहिले।',
        'end' => 'तपाईं आफ्ना सुरक्षित गरिएका कमिटहरूको अन्त्यमा पुग्नुभयो।',
        'empty_command' => 'fatal: अहिलेसम्म कुनै सुरक्षित कमिट छैन',
        'empty' => 'कमिटलाई यहाँ पाउन त्यसलाई सुरक्षित गर्नुहोस्। तपाईंका सुरक्षित गरिएका कमिटहरू निजी हुन्छन्।',
        'explore' => 'ट्रेन्डिङ अन्वेषण गर्नुहोस्',
    ],

    'preview' => [
        'about' => '@:username को बारेमा',
        'blocked' => 'तपाईंले @:username लाई ब्लक गर्नुभएको छ।',
        'unavailable' => 'यो प्रोफाइल उपलब्ध छैन।',
    ],

    'safety' => [
        'more_options' => '@:username का लागि थप विकल्पहरू',
        'mute' => '@:username लाई म्युट गर्नुहोस्',
        'unmute' => '@:username लाई अनम्युट गर्नुहोस्',
        'block' => '@:username लाई ब्लक गर्नुहोस्',
        'unblock' => '@:username लाई अनब्लक गर्नुहोस्',
        'block_title' => '@:username लाई ब्लक गर्ने?',
        'block_description' => 'तपाईंहरूले एकअर्कालाई फलो गर्न छाड्नुहुनेछ। तपाईंहरूमध्ये कसैले पनि एकअर्कालाई फलो गर्न, जवाफ दिन वा स्टार गर्न, वा एकअर्काका कमिटहरू हेर्न सक्नुहुने छैन। उनीहरूलाई जानकारी दिइने छैन, तर उनीहरूले तपाईंको प्रोफाइल अनुपलब्ध भएको देख्नेछन्। तपाईंले आफ्नो सेटिङबाट कुनै पनि समय उनीहरूलाई अनब्लक गर्न सक्नुहुन्छ।',
        'cancel' => 'रद्द गर्नुहोस्',
        'confirm_block' => 'ब्लक गर्नुहोस्',
    ],

    'achievements' => [
        'title' => 'डेभलपर उपलब्धिहरू',
        'label' => 'डेभलपर उपलब्धिहरू',
        'level' => 'स्तर :level · :rank',
        'summary' => '@:username · :xp XP · :unlocked/:total ब्याजहरू अनलक भए',
        'unlocked' => ':unlocked / :total अनलक भए',
        'xp' => ':xp XP',
        'xp_progress' => ':xp / :next XP',
        'xp_to_rank' => ':rank का लागि :xp XP',
        'max_rank' => 'अधिकतम श्रेणी (UID 0)',
        'badges_tab' => 'ब्याजहरू (:count)',
        'ranks_tab' => 'Unix श्रेणीहरू (:count)',
        'current' => 'हालको',
        'achieved' => '✓ हासिल भयो',
        'requires' => ':xp XP आवश्यक पर्छ',
        'formula' => 'XP: १०/कमिट · १५/दिनको निरन्तरता · ५/प्राप्त स्टार · ५/जवाफ · २/दिएको स्टार',
    ],

    'activity' => [
        'heading' => 'कमिट गतिविधि',
        'no_commits_yet' => 'अहिलेसम्म कुनै कमिट छैन',
        'no_commits' => 'कुनै कमिट छैन',
        'commit_count' => ':count कमिट|:count कमिटहरू',
        'summary' => ':window :commits',
        'windows' => [
            'day' => 'पछिल्लो २४ घण्टामा',
            'week' => 'पछिल्लो ७ दिनमा',
            'month' => 'पछिल्लो ३० दिनमा',
            'year' => 'पछिल्लो एक वर्षमा',
        ],
        'on_date' => ':date मा :commits',
        'between_today' => 'आज :start र :end बीच :commits',
        'between_yesterday' => 'हिजो :start र :end बीच :commits',
        'month_day' => ':month :day',
        'date_with_year' => ':month :day, :year',
        'date_with_weekday' => ':weekday, :month :day',
        'weekday_day' => ':weekday :day',
        'less' => 'कम',
        'more' => 'धेरै',
        'months' => [
            'jan' => 'जन',
            'feb' => 'फेब',
            'mar' => 'मार्च',
            'apr' => 'अप्रिल',
            'may' => 'मे',
            'jun' => 'जुन',
            'jul' => 'जुल',
            'aug' => 'अग',
            'sep' => 'सेप्टे',
            'oct' => 'अक्टो',
            'nov' => 'नोभे',
            'dec' => 'डिसे',
        ],
        'weekdays' => [
            'sun' => 'आइत',
            'mon' => 'सोम',
            'tue' => 'मङ्गल',
            'wed' => 'बुध',
            'thu' => 'बिही',
            'fri' => 'शुक्र',
            'sat' => 'शनि',
        ],
    ],

    'streaks' => [
        'longest' => 'सबैभन्दा लामो निरन्तरता',
        'days' => 'दिन|दिनहरू',
        'current' => 'हालको',
    ],

    'embed' => [
        'title' => 'आफ्नो गतिविधि ग्राफ इम्बेड गर्नुहोस्',
        'description' => 'तपाईंको GitHub प्रोफाइल README वा Markdown समर्थन गर्ने जुनसुकै ठाउँका लागि SVG को रूपमा तपाईंको कमिट ग्राफ। यसले स्वीकृत कमिटहरू मात्र देखाउँछ र प्रति घण्टा लगभग एक पटक ताजा हुन्छ।',
        'theme_label' => 'ग्राफ थिम',
        'themes' => [
            'light' => 'उज्यालो (Light)',
            'dark' => 'गाढा (Dark)',
            'auto' => 'स्वतः (Auto)',
        ],
        'theme_hints' => [
            'light' => 'पाठकले जे रोजे पनि सधैं उज्यालो।',
            'dark' => 'पाठकले जे रोजे पनि सधैं गाढा।',
            'auto' => 'जसले पढिरहेको छ उसको रङ्ग योजना पछ्याउँछ।',
        ],
        'preview_alt' => '@:username को कमिट गतिविधि ग्राफ',
        'copy' => 'Markdown प्रतिलिपि गर्नुहोस्',
        'copied' => 'प्रतिलिपि गरियो',
        'copied_toast' => 'Markdown प्रतिलिपि गरियो। यसलाई आफ्नो README मा टाँस्नुहोस्।',
        'copy_failed' => 'fatal: Markdown प्रतिलिपि गर्न सकिएन',
    ],

    'live' => [
        'heading' => 'हामीले निर्माण गरिरहेको प्रत्यक्ष हेर्नुहोस्',
        'player_title' => 'YouTube मा हामीले /dev/null निर्माण गरिरहेको प्रत्यक्ष हेर्नुहोस्',
    ],
];
