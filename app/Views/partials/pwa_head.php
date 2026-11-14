<?php
// ============================================================
// PWA head tags - included by every layout that users can land on
// (admin, tenant portal, both login screens, public landing page).
// Keeping them in one partial means the manifest, icons and the
// install prompt never drift apart between layouts.
//
// The manifest endpoint is manifest.php (see the comment there for
// why it is not a static .json file).
// ============================================================
?>
    <!-- PWA: installable app -->
    <meta name="theme-color" content="#007c47">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="RMS">
    <link rel="manifest" href="<?php echo e(url('manifest.php')); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo asset('assets/icons/apple-touch-icon.png'); ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo asset('assets/icons/favicon-32.png'); ?>">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <script src="<?php echo asset('assets/js/pwa.js'); ?>" defer></script>
