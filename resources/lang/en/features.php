<?php

return [
    'title' => 'Features',

    'placeholder' => 'Choose a feature',

    'columns' => [
        'feature' => 'Feature',
        'active' => 'Active',
    ],

    'grant' => [
        'label' => 'Grant feature',
        'choose' => 'Choose a feature to turn on for this :record.',
        'confirm' => 'Turn on :feature for this :record?',
        'submit' => 'Grant',
        'success' => 'Feature granted',
    ],

    'revoke' => [
        'label' => 'Revoke feature',
        'choose' => 'Choose a feature to turn off for this :record.',
        'confirm' => 'Turn off :feature for this :record?',
        'submit' => 'Revoke',
        'success' => 'Feature revoked',
    ],

    'toggle' => [
        'on' => [
            'description' => 'Turn this feature on for this :record?',
            'submit' => 'Turn on',
        ],
        'off' => [
            'description' => 'Turn this feature off for this :record?',
            'submit' => 'Turn off',
        ],
    ],

    'overview' => [
        'title' => 'Feature rollouts',
        'description' => "Records without a saved value follow the feature's rule.",
        'saved' => 'Saved values',
        'on' => ':count on',
        'off' => ':count off',
        'none' => 'No saved values',
        'empty_heading' => 'No features yet',
        'empty_description' => 'Define a feature with Pennant and it will appear here.',
        'applies_to' => 'Applies to',
        'undefined' => 'No longer defined',
    ],

    'bulk' => [
        'on' => [
            'button' => 'Turn on',
            'label' => 'Turn on saved values',
            'heading' => 'Turn on :feature for :count saved record?|Turn on :feature for :count saved records?',
            'description' => "Replaces every saved value, including ones set by hand. Records without one won't change.",
            'success' => 'Saved values turned on',
        ],
        'off' => [
            'button' => 'Turn off',
            'label' => 'Turn off saved values',
            'heading' => 'Turn off :feature for :count saved record?|Turn off :feature for :count saved records?',
            'description' => "Replaces every saved value, including ones set by hand. Records without one won't change.",
            'success' => 'Saved values turned off',
        ],
        'clear' => [
            'button' => 'Clear',
            'label' => 'Clear saved values',
            'heading' => 'Clear :count saved value for :feature?|Clear :count saved values for :feature?',
            'description' => "Every record goes back to the feature's rule, including ones set by hand. This can't be undone.",
            'success' => 'Saved values cleared',
        ],
    ],
];
