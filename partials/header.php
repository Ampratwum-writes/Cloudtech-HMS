<?php
/* Expects $pageTitle, $pageSubtitle, $activeNav to be set by the including page. */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
    <title><?= htmlspecialchars($pageTitle) ?> — CLOUD TECH HOSTEL</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="app-shell">

    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="crest-mark"><span></span><span></span><span></span><span></span></div>
            <div class="brand-text">
                <div class="brand-title">CLOUD TECH HOSTEL</div>
                <div class="brand-sub">staff Management System</div>
            </div>
        </div>

        <nav class="nav-section">
            <div class="nav-label">Overview</div>
            <a href="index.php" class="nav-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                Dashboard
            </a>

            <div class="nav-label" style="margin-top:16px;">Records</div>
            <a href="students.php" class="nav-link <?= $activeNav === 'students' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
                Students
            </a>
            <a href="rooms.php" class="nav-link <?= $activeNav === 'rooms' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 4l9 6.5"/><path d="M5 9.5V20h14V9.5"/></svg>
                Rooms
            </a>
            <a href="bookings.php" class="nav-link <?= $activeNav === 'bookings' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 9h18"/></svg>
                Bookings
            </a>
            <a href="payments.php" class="nav-link <?= $activeNav === 'payments' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                Payments
            </a>
            <a href="staff.php" class="nav-link <?= $activeNav === 'staff' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
                staff
            </a>
            <a href="maintenance.php" class="nav-link <?= $activeNav === 'maintenance' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 1-5.4 5.4L4 17v3h3l5.3-5.3a4 4 0 0 1 5.4-5.4l-2.8 2.8-2-2z"/></svg>
                maintenance
            </a>
            <a href="visitors.php" class="nav-link <?= $activeNav === 'visitors' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                Visitors
            </a>
            <a href="applications.php" class="nav-link <?= $activeNav === 'applications' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 11l3 3L22 4"/>
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
                Applications
            </a>
        </nav>

        <div class="sidebar-footer">
            Ghana Communication Technology University<br>
            CLOUD TECH HOSTEL
        </div>
    </aside>

    <div class="main">
        <div class="topbar">
            <div style="display:flex; align-items:center;">
                <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div>
                    <h1><?= htmlspecialchars($pageTitle) ?></h1>
                    <p><?= htmlspecialchars($pageSubtitle) ?></p>
                </div>
            </div>
            <div class="topbar-right" style="display:flex; align-items:center; gap:14px;">
    <span>Signed in as <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
    <a href="logout.php" class="btn-ghost">Log out</a>
</div>
        </div>
