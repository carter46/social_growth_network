<?php

/**
 * Legal copy uses :site_name, replaced at render time with the branding site name.
 *
 * "documents" is the public set for Creators (linked site-wide and indexed).
 * "agent_documents" is the Agent-only set, served at /agents/legal with noindex.
 */
return [
    'updated_at' => '2026-10-19',

    'contact' => [
        'email' => env('LEGAL_CONTACT_EMAIL'),
    ],

    'google_links' => [
        ['label' => 'YouTube Terms of Service', 'url' => 'https://www.youtube.com/t/terms'],
        ['label' => 'Google Privacy Policy', 'url' => 'https://policies.google.com/privacy'],
        ['label' => 'Google API Services User Data Policy', 'url' => 'https://developers.google.com/terms/api-services-user-data-policy'],
        ['label' => 'Manage third-party access to your Google Account', 'url' => 'https://myaccount.google.com/connections'],
    ],

    'documents' => [
        'terms' => [
            'label' => 'Terms of Service',
            'eyebrow' => 'Compliance & Legal',
            'intro' => 'The rules for using :site_name to promote your YouTube channel and videos with marketing campaigns.',
            'summary' => ':site_name is a marketing service that runs promotion campaigns to help YouTube creators increase the visibility of their channel and videos. These terms cover your account, campaigns, payments, the optional YouTube channel connection, and what we expect from each other.',
            'sections' => [
                [
                    'id' => 'acceptance',
                    'nav' => '1. Acceptance of Terms',
                    'title' => 'Acceptance of Terms',
                    'number' => '01',
                    'paragraphs' => [
                        'By creating an account or using :site_name ("the Platform", "we", "us"), you agree to these Terms of Service and to our Privacy Policy. If you do not agree, please do not use the Platform.',
                        'You must be at least 18 years old, or the age of majority where you live, and able to enter into a binding agreement. If you use the Platform for a business or brand, you confirm you are authorised to accept these terms on its behalf.',
                        'We may update these terms from time to time. We will show the new date at the top of this page, and for important changes we will let you know by email or in your dashboard. Continuing to use the Platform after a change means you accept the updated terms.',
                    ],
                ],
                [
                    'id' => 'service',
                    'nav' => '2. Our Service',
                    'title' => 'Our Service',
                    'number' => '02',
                    'paragraphs' => [
                        ':site_name is a digital marketing company. We plan and run promotion campaigns that help put your YouTube channel and videos in front of more viewers, so more people can discover your content.',
                        'You choose a campaign package, tell us which channel or video to promote, and pay through the Platform. We then deliver the campaign using our own promotion network and marketing methods, and you can follow its progress from your dashboard.',
                    ],
                    'cards' => [
                        [
                            'title' => 'What we do',
                            'body' => 'Promote your content to increase its visibility, report campaign progress in your dashboard, and support you if something does not look right.',
                        ],
                        [
                            'title' => 'What we never do',
                            'body' => 'Ask for your YouTube password, post or change anything on your channel, or take ownership of your content.',
                        ],
                    ],
                ],
                [
                    'id' => 'security',
                    'nav' => '3. Accounts & Security',
                    'title' => 'Your Account & Security',
                    'number' => '03',
                    'paragraphs' => [
                        'You are responsible for your account and the activity on it. You agree to:',
                    ],
                    'checklist' => [
                        'Give accurate information when you register and keep it up to date.',
                        'Keep your password and one-time codes private, and protect access to your devices and email.',
                        'Tell us quickly through support if you think someone else has accessed your account or wallet.',
                        'Keep one account for yourself or your business, and not share, sell or transfer it.',
                    ],
                ],
                [
                    'id' => 'campaigns',
                    'nav' => '4. Campaigns & Packages',
                    'title' => 'Campaigns & Packages',
                    'number' => '04',
                    'paragraphs' => [
                        'Each package describes what the campaign includes and its price at the time you order. The package description and checkout screen are what apply to your order.',
                    ],
                    'bullets' => [
                        'You must own the channel and content you promote, or have permission from the owner.',
                        'Your content must follow the law, the YouTube Terms of Service and the YouTube Community Guidelines.',
                        'Give us correct links and instructions. If a video or channel is private, removed, age-restricted or changed during a campaign, delivery may pause or stop.',
                        'We may review, pause or decline a campaign that breaks these terms or that we reasonably believe could harm viewers, you, or the Platform.',
                        'Campaign progress and order history are available in your dashboard.',
                    ],
                ],
                [
                    'id' => 'results',
                    'nav' => '5. Campaign Results',
                    'title' => 'Campaign Results',
                    'number' => '05',
                    'paragraphs' => [
                        'We work to increase the visibility of the content you promote, but we cannot promise specific results such as rankings, revenue, monetization, or a particular number of subscribers or views.',
                        'Results depend on many things outside our control, including YouTube\'s own systems and policies, how your content is received, and the competition in your niche. YouTube may also count, filter or display activity differently from the progress we report.',
                        'We are independent from YouTube and Google. YouTube is a trademark of Google LLC, and nothing on the Platform means YouTube or Google endorses us.',
                    ],
                ],
                [
                    'id' => 'youtube-channel',
                    'nav' => '6. Connecting Your YouTube Channel',
                    'title' => 'Connecting Your YouTube Channel',
                    'number' => '06',
                    'paragraphs' => [
                        'Connecting your YouTube channel is optional. It lets us confirm the channel belongs to you, fill in your channel details, and let you pick your own videos when you set up a campaign, so campaigns point at the right content.',
                        'You can connect with Google, which gives us read-only access to identify your channel, or by adding a short code to your channel description. We can never post, comment, upload, delete or change anything on your channel. You can disconnect your channel or remove Google\'s access at any time from the YouTube channel page in your settings.',
                        'The Platform uses YouTube API Services. By connecting your channel you also agree to be bound by the YouTube Terms of Service. How we handle the information we receive is explained in our Privacy Policy.',
                    ],
                    'links' => 'google',
                ],
                [
                    'id' => 'payments',
                    'nav' => '7. Payments, Wallet & Refunds',
                    'title' => 'Payments, Wallet & Refunds',
                    'number' => '07',
                    'paragraphs' => [
                        'All prices and balances on the Platform are in US dollars (USD). You can pay with your wallet balance, a card or bank payment gateway, or a manual bank transfer, when each method is available.',
                    ],
                    'bullets' => [
                        'Payments are processed by our payment providers. We do not store your full card number.',
                        'Your wallet balance is for paying for campaigns on the Platform.',
                        'Manual bank transfers are credited after we confirm the payment. Please use the exact reference shown.',
                        'If a campaign cannot be delivered as described, contact support. Approved refunds are usually credited to your wallet, and the outcome depends on the package, the delivery records and applicable law.',
                        'Opening a chargeback or payment dispute without first contacting support may lead to your account being restricted while we investigate.',
                        'We may ask you to verify your identity (KYC) before larger payments or where the law requires it.',
                    ],
                ],
                [
                    'id' => 'prohibited',
                    'nav' => '8. What Is Not Allowed',
                    'title' => 'What Is Not Allowed',
                    'number' => '08',
                    'variant' => 'danger',
                    'paragraphs' => [
                        'You must not use the Platform to:',
                    ],
                    'blocks' => [
                        'Promote content you do not own or have rights to',
                        'Promote illegal, hateful, violent or adult content',
                        'Commit fraud, payment abuse or chargeback fraud',
                        'Mislead viewers or impersonate others',
                        'Attack, scrape or overload the Platform',
                        'Get around our payment process',
                    ],
                ],
                [
                    'id' => 'suspension',
                    'nav' => '9. Suspension & Closing Your Account',
                    'title' => 'Suspension & Closing Your Account',
                    'number' => '09',
                    'paragraphs' => [
                        'We may suspend or close an account that breaks these terms, puts others at risk, or where the law requires it. We will tell you by email when your account is suspended or restored. If you think we made a mistake, contact our support team.',
                        'You can ask us to close your account at any time through support. Records we must keep for legal, tax or fraud prevention reasons are kept as explained in our Privacy Policy.',
                    ],
                ],
                [
                    'id' => 'ip',
                    'nav' => '10. Intellectual Property',
                    'title' => 'Intellectual Property',
                    'number' => '10',
                    'paragraphs' => [
                        'The Platform, its software, design and branding belong to :site_name and its licensors. Your channel and content remain yours. You give us a limited permission to use the links, titles and images of the content you promote only to run, show and report on your campaigns.',
                    ],
                ],
                [
                    'id' => 'liability',
                    'nav' => '11. Limitation of Liability',
                    'title' => 'Limitation of Liability',
                    'number' => '11',
                    'paragraphs' => [
                        'To the fullest extent the law allows, :site_name is not liable for indirect, incidental or consequential losses, or for loss of revenue, data or channel standing, arising from your use of the Platform. Our total liability for any claim is limited to the amount you paid for the campaign the claim relates to.',
                        'Nothing in these terms limits rights you have under consumer protection laws that cannot be limited.',
                    ],
                ],
                [
                    'id' => 'contact',
                    'nav' => '12. Contact',
                    'title' => 'Contact',
                    'number' => '12',
                    'variant' => 'contact',
                    'paragraphs' => [
                        'Questions about these terms can be sent through the Contact page or a support ticket in your dashboard.',
                    ],
                ],
            ],
        ],

        'privacy' => [
            'label' => 'Privacy Policy',
            'eyebrow' => 'Data & Privacy',
            'intro' => 'What information :site_name collects when you promote your YouTube content with us, why we need it, and how we protect it.',
            'summary' => 'We collect the information needed to run your account, your campaigns and your payments. If you connect your YouTube channel, we only get read-only access to identify your channel and list your public videos. We never post or change anything on your channel, and we do not sell your data.',
            'sections' => [
                [
                    'id' => 'collect',
                    'nav' => '1. Information We Collect',
                    'title' => 'Information We Collect',
                    'number' => '01',
                    'paragraphs' => [
                        'Depending on how you use the Platform, we collect:',
                    ],
                    'bullets' => [
                        'Account details: your name, email address, username, password (stored in encrypted form), and account settings. If you sign in with Google, we receive your name, email address and profile picture from Google.',
                        'Campaign and order details: the packages you choose, the channel and video links you promote, your instructions, and campaign progress.',
                        'YouTube channel details, only if you connect your channel. See "Your YouTube Channel and Google Data" below.',
                        'Payment and wallet records: deposits, payments, refunds and invoices. Card details are handled by our payment providers, and we do not store your full card number.',
                        'Identity verification (KYC) documents, only when we ask for them for compliance.',
                        'Messages you send us, such as support tickets and live chat.',
                        'Technical information: IP address, browser and device type, sign-in times, and security logs.',
                    ],
                ],
                [
                    'id' => 'use',
                    'nav' => '2. How We Use Information',
                    'title' => 'How We Use Information',
                    'number' => '02',
                    'paragraphs' => [
                        'We use your information to:',
                    ],
                    'bullets' => [
                        'Create and secure your account, including sign-in codes and suspicious activity checks.',
                        'Set up, deliver and report on your promotion campaigns.',
                        'Process payments, wallet funding and refunds.',
                        'Send you service emails, such as order updates, security alerts and account notices.',
                        'Answer your questions and resolve problems.',
                        'Prevent fraud and abuse, and meet our legal, tax and compliance obligations.',
                        'Improve the Platform using aggregated information that does not identify you.',
                    ],
                ],
                [
                    'id' => 'youtube-data',
                    'nav' => '3. Your YouTube Channel & Google Data',
                    'title' => 'Your YouTube Channel and Google Data',
                    'number' => '03',
                    'paragraphs' => [
                        'Connecting your YouTube channel is optional. We ask for it so we can confirm the channel is yours, fill in your channel details for you, and let you pick your own videos when you create a campaign. This keeps your campaigns pointed at the right channel and saves you copying links.',
                        'If you choose "Connect with Google", we ask Google for read-only access to your YouTube account (the youtube.readonly permission). We use it only to read your channel\'s ID, name, handle and profile picture, and the titles, thumbnails and links of your recent public videos. We cannot post, comment, upload, delete, like, subscribe or change anything on your channel, and we do not access your private videos, messages or Google account password.',
                        'If you connect by adding a code to your channel description instead, we only read your channel\'s public information.',
                        'The access token Google gives us is stored encrypted and used only for the purposes above. Our use and transfer of information received from Google APIs follows the Google API Services User Data Policy, including its Limited Use requirements. We do not sell this information, use it for advertising, use it to train artificial intelligence models, or let anyone read it except where needed to provide this feature, to comply with the law, or for security.',
                        'You stay in control. You can remove Google\'s access or disconnect your channel at any time from the YouTube channel page in your settings, and you can also remove our access from your Google Account permissions page. When you remove access, we delete the stored token straight away. A basic record of which channel was connected and when is kept for security and fraud prevention, as described under Retention.',
                        'The Platform uses YouTube API Services. By connecting your channel you agree to the YouTube Terms of Service, and Google\'s handling of your information is covered by the Google Privacy Policy.',
                    ],
                    'links' => 'google',
                ],
                [
                    'id' => 'sharing',
                    'nav' => '4. Sharing',
                    'title' => 'Sharing',
                    'number' => '04',
                    'paragraphs' => [
                        'We do not sell your personal information. We share it only with service providers who help us run the Platform, such as payment providers, identity verification providers, email delivery, hosting and security services, and Google when you use Google sign-in or connect your channel. They may only use it to provide their service to us.',
                        'We may also share information when the law requires it, or to protect our users, the Platform or the public from fraud or abuse.',
                    ],
                ],
                [
                    'id' => 'cookies',
                    'nav' => '5. Cookies',
                    'title' => 'Cookies',
                    'number' => '05',
                    'paragraphs' => [
                        'We use essential cookies to keep you signed in, protect forms and remember your preferences. Where enabled, we also use analytics and marketing tags to understand how visitors use our website and to measure our own advertising. You can control cookies in your browser settings, though the Platform needs essential cookies to work.',
                    ],
                ],
                [
                    'id' => 'retention',
                    'nav' => '6. Retention',
                    'title' => 'Retention',
                    'number' => '06',
                    'paragraphs' => [
                        'We keep your information while your account is open and for as long as needed to provide the service, resolve disputes and meet legal, tax and fraud prevention obligations. Payment records are kept for the period the law requires. When we no longer need information, we delete or anonymise it.',
                    ],
                ],
                [
                    'id' => 'security-privacy',
                    'nav' => '7. Security',
                    'title' => 'Security',
                    'number' => '07',
                    'paragraphs' => [
                        'We use technical and organisational measures to protect your information, including encrypted connections, encrypted storage of passwords and Google access tokens, sign-in codes and access controls. No system is completely secure, so please keep your password and codes private.',
                    ],
                ],
                [
                    'id' => 'rights',
                    'nav' => '8. Your Choices & Rights',
                    'title' => 'Your Choices & Rights',
                    'number' => '08',
                    'bullets' => [
                        'Update your profile details from your account settings.',
                        'Disconnect your YouTube channel or remove Google\'s access at any time.',
                        'Unsubscribe from marketing emails using the link in each email. Service and security emails continue while your account is open.',
                        'Ask us for a copy of your information, to correct it, or to delete your account and data, by contacting support. Some records may be kept where the law requires it.',
                    ],
                ],
                [
                    'id' => 'children',
                    'nav' => '9. Children',
                    'title' => 'Children',
                    'number' => '09',
                    'paragraphs' => [
                        'The Platform is not meant for anyone under 18. We do not knowingly collect information from children. If you believe a child has given us information, please contact us and we will delete it.',
                    ],
                ],
                [
                    'id' => 'contact-privacy',
                    'nav' => '10. Contact',
                    'title' => 'Contact',
                    'number' => '10',
                    'variant' => 'contact',
                    'paragraphs' => [
                        'Privacy questions, data requests and requests to delete information we hold about your YouTube channel can be sent through the Contact page or a support ticket in your dashboard.',
                    ],
                ],
            ],
        ],
    ],

    'agent_documents' => [
        'terms' => [
            'label' => 'Agent Terms of Use',
            'eyebrow' => 'Agent Program',
            'intro' => 'The rules for taking part in the :site_name Agent program, completing YouTube tasks and receiving rewards.',
            'summary' => 'These terms apply to Agents only. They cover joining with an invite code, connecting your own YouTube channel, completing tasks honestly, how rewards and withdrawals work, referrals, and keeping the Agent program confidential.',
            'sections' => [
                [
                    'id' => 'acceptance',
                    'nav' => '1. Acceptance',
                    'title' => 'Acceptance of These Terms',
                    'number' => '01',
                    'paragraphs' => [
                        'By creating an Agent account or using the Agent area of :site_name, you agree to these Agent Terms of Use and the Agent Privacy Policy.',
                        'You must be at least 18 years old, or the age of majority where you live. Joining requires a valid invite (referral) code from an existing Agent.',
                        'We may update these terms. We will show the new date on this page and tell you about important changes by email or in your dashboard. Continuing to use the Agent area after a change means you accept the updated terms.',
                    ],
                ],
                [
                    'id' => 'program',
                    'nav' => '2. The Agent Program',
                    'title' => 'The Agent Program',
                    'number' => '02',
                    'paragraphs' => [
                        'Agents choose and complete YouTube tasks that are part of promotion campaigns on :site_name, such as watching a video for a set time, liking, subscribing or commenting, and receive a reward when the task is approved.',
                        'You take part as an independent participant, not as an employee, contractor or agent in the legal sense of :site_name. You decide when and whether to take tasks. You are responsible for any taxes on rewards you receive.',
                    ],
                ],
                [
                    'id' => 'account',
                    'nav' => '3. Your Account',
                    'title' => 'Your Account',
                    'number' => '03',
                    'checklist' => [
                        'One Agent account per person. Multiple accounts, shared accounts and accounts created for someone else are not allowed.',
                        'Give accurate information and complete identity verification (KYC) when asked.',
                        'Keep your password and one-time codes private. You are responsible for activity on your account.',
                        'Tell support quickly if you think someone else has accessed your account or wallet.',
                    ],
                ],
                [
                    'id' => 'youtube-channel',
                    'nav' => '4. Your YouTube Channel',
                    'title' => 'Your YouTube Channel',
                    'number' => '04',
                    'paragraphs' => [
                        'You need to connect your own YouTube channel before you take a YouTube task, because tasks are completed from that channel and checked against it. You can connect with Google (read-only access) or by adding a code to your channel description. We can never post, comment, upload, delete or change anything on your channel.',
                        'Each channel can only be connected to one account. You can disconnect your channel from your settings when you have no open tasks, and you must use your own channel, not one you bought, borrowed or control for someone else.',
                        'The Platform uses YouTube API Services. By connecting your channel you agree to be bound by the YouTube Terms of Service, and you must follow the YouTube Community Guidelines when completing tasks.',
                    ],
                    'links' => 'google',
                ],
                [
                    'id' => 'tasks',
                    'nav' => '5. Completing Tasks',
                    'title' => 'Completing Tasks',
                    'number' => '05',
                    'paragraphs' => [
                        'Each task shows its steps, time limit and reward before you start. When completing a task you agree to:',
                    ],
                    'bullets' => [
                        'Follow every step in order and within the time shown.',
                        'Do the activity yourself, with genuine attention, from your connected channel.',
                        'Keep the like, subscription or comment in place after the task is approved, unless the task says otherwise.',
                        'Write comments in your own words that are respectful and relevant to the video.',
                        'Submit only genuine proof that belongs to you and to that task.',
                    ],
                ],
                [
                    'id' => 'rewards',
                    'nav' => '6. Verification & Rewards',
                    'title' => 'Verification & Rewards',
                    'number' => '06',
                    'paragraphs' => [
                        'We check tasks automatically and, where needed, by manual review. A reward is added to your wallet only after the task is approved. Opening or autoplaying a video, or completing only some steps, is not enough.',
                        'Tasks may be rejected if they do not meet the instructions, if proof is missing or reused, or if activity is later removed. Rewards for tasks found to be fraudulent may be reversed. Open tasks change often, and we do not promise a minimum number of tasks or any level of earnings.',
                    ],
                ],
                [
                    'id' => 'wallet',
                    'nav' => '7. Wallet & Withdrawals',
                    'title' => 'Wallet & Withdrawals',
                    'number' => '07',
                    'paragraphs' => [
                        'Rewards and balances are in US dollars (USD).',
                    ],
                    'bullets' => [
                        'Withdrawals are sent to a payout account in your own name. The payout provider may convert the amount to your local currency at its own rate.',
                        'Provider fees and any platform fee are shown before you confirm a withdrawal.',
                        'If a payout is returned because the account details you gave were wrong, the amount is returned to your wallet, less any returned payout fee you accepted when adding that account and that the provider charged.',
                        'Withdrawals may require identity verification and can take from minutes to several days depending on the method and compliance checks.',
                        'We may hold a balance while we investigate suspected fraud or a breach of these terms.',
                    ],
                ],
                [
                    'id' => 'referrals',
                    'nav' => '8. Invites & Referrals',
                    'title' => 'Invites & Referrals',
                    'number' => '08',
                    'paragraphs' => [
                        'You may invite people you know using your referral code. Referral rewards, where offered, follow the program rules shown in your dashboard. Inviting yourself, creating accounts to collect referral rewards, or sending spam invitations is not allowed, and related rewards may be cancelled.',
                    ],
                ],
                [
                    'id' => 'confidentiality',
                    'nav' => '9. Confidentiality',
                    'title' => 'Confidentiality',
                    'number' => '09',
                    'paragraphs' => [
                        'The Agent program is private. Do not publish, share or sell task details, campaign information, screenshots of the Agent area, or information about the channels and videos you work on. Do not contact channel owners about tasks or ask them for payment outside the Platform.',
                    ],
                ],
                [
                    'id' => 'prohibited',
                    'nav' => '10. What Is Not Allowed',
                    'title' => 'What Is Not Allowed',
                    'number' => '10',
                    'variant' => 'danger',
                    'paragraphs' => [
                        'The following lead to rejected tasks, reversed rewards and possible suspension:',
                    ],
                    'blocks' => [
                        'Bots, scripts or automation tools',
                        'Multiple accounts or shared channels',
                        'Fake, edited or reused proof',
                        'Undoing likes, subscriptions or comments',
                        'Spam, abusive or copied comments',
                        'Off-platform payment requests',
                    ],
                ],
                [
                    'id' => 'suspension',
                    'nav' => '11. Suspension & Closing',
                    'title' => 'Suspension & Closing Your Account',
                    'number' => '11',
                    'paragraphs' => [
                        'We may suspend or close an Agent account that breaks these terms or where the law requires it. We will tell you by email when your account is suspended or restored. If you think we made a mistake, contact our support team. You can ask us to close your account at any time through support.',
                    ],
                ],
                [
                    'id' => 'liability',
                    'nav' => '12. Limitation of Liability',
                    'title' => 'Limitation of Liability',
                    'number' => '12',
                    'paragraphs' => [
                        'To the fullest extent the law allows, :site_name is not liable for indirect or consequential losses, or for actions YouTube takes on your channel. Our total liability for any claim is limited to the approved rewards in your wallet at the time of the claim. Nothing in these terms limits rights that cannot be limited by law.',
                    ],
                ],
                [
                    'id' => 'contact',
                    'nav' => '13. Contact',
                    'title' => 'Contact',
                    'number' => '13',
                    'variant' => 'contact',
                    'paragraphs' => [
                        'Questions about these terms can be sent through a support ticket in your Agent dashboard.',
                    ],
                ],
            ],
        ],

        'privacy' => [
            'label' => 'Agent Privacy Policy',
            'eyebrow' => 'Agent Program',
            'intro' => 'What information :site_name collects from Agents, why we need it to check tasks and pay rewards, and how we protect it.',
            'summary' => 'We collect what we need to run your Agent account, check your YouTube tasks, pay rewards and prevent fraud. If you connect with Google, we only get read-only access to identify your channel. Task checks use public YouTube data. We never post or change anything on your channel, and we do not sell your data.',
            'sections' => [
                [
                    'id' => 'collect',
                    'nav' => '1. Information We Collect',
                    'title' => 'Information We Collect',
                    'number' => '01',
                    'bullets' => [
                        'Account details: name, email address, username, password (stored in encrypted form), the invite code you joined with, and your settings.',
                        'Your YouTube channel: channel ID, name, handle and profile picture.',
                        'Task activity: the tasks you take, the steps you complete, start and finish times, the proof you submit (such as links or screenshots), and review results.',
                        'Public YouTube data used to check tasks, such as whether your channel publicly subscribes to a channel and the public comments you were asked to post.',
                        'Wallet, reward, referral and withdrawal records, and the payout account details you add.',
                        'Identity verification (KYC) documents when required.',
                        'Support messages, and technical information such as IP address, device and browser type, and security logs.',
                    ],
                ],
                [
                    'id' => 'use',
                    'nav' => '2. How We Use Information',
                    'title' => 'How We Use Information',
                    'number' => '02',
                    'bullets' => [
                        'Run and secure your Agent account.',
                        'Match you to tasks and check that each step was completed as instructed.',
                        'Credit rewards and process withdrawals with our payout providers.',
                        'Detect and prevent fraud, multiple accounts and fake proof.',
                        'Send service emails such as task updates, security alerts and account notices.',
                        'Meet legal, tax and compliance obligations.',
                    ],
                ],
                [
                    'id' => 'youtube-data',
                    'nav' => '3. Your YouTube Channel & Google Data',
                    'title' => 'Your YouTube Channel and Google Data',
                    'number' => '03',
                    'paragraphs' => [
                        'We need your channel so that tasks can be checked against the channel you completed them from, and so each channel is used by only one person.',
                        'If you choose "Connect with Google", we ask Google for read-only access to your YouTube account (the youtube.readonly permission) and use it only to identify your channel: its ID, name, handle and profile picture. We cannot post, comment, upload, delete, like, subscribe or change anything on your channel, and we do not access your private videos, messages or Google account password.',
                        'Task checks use public YouTube data through the YouTube Data API. For subscribe steps to be checked automatically, your subscriptions need to be public; otherwise those steps are reviewed from your screenshots.',
                        'The access token Google gives us is stored encrypted. Our use and transfer of information received from Google APIs follows the Google API Services User Data Policy, including its Limited Use requirements. We do not sell this information, use it for advertising, or use it to train artificial intelligence models.',
                        'You can remove Google\'s access at any time from the YouTube channel page in your settings or from your Google Account permissions page. When you remove access, we delete the stored token straight away. A basic record of which channel was connected and when is kept for security and fraud prevention.',
                        'The Platform uses YouTube API Services. By connecting your channel you agree to the YouTube Terms of Service, and Google\'s handling of your information is covered by the Google Privacy Policy.',
                    ],
                    'links' => 'google',
                ],
                [
                    'id' => 'sharing',
                    'nav' => '4. Sharing',
                    'title' => 'Sharing',
                    'number' => '04',
                    'paragraphs' => [
                        'We do not sell your personal information, and we do not share your identity with the channel owners whose content you work on. We share information only with service providers who help us run the Platform, such as payout providers, identity verification providers, email delivery, hosting and security services, and Google when you sign in with Google or connect your channel.',
                        'We may also share information when the law requires it, or to protect our users, the Platform or the public from fraud or abuse.',
                    ],
                ],
                [
                    'id' => 'retention',
                    'nav' => '5. Retention',
                    'title' => 'Retention',
                    'number' => '05',
                    'paragraphs' => [
                        'We keep your information while your account is open and afterwards for as long as needed to resolve disputes, prevent fraud and meet legal, tax and compliance obligations. Task and payout records are kept for the period the law requires. When we no longer need information, we delete or anonymise it.',
                    ],
                ],
                [
                    'id' => 'security-privacy',
                    'nav' => '6. Security',
                    'title' => 'Security',
                    'number' => '06',
                    'paragraphs' => [
                        'We use technical and organisational measures to protect your information, including encrypted connections, encrypted storage of passwords and Google access tokens, sign-in codes and access controls. Please keep your password and codes private.',
                    ],
                ],
                [
                    'id' => 'rights',
                    'nav' => '7. Your Choices & Rights',
                    'title' => 'Your Choices & Rights',
                    'number' => '07',
                    'bullets' => [
                        'Update your profile and payout details from your settings.',
                        'Remove Google\'s access at any time, and disconnect your channel when you have no open tasks.',
                        'Ask us for a copy of your information, to correct it, or to delete your account and data, by contacting support. Some records may be kept where the law requires it.',
                    ],
                ],
                [
                    'id' => 'contact-privacy',
                    'nav' => '8. Contact',
                    'title' => 'Contact',
                    'number' => '08',
                    'variant' => 'contact',
                    'paragraphs' => [
                        'Privacy questions and data requests can be sent through a support ticket in your Agent dashboard.',
                    ],
                ],
            ],
        ],
    ],
];
