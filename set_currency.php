<?php
require_once __DIR__ . '/db.php';
if (is_post()) {
    if (isset(currencies()[post('currency')])) $_SESSION['currency'] = post('currency');
    if (in_array(post('lang'), ['en', 'hi', 'gu'], true)) $_SESSION['lang'] = post('lang');
}
back();
