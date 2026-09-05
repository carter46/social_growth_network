<?php

/**
 * Legal copy uses :site_name — replaced at render time with branding site name.
 */
return [
    'updated_at' => '2026-09-04',

    'contact' => [
        'email' => env('LEGAL_CONTACT_EMAIL'),
    ],

    'documents' => [
        'terms' => [
            'label' => 'Terms of Service',
            'eyebrow' => 'Compliance & Legal',
            'intro' => 'Please review the rules for using :site_name — social media services, wallet, and checkout.',
            'summary' => 'By using :site_name you agree to our platform rules, KYC requirements where applicable, and wallet / checkout terms. This document was last updated September 2026.',
            'sections' => [
                [
                    'id' => 'acceptance',
                    'nav' => '1. Acceptance of Terms',
                    'title' => 'Acceptance of Terms',
                    'number' => '01',
                    'paragraphs' => [
                        'By accessing or using :site_name (“the Platform”), including the social media services catalog and Naira wallet, you confirm that you have read, understood, and agree to these Terms of Service.',
                        'If you do not agree, you must stop using the Platform. We may update these terms from time to time; continued use after changes are posted means you accept the updated terms.',
                    ],
                ],
                [
                    'id' => 'security',
                    'nav' => '2. Accounts & Security',
                    'title' => 'User Accounts & Security',
                    'number' => '02',
                    'paragraphs' => [
                        'Account security is a shared responsibility. You agree to:',
                    ],
                    'checklist' => [
                        'Provide accurate information during registration and any KYC (Know Your Customer) process.',
                        'Keep your login credentials confidential and protect access to your devices.',
                        'Notify support promptly if you suspect unauthorized access to your account.',
                    ],
                ],
                [
                    'id' => 'services',
                    'nav' => '3. Social Media Services',
                    'title' => 'Social Media Services',
                    'number' => '03',
                    'paragraphs' => [
                        'The Platform sells platform-operated social media growth and engagement services (for example Instagram, TikTok, YouTube, Twitter/X, and Facebook packs).',
                    ],
                    'cards' => [
                        [
                            'title' => 'Platform services',
                            'body' => 'Catalog products are fulfilled according to the product description and checkout terms shown at purchase. Purchased services appear in your dashboard under My Tools and My Orders.',
                        ],
                        [
                            'title' => 'No third-party marketplace',
                            'body' => 'The Platform does not operate a peer-to-peer marketplace or escrow listings. All catalog products are sold by the Platform.',
                        ],
                    ],
                ],
                [
                    'id' => 'financial',
                    'nav' => '4. Financial Transactions',
                    'title' => 'Financial Transactions',
                    'number' => '04',
                    'paragraphs' => [
                        'Wallet funding, withdrawals, and service checkout are subject to verification, admin review where required, and applicable fees.',
                    ],
                    'bullets' => [
                        'You may pay with wallet balance, card/transfer gateway, or manual bank transfer when those methods are enabled.',
                        'You are responsible for providing correct bank details for deposits and withdrawals. We are not liable for losses from incorrect details you supply.',
                        'Deposits and withdrawals may take from minutes up to longer review windows depending on method and compliance checks.',
                    ],
                ],
                [
                    'id' => 'prohibited',
                    'nav' => '5. Prohibited Activities',
                    'title' => 'Prohibited Activities',
                    'number' => '05',
                    'variant' => 'danger',
                    'paragraphs' => [
                        'You must not use the Platform for:',
                    ],
                    'blocks' => [
                        'Fraud, phishing, or payment abuse',
                        'Money laundering or illegal payments',
                        'Chargeback fraud or false claims',
                        'Scraping, attacks, or account takeover',
                    ],
                ],
                [
                    'id' => 'ip',
                    'nav' => '6. Intellectual Property',
                    'title' => 'Intellectual Property',
                    'number' => '06',
                    'paragraphs' => [
                        'Platform software, branding, UI, and content remain the property of :site_name and its licensors. You may not copy or redistribute them without permission.',
                    ],
                ],
                [
                    'id' => 'liability',
                    'nav' => '7. Limitation of Liability',
                    'title' => 'Limitation of Liability',
                    'number' => '07',
                    'paragraphs' => [
                        'To the fullest extent permitted by law, :site_name is not liable for indirect, incidental, or consequential damages arising from use of the Platform. Service outcomes depend on product descriptions and third-party platform policies outside our control.',
                    ],
                ],
                [
                    'id' => 'contact',
                    'nav' => '8. Contact',
                    'title' => 'Contact',
                    'number' => '08',
                    'paragraphs' => [
                        'Questions about these terms can be sent via the Contact page or a support ticket in your dashboard.',
                    ],
                ],
            ],
        ],

        'privacy' => [
            'label' => 'Privacy Policy',
            'eyebrow' => 'Data & Privacy',
            'intro' => 'How :site_name collects, uses, and protects your information when you use social media services and wallet features.',
            'summary' => 'We collect account, KYC, and payment data needed to operate social media service purchases and your Naira wallet. This policy was last updated September 2026.',
            'sections' => [
                [
                    'id' => 'collect',
                    'nav' => '1. Information We Collect',
                    'title' => 'Information We Collect',
                    'number' => '01',
                    'paragraphs' => [
                        'We may collect account details (name, email), KYC documents when required, payment and wallet transaction records, and technical logs needed for security and support.',
                    ],
                ],
                [
                    'id' => 'use',
                    'nav' => '2. How We Use Information',
                    'title' => 'How We Use Information',
                    'number' => '02',
                    'paragraphs' => [
                        'We use your information to provide social media services, process payments, prevent fraud, meet compliance obligations, and support your account.',
                    ],
                ],
                [
                    'id' => 'sharing',
                    'nav' => '3. Sharing',
                    'title' => 'Sharing',
                    'number' => '03',
                    'paragraphs' => [
                        'We may share data with payment processors, KYC providers, and service providers who help operate the Platform, under appropriate safeguards. We do not sell your personal data.',
                    ],
                ],
                [
                    'id' => 'security-privacy',
                    'nav' => '4. Security',
                    'title' => 'Security',
                    'number' => '04',
                    'paragraphs' => [
                        'We apply reasonable technical and organizational measures to protect your data. No method of transmission or storage is fully secure; please protect your login credentials.',
                    ],
                ],
                [
                    'id' => 'rights',
                    'nav' => '5. Your Rights',
                    'title' => 'Your Rights',
                    'number' => '05',
                    'paragraphs' => [
                        'You may request access or correction of your account information via support, subject to legal retention requirements for payments and compliance records.',
                    ],
                ],
                [
                    'id' => 'contact-privacy',
                    'nav' => '6. Contact',
                    'title' => 'Contact',
                    'number' => '06',
                    'paragraphs' => [
                        'Privacy questions can be sent via the Contact page or a support ticket in your dashboard.',
                    ],
                ],
            ],
        ],
    ],
];
