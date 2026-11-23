<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';
$memberCount = tableExists($pdo, 'users_data') ? countRows($pdo, 'users_data') : 0;
require __DIR__ . '/../includes/header.php';
?>
<section class="bd-start-panel">
    <div class="start-banner"><span class="rocket"> <svg width="20" height="21" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12.2 7.74432C12.5556 8.14432 13.0444 8.29988 13.6667 8.21099C14.2889 8.1221 14.8444 7.8221 15.3333
            7.31099C15.8222 6.79988 16.1111 6.23321 16.2 5.61099C16.2889 4.98877 16.1556 4.48877 15.8 4.11099C15.4444
            3.73321 14.9556 3.58877 14.3333 3.67766C13.7111 3.76655 13.1444 4.06655 12.6333 4.57766C12.1222 5.08877
            11.8222 5.65544 11.7333 6.27766C11.6444 6.89988 11.8 7.38877 12.2 7.74432ZM19.7333 4.14432C19.2 6.54432
            17.6889 9.14432 15.2 11.9443L14.9333 14.9443C14.8444 15.6999 14.5333 16.3221 14 16.811L11 19.811C10.9111
            19.8999 10.8 19.9554 10.6667 19.9777C10.5333 19.9999 10.4 19.9888 10.2667 19.9443C10.1333 19.8999 10.0444
            19.811 10 19.6777C9.95555 19.5443 9.93333 19.411 9.93333 19.2777L10.2667 16.8777C10.3111 16.5665 10.2667
            16.1888 10.1333 15.7443L9.66666 14.611C8.68888 14.3888 7.78888 13.8665 6.96666 13.0443C6.14444 12.2221 5.59999
            11.2999 5.33333 10.2777L4.26666 9.87766C3.82222 9.74432 3.42222 9.69988 3.06666 9.74432L0.733328
            10.0777C0.599995 10.0777 0.466661 10.0443 0.333328 9.97766C0.199995 9.91099 0.111106 9.8221 0.0666612
            9.71099C0.0222168 9.59988 0.0111057 9.47766 0.0333279 9.34432C0.0555501 9.21099 0.111106 9.07766 0.199995
            8.94432L3.13333 6.01099C3.66666 5.47766 4.31111 5.16655 5.06666 5.07766L8.06666 4.74432C10.7778 2.34432
            13.3556 0.855436 15.8 0.277658C16.7333 0.0554352 17.6 -0.033453 18.4 0.0109921C18.6222 0.0554352 18.8444
            0.0998802 19.0667 0.144325L19.2667 0.210991C19.4889 0.299881 19.6444 0.410992 19.7333
            0.544325V0.677658C19.8667 0.944324 19.9333 1.23321 19.9333 1.54432C20.0222 2.34432 19.9556 3.21099 19.7333
            4.14432ZM13.7333 13.4777H13.8L13.6667 14.811C13.6222 15.2554 13.4222 15.6332 13.0667 15.9443L11.4667
            17.5443L11.5333 17.0777C11.6222 16.5888 11.5556 16.011 11.3333 15.3443L11.1333 14.7443C12.1111 14.6554 12.9778
            14.2332 13.7333 13.4777ZM6.53333 6.27766C5.77777 7.03321 5.35555 7.89988 5.26666 8.87766L4.66666
            8.67766C3.99999 8.45543 3.42222 8.38877 2.93333 8.47766L2.46666 8.54432L4.06666 6.87766C4.37777 6.56655
            4.75555 6.38877 5.19999 6.34432L6.53333 6.21099V6.27766ZM18.2667 1.27766C17.6 1.23321 16.8667 1.3221 16.0667
            1.54432C14.9111 1.81099 13.6889 2.3221 12.4 3.07766C10.8 4.09988 9.13333 5.45543 7.39999 7.14432C6.68888
            7.89988 6.39999 8.76655 6.53333 9.74432C6.66666 10.5888 7.09999 11.3777 7.83333 12.111C8.56666 12.8443 9.37777
            13.2999 10.2667 13.4777C11.2444 13.611 12.0889 13.3221 12.8 12.611C14.5778 10.8332 15.9556 9.1221 16.9333
            7.47766C17.7333 6.18877 18.2667 4.98877 18.5333 3.87766C18.7111 3.07777 18.7556 2.36655 18.6667
            1.74433V1.34432L18.2667 1.27766ZM8.73333 15.211C7.93333 14.7221 7.17777 14.1332 6.46666 13.4443C5.75555
            12.7554 5.17777 11.9665 4.73333 11.0777C4.33333 11.4777 3.91111 12.211 3.46666 13.2777C3.15555 14.1221 2.88888
            15.0554 2.66666 16.0777C2.44444 17.011 2.36666 17.511 2.43333 17.5777C2.49999 17.6443 2.98888 17.5443 3.89999
            17.2777C4.81111 17.011 5.68888 16.7221 6.53333 16.411C7.59999 15.9665 8.33333 15.5665 8.73333 15.211Z" fill="white"/>
    </svg></span><strong>Start Here</strong><b>Complete these one-time steps to ensure your site runs smoothly.</b></div>
    <div class="start-grid">
        <article class="website-card">
            <h3>Brilliant Directories Demo</h3><img class="website-preview-image" src="<?= e($config['base_url']) ?>/assets/images/directory-site-preview.png" alt="Directory website preview">
            <div class="website-info"><a href="<?= e($config['base_url']) ?>/public/index.php" target="_blank" rel="noopener">♧ &nbsp;launch58415.directoryup.com</a>
                <p>▦ &nbsp;Start Date: <strong>Dec 11, 2025</strong></p>
                <p>♧ &nbsp;Members: <strong><?= number_format($memberCount) ?></strong></p>
                <p>☆ &nbsp;Plan: <strong class="plan-pill">Lifetime Plan</strong></p>
            </div>
        </article>
        <article class="setup-card-bd">
            <div class="progress-summary">
                <div class="progress-track"><span></span></div>
                <div class="progress-line"><strong>0%</strong><b>Basic Website Setup</b><small>Phase 1 of 2</small></div>
            </div>
            <div class="setup-step"><span class="step-check"></span>
                <div><b>1. Website Basics</b><small>Article ↗ &nbsp; Video ⊙</small></div><button>Edit Basics</button>
            </div>
            <div class="setup-step"><span class="step-check"></span>
                <div><b>2. Add Business<br>Listings</b><small>Article ↗ &nbsp; Video ⊙</small></div><button>Add Listings</button>
            </div>
            <div class="setup-step"><span class="step-check"></span>
                <div><b>3. Connect<br>Domain</b><small>Article ↗ &nbsp; Video ⊙</small></div><button>Connect Domain</button>
            </div>
        </article>
        <article class="explore-card-bd">
            <h3>Explore What You Can Do</h3>
            <a href="#"><span>⌂</span>Edit Homepage <i>›</i></a>
            <a href="#"><span>♙</span>View Members <i>›</i></a>
            <a href="#"><span>♧</span>Test Member Sign Up <i>›</i></a>
            <a href="#"><span>◉</span>Design &amp; Layout <i>›</i></a>
            <a href="#"><span>⚙</span>General Settings <i>›</i></a>
            <a class="ai-logo-button" href="#">✦ &nbsp;Generate Logo with AI</a>
        </article>
    </div>
    <div class="power-up">
        <div class="power-heading"><strong> <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M11.2533 0.0533285C11.36 0.12444 11.4311 0.213328 11.4667 0.319995C11.5022 0.426662 11.5022 0.533329 11.4667
            0.639996L9.65334 6.50666H13.0133C13.2267 6.50666 13.3778 6.60444 13.4667 6.8C13.5556 6.99555 13.5289 7.18222
            13.3867 7.36L5.38667 15.84C5.28 15.9111 5.17334 15.9556 5.06667 15.9733C4.96 15.9911 4.85334 15.9733 4.74667
            15.92C4.64 15.8667 4.56889 15.7867 4.53334 15.68C4.49778 15.5733 4.49778 15.4667 4.53334 15.36L6.34667
            9.49333H2.98667C2.77334 9.49333 2.62223 9.39555 2.53334 9.2C2.44445 9.00444 2.47112 8.81777 2.61334
            8.64L10.6133 0.159995C10.72 0.0888844 10.8267 0.0444393 10.9333 0.0266619C11.04 0.00888443 11.1467 0.0177727
            11.2533 0.0533285ZM4.16 8.48H6.98667C7.16445 8.48 7.30667 8.55111 7.41334 8.69333C7.52 8.83555 7.53778 8.99555
            7.46667 9.17333L6.13334 13.6L11.84 7.52H9.01334C8.83556 7.52 8.69334 7.44888 8.58667 7.30666C8.48 7.16444
            8.46223 7.00444 8.53334 6.82666L9.86667 2.4L4.16 8.48Z" fill="#253342"/>
    </svg> &nbsp;Power Up Your Site</strong><span>RECOMMENDED</span></div>
        <div class="promo-list">
            <article class="promo-item"><img src="<?= e($config['base_url']) ?>/assets/images/promo-hosting.jpg" alt="Enhanced hosting bundle">
                <div><b>Enhanced Hosting - 50% Off</b>
                    <p>Allow AI bots to crawl your site for better SEO visibility —</p><button>Activate</button>
                </div>
            </article>
            <article class="promo-item"><img src="<?= e($config['base_url']) ?>/assets/images/promo-ai-credits.jpg" alt="AI credits">
                <div><b>AI Credits Boost</b>
                    <p>One-time credit packs from $10 — larger packs include bonus</p><button>Get It Now</button>
                </div>
            </article>
            <article class="promo-item"><img src="<?= e($config['base_url']) ?>/assets/images/promo-business-data.jpg" alt="Instant business data">
                <div><b>Business Data</b>
                    <p>Add real local business data to populate your site</p><button>Get It Now</button>
                </div>
            </article>
        </div>
    </div>
<section class="snapshot-grid dashboard-section">
    <article class="snapshot-card snapshot-members">
        <h2>♟ Member Snapshot</h2>
        <div class="snapshot-main"><strong><?= number_format($memberCount) ?></strong><span>Total Members</span></div>
        <div class="snapshot-metrics"><span>Today<strong>—</strong></span><span>7 Days<strong>—</strong></span><span>30 Days<strong>—</strong></span></div>
        <p class="data-note">Member count is loaded from your database.</p>
    </article>
    <article class="snapshot-card snapshot-finance">
        <h2>▤ Finance Snapshot</h2>
        <div class="snapshot-main"><strong>—</strong><span>Total Revenue</span></div>
        <div class="snapshot-metrics"><span>Today<strong>—</strong></span><span>Upcoming<strong>—</strong></span><span>Paid Out<strong>—</strong></span></div>
        <p class="data-note">Payment data is not connected.</p>
    </article>
    <article class="snapshot-card snapshot-moderation">
        <h2>⌛ Moderation Tasks</h2>
        <div class="snapshot-main"><strong>—</strong><span>Total Pending Items</span></div>
        <div class="snapshot-metrics"><span>Flagged<strong>—</strong></span><span>Leads<strong>—</strong></span><span>Reviews<strong>—</strong></span></div>
        <p class="data-note">Moderation data is not connected.</p>
    </article>
</section>

<section class="dashboard-panel today-panel">
    <div class="dashboard-section-heading"><svg width="20" height="18" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M10.56 8.58C10.6089 8.33556 10.7555 8.21333 11 8.21333C11.2444 8.21333 11.3911 8.33556 11.44 8.58L12.3933
            11.22C12.5889 11.8556 12.9066 12.3933 13.3466 12.8333C13.7866 13.2733 14.3 13.5911 14.8866 13.7867L17.5266
            14.6667C17.7711 14.7156 17.8933 14.8622 17.8933 15.1067C17.8933 15.3511 17.7711 15.5222 17.5266 15.62L14.8866
            16.5C14.3 16.6956 13.7866 17.0133 13.3466 17.4533C12.9066 17.8933 12.5889 18.4067 12.3933 18.9933L11.44
            21.6333C11.3911 21.8778 11.2444 22 11 22C10.7555 22 10.6089 21.8778 10.56 21.6333L9.60664 18.9933C9.41109
            18.4067 9.09331 17.8933 8.65331 17.4533C8.21331 17.0133 7.69998 16.6956 7.11331 16.5L4.47331 15.62C4.22887
            15.5222 4.10664 15.3511 4.10664 15.1067C4.10664 14.8622 4.22887 14.7156 4.47331 14.6667L7.11331
            13.7867C7.69998 13.5911 8.21331 13.2733 8.65331 12.8333C9.09331 12.3933 9.41109 11.8556 9.60664 11.22L10.56
            8.58ZM5.20664 1.61333C5.25553 1.46667 5.35331 1.39333 5.49998 1.39333C5.64664 1.39333 5.74442 1.46667 5.79331
            1.61333L6.30664 3.15333C6.45331 3.54445 6.64887 3.86222 6.89331 4.10667C7.13775 4.35111 7.45553 4.54667
            7.84664 4.69333L9.38664 5.20667C9.53331 5.25556 9.60664 5.35333 9.60664 5.5C9.60664 5.64667 9.53331 5.74445
            9.38664 5.79333L7.84664 6.30667C7.45553 6.45333 7.13775 6.64889 6.89331 6.89333C6.64887 7.13778 6.45331
            7.45556 6.30664 7.84667L5.79331 9.38667C5.74442 9.53333 5.64664 9.60667 5.49998 9.60667C5.35331 9.60667
            5.25553 9.53333 5.20664 9.38667L4.69331 7.84667C4.54664 7.45556 4.35109 7.13778 4.10664 6.89333C3.8622 6.64889
            3.54442 6.45333 3.15331 6.30667L1.61331 5.79333C1.46664 5.74445 1.39331 5.64667 1.39331 5.5C1.39331 5.35333
            1.46664 5.25556 1.61331 5.20667L3.15331 4.69333C3.54442 4.54667 3.8622 4.35111 4.10664 4.10667C4.35109 3.86222
            4.54664 3.54445 4.69331 3.15333L5.20664 1.61333ZM14.96 0.146667C14.96 0.0488892 15.0089 0 15.1066 0C15.2044 0
            15.2778 0.0488892 15.3266 0.146667L15.6933 1.17333C15.84 1.71111 16.1578 2.05333 16.6466 2.2L17.7466
            2.56667C17.8444 2.61556 17.8933 2.67667 17.8933 2.75C17.8933 2.82333 17.8444 2.88445 17.7466 2.93333L16.6466
            3.3C16.1578 3.44667 15.84 3.78889 15.6933 4.32667L15.3266 5.35333C15.2778 5.45111 15.2044 5.5 15.1066
            5.5C15.0089 5.5 14.96 5.45111 14.96 5.35333L14.5933 4.32667C14.3978 3.78889 14.0555 3.44667 13.5666 3.3L12.54
            2.93333C12.4422 2.88445 12.3933 2.82333 12.3933 2.75C12.3933 2.67667 12.4422 2.61556 12.54 2.56667L13.5666
            2.2C14.0555 2.05333 14.3978 1.71111 14.5933 1.17333L14.96 0.146667Z" fill="white"/>
    </svg> &nbsp; What Do You Want to Do Today?</div>
    <div class="today-actions">
        <a href="#"><span>   <svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M23.4 30C24.6 30 25.7 29.7 26.7 29.1C27.7 28.5 28.5 27.7 29.1 26.7C29.7 25.7 30 24.6167 30 23.45C30 22.2833
            29.7 21.1833 29.1 20.15C28.5 19.1167 27.7 18.3167 26.7 17.75C25.7 17.1833 24.6167 16.9 23.45 16.9C22.2834 16.9
            21.1834 17.1833 20.15 17.75C19.1167 18.3167 18.3167 19.1167 17.75 20.15C17.1834 21.1833 16.9 22.2833 16.9
            23.45C16.9 24.6167 17.1834 25.7 17.75 26.7C18.3167 27.7 19.1167 28.5 20.15 29.1C21.1834 29.7 22.2667 30 23.4
            30ZM24.4 20.6V22.5H26.3C26.5 22.5 26.7 22.5833 26.9 22.75C27.1 22.9167 27.2 23.1333 27.2 23.4C27.2 23.6667
            27.1 23.9 26.9 24.1C26.7 24.3 26.5 24.4 26.3 24.4H24.4V26.2C24.4 26.4667 24.3 26.7 24.1 26.9C23.9 27.1 23.6667
            27.2 23.4 27.2C23.1334 27.2 22.9167 27.1 22.75 26.9C22.5834 26.7 22.5 26.4667 22.5 26.2V24.4H20.6C20.3334 24.4
            20.1167 24.3 19.95 24.1C19.7834 23.9 19.7 23.6667 19.7 23.4C19.7 23.1333 19.7834 22.9167 19.95 22.75C20.1167
            22.5833 20.3334 22.5 20.6 22.5H22.5V20.6C22.5 20.3333 22.5834 20.1167 22.75 19.95C22.9167 19.7833 23.1334 19.7
            23.4 19.7C23.6667 19.7 23.9 19.7833 24.1 19.95C24.3 20.1167 24.4 20.3333 24.4 20.6ZM20.6 9.4C20.6 10.9333
            20.05 12.25 18.95 13.35C17.85 14.45 16.5334 15 15 15C13.4667 15 12.15 14.45 11.05 13.35C9.95005 12.25 9.40005
            10.9167 9.40005 9.35C9.40005 7.78333 9.95005 6.46667 11.05 5.4C12.15 4.33333 13.4667 3.78333 15 3.75C16.5334
            3.71667 17.85 4.25 18.95 5.35C20.05 6.45 20.6 7.8 20.6 9.4ZM3.80005 24.4C3.80005 24.9333 3.93338 25.3333
            4.20005 25.6C4.46672 25.8667 4.76672 26.0667 5.10005 26.2H5.60005H15.5C15.1667 25.3333 15 24.4 15 23.4C15 22.2
            15.25 21.05 15.75 19.95C16.25 18.85 16.9667 17.9 17.9 17.1C17.0334 16.9667 16.0667 16.9 15 16.9C12.1334 16.9
            9.76672 17.4 7.90005 18.4C6.43338 19.2 5.33338 20.2667 4.60005 21.6C4.06672 22.6 3.80005 23.5333 3.80005 24.4Z" fill="#2563EB"/>
    </svg></span><strong>Add New Member</strong><small>View All Members</small></a>
        <a href="#"><span> <svg width="30" height="30" viewBox="0 0 31 31" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M29.9167 1.31996C30.1167 0.919954 30.0667 0.569956 29.7667 0.269955C29.4667 -0.0300446 29.1167 -0.0800457
            28.7167 0.119955L0.616667 11.32C0.283333 11.4533 0.0833333 11.72 0.0166667 12.12C-0.05 12.52 0.0833333 12.82
            0.416667 13.02L10.6167 19.42L17.0167 29.62C17.2167 29.9533 17.5167 30.0866 17.9167 30.02C18.3167 29.9533
            18.5833 29.7533 18.7167 29.42L29.9167 1.31996ZM26.5167 4.81995L12.4167 18.92L12.0167 18.22C11.95 18.1533
            11.8833 18.0866 11.8167 18.02L11.1167 17.62L25.2167 3.51995L27.4167 2.61996L26.5167 4.81995Z" fill="#EC4899"/>
    </svg></span><strong>Create Newsletter</strong><small>View All Newsletters</small></a>
        <a href="#"><span> <svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M22.5 1.9C23.0333 1.9 23.4833 2.08333 23.85 2.45C24.2166 2.81667 24.4 3.23333 24.4 3.7V26.2C24.4 26.7333
            24.2166 27.1833 23.85 27.55C23.4833 27.9167 23.0333 28.1 22.5 28.1H7.49995C6.96662 28.1 6.51662 27.9167
            6.14995 27.55C5.78328 27.1833 5.59995 26.7333 5.59995 26.2V3.7C5.59995 3.23333 5.78328 2.81667 6.14995
            2.45C6.51662 2.08333 6.96662 1.9 7.49995 1.9H22.5ZM7.49995 0C6.43328 0 5.53328 0.366667 4.79995 1.1C4.06662
            1.83333 3.69995 2.73333 3.69995 3.8V26.2C3.76662 27.2667 4.14995 28.1667 4.84995 28.9C5.54995 29.6333 6.43328
            30 7.49995 30H22.5C23.5666 30 24.4666 29.6333 25.2 28.9C25.9333 28.1667 26.3 27.2667 26.3 26.2V3.7C26.3 2.7
            25.9333 1.83333 25.2 1.1C24.4666 0.366667 23.5666 0 22.5 0H7.49995ZM7.49995 4.7C7.49995 4.43333 7.58328 4.2
            7.74995 4C7.91662 3.8 8.13328 3.7 8.39995 3.7H21.6C21.8666 3.7 22.0833 3.8 22.25 4C22.4166 4.2 22.5 4.43333
            22.5 4.7V8.4C22.5 8.66667 22.4166 8.9 22.25 9.1C22.0833 9.3 21.8666 9.4 21.6 9.4H8.39995C8.13328 9.4 7.91662
            9.3 7.74995 9.1C7.58328 8.9 7.49995 8.66667 7.49995 8.4V4.7ZM7.49995 12.2C7.49995 11.9333 7.58328 11.7 7.74995
            11.5C7.91662 11.3 8.13328 11.2 8.39995 11.2H10.3C10.5666 11.2 10.8 11.3 11 11.5C11.2 11.7 11.3 11.9333 11.3
            12.2V14.1C11.2333 14.3667 11.1166 14.5833 10.95 14.75C10.7833 14.9167 10.5666 15 10.3 15H8.39995C8.13328 15
            7.91662 14.9167 7.74995 14.75C7.58328 14.5833 7.49995 14.3667 7.49995 14.1V12.2ZM7.49995 17.8C7.49995 17.5333
            7.58328 17.3167 7.74995 17.15C7.91662 16.9833 8.13328 16.9 8.39995 16.9H10.3C10.5666 16.9 10.8 16.9833 11
            17.15C11.2 17.3167 11.3 17.5333 11.3 17.8V19.7C11.2333 19.9667 11.1166 20.1833 10.95 20.35C10.7833 20.5167
            10.5666 20.6 10.3 20.6H8.39995C8.13328 20.6 7.91662 20.5167 7.74995 20.35C7.58328 20.1833 7.49995 19.9667
            7.49995 19.7V17.8ZM7.49995 23.4C7.49995 23.1333 7.58328 22.9167 7.74995 22.75C7.91662 22.5833 8.13328 22.5
            8.39995 22.5H10.3C10.5666 22.5 10.8 22.5833 11 22.75C11.2 22.9167 11.3 23.1333 11.3 23.4V25.3C11.2333 25.5667
            11.1166 25.7833 10.95 25.95C10.7833 26.1167 10.5666 26.2 10.3 26.2H8.39995C8.13328 26.2 7.91662 26.1167
            7.74995 25.95C7.58328 25.7833 7.49995 25.5667 7.49995 25.3V23.4ZM13.1 12.2C13.1 11.9333 13.2 11.7 13.4
            11.5C13.6 11.3 13.8333 11.2 14.1 11.2H15.9C16.1666 11.2 16.4 11.3 16.6 11.5C16.8 11.7 16.9 11.9333 16.9
            12.2V14.1C16.9 14.3667 16.8 14.5833 16.6 14.75C16.4 14.9167 16.1666 15 15.9 15H14.1C13.8333 15 13.6 14.9167
            13.4 14.75C13.2 14.5833 13.1 14.3667 13.1 14.1V12.2ZM13.1 17.8C13.1 17.5333 13.2 17.3167 13.4 17.15C13.6
            16.9833 13.8333 16.9 14.1 16.9H15.9C16.1666 16.9 16.4 16.9833 16.6 17.15C16.8 17.3167 16.9 17.5333 16.9
            17.8V19.7C16.9 19.9667 16.8 20.1833 16.6 20.35C16.4 20.5167 16.1666 20.6 15.9 20.6H14.1C13.8333 20.6 13.6
            20.5167 13.4 20.35C13.2 20.1833 13.1 19.9667 13.1 19.7V17.8ZM13.1 23.4C13.1 23.1333 13.2 22.9167 13.4
            22.75C13.6 22.5833 13.8333 22.5 14.1 22.5H15.9C16.1666 22.5 16.4 22.5833 16.6 22.75C16.8 22.9167 16.9 23.1333
            16.9 23.4V25.3C16.9 25.5667 16.8 25.7833 16.6 25.95C16.4 26.1167 16.1666 26.2 15.9 26.2H14.1C13.8333 26.2 13.6
            26.1167 13.4 25.95C13.2 25.7833 13.1 25.5667 13.1 25.3V23.4ZM18.8 12.2C18.8 11.9333 18.8833 11.7 19.05
            11.5C19.2166 11.3 19.4333 11.2 19.7 11.2H21.6C21.8666 11.2 22.0833 11.3 22.25 11.5C22.4166 11.7 22.5 11.9333
            22.5 12.2V14.1C22.5 14.3667 22.4166 14.5833 22.25 14.75C22.0833 14.9167 21.8666 15 21.6 15H19.7C19.4333 15
            19.2 14.9167 19 14.75C18.8 14.5833 18.7 14.3667 18.7 14.1L18.8 12.2ZM18.8 17.8C18.8 17.5333 18.8833 17.3167
            19.05 17.15C19.2166 16.9833 19.4333 16.9 19.7 16.9H21.6C21.8666 16.9 22.0833 16.9833 22.25 17.15C22.4166
            17.3167 22.5 17.5333 22.5 17.8V25.3C22.5 25.5667 22.4166 25.7833 22.25 25.95C22.0833 26.1167 21.8666 26.2 21.6
            26.2H19.7C19.4333 26.2 19.2166 26.1167 19.05 25.95C18.8833 25.7833 18.7666 25.5667 18.7 25.3L18.8 17.8Z" fill="#1B9082"/>
    </svg></span><strong>Create Invoice</strong><small>View All Transactions</small></a>
        <a href="#"><span> <svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M29.1 3.6001C29.2334 3.8001 29.3 4.03343 29.3 4.3001C29.3 4.56676 29.2334 4.8001 29.1 5.0001L27.1 6.9001L23.4
            3.2001L25.3 1.2001C25.5 1.0001 25.7334 0.900097 26 0.900097C26.2667 0.900097 26.4667 1.0001 26.6 1.2001L29.1
            3.6001ZM25.8 8.2001L22 4.5001L9.30002 17.3001C9.16669 17.3668 9.06669 17.4668 9.00002 17.6001L7.50002
            22.2001C7.43336 22.3334 7.46669 22.4668 7.60002 22.6001C7.73336 22.7334 7.90002 22.8001 8.10002 22.8001L12.6
            21.2001C12.8 21.2001 12.9334 21.1334 13 21.0001L25.8 8.2001ZM1.90002 25.3001C1.90002 26.1001 2.16669 26.7668
            2.70002 27.3001C3.23336 27.8334 3.90002 28.1001 4.70002 28.1001H25.3C26.1 28.1001 26.7667 27.8334 27.3
            27.3001C27.8334 26.7668 28.1 26.1001 28.1 25.3001V14.1001C28.1 13.8334 28.0167 13.6001 27.85 13.4001C27.6834
            13.2001 27.4667 13.1001 27.2 13.1001C26.9334 13.1001 26.7 13.2001 26.5 13.4001C26.3 13.6001 26.2 13.8334 26.2
            14.1001V25.3001C26.2667 25.5668 26.2 25.7834 26 25.9501C25.8 26.1168 25.5667 26.2001 25.3
            26.2001H4.70002C4.43336 26.2001 4.21669 26.1168 4.05002 25.9501C3.88336 25.7834 3.80002 25.5668 3.80002
            25.3001V4.7001C3.80002 4.43343 3.88336 4.2001 4.05002 4.0001C4.21669 3.8001 4.43336 3.7001 4.70002
            3.7001H16.9C17.1667 3.7001 17.3834 3.61676 17.55 3.4501C17.7167 3.28343 17.8 3.06676 17.8 2.8001C17.8 2.53343
            17.7167 2.31676 17.55 2.1501C17.3834 1.98343 17.1667 1.9001 16.9 1.9001H4.70002C3.90002 1.9001 3.23336 2.16676
            2.70002 2.7001C2.16669 3.23343 1.90002 3.9001 1.90002 4.7001V25.3001Z" fill="#8B5CF6"/>
    </svg></span><strong>Create Post</strong><small>View All Posts</small></a>
        <a href="#"><span><svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M10.3 13.1C10.0333 13.1 9.81662 13.2 9.64995 13.4C9.48328 13.6 9.39995 13.8333 9.39995 14.1C9.39995 14.3667
            9.48328 14.5833 9.64995 14.75C9.81662 14.9167 10.0333 15 10.3 15H19.7C19.9666 15 20.1833 14.9167 20.35
            14.75C20.5166 14.5833 20.6 14.3667 20.6 14.1C20.6 13.8333 20.5166 13.6 20.35 13.4C20.1833 13.2 19.9666 13.1
            19.7 13.1H10.3ZM9.39995 17.8C9.39995 17.5333 9.48328 17.3167 9.64995 17.15C9.81662 16.9833 10.0333 16.9 10.3
            16.9H19.7C19.9666 16.9 20.1833 16.9833 20.35 17.15C20.5166 17.3167 20.6 17.5333 20.6 17.8C20.6 18.0667 20.5166
            18.2833 20.35 18.45C20.1833 18.6167 19.9666 18.7333 19.7 18.8H10.3C10.0333 18.8 9.81662 18.7 9.64995
            18.5C9.48328 18.3 9.39995 18.0667 9.39995 17.8ZM9.39995 21.6C9.39995 21.3333 9.48328 21.1 9.64995 20.9C9.81662
            20.7 10.0333 20.6 10.3 20.6H14.1C14.3666 20.6 14.5833 20.7 14.75 20.9C14.9166 21.1 15 21.3333 15 21.6C15
            21.8667 14.9166 22.0833 14.75 22.25C14.5833 22.4167 14.3666 22.5 14.1 22.5H10.3C10.0333 22.5 9.81662 22.4167
            9.64995 22.25C9.48328 22.0833 9.39995 21.8667 9.39995 21.6ZM17.8 0H7.49995C6.43328 0 5.53328 0.366667 4.79995
            1.1C4.06662 1.83333 3.69995 2.73333 3.69995 3.8V26.2C3.76662 27.2667 4.14995 28.1667 4.84995 28.9C5.54995
            29.6333 6.43328 30 7.49995 30H22.5C23.5666 30 24.4666 29.6333 25.2 28.9C25.9333 28.1667 26.3 27.2667 26.3
            26.2V8.4L17.8 0ZM17.8 1.9V5.6C17.8 6.4 18.0833 7.06667 18.65 7.6C19.2166 8.13333 19.8666 8.4 20.6
            8.4H24.4V26.2C24.4 26.7333 24.2166 27.1833 23.85 27.55C23.4833 27.9167 23.0333 28.1 22.5 28.1H7.49995C6.96662
            28.1 6.51662 27.9167 6.14995 27.55C5.78328 27.1833 5.59995 26.7333 5.59995 26.2V3.7C5.59995 3.23333 5.78328
            2.81667 6.14995 2.45C6.51662 2.08333 6.96662 1.9 7.49995 1.9H17.8Z" fill="#0EA5E9"/>
    </svg></span><strong>Create Web Page</strong><small>View All Web Pages</small></a>
    </div>
</section>

<section class="dashboard-three-columns dashboard-section">
    <article class="dashboard-widget">
        <div class="widget-heading">▧ &nbsp; My Web Pages <span>Last Updated⌄</span></div>
        <div class="widget-empty">No website pages are connected yet.</div>
    </article>
    <article class="dashboard-widget">
        <div class="widget-heading">▣ &nbsp; My Post Types <span>Most Posts⌄</span></div>
        <div class="widget-empty">No post types are available yet.</div>
    </article>
    <article class="dashboard-widget">
        <div class="widget-heading">♧ &nbsp; My Smart Lists <span>Most Contacts⌄</span></div>
        <div class="widget-empty">No smart lists are available yet.</div>
    </article>
</section>

<section class="dashboard-three-columns dashboard-section">
    <article class="dashboard-widget">
        <div class="widget-heading">♟ &nbsp; New Members <span>New Members⌄</span></div>
        <div class="widget-empty">No member records available.</div>
    </article>
    <article class="dashboard-widget">
        <div class="widget-heading">▤ &nbsp; New Payments <a href="#">View All</a></div>
        <div class="widget-empty">No payment data available yet.</div>
    </article>
    <article class="dashboard-widget">
        <div class="widget-heading">▧ &nbsp; New Posts <a href="#">View All</a></div>
        <div class="widget-empty">No post data available yet.</div>
    </article>
</section>

<section class="dashboard-three-columns dashboard-section">
    <article class="dashboard-widget">
        <div class="widget-heading">
            ♙ &nbsp; Most Viewed Profiles
            <span>Last 30 Days⌄</span>
        </div>
        <div class="widget-empty">No profile activity available.</div>
    </article>

    <article class="dashboard-widget">
        <div class="widget-heading">
            ▥ &nbsp; Most Viewed Posts
            <span>Last 30 Days⌄</span>
        </div>
        <div class="widget-empty">No post activity available.</div>
    </article>

    <article class="dashboard-widget">
        <div class="widget-heading">
            ♥ &nbsp; Visitor Activity
            <span>Last 30 Days⌄</span>
        </div>
        <div class="widget-empty">No visitor activity available.</div>
    </article>
</section>

<section class="analytics-membership-grid dashboard-section">
    <article class="dashboard-widget analytics-widget">
        <div class="widget-heading">Website Analytics <span>Weekly⌄</span></div>
        <div class="analytics-legend"><span>Sign Ups</span><span>Logins</span><span>Leads</span><span>Reviews</span><span>Posts</span><span>Comments</span></div>
        <div class="chart-empty"><span>ⓘ</span>No analytics data available yet.</div>
    </article>
    <article class="dashboard-widget membership-widget">
        <div class="widget-heading">♟ &nbsp; Top Membership Plans <a href="#">View All</a></div>
        <div class="chart-empty"><span>ⓘ</span>No membership plan data available.</div>
    </article>
</section>

<section class="resources-support-grid dashboard-section">
    <article class="dashboard-widget resources-widget">
        <div class="widget-heading">⌁ &nbsp; My Website Resources <span>Cleanup Tools⌄</span></div>
        <div class="resource-notice">Resource and service status will appear here when connected.</div>
        <div class="resource-grid">
            <div><strong>Member Count</strong><span>Not connected</span></div>
            <div><strong>Available Storage</strong><span>Not connected</span></div>
            <div><strong>November Bandwidth</strong><span>Not connected</span></div>
            <div><strong>Daily Email</strong><span>Not connected</span></div>
            <div><strong>Google Maps API Status</strong><span>Not configured</span></div>
            <div><strong>Email Address Status</strong><span>Not configured</span></div>
        </div>
    </article>
    <article class="dashboard-widget support-widget">
        <div class="widget-heading">☷ &nbsp; Support &amp; Help</div>
        <div class="support-links">
            <a href="#">
                <span>ⓘ</span>
                <div>
                    <strong>Support Documents</strong>
                    <small>Browse help articles and guides</small>
                </div>
                ›
            </a>
            <a href="#">
                <span>✉</span>
                <div>
                    <strong>Create Support Ticket</strong>
                    <small>Report a problem or ask a question</small>
                </div>
                ›
            </a>
            <a href="#">
                <span>f</span>
                <div>
                    <strong>Join the Facebook Group</strong>
                    <small>Connect with the community</small>
                </div>
                ›
            </a>
        </div>
    </article>
</section>

<div class="floating-help"><span>☻</span><a href="#">HELP CENTER</a></div>


</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>