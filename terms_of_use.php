<?php
$pageTitle = 'Terms of Use';
$activePage = 'terms-of-use';
$pageDescription = 'Terms for using the Kingsway Preparatory School website, online admissions, parent portal, website assistant and online payments.';
$showCookieConsent = true;
$legalTitle = 'Terms of Use';
$legalUpdated = '21 November 2026';
$legalIntro = 'These terms apply when you use the Kingsway Preparatory School website, online admissions, the parent portal, '
    . 'the staff system, the website assistant and online payments (together, "our services"). By using our services you agree to '
    . 'these terms and to our <a href="index.php?route=' . htmlspecialchars(public_route_token('privacy-policy')) . '">Privacy Policy</a>.';

$legalSections = [
    ['id' => 'about', 'title' => 'About these terms', 'html' => '
<p>Our services are provided by Kingsway Preparatory School, Simotwet, Londiani, Kericho County, Kenya. If you do not agree with these terms, please do not use our services. Separate agreements, such as the admission agreement and the School\'s rules, fee policy and code of conduct, also apply to enrolled families and to staff. Where they conflict with these terms, those agreements prevail.</p>'],

    ['id' => 'accounts', 'title' => 'Accounts and security', 'html' => '
<ul>
  <li>Parent portal and staff accounts are personal. Keep your password, one-time codes and devices secure, and do not share your account.</li>
  <li>Use the account only for its intended purpose: parents for their own linked children, and staff within the duties of their role.</li>
  <li>Tell the School at once if you suspect someone else has used your account.</li>
  <li>Learners do not have accounts. Parents and guardians access their children\'s information through the parent portal.</li>
  <li>We may suspend or close an account to protect learners, other users or our systems, or when a person is no longer entitled to it.</li>
</ul>'],

    ['id' => 'acceptable-use', 'title' => 'Acceptable use', 'html' => '
<p>You must not:</p>
<ul>
  <li>try to access information you are not authorised to see, including other families\', learners\' or staff members\' records;</li>
  <li>attempt to hack, probe, overload or disrupt our services, or use automated tools to scrape them;</li>
  <li>try to make the website or staff assistant ignore its rules, reveal its instructions or disclose internal information ("prompt injection");</li>
  <li>upload malicious software or unlawful, offensive or misleading content; or</li>
  <li>impersonate another person, or submit false information.</li>
</ul>
<p>We monitor our services for misuse. A session or device used for these activities may be blocked automatically, and we may report serious misuse to the authorities.</p>'],

    ['id' => 'assistant', 'title' => 'The website assistant', 'html' => '
<p>The website assistant gives general information from material the School has published. It is an automated tool: it may make mistakes, and its answers are not a binding offer or commitment. Always confirm fees, dates, admission requirements and other important details with the School office before relying on them. Do not enter personal or sensitive information into the assistant. Any survey questions it asks are optional and anonymous.</p>'],

    ['id' => 'admissions', 'title' => 'Online admission applications', 'html' => '
<ul>
  <li>The information and documents you submit must be true, complete and yours to share.</li>
  <li>Submitting an application does not guarantee a place. Admission follows the School\'s process, which includes an interview for Grades 4 to 9, and depends on available places.</li>
  <li>You confirm that you are the child\'s parent or legal guardian, or are authorised by them, and you consent to the School processing the child\'s data for the application, as explained in our Privacy Policy.</li>
</ul>'],

    ['id' => 'fees', 'title' => 'Fees and online payments', 'html' => '
<ul>
  <li>School fees are set out in the School\'s approved fee structure for each academic year. Fee information on the website and from the assistant is a guide. Your child\'s fee statement from the School is authoritative.</li>
  <li>Online payments are processed by third-party payment providers, such as M-Pesa and our bank, under their own terms. Check the amount and reference before you confirm a payment.</li>
  <li>A payment is credited once the provider confirms it to the School. Keep your transaction message and receipt.</li>
  <li>Refunds, credits and fee adjustments follow the School\'s fee policy. Please contact the accounts office for any payment query.</li>
</ul>'],

    ['id' => 'uniforms', 'title' => 'Uniform catalogue orders', 'html' => '
<p>Uniform orders placed through the catalogue are separate from school fees and never change a fee balance. Prices, sizes and availability are shown at the time of ordering and may change. Collection, exchange and payment arrangements are confirmed by the School store.</p>'],

    ['id' => 'content', 'title' => 'Our content', 'html' => '
<p>The content of our services, including text, photographs, logos, the school crest and documents, belongs to Kingsway Preparatory School or is used with permission. You may view and print it for personal, non-commercial use. You may not copy, publish or alter it for other purposes without our written permission. Photographs of learners must never be copied or shared.</p>'],

    ['id' => 'links', 'title' => 'Links to other websites', 'html' => '
<p>Our services may link to other websites, such as government education sites or social media. We are not responsible for their content or privacy practices.</p>'],

    ['id' => 'availability', 'title' => 'Availability and liability', 'html' => '
<p>We work to keep our services available, accurate and secure, but they are provided "as is" and may sometimes be unavailable, for example during maintenance. To the extent the law allows, the School is not liable for any indirect loss arising from your use of our services or from reliance on general information without confirming it with the School. Nothing in these terms limits any liability that cannot be limited under Kenyan law, or your statutory rights.</p>'],

    ['id' => 'law', 'title' => 'Governing law', 'html' => '
<p>These terms are governed by the laws of Kenya. We encourage you to raise any concern with the School first so that we can resolve it. Any dispute that cannot be resolved this way falls under the jurisdiction of the courts of Kenya.</p>'],

    ['id' => 'changes', 'title' => 'Changes to these terms', 'html' => '
<p>We may update these terms from time to time. The "last updated" date shows the current version. Continuing to use our services after a change means you accept the updated terms.</p>'],
];

include __DIR__ . '/public/layout/legal_page.php';
