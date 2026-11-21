<?php
$pageTitle = 'Cookie Policy';
$activePage = 'cookie-policy';
$pageDescription = 'The cookies and browser storage the Kingsway Preparatory School website and portals use, and how to control them.';
$showCookieConsent = true;
$legalTitle = 'Cookie Policy';
$legalUpdated = '21 November 2026';
$legalIntro = 'This policy explains the small files ("cookies") and browser storage our website, parent portal and staff system use. '
    . '<strong>We do not use advertising or tracking cookies, and we do not sell your browsing data.</strong>';

$row = static fn (string $name, string $type, string $purpose, string $duration): string =>
    '<tr><td><code>' . htmlspecialchars($name) . '</code></td><td>' . $type . '</td><td>' . $purpose . '</td><td>' . $duration . '</td></tr>';
$table = static fn (string $rows): string =>
    '<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr>'
    . '<th>Name</th><th>Type</th><th>Purpose</th><th>Kept for</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';

$legalSections = [
    ['id' => 'what', 'title' => 'What cookies and browser storage are', 'html' => '
<p>Cookies are small text files a website places on your device. Browser storage ("local storage" and "session storage") works in a similar way but stays on your device and is not sent with every request. We use both only to make our services work securely and to remember your choices.</p>'],

    ['id' => 'essential', 'title' => 'Strictly necessary', 'html' => '
<p>These are needed for the services to work and to keep accounts secure, so they cannot be switched off:</p>' . $table(
        $row('kingsway_cookie_consent', 'Cookie and local storage', 'Remembers whether you accepted or declined non-essential cookies.', '1 year')
        . $row('refresh_token', 'Cookie (secure, not readable by scripts)', 'Keeps a signed-in staff member signed in securely.', 'Until sign-out, or up to the "remember me" period')
        . $row('pp_csrf, pp_expires, pp_children', 'Browser storage', 'Protects the parent portal against forged requests and keeps the parent\'s session working.', 'Until sign-out or the session ends')
        . $row('auth_storage_mode, kingsway_browser_session_id', 'Browser storage', 'Keeps the staff system\'s sign-in session consistent and secure.', 'Until sign-out')
        . $row('kingsway_sticky_master', 'Cookie', 'Technical setting that keeps data you just saved consistent on the next page.', 'Short-lived')
    )],

    ['id' => 'functional', 'title' => 'Functional', 'html' => '
<p>These remember choices you make and support optional features:</p>' . $table(
        $row('kingsway_visitor_id / kw_visitor', 'Local storage / cookie', 'A random, anonymous identifier for the website assistant. It links your optional survey answers without identifying you, and protects the assistant from misuse.', 'Up to 6 months')
        . $row('kingsway_public_ai_conversation', 'Session storage', 'Keeps your current website-assistant conversation while the browser tab is open.', 'Until you close the tab')
        . $row('user_theme, sidebar_collapsed, kw_assistant_prefs', 'Local storage', 'Remembers display preferences and assistant settings in the staff system.', 'Until you clear them')
    )],

    ['id' => 'third-party', 'title' => 'Third-party content', 'html' => '
<p>To display fonts and icons, our pages load files from content delivery networks (cdnjs and Google Fonts). Your browser connects to these services directly, which shares your IP address with them, but we do not allow them to place tracking cookies. Links to our social media pages are ordinary links: those sites set their own cookies only if you visit them.</p>'],

    ['id' => 'control', 'title' => 'Managing cookies', 'html' => '
<p>You can accept or decline non-essential cookies using the banner shown on your first visit, and change your mind by clearing this site\'s data in your browser. You can also block or delete cookies in your browser settings. If you block strictly necessary cookies, signing in to the parent portal or staff system will not work.</p>'],

    ['id' => 'more', 'title' => 'More information', 'html' => '
<p>For how we handle personal data generally, see our <a href="index.php?route=' . htmlspecialchars(public_route_token('privacy-policy')) . '">Privacy Policy</a>.</p>'],
];

include __DIR__ . '/public/layout/legal_page.php';
