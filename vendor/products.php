<?php
require_once __DIR__ . '/../db.php'; $vend = require_vendor();
$panel = 'vendor'; $scopeVendor = (int)$vend['id'];
if ($vend['status'] !== 'approved') { flash('warning', 'Your store is awaiting admin approval. You can add products once approved.'); redirect('vendor/dashboard.php'); }
require __DIR__ . '/../includes/products_crud.php';
$title = 'My Products';
require __DIR__ . '/../includes/admin_header.php';
render_products($panel, $scopeVendor, $self, $cats, $vendorsList);
require __DIR__ . '/../includes/admin_footer.php';
