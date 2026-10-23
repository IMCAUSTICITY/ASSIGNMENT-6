<?php
require_once __DIR__ . '/db.php';
if (is_post()) {
    $em = strtolower(post('email'));
    if (!filter_var($em, FILTER_VALIDATE_EMAIL)) flash('danger', 'Please enter a valid email address.');
    else {
        $pdo->prepare('INSERT IGNORE INTO newsletter (email) VALUES (?)')->execute([$em]);
        send_mail($em, 'Welcome to ' . setting('site_name'), "Thanks for subscribing! You'll now receive our latest offers.");
        flash('success', 'Thanks for subscribing to our newsletter!');
    }
}
back();
