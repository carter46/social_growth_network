<?php

/**
 * Legal copy uses :site_name — replaced at render time with branding site name.
 */
return [
    'updated_at' => '2026-09-09',

    'contact' => [
        'email' => env('LEGAL_CONTACT_EMAIL'),
    ],

    'documents' => [
        'terms' => [
            'label' => 'Terms of Service',
            'eyebrow' => 'Compliance & Legal',
            'intro' => 'Rules for using :site_name as a Creator launching campaigns or an Agent completing verified tasks.',
            'summary' => 'By using :site_name you agree to these marketplace rules for Creators and Agents, wallet and checkout terms, KYC where required, and proof verification standards. Last updated September 2026.',
            'sections' => [
                [
                    'id' => 'acceptance',
                    'nav' => '1. Acceptance of Terms',
                    'title' => 'Acceptance of Terms',
                    'number' => '01',
                    'paragraphs' => [
                        'By accessing or using :site_name (“the Platform”), including campaign services, the Agent task marketplace, and the Naira wallet, you confirm that you have read, understood, and agree to these Terms of Service.',
                        'If you do not agree, you must stop using the Platform. We may update these terms from time to time; continued use after changes are posted means you accept the updated terms.',
                    ],
                ],
                [
                    'id' => 'roles',
                    'nav' => '2. Creators & Agents',
                    'title' => 'Creators and Agents',
                    'number' => '02',
                    'paragraphs' => [
                        ':site_name operates a two-sided digital campaign marketplace.',
                    ],
                    'cards' => [
                        [
                            'title' => 'Creators',
                            'body' => 'Businesses, brands, organizations, and individuals who purchase predefined campaign packages, set requirements (such as target URL and instructions), fund checkout, and monitor verified progress.',
                        ],
                        [
                            'title' => 'Agents',
                            'body' => 'Individuals who discover eligible campaign tasks, complete required activities, submit proof for review, and earn rewards after verification according to platform rules.',
                        ],
                    ],
                ],
                [
                    'id' => 'security',
                    'nav' => '3. Accounts & Security',
                    'title' => 'User Accounts & Security',
                    'number' => '03',
                    'paragraphs' => [
                        'Account security is a shared responsibility. You agree to:',
                    ],
                    'checklist' => [
                        'Provide accurate information during registration and any KYC (Know Your Customer) process.',
                        'Keep your login credentials confidential and protect access to your devices.',
                        'Notify support promptly if you suspect unauthorized access to your account or wallet.',
                        'Use the Platform only for lawful campaign and task activity consistent with these Terms.',
                    ],
                ],
                [
                    'id' => 'campaigns',
                    'nav' => '4. Campaigns & Tasks',
                    'title' => 'Campaigns, Packages & Tasks',
                    'number' => '04',
                    'paragraphs' => [
                        'The Platform lists campaign services and predefined packages (for example social growth and engagement campaigns across supported networks). Creators purchase packages through the Platform catalog. Agents fulfill related tasks through the Platform marketplace under package and Creator instructions.',
                    ],
                    'cards' => [
                        [
                            'title' => 'Creator obligations',
                            'body' => 'Provide accurate campaign targets and instructions. Do not request illegal activity, credential theft, or content that violates third-party platform rules. Fund checkout through official Platform payment flows only.',
                        ],
                        [
                            'title' => 'Agent obligations',
                            'body' => 'Complete tasks honestly, follow instructions, and submit genuine proof. Do not use bots, fake engagement, recycled evidence, or account takeover methods. Do not solicit off-platform payment from Creators.',
                        ],
                    ],
                    'bullets' => [
                        'Campaign status, orders, and task progress are tracked in your dashboard after login.',
                        'Package descriptions and checkout screens control deliverables and pricing at the time of purchase.',
                        'The Platform coordinates verification and reward settlement; Creators and Agents must not bypass Platform checkout or payout flows.',
                    ],
                ],
                [
                    'id' => 'verification',
                    'nav' => '5. Proof & Verification',
                    'title' => 'Proof, Verification & Secure Checkout',
                    'number' => '05',
                    'paragraphs' => [
                        'Creator payments for campaign packages are processed through Platform checkout and held securely until eligible tasks are completed and verified against package and Creator requirements.',
                        'Agent rewards are released only after proof is approved under Platform review rules. Rejected, fraudulent, or incomplete proof may result in non-payment, resubmission requirements, task reassignment, or account restrictions.',
                        'Disputes about proof, replacements, or refunds are handled under Platform policies and support review. Outcomes depend on evidence, package rules, and applicable law.',
                    ],
                ],
                [
                    'id' => 'financial',
                    'nav' => '6. Financial Transactions',
                    'title' => 'Financial Transactions',
                    'number' => '06',
                    'paragraphs' => [
                        'Wallet funding, campaign checkout, Agent reward credits, and withdrawals are subject to verification, admin review where required, fees, and applicable payment-provider rules (including Monnify and bank partners when enabled).',
                    ],
                    'bullets' => [
                        'You may fund or pay with wallet balance, card/transfer gateway, reserved virtual account deposit, or manual bank transfer when those methods are enabled.',
                        'You are responsible for providing correct bank details for deposits and withdrawals. We are not liable for losses from incorrect details you supply.',
                        'Deposits, rewards, and withdrawals may take from minutes up to longer review windows depending on method, KYC status, and compliance checks.',
                        'KYC may be required before wallet creation, higher funding limits, withdrawals, or high-tier campaigns.',
                    ],
                ],
                [
                    'id' => 'prohibited',
                    'nav' => '7. Prohibited Activities',
                    'title' => 'Prohibited Activities',
                    'number' => '07',
                    'variant' => 'danger',
                    'paragraphs' => [
                        'You must not use the Platform for:',
                    ],
                    'blocks' => [
                        'Fraud, phishing, or payment abuse',
                        'Fake proof, bots, or engagement farms',
                        'Money laundering or illegal payments',
                        'Chargeback fraud or false claims',
                        'Scraping, attacks, or account takeover',
                        'Off-platform payment bypass schemes',
                    ],
                ],
                [
                    'id' => 'ip',
                    'nav' => '8. Intellectual Property',
                    'title' => 'Intellectual Property',
                    'number' => '08',
                    'paragraphs' => [
                        'Platform software, branding, UI, and content remain the property of :site_name and its licensors. You may not copy or redistribute them without permission. Campaign assets and instructions you upload remain subject to your rights and licenses; you grant the Platform a limited license to process them for fulfillment and verification.',
                    ],
                ],
                [
                    'id' => 'liability',
                    'nav' => '9. Limitation of Liability',
                    'title' => 'Limitation of Liability',
                    'number' => '09',
                    'paragraphs' => [
                        'To the fullest extent permitted by law, :site_name is not liable for indirect, incidental, or consequential damages arising from use of the Platform. Campaign outcomes can depend on third-party platform policies, network availability, and user-submitted proof quality outside our exclusive control.',
                    ],
                ],
                [
                    'id' => 'contact',
                    'nav' => '10. Contact',
                    'title' => 'Contact',
                    'number' => '10',
                    'variant' => 'contact',
                    'paragraphs' => [
                        'Questions about these terms can be sent via the Contact page or a support ticket in your dashboard.',
                    ],
                ],
            ],
        ],

        'privacy' => [
            'label' => 'Privacy Policy',
            'eyebrow' => 'Data & Privacy',
            'intro' => 'How :site_name collects, uses, and protects information for Creators, Agents, payments, KYC, and campaign verification.',
            'summary' => 'We collect account, campaign, proof, KYC, and payment data needed to operate the Creator–Agent marketplace and your Naira wallet. Last updated September 2026.',
            'sections' => [
                [
                    'id' => 'collect',
                    'nav' => '1. Information We Collect',
                    'title' => 'Information We Collect',
                    'number' => '01',
                    'paragraphs' => [
                        'Depending on how you use the Platform, we may collect:',
                    ],
                    'bullets' => [
                        'Account details such as name, email, role (Creator and/or Agent), and profile settings.',
                        'Campaign and order data including package selections, target URLs, instructions, and progress status.',
                        'Agent task activity and proof assets (for example screenshots, links, and metadata needed for verification).',
                        'KYC documents and identity data when required for compliance, funding, or withdrawals.',
                        'Payment and wallet records, including deposits, checkout, rewards, and withdrawals.',
                        'Technical logs, device signals, and support communications needed for security and customer service.',
                    ],
                ],
                [
                    'id' => 'use',
                    'nav' => '2. How We Use Information',
                    'title' => 'How We Use Information',
                    'number' => '02',
                    'paragraphs' => [
                        'We use your information to operate the marketplace and related features, including to:',
                    ],
                    'bullets' => [
                        'Provide Creator campaign checkout and Agent task fulfillment.',
                        'Verify proof, prevent fraud, and settle rewards according to Platform rules.',
                        'Process wallet funding, payments, and withdrawals with payment partners.',
                        'Complete KYC and meet legal or compliance obligations.',
                        'Support your account, resolve disputes, and improve Platform reliability.',
                    ],
                ],
                [
                    'id' => 'creators-agents',
                    'nav' => '3. Creators & Agents',
                    'title' => 'Creators and Agents',
                    'number' => '03',
                    'paragraphs' => [
                        'Creator campaign requirements and Agent proof may be processed by Platform staff and automated systems solely to fulfill and verify campaigns. We do not sell personal data. Agents should avoid submitting unnecessary personal data in proof files beyond what a campaign requires.',
                    ],
                ],
                [
                    'id' => 'sharing',
                    'nav' => '4. Sharing',
                    'title' => 'Sharing',
                    'number' => '04',
                    'paragraphs' => [
                        'We may share data with payment processors (such as Monnify), KYC providers, hosting and security vendors, and other service providers who help operate the Platform, under appropriate safeguards. We may also disclose information when required by law or to protect the Platform, Creators, Agents, or the public from fraud or abuse.',
                    ],
                ],
                [
                    'id' => 'retention',
                    'nav' => '5. Retention',
                    'title' => 'Retention',
                    'number' => '05',
                    'paragraphs' => [
                        'We retain account, payment, KYC, and proof-related records for as long as needed to operate the Platform, resolve disputes, and meet legal, tax, and compliance requirements. When retention is no longer required, we delete or anonymize data where practical.',
                    ],
                ],
                [
                    'id' => 'security-privacy',
                    'nav' => '6. Security',
                    'title' => 'Security',
                    'number' => '06',
                    'paragraphs' => [
                        'We apply reasonable technical and organizational measures to protect your data. No method of transmission or storage is fully secure; please protect your login credentials and never share passwords or one-time codes.',
                    ],
                ],
                [
                    'id' => 'rights',
                    'nav' => '7. Your Rights',
                    'title' => 'Your Rights',
                    'number' => '07',
                    'paragraphs' => [
                        'You may request access or correction of your account information via support, subject to legal retention requirements for payments, KYC, and compliance records.',
                    ],
                ],
                [
                    'id' => 'contact-privacy',
                    'nav' => '8. Contact',
                    'title' => 'Contact',
                    'number' => '08',
                    'variant' => 'contact',
                    'paragraphs' => [
                        'Privacy questions can be sent via the Contact page or a support ticket in your dashboard.',
                    ],
                ],
            ],
        ],
    ],
];
